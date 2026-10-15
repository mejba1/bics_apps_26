<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Birthday wishes (Admin › Engagement › Birthdays).
 * Automatic: `php artisan bics:birthdays` (every 15 minutes) emails everyone whose birthday it is, once a year, from the
 * chosen send time on (in the chosen time zone), when switched on. 29 February birthdays are wished on 28 February in
 * other years. Manual: a custom card to one person at any time.
 */
class Birthdays
{
    public const DEFAULTS = [
        'enabled' => '0',
        'send_time' => '09:00',
        'timezone' => 'Asia/Dhaka',
        'subject' => 'Happy birthday, {first_name}! 🎂',
        'headline' => 'Happy Birthday',
        'email_body' => "Dear {name},\n\nEveryone at the **Beacon International Cancer Summit** wishes you a very happy birthday and a healthy, joyful year ahead.\n\nThank you for being part of the BICS community — we look forward to seeing you again soon.",
        'card_message' => 'Wishing you a year full of health, joy and success.',
        'signature' => 'The BICS family',
        'dashboard' => '1',
    ];

    public const VARS = [
        '{first_name}' => 'First name', '{last_name}' => 'Last name', '{name}' => 'Full name with title', '{age}' => 'Age they turn',
        '{age_ordinal}' => '“45th”', '{events}' => 'BICS events attended', '{since}' => 'Year of their first BICS event', '{participant_id}' => 'Participant ID',
    ];

    private static ?array $cache = null;

