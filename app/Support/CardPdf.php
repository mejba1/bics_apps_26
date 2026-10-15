<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Draws a card template on a TCPDF page as vector artwork (text stays text, shapes and QR codes stay vectors,
 * photos are embedded at full resolution). This is the print twin of assets/backend/card-studio/card-renderer.js:
 * same millimetre layout, same text fitting rules and same placeholder data (App\Support\CardData::for(..., forPrint: true)).
 */
class CardPdf
{
    public const PT = 25.4 / 72;
    private static array $icons = [];

    public static function newPdf(float $w, float $h): \TCPDF
    {
        $pdf = new CardTcpdf($w > $h ? 'L' : 'P', 'mm', [$w, $h], true, 'UTF-8', false);
        $pdf->SetCreator('BICS Card Design Studio');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->setFontSubsetting(true);
        $pdf->setImageScale(1);
        $pdf->SetCompression(true);
        // an embedded default font (otherwise TCPDF lists a non-embedded Helvetica, which print checks flag)
        $pdf->SetFont(CardFonts::use($pdf, 'Plus Jakarta Sans', 400, false), '', 8);
        return $pdf;
    }

    /**
     * Draw one side with its trim box top-left at ($ox, $oy). The bleed area around it is drawn too (clipped to it).
     */
    public static function draw(\TCPDF $pdf, array $design, string $side, array $data, float $ox, float $oy, bool $withBleed = true): void
    {
        $w = (float) $design['width'];
        $h = (float) $design['height'];
        $b = $withBleed ? (float) ($design['bleed'] ?? 0) : 0.0;
        $face = $design[$side] ?? ['background' => [], 'elements' => []];
        $bg = $face['background'] ?? [];

        $pdf->StartTransform();
        $pdf->Rect($ox - $b, $oy - $b, $w + 2 * $b, $h + 2 * $b, 'CNZ');
        static::fillRect($pdf, $ox - $b, $oy - $b, $w + 2 * $b, $h + 2 * $b, $bg['color'] ?? '#ffffff');
        if (!empty($bg['image'])) static::image($pdf, base_path($bg['image']), $ox - $b, $oy - $b, $w + 2 * $b, $h + 2 * $b, $bg['fit'] ?? 'cover', 0);
        foreach ($face['elements'] ?? [] as $e) {
            if (!empty($e['hidden'])) continue;
            static::element($pdf, $e + ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1], $data, $ox, $oy);
        }
        $pdf->StopTransform();
    }

    /** Crop marks for a card whose trim box starts at ($ox, $oy). */
    public static function cropMarks(\TCPDF $pdf, float $ox, float $oy, float $w, float $h, float $bleed, float $len = 4): void
    {
        if ($len <= 0.3) return;
        $style = ['width' => 0.12, 'color' => [0, 0, 0, 100]];   // registration-safe 100 % black (CMYK K)
        $o = $bleed + 1;
        foreach ([[0, 0], [$w, 0], [0, $h], [$w, $h]] as [$px, $py]) {
            $sx = $px == 0 ? -1 : 1;
            $sy = $py == 0 ? -1 : 1;
            $pdf->Line($ox + $px + $sx * $o, $oy + $py, $ox + $px + $sx * ($o + $len), $oy + $py, $style);
            $pdf->Line($ox + $px, $oy + $py + $sy * $o, $ox + $px, $oy + $py + $sy * ($o + $len), $style);
        }
    }

    /* ---------------------------------------------------------------- elements */

    private static function element(\TCPDF $pdf, array $e, array $data, float $ox, float $oy): void
    {
        $x = $ox + $e['x'];
        $y = $oy + $e['y'];
        $w = (float) $e['w'];
        $h = (float) $e['h'];
        $pdf->StartTransform();
        if (!empty($e['rotate'])) $pdf->Rotate(-$e['rotate'], $x + $w / 2, $y + $h / 2);   // TCPDF turns anticlockwise
        $op = isset($e['opacity']) ? (float) $e['opacity'] : 1;
        if ($op < 1) $pdf->setAlpha($op);
        switch ($e['type']) {
            case 'text': static::text($pdf, $e, $data, $x, $y); break;
            case 'image': static::imageEl($pdf, $e, $data, $x, $y); break;
            case 'qr': static::qr($pdf, $e, $data, $x, $y); break;
            case 'icon': static::icon($pdf, $e, $x, $y); break;
            case 'rect':
                $style = static::shapeStyle($pdf, $e);
                if ($style) {
                    $r = min((float) ($e['radius'] ?? 0), $w / 2, $h / 2);
                    $r > 0 ? $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', $style) : $pdf->Rect($x, $y, $w, $h, $style);
                }
                break;
            case 'ellipse':
                $style = static::shapeStyle($pdf, $e);
                if ($style) $pdf->Ellipse($x + $w / 2, $y + $h / 2, $w / 2, $h / 2, 0, 0, 360, $style);
                break;
            case 'line':
                $pdf->Line($x, $y + $h / 2, $x + $w, $y + $h / 2, ['width' => (float) ($e['strokeWidth'] ?? 0.3), 'color' => static::rgb($e['stroke'] ?? '#000000'), 'cap' => 'butt']);
                break;
        }
        if ($op < 1) $pdf->setAlpha(1);
        $pdf->StopTransform();
    }

    private static function shapeStyle(\TCPDF $pdf, array $e): string
    {
        $fill = $e['fill'] ?? '';
        $stroke = !empty($e['stroke']) && !empty($e['strokeWidth']) ? $e['stroke'] : '';
        if ($fill) $pdf->SetFillColorArray(static::rgb($fill));
        if ($stroke) {
            $pdf->SetDrawColorArray(static::rgb($stroke));
            $pdf->SetLineWidth((float) $e['strokeWidth']);
            $pdf->SetLineStyle(['width' => (float) $e['strokeWidth'], 'color' => static::rgb($stroke), 'dash' => 0, 'cap' => 'butt', 'join' => 'miter']);
        }
        return ($stroke ? 'D' : '') . ($fill ? 'F' : '');
    }

    private static function fillRect(\TCPDF $pdf, float $x, float $y, float $w, float $h, string $color): void
    {
        $pdf->SetFillColorArray(static::rgb($color ?: '#ffffff'));
        $pdf->Rect($x, $y, $w, $h, 'F');
    }

    /* ---------------------------------------------------------------- text */

    public static function fill(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($data['values'][$m[1]] ?? ''), $text);
    }

    private static function measure(\TCPDF $pdf, array $e, string $font, string $s, float $sizeMm): float
    {
        $w = $pdf->GetStringWidth($s, $font, '', $sizeMm / static::PT);
        return $w + (float) ($e['letter'] ?? 0) * $sizeMm * max(0, mb_strlen($s) - 1);
    }

    private static function wrap(\TCPDF $pdf, array $e, string $font, string $text, float $size, float $width): array
    {
        $out = [];
        foreach (explode("\n", $text) as $para) {
            $words = preg_split('/\s+/u', trim($para), -1, PREG_SPLIT_NO_EMPTY);
            if (!$words) { $out[] = ''; continue; }
            $line = '';
            foreach ($words as $word) {
                $t = $line === '' ? $word : $line . ' ' . $word;
                if ($line === '' || static::measure($pdf, $e, $font, $t, $size) <= $width) $line = $t;
                else { $out[] = $line; $line = $word; }
            }
            $out[] = $line;
        }
        while (count($out) > 1 && end($out) === '') array_pop($out);
        return $out;
    }

    private static function ellipsize(\TCPDF $pdf, array $e, string $font, string $line, float $size, float $width): string
    {
        if (static::measure($pdf, $e, $font, $line, $size) <= $width) return $line;
        while (mb_strlen($line) > 1 && static::measure($pdf, $e, $font, $line . '…', $size) > $width) $line = mb_substr($line, 0, -1);
        return rtrim($line) . '…';
    }

    /** Same rules as layoutText() in card-renderer.js. */
    public static function layout(\TCPDF $pdf, array $e, string $font, string $text): array
    {
        $size = (float) $e['size'] * static::PT;
        $maxLines = max(1, (int) ($e['maxLines'] ?? 1));
        $lh = (float) ($e['line'] ?? 1.15);
        $min = max(2 * static::PT, $size * 0.35);
        $w = (float) $e['w'];
        $lines = [];
        for ($i = 0; $i < 40; $i++) {
            $lines = static::wrap($pdf, $e, $font, $text, $size, $w);
            $widest = max(array_map(fn ($l) => static::measure($pdf, $e, $font, $l, $size), $lines));
            $ok = count($lines) <= $maxLines && $widest <= $w + 0.01 && (count($lines) * $size * $lh <= $e['h'] + 0.01 || count($lines) === 1);
            if ($ok || empty($e['fit']) || $size <= $min) break;
            $size = max($min, $size * 0.94);
        }
        $shrunk = $size < (float) $e['size'] * static::PT - 0.001;
        $cut = false;
        if (count($lines) > $maxLines) { $lines = array_slice($lines, 0, $maxLines); $lines[$maxLines - 1] .= '…'; $cut = true; }
        $lines = array_map(function ($l) use ($pdf, $e, $font, $size, $w, &$cut) { $n = static::ellipsize($pdf, $e, $font, $l, $size, $w); if ($n !== $l) $cut = true; return $n; }, $lines);
        return ['lines' => $lines, 'size' => $size, 'lh' => $size * $lh, 'shrunk' => $shrunk, 'cut' => $cut];
    }

    /** Text as it will print (placeholders filled, upper-case, spaces tidied). */
    public static function textOf(array $e, array $data): string
    {
        $t = static::fill((string) ($e['text'] ?? ''), $data);
        if (!empty($e['upper'])) $t = mb_strtoupper($t);
        return trim(preg_replace('/[ \t]+/', ' ', $t));
    }

    private static function text(\TCPDF $pdf, array $e, array $data, float $x, float $y): void
    {
        $text = static::textOf($e, $data);
        if ($text === '') return;
        $font = CardFonts::use($pdf, $e['font'] ?? 'Plus Jakarta Sans', (int) ($e['weight'] ?? 400), !empty($e['italic']));
        $L = static::layout($pdf, $e, $font, $text);
        $total = count($L['lines']) * $L['lh'];
        $valign = $e['valign'] ?? 'middle';
        $top = $valign === 'top' ? $y : ($valign === 'bottom' ? $y + $e['h'] - $total : $y + ($e['h'] - $total) / 2);
        $pdf->SetFont($font, '', $L['size'] / static::PT);
        $pdf->SetTextColorArray(static::rgb($e['color'] ?? '#000000'));
        $spacing = (float) ($e['letter'] ?? 0) * $L['size'];
        $pdf->setFontSpacing($spacing);
        foreach ($L['lines'] as $i => $line) {
            if ($line === '') continue;
            $lw = static::measure($pdf, $e, $font, $line, $L['size']);
            $align = $e['align'] ?? 'left';
            $lx = $align === 'center' ? $x + ($e['w'] - $lw) / 2 : ($align === 'right' ? $x + $e['w'] - $lw : $x);
            $baseline = $top + $i * $L['lh'] + $L['lh'] / 2 + $L['size'] * 0.35;
            $pdf->Text($lx, $baseline, $line, false, false, true, 0, 0, '', false, '', 0, false, 'L', 'M');
        }
        $pdf->setFontSpacing(0);
    }

    /* ---------------------------------------------------------------- images */

    private static function imageEl(\TCPDF $pdf, array $e, array $data, float $x, float $y): void
    {
        $file = match ($e['source'] ?? 'upload') {
            'photo' => $data['photo'] ?? null,
            'logo' => $data['logo'] ?? null,
            default => !empty($e['src']) ? base_path($e['src']) : null,
        };
        $w = (float) $e['w'];
        $h = (float) $e['h'];
        $r = min((float) ($e['radius'] ?? 0), $w / 2, $h / 2);
        if ($file && is_file($file)) {
            static::image($pdf, $file, $x, $y, $w, $h, $e['fit'] ?? 'cover', $r);
        } elseif (($e['source'] ?? '') === 'photo') {
            // no photo: initials on a soft background (as in the studio)
            $pdf->StartTransform();
            static::clip($pdf, $x, $y, $w, $h, $r);
            static::fillRect($pdf, $x, $y, $w, $h, '#e3ebf7');
            $ini = (string) ($data['initials'] ?? '');
            if ($ini !== '') {
                $font = CardFonts::use($pdf, 'Plus Jakarta Sans', 800, false);
                $size = min($w, $h) * 0.36;
                $pdf->SetFont($font, '', $size / static::PT);
                $pdf->SetTextColorArray(static::rgb('#1861c5'));
                $tw = $pdf->GetStringWidth($ini);
                $pdf->Text($x + ($w - $tw) / 2, $y + $h / 2 + min($w, $h) * 0.13, $ini, false, false, true, 0, 0, '', false, '', 0, false, 'L', 'M');
            }
            $pdf->StopTransform();
        }
        if (!empty($e['border']) && $e['border'] > 0) {
            $pdf->SetLineStyle(['width' => (float) $e['border'], 'color' => static::rgb($e['borderColor'] ?? '#ffffff'), 'dash' => 0]);
            $r > 0 ? $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', 'D') : $pdf->Rect($x, $y, $w, $h, 'D');
        }
    }

    private static function clip(\TCPDF $pdf, float $x, float $y, float $w, float $h, float $r): void
    {
        $r > 0 ? $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', 'CNZ') : $pdf->Rect($x, $y, $w, $h, 'CNZ');
    }

    /** Image in a box: "cover" fills and crops, "contain" shows it whole; rounded corners clip. */
    public static function image(\TCPDF $pdf, string $file, float $x, float $y, float $w, float $h, string $fit, float $r): void
    {
        // enough pixels for ~350 dpi at the largest size it can print at (a "cover" image is drawn larger than its box)
        $src = @getimagesize($file);
        if (!$src || !$src[0] || !$src[1]) return;
        $boxRatio = $w / max($h, 0.01);
        $longMm = $fit === 'contain' ? max($w, $h) : max($w, $h) * max($boxRatio * $src[1] / $src[0], $src[0] / $src[1] / $boxRatio, 1);
        $file = static::printable($file, (int) min(4000, max(300, ceil($longMm / 25.4 * 350))));
        if (!$file) return;
        $size = @getimagesize($file);
        if (!$size || !$size[0] || !$size[1]) return;
        $ratio = $size[0] / $size[1];
        if ($fit === 'contain') {
            $iw = min($w, $h * $ratio);
            $ih = $iw / $ratio;
        } else {
            $iw = max($w, $h * $ratio);
            $ih = $iw / $ratio;
        }
        $ix = $x + ($w - $iw) / 2;
        $iy = $y + ($h - $ih) / 2;
        $pdf->StartTransform();
        static::clip($pdf, $x, $y, $w, $h, $r);
        $pdf->Image($file, $ix, $iy, $iw, $ih, '', '', '', false, 300, '', false, false, 0, false, false, false);
        $pdf->StopTransform();
    }

    /**
     * A print copy: camera rotation applied, no more pixels than needed, JPG or PNG (WebP converted).
     * Kept in storage/app/print-cache (keyed by file, date and size).
     */
    private static function printable(string $file, int $px): ?string
    {
        if (!is_file($file)) return null;
        $type = @getimagesize($file)[2] ?? 0;
        $png = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true);   // may be transparent
        $dir = storage_path('app/print-cache/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $out = $dir . md5($file . '|' . filemtime($file) . '|' . $px) . ($png ? '.png' : '.jpg');
        if (is_file($out)) return $out;
        if (!Thumb::shrink($file, $out, $px)) {
            @unlink($out);
            return in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) ? $file : null;
        }
        return $out;
    }

    /** Remove print copies not used for 30 days. */
    public static function cleanup(): void
    {
        foreach (glob(storage_path('app/print-cache/*.{jpg,png}'), GLOB_BRACE) ?: [] as $f) if (@fileatime($f) < time() - 30 * 86400 && @filemtime($f) < time() - 30 * 86400) @unlink($f);
    }

    /* ---------------------------------------------------------------- QR & icons */

    public static function qrText(array $e, array $data): string
    {
        if (($e['source'] ?? '') === 'custom') return static::fill((string) ($e['text'] ?? ''), $data) ?: 'https://example.com';
        $q = $data['qr'] ?? [];
        return (($e['source'] ?? '') === 'nfc' ? ($q['nfc'] ?? '') : ($q['profile'] ?? '')) ?: 'https://example.com';
    }

    private static function qr(\TCPDF $pdf, array $e, array $data, float $x, float $y): void
    {
        $s = min((float) $e['w'], (float) $e['h']);
        $x0 = $x + ($e['w'] - $s) / 2;
        $y0 = $y + ($e['h'] - $s) / 2;
        if (!empty($e['bg'])) static::fillRect($pdf, $x0, $y0, $s, $s, $e['bg']);
        $m = Encoder::encode(static::qrText($e, $data), ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $m->getWidth();
        $q = 2;
        $cell = $s / ($n + 2 * $q);
        $pdf->SetFillColorArray(static::rgb($e['color'] ?? '#000000'));
        // one rectangle per run of dark modules in a row (small files, no hairline gaps)
        for ($r = 0; $r < $n; $r++) {
            $c = 0;
            while ($c < $n) {
                if ($m->get($c, $r) !== 1) { $c++; continue; }
                $start = $c;
                while ($c < $n && $m->get($c, $r) === 1) $c++;
                $pdf->Rect($x0 + ($start + $q) * $cell, $y0 + ($r + $q) * $cell, ($c - $start) * $cell, $cell * 1.002, 'F');
            }
        }
    }

    private static function icon(\TCPDF $pdf, array $e, float $x, float $y): void
    {
        if (!static::$icons) static::$icons = json_decode((string) @file_get_contents(base_path('assets/backend/card-studio/icons.json')), true) ?: [];
        $icon = static::$icons[$e['icon'] ?? ''] ?? null;
        if (!$icon || !$pdf instanceof CardTcpdf) return;
        $s = min((float) $e['w'], (float) $e['h']);
        $pdf->SetFillColorArray(static::rgb($e['color'] ?? '#000000'));
        foreach ($icon['paths'] as $p) {
            $pdf->fillSvgPath(SvgPath::normalize($p['d']), $x + ($e['w'] - $s) / 2, $y + ($e['h'] - $s) / 2, $s / 16, ($p['rule'] ?? '') === 'evenodd');
        }
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex ?: '#000000', '#');
        if (strlen($hex) !== 6) $hex = '000000';
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
