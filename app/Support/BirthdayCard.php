<?php

namespace App\Support;

/**
 * Birthday card pictures (JPEG, 1200 px wide) drawn with GD, so they look the same in every mail app.
 *   - presets: confetti, balloons, elegant, bloom — drawn at 2× and scaled down for smooth edges
 *   - upload: the admin's own artwork with the person's name (and optionally the message) written on it
 * Fonts come from App\Support\CardFonts (Google Fonts TrueType, cached in storage/app/card-fonts/ttf).
 */
class BirthdayCard
{
    public const W = 1200;
    public const H = 800;
    private const S = 2;   // supersampling for presets

    public const PRESETS = [
        'confetti' => ['Confetti night', 'Deep navy and magenta with gold confetti'],
        'balloons' => ['Balloons', 'Light and cheerful, balloons in BICS colours'],
        'elegant' => ['Gold & navy', 'Formal: navy with a fine gold frame'],
        'bloom' => ['Bloom', 'Soft pastel circles, calm and modern'],
    ];

    /** Options a card can set (defaults). Text fields fall back to the Birthday settings when empty. */
    public const DEFAULTS = [
        'headline' => '', 'message' => '', 'signature' => '',
        'show_photo' => true, 'show_age' => false, 'show_logo' => true,
        // uploaded artwork: where and how the name / message go (positions in % of the picture)
        'name_y' => 62, 'name_size' => 64, 'name_color' => '#ffffff', 'align' => 'center',
        'show_message' => true, 'message_color' => '', 'shadow' => true,
    ];

    /**
     * @param array $person  ['name', 'first_name', 'photo' (absolute path or null), 'age' (int|null)]
     * @param object|array $card  birthday_cards row (kind, preset, image, options)
     * @param array $overrides  per-send changes (headline, message, signature, …)
     */
    public static function jpeg(array $person, $card, array $overrides = [], int $quality = 88): string
    {
        $img = static::draw($person, $card, $overrides);
        ob_start();
        imagejpeg($img, null, $quality);
        imagedestroy($img);
        return ob_get_clean();
    }

    public static function options($card, array $overrides = []): array
    {
        $card = (object) $card;
        $saved = is_string($card->options ?? null) ? (json_decode($card->options, true) ?: []) : (array) ($card->options ?? []);
        $s = Birthdays::settings();
        $o = array_merge(static::DEFAULTS, array_filter($saved, fn ($v) => $v !== null && $v !== ''), array_filter($overrides, fn ($v) => $v !== null && $v !== ''));
        foreach (['show_photo', 'show_age', 'show_logo', 'show_message', 'shadow'] as $b) {
            if (array_key_exists($b, $overrides)) $o[$b] = filter_var($overrides[$b], FILTER_VALIDATE_BOOLEAN);
            elseif (array_key_exists($b, $saved)) $o[$b] = filter_var($saved[$b], FILTER_VALIDATE_BOOLEAN);
        }
        // a text sent empty on purpose means "the general text", not the design's own
        foreach (['headline', 'message', 'signature'] as $k) if (array_key_exists($k, $overrides) && trim((string) $overrides[$k]) === '') $o[$k] = '';
        if ($o['headline'] === '') $o['headline'] = $s['headline'];
        if ($o['message'] === '') $o['message'] = $s['card_message'];
        if ($o['signature'] === '') $o['signature'] = $s['signature'];
        return $o;
    }

    public static function draw(array $person, $card, array $overrides = [])
    {
        $card = (object) $card;
        $o = static::options($card, $overrides);
        if (($card->kind ?? 'preset') === 'upload' && $card->image && is_file(base_path($card->image))) {
            return static::drawUpload($person, base_path($card->image), $o);
        }
        $preset = array_key_exists($card->preset ?? '', static::PRESETS) ? $card->preset : 'confetti';
        return static::drawPreset($preset, $person, $o);
    }

    /* ======================= presets ======================= */

