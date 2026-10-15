<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the whole BICS platform (every summit, not just the current one) — shown on the sign-in pages.
 * Cached for 10 minutes.
 */
class PlatformStats
{
    public static function summary(): array
    {
        return Cache::remember('platform-stats:v2', 600, function () {
            try {
                $events = DB::table('events')->where('status', '!=', 'draft')
                    ->select('id', 'event_name', 'event_short', 'event_venue', 'event_start', 'is_current')->get();
                $people = DB::table('participants')->when(\Illuminate\Support\Facades\Schema::hasColumn('participants', 'merged_into_id'), fn ($q) => $q->whereNull('merged_into_id'))->count();
                $countries = DB::table('registrations')->whereNotNull('country')->where('country', '!=', '')->distinct()->count('country');
            } catch (\Throwable $e) {
                return ['events' => 0, 'participants' => 0, 'countries' => 0, 'timeline' => []];
            }
            // oldest → newest, the current summit last-but-highlighted
            $timeline = $events->sortBy(fn ($e) => [$e->event_start ? 1 : 0, $e->event_start ?: '', $e->id])->values()->map(fn ($e) => [
                'short' => $e->event_short ?: $e->event_name,
                'current' => (bool) $e->is_current,
                'upcoming' => $e->event_start && strtotime($e->event_start) > time(),
            ])->all();
            return ['events' => $events->count(), 'participants' => $people, 'countries' => $countries, 'timeline' => $timeline];
        });
    }

    /** 1234 → "1.2k" for the small stat tiles. */
    public static function short(int $n): string
    {
        return $n >= 10000 ? round($n / 1000) . 'k' : ($n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k' : (string) $n);
    }
}
