<?php

namespace App\Support;

use App\Models\Event;
use Carbon\Carbon;

/**
 * The CURRENT event's details (Admin > Events, the event marked "current").
 * Kept as the one place the site reads event values from, so pages, emails, cards and
 * reports did not have to change when events moved from the "settings" table to App\Models\Event.
 * config/bics.php is only a fallback before the events table exists.
 * Dates are entered in the event's own timezone and handed to the browser with that offset,
 * so every visitor counts down to the same moment.
 */
class EventSettings
{
    public const KEYS = [
        'event_name', 'event_short', 'event_venue', 'event_timezone', 'event_start', 'event_end',
        'registration_opens', 'registration_closes', 'registration_closed_message', 'registration_upcoming_message',
        'venue_code', 'tracking_no',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache !== null) return static::$cache;
        $event = Event::current();
        if ($event) return static::$cache = $event->toSettings();
        return static::$cache = [
            'event_name' => config('bics.event_name'),
            'event_short' => config('bics.event_short'),
            'event_venue' => config('bics.event_venue'),
            'event_timezone' => config('app.timezone'),
            'event_start' => '',
            'event_end' => '',
            'registration_opens' => '',
            'registration_closes' => '',
            'registration_closed_message' => Event::DEFAULT_CLOSED_MESSAGE,
            'registration_upcoming_message' => Event::DEFAULT_UPCOMING_MESSAGE,
            'venue_code' => config('bics.venue'),
            'tracking_no' => config('bics.tracking_no'),
        ];
    }

    public static function get(string $key, $default = null)
    {
        $v = static::all()[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /** Update the current event. */
    public static function save(array $values): void
    {
        $event = Event::current();
        if (!$event) return;
        $event->fill(array_intersect_key($values, array_flip(static::KEYS)))->save();
        static::forget();
    }

    public static function forget(): void
    {
        static::$cache = null;
        Event::forgetCurrent();
    }

    public static function timezone(): string
    {
        $tz = static::get('event_timezone', config('app.timezone'));
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    /** A stored "Y-m-d H:i" setting as a Carbon in the event timezone (null if empty / invalid). */
    public static function date(string $key): ?Carbon
    {
        $v = static::get($key);
        if (!$v) return null;
        try {
            return Carbon::parse(str_replace('T', ' ', $v), static::timezone());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "28 October 2026", "28–30 October 2026", "30 Oct – 2 Nov 2026"... */
    public static function datesLabel(): string
    {
        $start = static::date('event_start');
        if (!$start) return (string) config('bics.event_dates');
        $end = static::date('event_end');
        if (!$end || $end->isSameDay($start) || $end->lessThan($start)) return $start->format('j F Y');
        if ($start->isSameMonth($end)) return $start->format('j') . '–' . $end->format('j F Y');
        if ($start->isSameYear($end)) return $start->format('j M') . ' – ' . $end->format('j M Y');
        return $start->format('j M Y') . ' – ' . $end->format('j M Y');
    }

    /**
     * Registration window: state = open | upcoming | closed, plus the opening/closing moments.
     */
    public static function registration(): array
    {
        $opens = static::date('registration_opens');
        $closes = static::date('registration_closes');
        $now = Carbon::now(static::timezone());
        $state = 'open';
        if ($opens && $now->lessThan($opens)) $state = 'upcoming';
        elseif ($closes && $now->greaterThanOrEqualTo($closes)) $state = 'closed';
        return ['state' => $state, 'opens' => $opens, 'closes' => $closes];
    }

    public static function registrationOpen(): bool
    {
        return static::registration()['state'] === 'open';
    }

    /** Everything the page scripts need (countdowns), with full ISO dates. */
    public static function forBrowser(): array
    {
        $reg = static::registration();
        return [
            'eventStart' => optional(static::date('event_start'))->toIso8601String(),
            'registrationState' => $reg['state'],
            'registrationOpens' => optional($reg['opens'])->toIso8601String(),
            'registrationCloses' => optional($reg['closes'])->toIso8601String(),
        ];
    }
}