    private static function drawPreset(string $preset, array $p, array $o)
    {
        $S = static::S; $W = static::W * $S; $H = static::H * $S;
        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        mt_srand(crc32(($p['name'] ?? '') . date('Y')));   // same card for the same person this year

        $theme = static::theme($preset);
        static::vgradient($im, $W, $H, $theme['bg']);
        foreach ($theme['glows'] as [$cx, $cy, $r, $hex, $alpha]) static::glow($im, $cx * $W, $cy * $H, $r * $S, $hex, $alpha);
        static::{'deco' . ucfirst($preset)}($im, $W, $H);

        // contents, stacked and centred vertically
        $font = fn ($f, $w = 400, $i = false) => static::font($f, $w, $i);
        $blocks = [];
        $photo = $o['show_photo'] && !empty($p['photo']) && is_file($p['photo']) ? $p['photo'] : null;
        $mono = !$photo && $o['show_photo'] && !empty($p['monogram']) ? $p['monogram'] : null;   // sample previews
        if ($photo || $mono) $blocks[] = ['photo', 190 * $S, 22 * $S];
        $blocks[] = ['text', $o['headline'], $font($theme['script'], 400), $theme['headSize'] * $S, $theme['head'], 1000 * $S, 6 * $S];
        $name = trim($p['name'] ?? '');
        if ($name !== '') $blocks[] = ['text', $name, $font($theme['sans'], 800), 54 * $S, $theme['name'], 1000 * $S, 10 * $S];
        if ($o['show_age'] && !empty($p['age'])) $blocks[] = ['text', static::ordinal((int) $p['age']) . ' birthday', $font($theme['sans'], 600), 22 * $S, $theme['soft'], 900 * $S, 14 * $S, 3];
        if (trim($o['message']) !== '') $blocks[] = ['wrap', $o['message'], $font($theme['body'], 400), 27 * $S, $theme['text'], 820 * $S, 18 * $S];
        if (trim($o['signature']) !== '') $blocks[] = ['text', '— ' . $o['signature'], $font($theme['serif'], 400, true), 24 * $S, $theme['soft'], 900 * $S, 0];

        $top = ($o['show_logo'] ? 150 : 70) * $S; $bottom = $H - 60 * $S;
        static::stack($im, $blocks, $W, $top, $bottom, $photo, $theme, $mono);
        if ($o['show_logo']) static::logo($im, $W, 46 * $S, $theme['logoPill']);

        $out = imagecreatetruecolor(static::W, static::H);
        imagecopyresampled($out, $im, 0, 0, 0, 0, static::W, static::H, $W, $H);
        imagedestroy($im);
        return $out;
    }

    private static function theme(string $preset): array
    {
        $base = ['script' => 'Great Vibes', 'sans' => 'Montserrat', 'body' => 'Montserrat', 'serif' => 'Playfair Display', 'headSize' => 118, 'logoPill' => true];
        return array_merge($base, match ($preset) {
            'balloons' => ['bg' => ['#fffaf3', '#fdeef4'], 'glows' => [[0.15, 0.2, 380, '#cfe3ff', 70], [0.9, 0.95, 420, '#ffd6e6', 70]],
                'head' => '#b22e6b', 'name' => '#0f1f44', 'text' => '#3d4760', 'soft' => '#6b7489', 'ring' => '#ffffff'],
            'elegant' => ['bg' => ['#081a3a', '#0d2a5c'], 'glows' => [[0.5, 0.0, 520, '#2a4f93', 80]],
                'head' => '#e6c47a', 'name' => '#ffffff', 'text' => '#d7def0', 'soft' => '#c9b27a', 'ring' => '#e6c47a', 'headSize' => 112],
            'bloom' => ['bg' => ['#ffffff', '#f4f7fd'], 'glows' => [],
                'head' => '#0e4ca1', 'name' => '#0f1f44', 'text' => '#3d4760', 'soft' => '#6b7489', 'ring' => '#dbe6f7'],
            default => ['bg' => ['#0a1f4d', '#2a1550'], 'glows' => [[0.92, 0.05, 560, '#b22e6b', 45], [0.05, 1.0, 520, '#1f8fb3', 60]],
                'head' => '#ffd27a', 'name' => '#ffffff', 'text' => '#e4e9f6', 'soft' => '#ffc3dc', 'ring' => '#ffffff'],
        });
    }

