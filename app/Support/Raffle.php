<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raffle draw of the current event (Admin > Raffle Draw).
 *
 * - Only the users assigned as hosts of the event can open the draw screen and draw winners.
 *   Super Admins assign the hosts (they can assign themselves).
 * - Winners are drawn on the server; the screen only animates the result.
 * - Prizes are drawn in their order (1 = first). Each registration can win once per event.
 * - "Who can win" (per event): everyone registered, or only people checked in at the venue.
 */
class Raffle
{
    public const ELIGIBILITY = [
        'registered' => 'Everyone registered for the event',
        'checked_in' => 'Only people checked in at the venue',
        'list' => 'Only the numbers on my list',
    ];

    public static function event(): ?Event
    {
        return Event::current();
    }

    public static function hosts(?Event $event = null): Collection
    {
        $event = $event ?? static::event();
        if (!$event) return collect();
        return User::whereIn('id', DB::table('raffle_hosts')->where('event_id', $event->id)->pluck('user_id'))->orderBy('name')->get();
    }

    public static function isHost(?User $user, ?Event $event = null): bool
    {
        $event = $event ?? static::event();
        if (!$user || !$event || ($user->status ?? 'active') !== 'active') return false;
        try {
            return DB::table('raffle_hosts')->where('event_id', $event->id)->where('user_id', $user->id)->exists();
        } catch (\Throwable $e) {
            return false; // table not created yet
        }
    }

