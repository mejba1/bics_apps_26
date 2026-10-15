<?php

namespace App\Support;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * After an event, its data becomes read-only.
 *
 * An event is LOCKED when it is archived, or when its last day ended more than N days ago
 * (System › Platform settings, "events.lock_days", default 2 — time to wrap up).
 * Self check-in closes as soon as the last day ends.
 * A Super Admin can unlock an event for a while (24 h) to correct something; that is logged.
 *
 * Locked: check-in (self, help desk, Verify Attendee), gifts, deleting registrations, new admin registrations,
 *         confirmation emails, ID card assignment, the raffle (draw, prizes, winners, lists, hosts, screen).
 * Still allowed after the event: certificates, prize-winner emails, card printing, reports & exports, viewing,
 *         and editing the event itself (Admin › Events — e.g. to correct its dates or un-archive it).
 */
class EventLock
{
    public const DEFAULT_DAYS = 2;
    public const UNLOCK_HOURS = 24;

    /** What a locked event refuses (for messages and the help text). */
    public const ACTIONS = [
        'checkin' => 'check people in or undo a check-in',
        'gift' => 'hand out gifts',
        'registration' => 'add or delete registrations',
        'email' => 'send confirmation emails',
        'idcard' => 'assign ID cards',
        'raffle' => 'change the raffle (draw, prizes, winners, lists)',
    ];

    public static function days(): int
    {
        $v = DB::table('settings')->where('key', 'events.lock_days')->value('value');
        return is_numeric($v) ? max(0, min(60, (int) $v)) : static::DEFAULT_DAYS;
    }

    public static function tz(Event $e): string
    {
        return in_array($e->event_timezone, timezone_identifiers_list(), true) ? $e->event_timezone : config('app.timezone');
    }

    /** End of the event's last day, or null when it has no dates. */
    public static function endsAt(Event $e): ?Carbon
    {
        $d = $e->event_end ?: $e->event_start;
        if (!$d) return null;
        try { return Carbon::parse(str_replace('T', ' ', $d), static::tz($e))->endOfDay(); } catch (\Throwable $x) { return null; }
    }

    public static function startsAt(Event $e): ?Carbon
    {
        if (!$e->event_start) return null;
        try { return Carbon::parse(str_replace('T', ' ', $e->event_start), static::tz($e)); } catch (\Throwable $x) { return null; }
    }

    /** draft | archived | upcoming | live | ended | undated */
    public static function phase(Event $e): string
    {
        if ($e->status === 'draft') return 'draft';
        if ($e->status === 'archived') return 'archived';
        $end = static::endsAt($e);
        if (!$end) return 'undated';
        $now = Carbon::now(static::tz($e));
        if ($now->gt($end)) return 'ended';
        $start = static::startsAt($e);
        return $start && $now->lt($start->copy()->startOfDay()) ? 'upcoming' : 'live';
    }

    /** When the event locks (null = not by date). */
    public static function locksAt(Event $e): ?Carbon
    {
        $end = static::endsAt($e);
        return $end ? $end->copy()->addDays(static::days()) : null;
    }

    public static function unlocked(Event $e): bool
    {
        return $e->unlocked_until && Carbon::parse($e->unlocked_until)->isFuture();
    }

    /** Locked by its status or dates (ignoring a temporary unlock). */
    public static function lockedByRule(Event $e): bool
    {
        if ($e->status === 'archived') return true;
        $at = static::locksAt($e);
        return $at !== null && Carbon::now(static::tz($e))->gt($at);
    }

    public static function isLocked(?Event $e): bool
    {
        return $e !== null && static::lockedByRule($e) && !static::unlocked($e);
    }

    /** Short sentence for banners and refusals. */
    public static function reason(Event $e): string
    {
        if ($e->status === 'archived') return $e->event_short . ' is archived.';
        $end = static::endsAt($e);
        return $e->event_short . ' ended on ' . ($end ? $end->format('j F Y') : '—') . '.';
    }