    private static function decoConfetti($im, int $W, int $H): void
    {
        $cols = ['#ffd27a', '#ff7aa8', '#7fd3f7', '#ffffff', '#b8f2a0', '#f2b84b'];
        for ($i = 0; $i < 140; $i++) {
            $x = mt_rand(0, $W); $y = mt_rand(0, $H);
            // keep the middle calmer for the text
            if (abs($x - $W / 2) < $W * 0.3 && $y > $H * 0.18 && $y < $H * 0.9 && mt_rand(0, 3)) continue;
            $c = static::col($im, $cols[array_rand($cols)], mt_rand(0, 45));
            $s = mt_rand(8, 22);
            if (mt_rand(0, 2) === 0) imagefilledellipse($im, $x, $y, $s, $s, $c);
            else static::rotRect($im, $x, $y, $s * 2.2, $s * 0.9, mt_rand(0, 180), $c);
        }
        // streamers
        for ($k = 0; $k < 4; $k++) {
            $c = static::col($im, $cols[$k], 40); $x0 = mt_rand(0, $W); $amp = mt_rand(30, 60);
            for ($t = 0; $t < 220; $t++) { $y = $t * 3; $x = $x0 + sin($t / 14) * $amp; imagefilledellipse($im, (int) $x, (int) $y, 8, 8, $c); }
        }
    }

    private static function decoBalloons($im, int $W, int $H): void
    {
        $spots = [[0.08, 0.32, '#b22e6b'], [0.17, 0.2, '#1f8fb3'], [0.12, 0.62, '#f2b84b'], [0.9, 0.28, '#0e4ca1'], [0.82, 0.16, '#ff7aa8'], [0.92, 0.6, '#2f8a4c']];
        foreach ($spots as [$fx, $fy, $hex]) {
            $cx = (int) ($fx * $W); $cy = (int) ($fy * $H); $bw = mt_rand(150, 190); $bh = (int) ($bw * 1.22);
            $string = static::col($im, '#9aa3b5', 30);
            for ($t = 0; $t < 120; $t++) { $y = $cy + $bh / 2 + $t * 4; $x = $cx + sin($t / 9) * 12; imagefilledellipse($im, (int) $x, (int) $y, 4, 4, $string); }
            imagefilledpolygon($im, [$cx - 14, $cy + $bh / 2 + 14, $cx + 14, $cy + $bh / 2 + 14, $cx, $cy + $bh / 2 - 6], static::col($im, $hex));
            imagefilledellipse($im, $cx, $cy, $bw, $bh, static::col($im, $hex));
            imagefilledellipse($im, (int) ($cx - $bw * 0.22), (int) ($cy - $bh * 0.22), (int) ($bw * 0.22), (int) ($bh * 0.3), static::col($im, '#ffffff', 75));
        }
        $cols = ['#b22e6b', '#1f8fb3', '#f2b84b', '#0e4ca1'];
        for ($i = 0; $i < 60; $i++) {
            $x = mt_rand(0, $W); $y = mt_rand(0, $H);
            if (abs($x - $W / 2) < $W * 0.32) continue;
            imagefilledellipse($im, $x, $y, 10, 10, static::col($im, $cols[array_rand($cols)], 50));
        }
    }

    private static function decoElegant($im, int $W, int $H): void
    {
        $gold = static::col($im, '#e6c47a', 10); $goldSoft = static::col($im, '#e6c47a', 85);
        $m = 44; imagesetthickness($im, 4); imagerectangle($im, $m, $m, $W - $m, $H - $m, $gold);
        $m2 = 64; imagesetthickness($im, 2); imagerectangle($im, $m2, $m2, $W - $m2, $H - $m2, $gold);
        imagesetthickness($im, 1);
        foreach ([[$m2, $m2], [$W - $m2, $m2], [$m2, $H - $m2], [$W - $m2, $H - $m2]] as [$x, $y]) {
            static::rotRect($im, $x, $y, 34, 34, 45, $gold);
            static::rotRect($im, $x, $y, 18, 18, 45, static::col($im, '#081a3a'));
        }
        for ($i = 0; $i < 70; $i++) { $s = mt_rand(3, 8); imagefilledellipse($im, mt_rand(100, $W - 100), mt_rand(100, $H - 100), $s, $s, $goldSoft); }
    }

