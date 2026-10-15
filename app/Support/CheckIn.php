<?php

namespace App\Support;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Event check-in (Attendee Activities › Self Check-in).
 * Each event has one check-in link / QR (printed on posters at the venue): /checkin/{event-slug}/{token}. Participants
 * scan it with their phone, type their registration number and see a welcome screen. Once only — a second try shows
 * "already checked in". Every extra check is an option in the admin panel (all off by default):
 * opening hours, a second detail (last name / email / birth year), location near the venue, approved-only.
 */
class CheckIn
{
    public const EXTRA = ['none' => 'Nothing — registration number only', 'last_name' => 'Last name', 'email' => 'Email address', 'birth_year' => 'Year of birth'];

    public const DEFAULTS = [
        'enabled' => false,
        'token' => '',
        'hours' => 'always',            // always | window
        'opens' => '', 'closes' => '',  // "Y-m-d H:i" in the event time zone
        'extra' => 'none',
        'location' => false, 'lat' => '', 'lng' => '', 'radius' => 500,
        'approved_only' => true,
        'welcome' => 'Welcome to {event_short}, {first_name}!',
        'message' => 'Your check-in is confirmed. Enjoy the summit!',
        'seconds' => 10,
        'show_photo' => true, 'show_profession' => true, 'show_country' => true, 'show_organization' => false,
        'forgot' => true,               // "Forgot your number?" → emailed to them
        'certificate' => 'event',       // event (follow the certificate settings) | never (not on self check-in)
        'sound' => true,
    ];

    /* ---------------- settings ---------------- */

    public static function settings(Event $event): array
    {
        $raw = DB::table('settings')->where('key', 'checkin.' . $event->id)->value('value');
        $s = array_merge(static::DEFAULTS, $raw ? (json_decode($raw, true) ?: []) : []);
        if ($s['token'] === '') { $s['token'] = Str::random(8); static::write($event, $s); }
        $s['seconds'] = max(3, min(60, (int) $s['seconds']));
        $s['radius'] = max(50, min(20000, (int) $s['radius']));
        if (!array_key_exists($s['extra'], static::EXTRA)) $s['extra'] = 'none';
        return $s;
    }

    public static function save(Event $event, array $values): array
    {
        $s = array_merge(static::settings($event), array_intersect_key($values, static::DEFAULTS));
        static::write($event, $s);
        return static::settings($event);
    }

