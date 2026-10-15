<?php

namespace App\Support;

/**
 * Starter card designs for the Card Design Studio (all positions in millimetres, font sizes in points).
 *
 * Template layout:
 *   width, height (mm, without bleed) · bleed (mm, printed beyond the cut) · safe (mm, keep text inside) · double_sided
 *   front / back: { background: {color, image, fit}, elements: [ … ] }
 * Element (all): id, type, name, x, y, w, h, rotate, opacity, locked, hidden
 *   text:    text (with {{placeholders}}), font, size, weight, italic, color, align, valign, upper, letter, line, fit, maxLines
 *   image:   source (photo | logo | upload), src (upload path), fit (cover | contain), radius, border, borderColor
 *   qr:      source (profile | nfc | custom), text, color, bg
 *   rect:    fill, stroke, strokeWidth, radius · ellipse: fill, stroke, strokeWidth · line: stroke, strokeWidth
 *   icon:    icon (see assets/backend/card-studio/icons.json), color
 */
class CardPresets
{
    public const SIZES = [
        'cr80_portrait' => ['CR80 card — portrait (54 × 85.6 mm)', 54, 85.6],
        'cr80_landscape' => ['CR80 card — landscape (85.6 × 54 mm)', 85.6, 54],
        'badge_a7' => ['Badge A7 (74 × 105 mm)', 74, 105],
        'badge_a6' => ['Badge A6 (105 × 148 mm)', 105, 148],
    ];

    private static int $n = 0;

    private static function el(string $type, string $name, float $x, float $y, float $w, float $h, array $props = []): array
    {
        return array_merge(['id' => 'e' . (++static::$n), 'type' => $type, 'name' => $name, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'rotate' => 0, 'opacity' => 1, 'locked' => false, 'hidden' => false], $props);
    }

    private static function text(string $name, float $x, float $y, float $w, float $h, string $text, array $p = []): array
    {
        return static::el('text', $name, $x, $y, $w, $h, array_merge(['text' => $text, 'font' => 'Plus Jakarta Sans', 'size' => 8, 'weight' => 400,
            'italic' => false, 'color' => '#101a2e', 'align' => 'center', 'valign' => 'middle', 'upper' => false, 'letter' => 0, 'line' => 1.15,
            'fit' => true, 'maxLines' => 1], $p));
    }

    public static function all(): array
    {
        return ['badge' => ['Conference badge (portrait)', static::badge()], 'business' => ['Business card (landscape)', static::business()]];
    }

    public static function blank(float $w = 54, float $h = 85.6): array
    {
        return ['width' => $w, 'height' => $h, 'bleed' => 2, 'safe' => 3, 'double_sided' => true,
            'front' => ['background' => ['color' => '#ffffff', 'image' => '', 'fit' => 'cover'], 'elements' => []],
            'back' => ['background' => ['color' => '#ffffff', 'image' => '', 'fit' => 'cover'], 'elements' => []]];
    }