    private static function decoBloom($im, int $W, int $H): void
    {
        $blobs = [[0.0, 0.0, 900, '#dce8fb'], [1.0, 0.1, 760, '#fbe1ec'], [0.95, 1.0, 880, '#d9f0f7'], [0.05, 0.95, 640, '#fdf0d5'], [0.97, 0.52, 220, '#f3e3f6']];
        foreach ($blobs as [$fx, $fy, $d, $hex]) imagefilledellipse($im, (int) ($fx * $W), (int) ($fy * $H), $d, $d, static::col($im, $hex));
        $ring = static::col($im, '#b22e6b', 95); imagesetthickness($im, 6);
        imagearc($im, (int) (0.86 * $W), (int) (0.22 * $H), 260, 260, 0, 360, $ring);
        imagearc($im, (int) (0.12 * $W), (int) (0.8 * $H), 200, 200, 0, 360, static::col($im, '#0e4ca1', 95));
        imagesetthickness($im, 1);
        $dot = static::col($im, '#0e4ca1', 100);
        for ($r = 0; $r < 6; $r++) for ($c = 0; $c < 8; $c++) imagefilledellipse($im, (int) (0.06 * $W) + $c * 26, (int) (0.1 * $H) + $r * 26, 7, 7, $dot);
    }

    /** Lay the blocks out top-to-bottom, centred between $top and $bottom (shrinking text that is too wide). */
    private static function stack($im, array $blocks, int $W, int $top, int $bottom, ?string $photo, array $theme, ?string $mono = null): void
    {
        $measured = [];
        $total = 0;
        foreach ($blocks as $b) {
            if ($b[0] === 'photo') { $h = $b[1]; }
            elseif ($b[0] === 'wrap') { $lines = static::wrap($b[1], $b[2], $b[3], $b[5]); $lines = array_slice($lines, 0, 3); $h = count($lines) * $b[3] * 1.5; $b['lines'] = $lines; }
            else { $b[3] = static::fit($b[1], $b[2], $b[3], $b[5]); $h = $b[3] * ($b[0] === 'text' && $b[2] === static::font($theme['script'], 400) ? 1.15 : 1.25); }
            $gap = $b[0] === 'photo' ? $b[2] : $b[6];
            $measured[] = [$b, $h, $gap];
            $total += $h + $gap;
        }
        $total -= $measured ? end($measured)[2] : 0;
        $y = $top + max(0, ($bottom - $top - $total) / 2);
        foreach ($measured as [$b, $h, $gap]) {
            if ($b[0] === 'photo') {
                if ($photo) static::photoCircle($im, $photo, (int) ($W / 2), (int) ($y + $h / 2), (int) $h, $theme['ring']);
                else static::monogram($im, $mono, (int) ($W / 2), (int) ($y + $h / 2), (int) $h, $theme);
            } elseif ($b[0] === 'wrap') {
                foreach ($b['lines'] as $k => $line) static::center($im, $line, $b[2], $b[3], $b[4], $W, (int) ($y + $b[3] * 1.15 + $k * $b[3] * 1.5));
            } else {
                $tracking = $b[7] ?? 0;
                static::center($im, $tracking ? mb_strtoupper($b[1]) : $b[1], $b[2], $b[3], $b[4], $W, (int) ($y + $h * 0.8));
            }
            $y += $h + $gap;
        }
    }

    /* ======================= uploaded artwork ======================= */

    private static function drawUpload(array $p, string $file, array $o)
    {
        $src = static::load($file);
        $sw = imagesx($src); $sh = imagesy($src);
        $w = static::W; $h = (int) min(1800, round($sh * $w / $sw));
        $im = imagecreatetruecolor($w, $h);
        imagecopyresampled($im, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);
        imagealphablending($im, true);

        $size = max(20, min(160, (int) $o['name_size']));
        $nameFont = static::font('Montserrat', 800);
        $y = (int) ($h * max(3, min(97, (float) $o['name_y'])) / 100);
        $name = trim($p['name'] ?? '');
        $size = static::fit($name, $nameFont, $size, (int) ($w * 0.88));
        static::place($im, $name, $nameFont, $size, $o['name_color'], $w, $y, $o['align'], $o['shadow']);
        if ($o['show_message'] && trim($o['message']) !== '') {
            $mf = static::font('Montserrat', 500); $ms = max(16, (int) round($size * 0.42));
            $lines = array_slice(static::wrap($o['message'], $mf, $ms, (int) ($w * 0.78)), 0, 3);
            foreach ($lines as $k => $line) static::place($im, $line, $mf, $ms, $o['message_color'] ?: $o['name_color'], $w, (int) ($y + $size * 0.75 + ($k + 1) * $ms * 1.5), $o['align'], $o['shadow']);
        }
        return $im;
    }