    private static function write(Event $event, array $s): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'checkin.' . $event->id], ['value' => json_encode($s), 'updated_at' => now(), 'created_at' => now()]);
    }

    public static function newToken(Event $event): string
    {
        static::save($event, ['token' => Str::random(8)]);
        return static::settings($event)['token'];
    }

    public static function url(Event $event, bool $kiosk = false): string
    {
        return PublicLinks::base() . '/checkin/' . $event->slug . '/' . static::settings($event)['token'] . ($kiosk ? '?kiosk=1' : '');
    }

    public static function tz(Event $event): string
    {
        return in_array($event->event_timezone, timezone_identifiers_list(), true) ? $event->event_timezone : config('app.timezone');
    }

    /** [open?, message when closed] */
    public static function state(Event $event, ?array $s = null): array
    {
        $s = $s ?: static::settings($event);
        if (EventLock::isLocked($event)) return [false, EventLock::reason($event) . ' Check-in is closed.'];
        if (!EventLock::isCurrentOrUnlocked($event)) return [false, 'Check-in is only open for the current event.'];
        if (!EventLock::unlocked($event) && in_array(EventLock::phase($event), ['ended', 'archived'], true)) return [false, 'This event has ended — check-in is closed. Thank you for coming!'];
        if (!$s['enabled']) return [false, 'Check-in is not open yet.'];
        if ($s['hours'] === 'window') {
            $now = Carbon::now(static::tz($event));
            $opens = $s['opens'] ? Carbon::parse($s['opens'], static::tz($event)) : null;
            $closes = $s['closes'] ? Carbon::parse($s['closes'], static::tz($event)) : null;
            if ($opens && $now->lt($opens)) return [false, 'Check-in opens on ' . $opens->format('l j F, H:i') . '.'];
            if ($closes && $now->gt($closes)) return [false, 'Check-in has closed.'];
        }
        return [true, null];
    }

    /* ---------------- people ---------------- */

    /** Registrations of this event (by event id, or the venue code for older rows). */
    public static function registrations(Event $event)
    {
        return DB::table('registrations')->where(fn ($q) => $q->where('event_id', $event->id)->orWhere(fn ($w) => $w->whereNull('event_id')->where('venue', $event->venue_code)));
    }

    public static function find(Event $event, string $number): ?object
    {
        return static::registrations($event)->where('qr_code', $number)->first();
    }

    /** The event a registration number belongs to, when it is not this one (to say "you registered for X"). */
    public static function otherEvent(string $number): ?object
    {
        $r = DB::table('registrations')->where('qr_code', $number)->orderByDesc('id')->first();
        return $r ? (($r->event_id ? Event::find($r->event_id) : Event::forVenue($r->venue)) ?: (object) ['event_short' => $r->venue]) : null;
    }

    public static function extraMatches(object $r, string $extra, ?string $value): bool
    {
        $v = mb_strtolower(trim((string) $value));
        if ($extra === 'none') return true;
        if ($v === '') return false;
        return match ($extra) {
            'last_name' => static::norm($r->lastname) === static::norm($v) || static::norm($r->firstname . ' ' . $r->lastname) === static::norm($v),
            'email' => mb_strtolower(trim((string) $r->email)) === $v,
            'birth_year' => ($d = BirthDate::parse($r->birthday)) !== null && substr($d, 0, 4) === $v,
            default => false,
        };
    }

    private static function norm(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = \Illuminate\Support\Str::ascii($s);
        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    /** What the welcome screen shows. */
    public static function profile(object $r, array $s, Event $event): array
    {
        $p = $r->participant_id ? DB::table('participants')->where('id', $r->participant_id)->first(['photo', 'code', 'organization']) : null;
        $photo = null;
        if ($s['show_photo']) {
            if ($p && $p->photo && is_file(base_path($p->photo))) $photo = Thumb::url($p->photo, 480);
            elseif ($r->image && is_file(base_path('upload/images/' . $r->image))) $photo = Thumb::url('upload/images/' . $r->image, 480);
        }
        $vars = ['{first_name}' => (string) $r->firstname, '{last_name}' => (string) $r->lastname, '{name}' => trim($r->title . ' ' . $r->firstname . ' ' . $r->lastname),
            '{event_short}' => (string) $event->event_short, '{event_name}' => (string) $event->event_name];
        return [
            'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname),
            'initials' => mb_strtoupper(mb_substr((string) $r->firstname, 0, 1) . mb_substr((string) $r->lastname, 0, 1)),
            'photo' => $photo,
            'profession' => $s['show_profession'] ? trim($r->profession . ($r->specialty ? ' · ' . $r->specialty : '')) : null,
            'country' => $s['show_country'] ? $r->country : null,
            'organization' => $s['show_organization'] ? ($r->institute ?: ($p->organization ?? null)) : null,
            'number' => (string) $r->qr_code,
            'welcome' => strtr($s['welcome'], $vars), 'message' => strtr($s['message'], $vars),
        ];
    }

    /* ---------------- checking in ---------------- */

    /** Check a registration in. Returns [new?, checkin row]. Safe to call twice — the second call changes nothing. */
    public static function confirm(object $r, string $method, array $meta = [], bool $certificate = true): array
    {
        $existing = DB::table('checkins')->where('registration_id', $r->id)->first();
        if ($existing) return [false, $existing];
        $ok = DB::table('checkins')->insertOrIgnore([
            'event_id' => $r->event_id, 'registration_id' => $r->id, 'participant_id' => $r->participant_id, 'method' => $method,
            'ip' => $meta['ip'] ?? null, 'device' => $meta['device'] ?? null, 'lat' => $meta['lat'] ?? null, 'lng' => $meta['lng'] ?? null,
            'distance_m' => $meta['distance'] ?? null, 'user_id' => $meta['user_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('checkins')->where('registration_id', $r->id)->first();
        if (!$ok) return [false, $row];   // someone else was faster
        DB::table('registrations')->where('id', $r->id)->update(['verify_status' => 'Yes', 'verify_at' => date('d-m-Y h:i:sa')]);
        if ($certificate) Certificate::onCheckedIn($r->id);
        return [true, $row];
    }

    public static function undo(int $registrationId): bool
    {
        $n = DB::table('checkins')->where('registration_id', $registrationId)->delete();
        DB::table('registrations')->where('id', $registrationId)->update(['verify_status' => 'No', 'verify_at' => null]);
        return $n > 0;
    }

    /** Metres between two points. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000; $p1 = deg2rad($lat1); $p2 = deg2rad($lat2); $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /* ---------------- desk ---------------- */

    public static function stats(Event $event): array
    {
        $regs = static::registrations($event);
        $total = (clone $regs)->count();
        $by = DB::table('checkins')->whereIn('registration_id', (clone $regs)->select('id'))->select('method', DB::raw('count(*) n'))->groupBy('method')->pluck('n', 'method')->all();
        $in = array_sum($by);
        $today = DB::table('checkins')->whereIn('registration_id', (clone $regs)->select('id'))->where('created_at', '>=', Carbon::now(static::tz($event))->startOfDay()->utc())->count();
        return ['registered' => $total, 'checked' => $in, 'waiting' => max(0, $total - $in), 'today' => $today,
            'self' => (int) ($by['self'] ?? 0), 'kiosk' => (int) ($by['kiosk'] ?? 0), 'desk' => (int) ($by['desk'] ?? 0),
            'percent' => $total ? round($in / $total * 100) : 0];
    }

    public static function recent(Event $event, int $afterId = 0, int $limit = 40): array
    {
        $rows = DB::table('checkins as c')->join('registrations as r', 'r.id', '=', 'c.registration_id')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('participants as p', 'p.id', '=', 'r.participant_id')
            ->where(fn ($q) => $q->where('r.event_id', $event->id)->orWhere(fn ($w) => $w->whereNull('r.event_id')->where('r.venue', $event->venue_code)))
            ->when($afterId, fn ($q) => $q->where('c.id', '>', $afterId))
            ->orderByDesc('c.id')->limit($limit)
            ->get(['c.id', 'c.method', 'c.created_at', 'c.distance_m', 'c.device', 'r.id as rid', 'r.title', 'r.firstname', 'r.lastname', 'r.qr_code', 'r.country', 'r.profession', 'r.image', 'p.photo', 'u.name as by']);
        // one phone checking in several people is worth a look
        $devices = DB::table('checkins')->where('event_id', $event->id)->whereNotNull('device')->select('device', DB::raw('count(*) n'))->groupBy('device')->having('n', '>', 1)->pluck('n', 'device')->all();
        $tz = static::tz($event);
        return $rows->map(function ($c) use ($devices, $tz) {
            $photo = $c->photo && is_file(base_path($c->photo)) ? Thumb::url($c->photo, 480) : ($c->image && is_file(base_path('upload/images/' . $c->image)) ? Thumb::url('upload/images/' . $c->image, 480) : null);
            return ['id' => $c->id, 'rid' => $c->rid, 'name' => trim(($c->title ? $c->title . ' ' : '') . $c->firstname . ' ' . $c->lastname), 'number' => $c->qr_code,
                'country' => $c->country, 'profession' => $c->profession, 'photo' => $photo, 'method' => $c->method, 'by' => $c->by,
                'initials' => mb_strtoupper(mb_substr((string) $c->firstname, 0, 1) . mb_substr((string) $c->lastname, 0, 1)),
                'time' => Carbon::parse($c->created_at)->timezone($tz)->format('H:i'), 'date' => Carbon::parse($c->created_at)->timezone($tz)->format('j M'),
                'shared' => $c->device && isset($devices[$c->device]) ? $devices[$c->device] : 0, 'distance' => $c->distance_m];
        })->all();
    }
}
