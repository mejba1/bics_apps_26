<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The web address used in participant profile links, QR codes, vCards and NFC card links
 * (Admin > Participant Profiles > Link settings).
 *
 * - auto (default): the address the site is running on — http://localhost/bics on this computer,
 *   https://bics.org once uploaded to the server. Nothing to change when going live.
 * - custom: a fixed address, e.g. https://card.bics.org (must point to this site).
 *
 * Printed QR codes and written NFC chips keep the address they were made with, so set the final address
 * before printing / encoding real cards. isLocal() tells the admin pages to warn about that.
 */
class PublicLinks
{
    private static ?string $base = null;

    public static function settings(): array
    {
        try {
            $rows = DB::table('settings')->whereIn('key', ['links.mode', 'links.base_url'])->pluck('value', 'key');
        } catch (\Throwable $e) {
            $rows = collect();
        }
        $mode = ($rows['links.mode'] ?? 'auto') === 'custom' ? 'custom' : 'auto';
        return ['mode' => $mode, 'base_url' => (string) ($rows['links.base_url'] ?? '')];
    }

    /** e.g. "http://localhost/bics" or "https://bics.org" (no trailing slash). */
    public static function base(): string
    {
        if (static::$base !== null) return static::$base;
        $s = static::settings();
        $custom = rtrim(trim($s['base_url']), '/');
        return static::$base = ($s['mode'] === 'custom' && filter_var($custom, FILTER_VALIDATE_URL)) ? $custom : rtrim(url('/'), '/');
    }

    public static function save(string $mode, ?string $baseUrl): void
    {
        foreach (['links.mode' => $mode === 'custom' ? 'custom' : 'auto', 'links.base_url' => rtrim(trim((string) $baseUrl), '/')] as $k => $v) {
            DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
        }
        static::$base = null;
    }

    /** True when links point to this computer / a local network (fine for testing, not for printed cards). */
    public static function isLocal(?string $base = null): bool
    {
        $host = strtolower((string) parse_url($base ?? static::base(), PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test') || str_ends_with($host, '.local')) return true;
        if (filter_var($host, FILTER_VALIDATE_IP)) return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return false;
    }

    public static function forget(): void
    {
        static::$base = null;
    }
}
