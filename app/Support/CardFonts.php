<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fonts for card print files (TCPDF). The studio uses Google Fonts in the browser; for PDFs the same family / weight is
 * downloaded once as TrueType from Google Fonts, converted for TCPDF and kept in storage/app/card-fonts
 * (upload that folder with the site, or run `php artisan cards:fonts` on the server, if the server cannot reach Google).
 * When a font cannot be had, DejaVu Sans (bundled with TCPDF) is used and reported.
 */
class CardFonts
{
    public const WEIGHTS = [300, 400, 500, 600, 700, 800];

    /** Fonts that could not be loaded during this request ("Oswald 800 italic"). */
    public static array $missing = [];

    private static array $done = [];

    public static function dir(): string
    {
        $d = storage_path('app/card-fonts/');
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** TCPDF font name for a family / weight / style, added to $pdf (downloads and converts it the first time). */
    public static function use(\TCPDF $pdf, string $family, int $weight = 400, bool $italic = false): string
    {
        $key = $family . '|' . $weight . '|' . (int) $italic;
        if (isset(static::$done[$key]) && static::$done[$key][0] === spl_object_id($pdf)) return static::$done[$key][1];
        $name = static::ensure($family, $weight, $italic);
        if ($name) {
            $pdf->AddFont($name, '', static::dir() . $name . '.php');
        } else {
            static::$missing[$family . ' ' . $weight . ($italic ? ' italic' : '')] = true;
            $name = 'dejavusans';
        }
        static::$done[$key] = [spl_object_id($pdf), $name];
        return $name;
    }

    /** Converted font name, or null. Tries the exact face, then without italic, then the nearest weight. */
    public static function ensure(string $family, int $weight, bool $italic): ?string
    {
        $tries = [[$weight, $italic]];
        if ($italic) $tries[] = [$weight, false];
        foreach ([400, 700] as $w) if ($w !== $weight) $tries[] = [$w, false];
        foreach ($tries as [$w, $i]) {
            $name = static::fontName($family, $w, $i);
            if (is_file(static::dir() . $name . '.php')) return $name;
            if (static::download($family, $w, $i, $name)) return $name;
        }
        return null;
    }

    public static function fontName(string $family, int $weight, bool $italic): string
    {
        return 'cs' . strtolower(preg_replace('/[^a-z0-9]/i', '', $family)) . $weight . ($italic ? 'i' : '');
    }

    private static function download(string $family, int $weight, bool $italic, string $name): bool
    {
        $failed = static::dir() . $name . '.failed';
        if (is_file($failed) && filemtime($failed) > time() - 86400) return false;   // don't retry for a day
        try {
            $css = Http::timeout(15)->withHeaders(['User-Agent' => 'curl/8'])->get('https://fonts.googleapis.com/css2', [
                'family' => $family . ':' . ($italic ? 'ital,wght@1,' : 'wght@') . $weight,
            ]);
            if (!$css->ok() || !preg_match('#url\((https://fonts\.gstatic\.com/[^)]+\.ttf)\)#', $css->body(), $m)) throw new \RuntimeException('no TrueType link (' . $css->status() . ')');
            $ttf = Http::timeout(30)->get($m[1]);
            if (!$ttf->ok() || strlen($ttf->body()) < 1000) throw new \RuntimeException('download failed');
            @mkdir(static::dir() . 'ttf', 0775, true);
            $file = static::dir() . 'ttf/' . $name . '.ttf';
            file_put_contents($file, $ttf->body());
            // TCPDF names the converted font after the file name
            $made = \TCPDF_FONTS::addTTFfont($file, 'TrueTypeUnicode', '', 32, static::dir());
            if ($made !== $name || !is_file(static::dir() . $name . '.php')) throw new \RuntimeException('conversion failed');
            @unlink($failed);
            return true;
        } catch (\Throwable $e) {
            @file_put_contents($failed, $e->getMessage());
            Log::warning('BICS card font not available', ['font' => "$family $weight" . ($italic ? ' italic' : ''), 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** Every family / weight / style the studio offers (for `php artisan cards:fonts`). */
    public static function all(callable $progress = null): array
    {
        $out = [];
        foreach (\App\Models\CardTemplate::FONTS as $family) {
            foreach (static::WEIGHTS as $w) foreach ([false, true] as $i) {
                $name = static::fontName($family, $w, $i);
                $ok = is_file(static::dir() . $name . '.php') || static::download($family, $w, $i, $name);
                $out[$family . ' ' . $w . ($i ? ' italic' : '')] = $ok;
                if ($progress) $progress($family . ' ' . $w . ($i ? ' italic' : ''), $ok);
            }
        }
        return $out;
    }
}