    public static function setHosts(Event $event, array $userIds): void
    {
        DB::transaction(function () use ($event, $userIds) {
            DB::table('raffle_hosts')->where('event_id', $event->id)->delete();
            foreach (array_unique(array_map('intval', $userIds)) as $id) {
                DB::table('raffle_hosts')->insert(['event_id' => $event->id, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public static function eligibility(?Event $event = null): string
    {
        $event = $event ?? static::event();
        $v = $event ? DB::table('settings')->where('key', 'raffle.eligibility.' . $event->id)->value('value') : null;
        return array_key_exists((string) $v, static::ELIGIBILITY) ? $v : 'registered';
    }

    public static function setEligibility(Event $event, string $rule): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'raffle.eligibility.' . $event->id], ['value' => $rule, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Draw screen options (Raffle setup > Draw screen), including the sounds. */
    public const SCREEN_DEFAULTS = [
        'show_prizes' => true, 'countdown' => 10, 'shuffle' => 'numbers',
        'draw_sound' => 'mystical-wind', 'draw_volume' => 70, 'draw_loop' => true,
        'tick' => true, 'tick_volume' => 60,
        'win_sound' => 'winner', 'win_volume' => 90,
    ];
    public const SHUFFLES = ['numbers' => 'Participants’ registration numbers', 'digits' => 'Random digits'];

    /** Sounds that come with the site (assets/frontend/raffle/images). */
    public const BUILTIN_SOUNDS = [
        'mystical-wind' => ['Mystical wind (suspense)', 'assets/frontend/raffle/images/mystical-wind.mp3'],
        'countdown' => ['Countdown music (20 s)', 'assets/frontend/raffle/images/20-countdown.mp3'],
        'crowd-cheer' => ['Crowd cheer', 'assets/frontend/raffle/images/crowd-cheer.mp3'],
        'winner' => ['Winner fanfare', 'assets/frontend/raffle/images/winner.mp3'],
    ];

    public static function screen(?Event $event = null): array
    {
        $event = $event ?? static::event();
        $saved = $event ? json_decode((string) DB::table('settings')->where('key', 'raffle.screen.' . $event->id)->value('value'), true) : null;
        $screen = array_merge(static::SCREEN_DEFAULTS, is_array($saved) ? array_intersect_key($saved, static::SCREEN_DEFAULTS) : []);
        // A deleted uploaded sound falls back to the default
        $options = static::soundOptions();
        foreach (['draw_sound', 'win_sound'] as $k) if (!array_key_exists($screen[$k], $options)) $screen[$k] = static::SCREEN_DEFAULTS[$k];
        $screen['draw_url'] = static::soundUrl($screen['draw_sound']);
        $screen['win_url'] = static::soundUrl($screen['win_sound']);
        return $screen;
    }

    public static function setScreen(Event $event, array $values): void
    {
        $options = static::soundOptions();
        $vol = fn ($k) => max(0, min(100, (int) ($values[$k] ?? static::SCREEN_DEFAULTS[$k])));
        $sound = fn ($k) => array_key_exists((string) ($values[$k] ?? ''), $options) ? (string) $values[$k] : static::SCREEN_DEFAULTS[$k];
        $clean = [
            'show_prizes' => (bool) ($values['show_prizes'] ?? true),
            'countdown' => max(0, min(60, (int) ($values['countdown'] ?? 10))),
            'shuffle' => array_key_exists($values['shuffle'] ?? '', static::SHUFFLES) ? $values['shuffle'] : 'numbers',
            'draw_sound' => $sound('draw_sound'), 'draw_volume' => $vol('draw_volume'), 'draw_loop' => (bool) ($values['draw_loop'] ?? true),
            'tick' => (bool) ($values['tick'] ?? true), 'tick_volume' => $vol('tick_volume'),
            'win_sound' => $sound('win_sound'), 'win_volume' => $vol('win_volume'),
        ];
        DB::table('settings')->updateOrInsert(['key' => 'raffle.screen.' . $event->id], ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Uploaded sounds (shared by all events): [{id, name, file}]. Files live in upload/raffle-sounds. */
    public static function uploadedSounds(): array
    {
        $list = json_decode((string) DB::table('settings')->where('key', 'raffle.sounds')->value('value'), true);
        return array_values(array_filter(is_array($list) ? $list : [], fn ($s) => !empty($s['file']) && is_file(base_path('upload/raffle-sounds/' . $s['file']))));
    }

    public static function saveUploadedSounds(array $list): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'raffle.sounds'], ['value' => json_encode(array_values($list)), 'updated_at' => now(), 'created_at' => now()]);
    }

    /** key => label: "none", the built-in sounds, and uploaded ones ("u:ID"). */
    public static function soundOptions(): array
    {
        $out = ['none' => 'No sound'];
        foreach (static::BUILTIN_SOUNDS as $k => $s) $out[$k] = $s[0];
        foreach (static::uploadedSounds() as $s) $out['u:' . $s['id']] = $s['name'] . ' (uploaded)';
        return $out;
    }

    public static function soundUrl(string $key): ?string
    {
        if (isset(static::BUILTIN_SOUNDS[$key])) return asset(static::BUILTIN_SOUNDS[$key][1]);
        if (str_starts_with($key, 'u:')) {
            foreach (static::uploadedSounds() as $s) if ('u:' . $s['id'] === $key) return asset('upload/raffle-sounds/' . $s['file']);
        }
        return null;
    }

    /** How the draw order is set: by hand, or automatically from the prize titles. */
    public const ORDERS = [
        'last_first' => 'Last prize first (e.g. 5th → 1st, grand prize last)',
        'first_first' => 'First prize first (e.g. grand / 1st → 5th)',
        'custom' => 'Custom order (arrows)',
    ];

    public static function order(?Event $event = null): string
    {
        $event = $event ?? static::event();
        $v = $event ? DB::table('settings')->where('key', 'raffle.order.' . $event->id)->value('value') : null;
        return array_key_exists((string) $v, static::ORDERS) ? $v : 'custom';
    }

    public static function setOrder(Event $event, string $mode): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'raffle.order.' . $event->id], ['value' => $mode, 'updated_at' => now(), 'created_at' => now()]);
        static::applyOrder($event);
    }

    /**
     * Rank of a prize from its title: Grand / Mega / Bumper = 0, First / 1st = 1, Second / 2nd = 2, ... "Prize 7" = 7.
     * Titles without a rank (e.g. "Special Prize") count as the smallest prizes.
     */
    public static function rank(?string $title): int
    {
        $t = strtolower((string) $title);
        if (preg_match('/\b(grand|mega|bumper|jackpot)\b/', $t)) return 0;
        $words = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'six' => 6, 'seventh' => 7, 'seven' => 7,
            'eighth' => 8, 'eight' => 8, 'ninth' => 9, 'nine' => 9, 'tenth' => 10, 'ten' => 10, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
        foreach ($words as $w => $n) if (preg_match('/\b' . $w . '\b/', $t)) return $n;
        if (preg_match('/\b(\d{1,3})(st|nd|rd|th)?\b/', $t, $m)) return (int) $m[1];
        return 999;
    }

    /** Re-number the draw order from the prize titles (does nothing in custom mode). Ties keep their current order. */
    public static function applyOrder(Event $event): void
    {
        $mode = static::order($event);
        if ($mode === 'custom') return;
        $prizes = DB::table('prizes')->where('event_id', $event->id)->orderBy('sort')->orderBy('id')->get(['id', 'prize_type']);
        $sorted = $prizes->values()->map(fn ($p, $i) => [$p->id, static::rank($p->prize_type), $i])->sort(function ($a, $b) use ($mode) {
            $cmp = $mode === 'last_first' ? $b[1] <=> $a[1] : $a[1] <=> $b[1];
            return $cmp ?: $a[2] <=> $b[2];
        })->values();
        foreach ($sorted as $i => $p) DB::table('prizes')->where('id', $p[0])->update(['sort' => $i + 1]);
    }

    /** Prizes of the event in draw order. */
    public static function prizes(?Event $event = null): Collection
    {
        $event = $event ?? static::event();
        if (!$event) return collect();
        return DB::table('prizes')->leftJoin('winners', 'winners.id', '=', 'prizes.winner_id')
            ->where('prizes.event_id', $event->id)
            ->orderBy('prizes.sort')->orderBy('prizes.id')
            ->get(['prizes.id', 'prizes.prize', 'prizes.prize_type', 'prizes.sort', 'prizes.winner_id',
                'winners.winners_name', 'winners.winners_number', 'winners.winners_country', 'winners.created_at as won_at',
                'winners.emailed_at', 'winners.email_error']);
    }

    public static function nextPrize(?Event $event = null): ?object
    {
        return static::prizes($event)->first(fn ($p) => !$p->winner_id);
    }

    /** Registrations that can still win (query on "registrations"). */
    /**
     * Everyone who can still win, as one query with the same columns in every mode:
     * cid (unique id), registration_id, entry_id, qr_code (the number), title, firstname, lastname,
     * profession, specialty, institute, country, image, entry_name.
     *
     * - "registered" / "checked_in": registrations of the event.
     * - "list": every number on "my list" — registered or not. A registered number shows the person's details;
     *   other numbers win by number (with the name / country from the imported file, if any).
     *
     * $skipped: people marked "not present" on stage ("r:ID" = registration, "e:ID" = list entry; plain ids are registrations).
     */
    public static function pool(?Event $event = null, array $skipped = [])
    {
        $event = $event ?? static::event();
        $skip = static::skipKeys($skipped);
        $wonRegs = DB::table('winners')->where('event_id', $event->id)->whereNotNull('register_id')->pluck('register_id')->all();
        $ofEvent = fn ($q) => $q->where('r.event_id', $event->id)->orWhere('r.venue', $event->venue_code);

        if (static::eligibility($event) === 'list') {
            $wonEntries = DB::table('winners')->where('event_id', $event->id)->whereNotNull('entry_id')->pluck('entry_id')->all();
            return DB::table('raffle_entries as e')
                ->leftJoin('registrations as r', fn ($j) => $j->on('r.qr_code', '=', 'e.number')->where($ofEvent))
                ->where('e.event_id', $event->id)
                ->when($wonEntries, fn ($q) => $q->whereNotIn('e.id', $wonEntries))
                ->when($wonRegs, fn ($q) => $q->where(fn ($w) => $w->whereNull('r.id')->orWhereNotIn('r.id', $wonRegs)))
                ->when($skip['e'], fn ($q) => $q->whereNotIn('e.id', $skip['e']))
                ->when($skip['r'], fn ($q) => $q->where(fn ($w) => $w->whereNull('r.id')->orWhereNotIn('r.id', $skip['r'])))
                ->select('e.id as cid', 'r.id as registration_id', 'e.id as entry_id', 'e.number as qr_code', 'r.title', 'r.firstname', 'r.lastname',
                    'r.profession', 'r.specialty', 'r.institute', DB::raw('COALESCE(r.country, e.country) as country'), 'r.image', 'e.name as entry_name');
        }

        return DB::table('registrations as r')
            ->where($ofEvent)
            ->where(fn ($q) => $q->whereNull('r.status')->orWhere('r.status', '!=', 'Rejected'))
            ->when($wonRegs, fn ($q) => $q->whereNotIn('r.id', $wonRegs))
            ->when($skip['r'], fn ($q) => $q->whereNotIn('r.id', $skip['r']))
            ->when(static::eligibility($event) === 'checked_in', fn ($q) => $q->where('r.verify_status', 'Yes'))
            ->select('r.id as cid', 'r.id as registration_id', DB::raw('NULL as entry_id'), 'r.qr_code', 'r.title', 'r.firstname', 'r.lastname',
                'r.profession', 'r.specialty', 'r.institute', 'r.country', 'r.image', DB::raw('NULL as entry_name'));
    }

    /** Pick one at random (random_int on the server). */
    public static function pick(Event $event, array $skipped = []): ?object
    {
        $pool = static::pool($event, $skipped);
        $count = (clone $pool)->count();
        if (!$count) return null;
        return (clone $pool)->orderBy('cid')->offset(random_int(0, $count - 1))->limit(1)->first();
    }

    /** "r:12" / "e:7" key of a pool row (used for "not present"). */
    public static function key(object $row): string
    {
        return $row->registration_id ? 'r:' . $row->registration_id : 'e:' . $row->entry_id;
    }

    private static function skipKeys(array $skipped): array
    {
        $out = ['r' => [], 'e' => []];
        foreach ($skipped as $k) {
            if (is_int($k) || ctype_digit((string) $k)) $out['r'][] = (int) $k;
            elseif (preg_match('/^([re]):(\d+)$/', (string) $k, $m)) $out[$m[1]][] = (int) $m[2];
        }
        return $out;
    }

    /** What the draw screen shows about a person (a registration, or a list number without one). */
    public static function person(object $r): array
    {
        if (empty($r->registration_id) && empty($r->firstname)) {
            $name = trim((string) ($r->entry_name ?? ''));
            return [
                'number' => (string) $r->qr_code, 'name' => $name !== '' ? $name : 'Number ' . $r->qr_code,
                'profession' => null, 'specialty' => null, 'institute' => null, 'country' => $r->country ?? null, 'photo' => null,
                'initials' => $name !== '' ? mb_strtoupper(collect(preg_split('/\s+/', $name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) : '#',
            ];
        }
        $photo = !empty($r->image) && $r->image !== 'no-photo.png' && is_file(base_path('upload/images/' . $r->image)) ? Thumb::url('upload/images/' . $r->image, 480) : null;
        return [
            'number' => (string) $r->qr_code,
            'name' => trim(preg_replace('/\s+/', ' ', ($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname)),
            'profession' => $r->profession,
            'specialty' => $r->specialty,
            'institute' => $r->institute,
            'country' => $r->country,
            'photo' => $photo,
            'initials' => mb_strtoupper(mb_substr(trim((string) $r->firstname), 0, 1) . mb_substr(trim((string) $r->lastname), 0, 1)),
        ];
    }
}