    private static function place($im, string $text, string $font, int $size, string $hex, int $W, int $baseline, string $align, bool $shadow): void
    {
        $bw = static::width($text, $font, $size);
        $x = match ($align) { 'left' => (int) ($W * 0.07), 'right' => (int) ($W * 0.93 - $bw), default => (int) (($W - $bw) / 2) };
        if ($shadow) {
            for ($d = 1; $d <= 3; $d++) imagettftext($im, $size * 0.75, 0, $x + $d, $baseline + $d, static::col($im, '#000000', 95 - $d * 8), $font, $text);
        }
        imagettftext($im, $size * 0.75, 0, $x, $baseline, static::col($im, static::hex($hex, '#ffffff')), $font, $text);
    }

    /* ======================= drawing helpers ======================= */

    private static function font(string $family, int $weight = 400, bool $italic = false): string
    {
        static $cache = [];
        $key = "$family|$weight|$italic";
        if (isset($cache[$key])) return $cache[$key];
        $name = CardFonts::ensure($family, $weight, $italic);
        $file = $name ? CardFonts::dir() . 'ttf/' . $name . '.ttf' : null;
        if (!$file || !is_file($file)) {
            $file = collect(glob(CardFonts::dir() . 'ttf/*.ttf'))->first() ?: base_path('vendor/tecnickcom/tcpdf/fonts/dejavu-fonts-ttf-2.34/ttf/DejaVuSans.ttf');
        }
        return $cache[$key] = $file;
    }

    /** imagettftext sizes are in points; we think in pixels (× 0.75). */
    private static function width(string $text, string $font, int $px): int
    {
        $b = imagettfbbox($px * 0.75, 0, $font, $text);
        return (int) abs($b[2] - $b[0]);
    }

    private static function fit(string $text, string $font, int $px, int $max): int
    {
        while ($px > 12 && static::width($text, $font, $px) > $max) $px -= 2;
        return $px;
    }

    private static function center($im, string $text, string $font, int $px, string $hex, int $W, int $baseline): void
    {
        $x = (int) (($W - static::width($text, $font, $px)) / 2);
        imagettftext($im, $px * 0.75, 0, $x, $baseline, static::col($im, $hex), $font, $text);
    }

