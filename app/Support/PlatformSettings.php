<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * System › Platform settings: participant ID prefix and how long logs are kept (0 = keep forever).
 *   participants.prefix · retention.audit_days · retention.stats_days · retention.logins_days
 */
class PlatformSettings
{
    public const KEYS = [
        'audit_days' => ['retention.audit_days', 365],      // admin audit log
        'stats_days' => ['retention.stats_days', 0],        // daily profile visit counts (0 = keep)
        'logins_days' => ['retention.logins_days', 180],    // participant sign-in history & used sign-in codes
        'lock_days' => ['events.lock_days', 2],              // ended events become read-only this many days after their last day (App\Support\EventLock)
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache !== null) return static::$cache;
        $rows = DB::table('settings')->whereIn('key', array_merge(array_column(static::KEYS, 0), ['participants.prefix']))->pluck('value', 'key');
        $out = ['prefix' => \App\Models\Participant::prefix()];
        foreach (static::KEYS as $k => [$key, $default]) $out[$k] = isset($rows[$key]) && is_numeric($rows[$key]) ? (int) $rows[$key] : $default;
        return static::$cache = $out;
    }

    public static function get(string $k)
    {
        return static::all()[$k] ?? null;
    }

    public static function save(array $values): void
    {
        $now = now();
        $put = fn ($key, $v) => DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string) $v, 'updated_at' => $now, 'created_at' => $now]);
        if (isset($values['prefix'])) $put('participants.prefix', strtoupper($values['prefix']));
        foreach (static::KEYS as $k => [$key]) if (array_key_exists($k, $values)) $put($key, (int) $values[$k]);
        static::$cache = null;
    }

    /** Delete what is older than the chosen periods. Returns [what => rows deleted]. */
    public static function prune(): array
    {
        $s = static::all();
        $out = ['audit log' => Audit::prune()];
        $out['profile visit counts'] = $s['stats_days'] > 0 ? DB::table('profile_stats')->where('day', '<', now()->subDays($s['stats_days'])->toDateString())->delete() : 0;
        $out['participant sign-ins'] = $s['logins_days'] > 0 ? DB::table('participant_logins')->where('created_at', '<', now()->subDays($s['logins_days']))->delete() : 0;
        $out['used sign-in codes'] = DB::table('participant_login_codes')->where('created_at', '<', now()->subDays(7))->delete();
        return $out;
    }
}
