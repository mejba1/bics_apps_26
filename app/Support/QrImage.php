<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\Cmyk;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code as a file for designers: SVG / EPS (vector) and PNG / JPG (pixels, drawn with GD — sharp square modules).
 * Options: size (px), colour, background (or transparent for PNG / SVG), quiet zone (modules) and error correction
 * (H = 30 % can be covered, e.g. by a logo in the middle).
 * EPS uses CMYK colours (pure K black by default) for professional printing.
 */
class QrImage
{
    public const FORMATS = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'eps' => 'application/postscript'];

    public static function make(string $text, string $format, array $o = []): string
    {
        $o = static::options($o);
        return match ($format) {
            'svg' => static::vector($text, $o, new SvgImageBackEnd(), false),
            'eps' => static::vector($text, $o, new EpsImageBackEnd(), true),
            'jpg' => static::raster($text, $o, 'jpg'),
            default => static::raster($text, $o, 'png'),
        };
    }

    public static function options(array $o): array
    {
        $hex = fn ($v, $d) => is_string($v) && preg_match('/^#?[0-9a-f]{6}$/i', $v) ? '#' . ltrim(strtolower($v), '#') : $d;
        return [
            'size' => max(200, min(4000, (int) ($o['size'] ?? 1200))),
            'fg' => $hex($o['fg'] ?? null, '#000000'),
            'bg' => $hex($o['bg'] ?? null, '#ffffff'),
            'transparent' => filter_var($o['transparent'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'margin' => max(0, min(8, (int) ($o['margin'] ?? 2))),
            'ecc' => in_array($o['ecc'] ?? null, ['L', 'M', 'Q', 'H'], true) ? $o['ecc'] : 'H',
        ];
    }

    private static function ecc(string $l): ErrorCorrectionLevel
    {
        return match ($l) { 'L' => ErrorCorrectionLevel::L(), 'M' => ErrorCorrectionLevel::M(), 'Q' => ErrorCorrectionLevel::Q(), default => ErrorCorrectionLevel::H() };
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function color(string $hex, bool $cmyk): ColorInterface
    {
        [$r, $g, $b] = static::rgb($hex);
        if (!$cmyk) return new Rgb($r, $g, $b);
        // simple RGB → CMYK; black becomes pure K (no rich black on the press)
        $k = 1 - max($r, $g, $b) / 255;
        if ($k >= 1) return new Cmyk(0, 0, 0, 100);
        $f = fn ($c) => (int) round((1 - $c / 255 - $k) / (1 - $k) * 100);
        return new Cmyk($f($r), $f($g), $f($b), (int) round($k * 100));
    }

    private static function vector(string $text, array $o, $backend, bool $cmyk): string
    {
        $bg = static::color($o['bg'], $cmyk);
        if ($o['transparent'] && !$cmyk) $bg = new Alpha(0, $bg);
        $fill = Fill::uniformColor($bg, static::color($o['fg'], $cmyk));
        $renderer = new ImageRenderer(new RendererStyle($o['size'], $o['margin'], null, null, $fill), $backend);
        return (new Writer($renderer))->writeString($text, 'UTF-8', static::ecc($o['ecc']));
    }

    private static function raster(string $text, array $o, string $type): string
    {
        $matrix = Encoder::encode($text, static::ecc($o['ecc']), 'UTF-8')->getMatrix();
        $modules = $matrix->getWidth();
        $q = $o['margin'];
        $scale = max(1, intdiv($o['size'], $modules + 2 * $q));
        $px = ($modules + 2 * $q) * $scale;
        $img = imagecreatetruecolor($px, $px);
        $transparent = $o['transparent'] && $type === 'png';
        if ($transparent) {
            imagealphablending($img, false); imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, ...static::rgb($o['bg'])));
        }
        $fg = imagecolorallocate($img, ...static::rgb($o['fg']));
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) imagefilledrectangle($img, ($x + $q) * $scale, ($y + $q) * $scale, ($x + $q + 1) * $scale - 1, ($y + $q + 1) * $scale - 1, $fg);
            }
        }
        ob_start();
        $type === 'jpg' ? imagejpeg($img, null, 95) : imagepng($img, null, 6);
        imagedestroy($img);
        return (string) ob_get_clean();
    }
}
