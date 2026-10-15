<?php

namespace App\Support;

/** Small helpers for rendering admin-entered links and images. */
class Cms
{
    /** "registration" -> site URL, "#x" / "https://..." / "mailto:" kept as is, empty -> "#". */
    public static function link(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(https?:|mailto:|tel:|//)#i', $url)) {
            return $url === '' ? '#' : $url;
        }
        return url($url);
    }

    /** Stored image path ("upload/cms/x.jpg") -> full URL; external URLs kept as is. */
    public static function image(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^(https?:|//|data:)#i', $path)) {
            return $path;
        }
        return asset(ltrim($path, '/'));
    }

    /** YouTube watch/short/embed link -> embed URL (null if not recognised). */
    public static function youtubeEmbed(?string $url): ?string
    {
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([\w-]{11})#', (string) $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        return null;
    }

    /**
     * Registration success texts from Admin > Website Pages > Site-wide (defaults fill any gaps),
     * plus "done_url_full" - the main button's link as a full address.
     */
    public static function registrationSuccess(): array
    {
        $texts = array_merge(PageSeed::registrationSuccess(), \App\Models\Page::globalContent('registration_success') ?? []);
        $texts['done_url_full'] = $texts['done_url'] !== '' ? static::link($texts['done_url']) : '';
        return $texts;
    }

    /** class + id attributes for a themed section: background (white | soft | dark) and menu anchor. */
    public static function sectionAttrs(array $c, string $default = 'white', string $extra = ''): string
    {
        $bg = ['soft' => ' is-soft', 'dark' => ' is-dark'][$c['background'] ?? $default] ?? '';
        $anchor = \Illuminate\Support\Str::slug($c['anchor'] ?? '');
        return 'class="bx-section' . $bg . ($extra ? ' ' . e($extra) : '') . '"' . ($anchor ? ' id="' . e($anchor) . '"' : '');
    }

    /** Text with one entry per line -> array of non-empty lines. */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text)), 'strlen'));
    }
}