    public static function settings(): array
    {
        if (static::$cache !== null) return static::$cache;
        $rows = DB::table('settings')->where('key', 'like', 'birthdays.%')->pluck('value', 'key');
        $out = [];
        foreach (static::DEFAULTS as $k => $v) $out[$k] = isset($rows['birthdays.' . $k]) ? (string) $rows['birthdays.' . $k] : $v;
        if (!in_array($out['timezone'], timezone_identifiers_list(), true)) $out['timezone'] = 'UTC';
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $out['send_time'])) $out['send_time'] = '09:00';
        return static::$cache = $out;
    }

    public static function save(array $values): void
    {
        $now = now();
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, static::DEFAULTS)) continue;
            DB::table('settings')->updateOrInsert(['key' => 'birthdays.' . $k], ['value' => is_bool($v) ? ($v ? '1' : '0') : (string) $v, 'updated_at' => $now, 'created_at' => $now]);
        }
        static::$cache = null;
    }

    public static function enabled(): bool
    {
        return static::settings()['enabled'] === '1';
    }

    public static function now(): Carbon
    {
        return Carbon::now(static::settings()['timezone']);
    }

    /* ---------------- who ---------------- */

    /** Participants who can get wishes (active, not merged / deleted, with a date of birth). */
    public static function people(bool $includeOptedOut = false)
    {
        return DB::table('participants')->whereNull('deleted_at')->whereNull('merged_into_id')->where('status', 'active')->whereNotNull('birth_date')
            ->when(!$includeOptedOut, fn ($q) => $q->where('birthday_wishes', true))
            ->select('id', 'code', 'title', 'first_name', 'last_name', 'email', 'photo', 'birth_date', 'birthday_wishes', 'country', 'organization');
    }

    /** People whose birthday is on $day (29 Feb birthdays on 28 Feb in non-leap years). */
    public static function on(Carbon $day, bool $includeOptedOut = false): Collection
    {
        $q = static::people($includeOptedOut)->where(function ($w) use ($day) {
            $w->where(fn ($x) => $x->whereMonth('birth_date', $day->month)->whereDay('birth_date', $day->day));
            if ($day->month === 2 && $day->day === 28 && !$day->isLeapYear()) $w->orWhere(fn ($x) => $x->whereMonth('birth_date', 2)->whereDay('birth_date', 29));
        });
        return $q->orderBy('first_name')->get();
    }

    /** Everyone with their next birthday within $days days (today = 0), soonest first. */
    public static function upcoming(int $days = 30, bool $includeOptedOut = true): Collection
    {
        $today = static::now()->startOfDay();
        return static::people($includeOptedOut)->get()->map(function ($p) use ($today) {
            $p->next = static::nextBirthday($p->birth_date, $today);
            $p->in_days = (int) $today->diffInDays($p->next);
            $p->turns = (int) Carbon::parse($p->birth_date)->diffInYears($p->next);
            return $p;
        })->filter(fn ($p) => $p->in_days <= $days)->sortBy([['in_days', 'asc'], ['first_name', 'asc']])->values();
    }

    public static function nextBirthday(string $birth, Carbon $today): Carbon
    {
        $b = Carbon::parse($birth);
        $make = function (int $year) use ($b, $today) {
            $d = $b->month === 2 && $b->day === 29 && !Carbon::create($year, 1, 1)->isLeapYear() ? 28 : $b->day;
            return Carbon::create($year, $b->month, $d, 0, 0, 0, $today->timezone);
        };
        $next = $make($today->year);
        return $next->lt($today) ? $make($today->year + 1) : $next;
    }

    /** Birthdays per month (1–12) for the chart. */
    public static function byMonth(): array
    {
        $rows = static::people(true)->select(DB::raw('MONTH(birth_date) m'), DB::raw('COUNT(*) n'))->groupByRaw('MONTH(birth_date)')->pluck('n', 'm');
        $out = [];
        for ($m = 1; $m <= 12; $m++) $out[$m] = (int) ($rows[$m] ?? 0);
        return $out;
    }

    /* ---------------- texts ---------------- */

    public static function vars(object $p, ?Carbon $on = null): array
    {
        $on = $on ?: static::now();
        $age = $p->birth_date ? (int) Carbon::parse($p->birth_date)->diffInYears(static::nextBirthday($p->birth_date, $on->copy()->startOfDay())) : null;
        $events = DB::table('registrations')->where('participant_id', $p->id)->count();
        $first = DB::table('registrations')->where('participant_id', $p->id)->min('created_at');
        $name = trim(($p->title ? $p->title . ' ' : '') . $p->first_name . ' ' . $p->last_name);
        return [
            '{first_name}' => (string) $p->first_name, '{last_name}' => (string) $p->last_name, '{name}' => $name,
            '{age}' => $age ? (string) $age : '', '{age_ordinal}' => $age ? BirthdayCard::ordinal($age) : '',
            '{events}' => (string) $events, '{since}' => $first ? substr($first, 0, 4) : '', '{participant_id}' => (string) $p->code,
        ];
    }

    public static function person(object $p, ?Carbon $on = null): array
    {
        $v = static::vars($p, $on);
        $photo = $p->photo && is_file(base_path($p->photo)) ? base_path($p->photo) : null;
        return ['name' => $v['{name}'], 'first_name' => $p->first_name, 'photo' => $photo, 'age' => $v['{age}'] ? (int) $v['{age}'] : null];
    }

    /* ---------------- cards ---------------- */

    public static function cards(): Collection
    {
        return DB::table('birthday_cards')->orderByDesc('is_default')->orderBy('kind')->orderBy('id')->get();
    }

    public static function defaultCard(): ?object
    {
        return DB::table('birthday_cards')->where('is_default', true)->first() ?? DB::table('birthday_cards')->orderBy('id')->first();
    }

    /** Made-up person for design previews (a monogram where the photo goes). */
    public static function sample(): array
    {
        return ['name' => 'Dr Sarah Rahman', 'first_name' => 'Sarah', 'photo' => null, 'age' => 42, 'monogram' => 'SR'];
    }

    /* ---------------- sending ---------------- */

    /**
     * Email a birthday card. $opts: card_id, subject, email_body, card (overrides for the picture), to (other address),
     * mode (auto|manual|test). Returns [ok, message].
     */
    public static function send(object $p, array $opts = []): array
    {
        $s = static::settings();
        $mode = $opts['mode'] ?? 'manual';
        $today = static::now();
        $card = !empty($opts['card_id']) ? DB::table('birthday_cards')->where('id', $opts['card_id'])->first() : static::defaultCard();
        if (!$card) return [false, 'There is no birthday card design.'];
        $to = $opts['to'] ?? $p->email;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return static::log($p, $mode, $card, null, 'failed', 'No valid email address.', $opts);

        $vars = static::vars($p, $today);
        $cardOpts = $opts['card'] ?? [];
        foreach (['headline', 'message', 'signature'] as $k) if (isset($cardOpts[$k])) $cardOpts[$k] = strtr((string) $cardOpts[$k], $vars);
        $base = BirthdayCard::options($card, $cardOpts);
        foreach (['headline', 'message', 'signature'] as $k) $cardOpts[$k] = strtr($base[$k], $vars);

        $subject = EmailTemplate::text($opts['subject'] ?? $s['subject'], $vars);
        $body = $opts['email_body'] ?? $s['email_body'];
        try {
            $jpeg = BirthdayCard::jpeg(static::person($p, $today), $card, $cardOpts);
            Mail::to($to, $vars['{name}'])->send(new \App\Mail\BirthdayMail($subject, $body, $vars, $jpeg, Str::slug('happy-birthday-' . $p->first_name) . '.jpg'));
        } catch (\Throwable $e) {
            Log::warning('BICS birthday email failed', ['participant_id' => $p->id, 'error' => $e->getMessage()]);
            return static::log($p, $mode, $card, $to, 'failed', Str::limit($e->getMessage(), 240), $opts + ['subject' => $subject]);
        }
        return static::log($p, $mode, $card, $to, 'sent', null, $opts + ['subject' => $subject, 'email_body' => $body]);
    }

    private static function log(object $p, string $mode, ?object $card, ?string $to, string $status, ?string $error, array $opts): array
    {
        if ($mode !== 'test') {
            $year = static::now()->year;
            $row = ['participant_id' => $p->id, 'year' => $year, 'card_id' => $card->id ?? null, 'mode' => $mode, 'email' => $to,
                'subject' => Str::limit((string) ($opts['subject'] ?? ''), 195, ''), 'message' => $opts['email_body'] ?? null,
                'card_options' => !empty($opts['card']) ? json_encode($opts['card']) : null, 'status' => $status, 'error' => $error,
                'sent_by' => $mode === 'auto' ? null : auth()->id(), 'sent_at' => $status === 'sent' ? now() : null, 'updated_at' => now()];
            if ($mode === 'auto') {
                DB::table('birthday_greetings')->updateOrInsert(['participant_id' => $p->id, 'auto_year' => $year], $row + ['created_at' => now()]);
            } else {
                DB::table('birthday_greetings')->insert($row + ['created_at' => now()]);
            }
        }
        return $status === 'sent' ? [true, 'Birthday card sent to ' . $to . '.'] : [false, $error ?: 'Not sent.'];
    }

    /** The automatic run. Returns [sent, failed, waiting-reason|null]. */
    public static function run(bool $ignoreTime = false, bool $ignoreSwitch = false, int $limit = 150): array
    {
        if (!static::enabled() && !$ignoreSwitch) return [0, 0, 'Birthday wishes are switched off.'];
        $now = static::now();
        if (!$ignoreTime && $now->format('H:i') < static::settings()['send_time']) return [0, 0, 'Waiting for the send time (' . static::settings()['send_time'] . ').'];
        $done = DB::table('birthday_greetings')->where('auto_year', $now->year)->where(fn ($q) => $q->where('status', 'sent')->orWhere('updated_at', '>', now()->subHours(3)))->pluck('participant_id')->all();
        $sent = $failed = 0;
        foreach (static::on($now)->whereNotIn('id', $done)->take($limit) as $p) {
            [$ok] = static::send($p, ['mode' => 'auto']);
            $ok ? $sent++ : $failed++;
        }
        return [$sent, $failed, null];
    }

    /** Greeting status for this year, per participant id: sent | failed (latest). */
    public static function statusThisYear(array $ids): array
    {
        if (!$ids) return [];
        return DB::table('birthday_greetings')->where('year', static::now()->year)->whereIn('participant_id', $ids)->where('mode', '!=', 'test')
            ->orderBy('id')->get(['participant_id', 'status', 'mode', 'sent_at', 'error'])->keyBy('participant_id')->all();
    }
}