    /** Portrait conference badge, CR80 (54 × 85.6 mm). */
    public static function badge(): array
    {
        static::$n = 0;
        $t = static::blank(54, 85.6);
        $t['front']['elements'] = [
            static::el('rect', 'Header band', -2, -2, 58, 31, ['fill' => '#0e4ca1', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Accent line', -2, 29, 58, 1.4, ['fill' => '#b22e6b', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Logo plate', 4, 4, 22, 9, ['fill' => '#ffffff', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 1.6, 'locked' => true]),
            static::el('image', 'BICS logo', 5, 4.7, 20, 7.6, ['source' => 'upload', 'src' => 'assets/frontend/images/logo.png', 'fit' => 'contain', 'radius' => 0, 'border' => 0, 'borderColor' => '#ffffff', 'locked' => true]),
            static::text('Event', 27, 4.5, 23, 8, '{{event_short}}', ['color' => '#ffffff', 'size' => 7.5, 'weight' => 800, 'align' => 'right', 'maxLines' => 2]),
            static::el('image', 'Photo', 14, 16, 26, 26, ['source' => 'photo', 'src' => '', 'fit' => 'cover', 'radius' => 13, 'border' => 0.9, 'borderColor' => '#ffffff']),
            static::text('Name', 3, 44.5, 48, 9, '{{name}}', ['size' => 13, 'weight' => 800, 'maxLines' => 2]),
            static::text('Designation', 3, 53.5, 48, 5, '{{designation}}', ['size' => 7.5, 'weight' => 700, 'color' => '#0e4ca1']),
            static::text('Organization', 3, 58.5, 48, 6.5, '{{organization}}', ['size' => 6.5, 'color' => '#5b6475', 'maxLines' => 2]),
            static::el('rect', 'Category pill', 14, 66.5, 26, 6, ['fill' => '#b22e6b', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 3]),
            static::text('Category', 14, 66.5, 26, 6, '{{category}}', ['size' => 6.5, 'weight' => 800, 'color' => '#ffffff', 'upper' => true, 'letter' => 0.06]),
            static::el('rect', 'Footer band', -2, 76, 58, 11.6, ['fill' => '#0a2350', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::text('Participant ID', 4, 78.3, 34, 5, '{{participant_id}}', ['size' => 7.5, 'weight' => 800, 'color' => '#ffffff', 'align' => 'left', 'letter' => 0.05]),
            static::el('icon', 'NFC icon', 44, 78.2, 5.4, 5.4, ['icon' => 'broadcast', 'color' => '#f2b544']),
        ];
        $t['back']['elements'] = [
            static::el('rect', 'Header band', -2, -2, 58, 13, ['fill' => '#0e4ca1', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::text('Call to action', 3, 3.5, 48, 6, 'Scan or tap to view my profile', ['size' => 7, 'weight' => 700, 'color' => '#ffffff']),
            static::el('qr', 'Profile QR', 9.5, 16, 35, 35, ['source' => 'profile', 'text' => '', 'color' => '#0a2350', 'bg' => '#ffffff']),
            static::text('Name', 3, 53, 48, 6, '{{name}}', ['size' => 9, 'weight' => 800]),
            static::text('ID', 3, 58.5, 48, 4.5, '{{participant_id}}', ['size' => 7, 'weight' => 700, 'color' => '#0e4ca1', 'letter' => 0.05]),
            static::el('icon', 'NFC icon', 24.5, 65, 5, 5, ['icon' => 'broadcast', 'color' => '#b22e6b']),
            static::text('NFC hint', 3, 70.5, 48, 4, 'Tap the card on a phone (NFC)', ['size' => 6, 'color' => '#5b6475']),
            static::text('Event name', 3, 75, 48, 6.5, '{{event_name}}', ['size' => 6, 'weight' => 700, 'color' => '#101a2e', 'maxLines' => 2]),
            static::el('rect', 'Brand 1', -2, 83, 14.5, 4.6, ['fill' => '#0e4ca1', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Brand 2', 12.5, 83, 14.5, 4.6, ['fill' => '#b22e6b', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Brand 3', 27, 83, 14.5, 4.6, ['fill' => '#5d9b3a', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Brand 4', 41.5, 83, 14.5, 4.6, ['fill' => '#3f97bf', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
        ];
        return $t;
    }

    /** Landscape business card, CR80 (85.6 × 54 mm). */
    public static function business(): array
    {
        static::$n = 0;
        $t = static::blank(85.6, 54);
        $t['front']['elements'] = [
            static::el('rect', 'Side band', -2, -2, 30, 58, ['fill' => '#0a2350', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('rect', 'Accent', 28, -2, 1.4, 58, ['fill' => '#b22e6b', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 0, 'locked' => true]),
            static::el('image', 'Photo', 4, 6, 20, 20, ['source' => 'photo', 'src' => '', 'fit' => 'cover', 'radius' => 10, 'border' => 0.8, 'borderColor' => '#ffffff']),
            static::text('ID', 2, 29, 24, 4.5, '{{participant_id}}', ['size' => 6, 'weight' => 800, 'color' => '#ffffff', 'letter' => 0.05]),
            static::el('icon', 'NFC icon', 11.5, 40, 5, 5, ['icon' => 'broadcast', 'color' => '#f2b544']),
            static::text('Name', 34, 6, 48, 8, '{{name}}', ['size' => 12, 'weight' => 800, 'align' => 'left']),
            static::text('Designation', 34, 14, 48, 5, '{{designation}}', ['size' => 7.5, 'weight' => 700, 'color' => '#0e4ca1', 'align' => 'left']),
            static::text('Organization', 34, 19, 48, 5, '{{organization}}', ['size' => 6.5, 'color' => '#5b6475', 'align' => 'left']),
            static::el('line', 'Divider', 34, 26, 30, 0.1, ['stroke' => '#c9a227', 'strokeWidth' => 0.4]),
            static::el('icon', 'Email icon', 34, 30, 3.2, 3.2, ['icon' => 'envelope-fill', 'color' => '#b22e6b']),
            static::text('Email', 39, 29.6, 43, 4, '{{email}}', ['size' => 6, 'align' => 'left']),
            static::el('icon', 'Phone icon', 34, 35, 3.2, 3.2, ['icon' => 'telephone-fill', 'color' => '#b22e6b']),
            static::text('Mobile', 39, 34.6, 43, 4, '{{mobile}}', ['size' => 6, 'align' => 'left']),
            static::el('icon', 'Web icon', 34, 40, 3.2, 3.2, ['icon' => 'globe2', 'color' => '#b22e6b']),
            static::text('Website', 39, 39.6, 43, 4, '{{website}}', ['size' => 6, 'align' => 'left']),
            static::el('image', 'BICS logo', 64, 45, 18, 6, ['source' => 'upload', 'src' => 'assets/frontend/images/logo.png', 'fit' => 'contain', 'radius' => 0, 'border' => 0, 'borderColor' => '#ffffff', 'locked' => true]),
        ];
        $t['back']['background']['color'] = '#0a2350';
        $t['back']['elements'] = [
            static::el('rect', 'QR plate', 27.8, 8, 30, 30, ['fill' => '#ffffff', 'stroke' => '', 'strokeWidth' => 0, 'radius' => 2]),
            static::el('qr', 'Profile QR', 29.8, 10, 26, 26, ['source' => 'profile', 'text' => '', 'color' => '#0a2350', 'bg' => '#ffffff']),
            static::text('Call to action', 3, 40, 79.6, 5, 'Scan or tap to save my contact', ['size' => 7, 'weight' => 700, 'color' => '#ffffff']),
            static::text('Event', 3, 45.5, 79.6, 4.5, '{{event_name}}', ['size' => 5.5, 'color' => '#c3d0e6']),
        ];
        return $t;
    }
}
