<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Each admin user can pin one event (venue code). The dashboard opens on it and every report downloads it by default.
 * Pinning never changes where new registrations are saved (that is the current event in Admin > Events).
 * Stored in the "settings" table as "pinned_venue.{user id}".
 */
class PinnedEvent
{
    public static function current(): string
    {
        return (string) EventSettings::get('venue_code', config('bics.venue'));
    }

    /**
     * Every event (Admin > Events) with its registration count: current event first, then newest.
     * Venue codes in registrations without an event row are added too, so nothing goes missing.
     */
    public static function venues(): Collection
    {
        $counts = DB::table('registrations')
            ->select('venue', DB::raw('COUNT(*) as total'), DB::raw('MAX(created_at) as last_at'))
            ->whereNotNull('venue')->where('venue', '!=', '')
            ->groupBy('venue')->get()->keyBy('venue');
        $venues = collect();
        try {
            $events = Event::orderByDesc('is_current')->orderByRaw("status = 'draft'")->orderByDesc('event_start')->orderByDesc('id')->get();
        } catch (\Throwable $e) {
            $events = collect(); // events table not created yet
        }
        foreach ($events as $e) {
            $c = $counts->pull($e->venue_code);
            $venues->push((object) ['venue' => $e->venue_code, 'name' => $e->event_short ?: $e->event_name, 'event_id' => $e->id,
                'status' => $e->status, 'is_current' => $e->is_current, 'total' => $c->total ?? 0, 'last_at' => $c->last_at ?? null,
                'phase' => EventLock::phase($e), 'locked' => EventLock::isLocked($e)]);
        }
        foreach ($counts->sortByDesc('last_at') as $c) {
            $venues->push((object) ['venue' => $c->venue, 'name' => $c->venue, 'event_id' => null, 'status' => 'archived', 'is_current' => false, 'total' => $c->total, 'last_at' => $c->last_at, 'phase' => 'archived', 'locked' => true]);
        }
        $current = static::current();
        if ($current !== '' && !$venues->contains('venue', $current)) {
            $venues->prepend((object) ['venue' => $current, 'name' => $current, 'event_id' => null, 'status' => 'published', 'is_current' => true, 'total' => 0, 'last_at' => null, 'phase' => 'undated', 'locked' => false]);
        }
        return $venues->values();
    }

    public static function get(?int $userId = null): string
    {
        $userId = $userId ?? Auth::id();
        $pinned = $userId ? DB::table('settings')->where('key', static::key($userId))->value('value') : null;
        if ($pinned && static::exists($pinned)) return $pinned;
        return static::current();
    }

    /** Pin an event; pinning the current event clears the pin (it is the default anyway). */
    public static function set(int $userId, string $venue): void
    {
        if ($venue === static::current()) {
            DB::table('settings')->where('key', static::key($userId))->delete();
            return;
        }
        DB::table('settings')->updateOrInsert(['key' => static::key($userId)], ['value' => $venue, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** The pinned event as an Event (the current event when the pinned venue has no event row). */
    public static function event(?int $userId = null): ?Event
    {
        try {
            return Event::forVenue(static::get($userId)) ?? Event::current();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The event a page shows: ?event={id} when given (looking at another event without re-pinning),
     * otherwise the user's pinned event.
     */
    public static function forPage(\Illuminate\Http\Request $request, string $param = 'event'): ?Event
    {
        $id = (int) $request->query($param, $request->input($param, 0));
        return ($id ? Event::find($id) : null) ?? static::event();
    }

    /** Registrations of an event (by event id, or its venue code for older rows). */
    public static function registrations(Event $event)
    {
        return DB::table('registrations')->where(fn ($q) => $q->where('event_id', $event->id)->orWhere(fn ($w) => $w->whereNull('event_id')->where('venue', $event->venue_code)));
    }

    public static function exists(string $venue): bool
    {
        return $venue === static::current() || Event::forVenue($venue) !== null || DB::table('registrations')->where('venue', $venue)->exists();
    }

    private static function key(int $userId): string
    {
        return 'pinned_venue.' . $userId;
    }
}