    private static function wrap(string $text, string $font, int $px, int $max): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($text)) as $para) {
            $line = '';
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                $try = $line === '' ? $word : "$line $word";
                if ($line !== '' && static::width($try, $font, $px) > $max) { $out[] = $line; $line = $word; }
                else $line = $try;
            }
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }

    private static function vgradient($im, int $W, int $H, array $stops): void
    {
        [$a, $b] = [static::rgb($stops[0]), static::rgb($stops[1])];
        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            imageline($im, 0, $y, $W, $y, imagecolorallocate($im, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t)));
        }
    }

    /** Soft light: many transparent circles, growing. */
    /** Soft light: stacked transparent circles — the centre gets every layer, the edge only one. */
    private static function glow($im, float $cx, float $cy, float $r, string $hex, int $alpha): void
    {
        $n = 24;
        $target = (127 - $alpha) / 127;                        // opacity wanted in the middle
        $layer = 1 - (1 - $target) ** (1 / $n);
        $a = (int) round(127 * (1 - $layer));
        for ($i = $n; $i >= 1; $i--) {
            $d = (int) ($r * 2 * $i / $n);
            imagefilledellipse($im, (int) $cx, (int) $cy, $d, $d, static::col($im, $hex, $a));
        }
    }

    private static function rotRect($im, float $cx, float $cy, float $w, float $h, float $deg, $color): void
    {
        $a = deg2rad($deg); $c = cos($a); $s = sin($a); $pts = [];
        foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$dx, $dy]) {
            $x = $dx * $w / 2; $y = $dy * $h / 2;
            $pts[] = (int) ($cx + $x * $c - $y * $s); $pts[] = (int) ($cy + $x * $s + $y * $c);
        }
        imagefilledpolygon($im, $pts, $color);
    }

    private static function photoCircle($im, string $file, int $cx, int $cy, int $d, string $ringHex): void
    {
        try { $src = static::load($file); } catch (\Throwable $e) { return; }
        $sw = imagesx($src); $sh = imagesy($src); $side = min($sw, $sh);
        $sq = imagecreatetruecolor($d, $d);
        // portrait photos: keep the upper part (the face), not the middle
        $sy = $sh > $sw ? (int) (($sh - $side) * 0.18) : (int) (($sh - $side) / 2);
        imagecopyresampled($sq, $src, 0, 0, (int) (($sw - $side) / 2), $sy, $d, $d, $side, $side);
        imagedestroy($src);
        $ring = (int) round($d * 0.045);
        imagefilledellipse($im, $cx, $cy, $d + $ring * 2, $d + $ring * 2, static::col($im, $ringHex));
        $r = $d / 2; $x0 = $cx - $r; $y0 = $cy - $r; $r2 = ($r - 0.5) ** 2;
        for ($y = 0; $y < $d; $y++) {
            $dy = $y - $r + 0.5;
            for ($x = 0; $x < $d; $x++) {
                $dx = $x - $r + 0.5;
                if ($dx * $dx + $dy * $dy <= $r2) imagesetpixel($im, (int) ($x0 + $x), (int) ($y0 + $y), imagecolorat($sq, $x, $y));
            }
        }
        imagedestroy($sq);
    }

    private static function monogram($im, string $text, int $cx, int $cy, int $d, array $theme): void
    {
        $ring = (int) round($d * 0.045);
        imagefilledellipse($im, $cx, $cy, $d + $ring * 2, $d + $ring * 2, static::col($im, $theme['ring']));
        imagefilledellipse($im, $cx, $cy, $d, $d, static::col($im, '#0e4ca1'));
        imagefilledellipse($im, $cx, $cy, (int) ($d * 0.78), (int) ($d * 0.78), static::col($im, '#b22e6b', 70));
        $f = static::font('Montserrat', 800); $px = (int) ($d * 0.36);
        $b = imagettfbbox($px * 0.75, 0, $f, $text);
        imagettftext($im, $px * 0.75, 0, (int) ($cx - ($b[2] - $b[0]) / 2), (int) ($cy + ($b[1] - $b[7]) / 2 - 2), static::col($im, '#ffffff'), $f, $text);
    }

    private static function logo($im, int $W, int $y, bool $pill): void
    {
        $file = base_path('assets/frontend/images/logo.png');
        if (!is_file($file)) return;
        $logo = @imagecreatefrompng($file); if (!$logo) return;
        $lh = 64 * static::S; $lw = (int) (imagesx($logo) * $lh / imagesy($logo));
        $x = (int) (($W - $lw) / 2);
        if ($pill) {
            $px = 22 * static::S; $py = 12 * static::S; $col = static::col($im, '#ffffff');
            static::roundRect($im, $x - $px, $y - $py, $x + $lw + $px, $y + $lh + $py, 18 * static::S, $col);
        }
        imagecopyresampled($im, $logo, $x, $y, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }

    private static function roundRect($im, int $x1, int $y1, int $x2, int $y2, int $r, $col): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$x, $y]) imagefilledellipse($im, $x, $y, $r * 2, $r * 2, $col);
    }

    public static function load(string $file)
    {
        $data = @file_get_contents($file);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if (!$img) throw new \RuntimeException('Cannot read image ' . basename($file));
        if (!imageistruecolor($img)) imagepalettetotruecolor($img);
        return $img;
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim(static::hex($hex, '#000000'), '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function hex(?string $v, string $fallback): string
    {
        return is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : $fallback;
    }

    private static function col($im, string $hex, int $alpha = 0)
    {
        [$r, $g, $b] = static::rgb($hex);
        return imagecolorallocatealpha($im, $r, $g, $b, max(0, min(127, $alpha)));
    }

    public static function ordinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
}
