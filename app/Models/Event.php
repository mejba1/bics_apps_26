<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An event (Admin > Events). Exactly one event is "current": the public registration form,
 * the raffle and the confirmation email use it. Registrations are linked by event_id and by
 * venue_code (the "venue" column the older screens and reports use).
 *
 * Dates are "Y-m-d H:i" strings in the event's own timezone.
 */
class Event extends Model
{
    public const DATES = ['event_start', 'event_end', 'registration_opens', 'registration_closes'];

    public const DEFAULT_CLOSED_MESSAGE = 'Registration for this event is now closed.';
    public const DEFAULT_UPCOMING_MESSAGE = 'Registration opens soon.';

    protected $fillable = [
        'event_name', 'event_short', 'slug', 'venue_code', 'tracking_no', 'event_venue', 'event_timezone',
        'event_start', 'event_end', 'registration_opens', 'registration_closes',
        'registration_closed_message', 'registration_upcoming_message', 'status',
    ];

    protected $casts = ['is_current' => 'boolean'];

    private static ?Event $currentCache = null;
    private static bool $currentLoaded = false;

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /** The event new registrations go to (null only before the events table exists / is filled). */
    public static function current(): ?Event
    {
        if (static::$currentLoaded) return static::$currentCache;
        static::$currentLoaded = true;
        try {
            return static::$currentCache = static::where('is_current', true)->first() ?? static::orderByDesc('id')->first();
        } catch (\Throwable $e) {
            return static::$currentCache = null; // events table not created yet
        }
    }

    public static function forgetCurrent(): void
    {
        static::$currentCache = null;
        static::$currentLoaded = false;
    }

    /** The event a registration belongs to, by its venue code. */
    public static function forVenue(?string $code): ?Event
    {
        if (!$code) return null;
        $current = static::current();
        if ($current && $current->venue_code === $code) return $current;
        static $found = [];
        if (!array_key_exists($code, $found)) {
            try { $found[$code] = static::where('venue_code', $code)->first(); } catch (\Throwable $e) { $found[$code] = null; }
        }
        return $found[$code];
    }

    /** Make this the only current event (and publish it). */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::where('id', '!=', $this->id)->where('is_current', true)->update(['is_current' => false]);
            $this->is_current = true;
            if ($this->status !== 'published') $this->status = 'published';
            $this->save();
        });
        static::forgetCurrent();
    }

    public static function uniqueSlug(string $text, ?int $ignoreId = null): string
    {
        $base = Str::slug($text) ?: 'event';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function timezone(): string
    {
        return in_array($this->event_timezone, timezone_identifiers_list(), true) ? $this->event_timezone : 'UTC';
    }

    /** A stored date as a Carbon in the event timezone (null if empty / invalid). */
    public function dateOf(string $key): ?Carbon
    {
        $v = $this->getAttribute($key);
        if (!$v) return null;
        try {
            return Carbon::parse(str_replace('T', ' ', $v), $this->timezone());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "28 October 2026", "28–30 October 2026", "30 Oct – 2 Nov 2026"... */
    public function datesLabel(): string
    {
        $start = $this->dateOf('event_start');
        if (!$start) return '';
        $end = $this->dateOf('event_end');
        if (!$end || $end->isSameDay($start) || $end->lessThan($start)) return $start->format('j F Y');
        if ($start->isSameMonth($end)) return $start->format('j') . '–' . $end->format('j F Y');
        if ($start->isSameYear($end)) return $start->format('j M') . ' – ' . $end->format('j M Y');
        return $start->format('j M Y') . ' – ' . $end->format('j M Y');
    }

    /** Registration window: state = open | upcoming | closed, plus the opening/closing moments. */
    public function registrationWindow(): array
    {
        $opens = $this->dateOf('registration_opens');
        $closes = $this->dateOf('registration_closes');
        $now = Carbon::now($this->timezone());
        $state = 'open';
        if ($opens && $now->lessThan($opens)) $state = 'upcoming';
        elseif ($closes && $now->greaterThanOrEqualTo($closes)) $state = 'closed';
        return ['state' => $state, 'opens' => $opens, 'closes' => $closes];
    }

    /** The values in the shape EventSettings has always returned. */
    public function toSettings(): array
    {
        $out = [];
        foreach (\App\Support\EventSettings::KEYS as $k) $out[$k] = (string) ($this->getAttribute($k) ?? '');
        if ($out['registration_closed_message'] === '') $out['registration_closed_message'] = static::DEFAULT_CLOSED_MESSAGE;
        if ($out['registration_upcoming_message'] === '') $out['registration_upcoming_message'] = static::DEFAULT_UPCOMING_MESSAGE;
        return $out;
    }
}
