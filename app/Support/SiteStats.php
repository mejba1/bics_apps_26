<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live numbers for the website "Key numbers" section. Admins type a token as the value
 * (e.g. "{registrations}+") and it is replaced with the real figure. Cached for 10 minutes.
 */
class SiteStats
{
    public const TOKENS = [
        '{registrations}' => 'Registrations across all summits',
        '{countries}' => 'Countries attendees came from',
        '{summits}' => 'Summits held or planned (Admin > Events)',
        '{host_cities}' => 'Different host cities (Admin > Events)',
    ];

    public static function all(): array
    {
        return Cache::remember('bics.site-stats', 600, function () {
            try {
                return [
                    '{registrations}' => DB::table('registrations')->count(),
                    '{countries}' => DB::table('registrations')->whereNotNull('country')->where('country', '!=', '')->distinct()->count('country'),
                    '{summits}' => DB::table('events')->where('status', '!=', 'draft')->count(),
                    '{host_cities}' => DB::table('events')->where('status', '!=', 'draft')->whereNotNull('event_venue')->where('event_venue', '!=', '')->distinct()->count('event_venue'),
                ];
            } catch (\Throwable $e) {
                return array_fill_keys(array_keys(static::TOKENS), 0);
            }
        });
    }

    /** "{registrations}+" -> ['number' => 300, 'suffix' => '+', 'text' => '300+'] (plain values are kept as text). */
    public static function value(?string $raw): array
    {
        $raw = trim((string) $raw);
        $text = strtr($raw, array_map('strval', static::all()));
        if (preg_match('/^([^\d]*)([\d,.]+)(.*)$/u', $text, $m) && $m[1] === '') {
            $number = (float) str_replace(',', '', $m[2]);
            return ['number' => $number, 'suffix' => $m[3], 'text' => number_format($number) . $m[3]];
        }
        return ['number' => null, 'suffix' => '', 'text' => $text];
    }
}