    /** Everything a page needs to show the banner and switch off its buttons. */
    public static function state(?Event $e): array
    {
        if (!$e) return ['locked' => false, 'phase' => null];
        $tz = static::tz($e);
        $locksAt = static::locksAt($e);
        return [
            'locked' => static::isLocked($e),
            'phase' => static::phase($e),
            'byRule' => static::lockedByRule($e),
            'unlocked' => static::unlocked($e),
            'unlockedUntil' => $e->unlocked_until ? Carbon::parse($e->unlocked_until)->timezone($tz)->format('j M, H:i') : null,
            'unlockedBy' => $e->unlocked_by ? DB::table('users')->where('id', $e->unlocked_by)->value('name') : null,
            'reason' => static::reason($e),
            'locksAt' => $locksAt && $e->status !== 'archived' && !static::lockedByRule($e) && static::phase($e) === 'ended' ? $locksAt->format('j M Y, H:i') : null,
            'event' => $e,
        ];
    }

    /* ---------------- refusing ---------------- */

    /** Stop the request with a clear message when the event is locked. */
    public static function ensureOpen(?Event $e, string $action = 'checkin'): void
    {
        if (!static::isLocked($e)) return;
        $what = static::ACTIONS[$action] ?? 'change this';
        $message = static::reason($e) . ' Its data is read-only, so you cannot ' . $what . ' any more.'
            . (auth()->check() && auth()->user()->isSuperAdmin() ? ' A Super Admin can unlock it for ' . static::UNLOCK_HOURS . ' hours to correct something.' : '');
        $request = request();
        throw new HttpResponseException($request->expectsJson() || $request->ajax()
            ? response()->json(['message' => $message, 'locked' => true], 423)
            : back()->with('error', $message));
    }

    /**
     * Stricter rule for confirmation emails and deleting registrations: only the current event (Admin › Events),
     * or another event a Super Admin unlocked for corrections.
     */
    public static function isCurrentOrUnlocked(?Event $e): bool
    {
        return $e !== null && ($e->is_current || static::unlocked($e));
    }

    /** Venue codes where those actions are allowed right now (for pages that list several events). */
    public static function openVenues(): array
    {
        try {
            return Event::where('is_current', true)->orWhere('unlocked_until', '>', now())->pluck('venue_code')->all();
        } catch (\Throwable $x) {
            return [];
        }
    }

    public static function ensureCurrent(?Event $e, string $action = 'email'): void
    {
        static::ensureOpen($e, $action);
        if (static::isCurrentOrUnlocked($e)) return;
        $what = static::ACTIONS[$action] ?? 'change this';
        $cur = Event::current();
        $message = ($e ? $e->event_short . ' is a previous event' : 'This registration has no event') . ' — you can only ' . $what . ' for the current event'
            . ($cur ? ' (' . $cur->event_short . ')' : '') . '.'
            . (auth()->check() && auth()->user()->isSuperAdmin() ? ' A Super Admin can unlock it for ' . static::UNLOCK_HOURS . ' hours in Admin › Events if a correction is needed.' : '');
        $request = request();
        throw new HttpResponseException($request->expectsJson() || $request->ajax()
            ? response()->json(['message' => $message, 'locked' => true], 423)
            : back()->with('error', $message));
    }

    /** The event a registration belongs to. */
    public static function forRegistration($r): ?Event
    {
        if (is_numeric($r)) $r = DB::table('registrations')->where('id', $r)->first();
        if (!$r) return null;
        return $r->event_id ? Event::find($r->event_id) : Event::forVenue($r->venue);
    }

    /* ---------------- unlocking (Super Admin) ---------------- */

    public static function unlock(Event $e, int $userId): void
    {
        $e->forceFill(['unlocked_until' => now()->addHours(static::UNLOCK_HOURS), 'unlocked_by' => $userId])->save();
    }

    public static function relock(Event $e): void
    {
        $e->forceFill(['unlocked_until' => null, 'unlocked_by' => null])->save();
    }
}
