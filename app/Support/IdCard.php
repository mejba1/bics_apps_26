<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The attendee ID card (badge) PDF: A6 portrait, printable and lanyard-sized.
 * Saved at upload/userprofile/{lastname}-{code}.pdf, the address linked from the confirmation email,
 * so regenerating a card keeps every link working.
 * Colours follow Admin > Email Template > Design.
 */
class IdCard
{
    /** A6 portrait in points */
    public const PAPER = [0, 0, 297.64, 419.53];

    public static function path(object $r): string
    {
        return 'upload/userprofile/' . $r->lastname . '-' . $r->qr_code . '.pdf';
    }

    /** Render and save the card; returns its relative path. */
    public static function make(object $r): string
    {
        $path = static::path($r);
        File::ensureDirectoryExists(dirname(base_path($path)));
        File::put(base_path($path), static::pdf($r)->output());
        return $path;
    }

    public static function pdf(object $r)
    {
        return Pdf::loadView('userprofile.profile', static::viewData($r))
            ->setPaper(static::PAPER)
            ->setOption('isFontSubsettingEnabled', true);
    }

    public static function viewData(object $r): array
    {
        $brand = static::color(EmailTemplate::get('brand_color'), '#0e4ca1');
        $accent = static::color(EmailTemplate::get('accent_color'), '#b22e6b');
        $photoPath = base_path('upload/images/' . $r->image);
        $event = \App\Models\Event::forVenue($r->venue); // the attendee's own event (Admin > Events)
        $verifyUrl = $r->qr_url ?: ('https://bics.org/verify/' . $r->qr_code);
        $name = Str::limit(trim(preg_replace('/\s+/', ' ', $r->title . ' ' . $r->firstname . ' ' . $r->lastname)), 70);
        [$nameSize, $nameLines] = static::fitName($name);

        return [
            'data' => $r,
            'name' => $name,
            'nameSize' => $nameSize,
            'nameLines' => $nameLines,
            'photo' => static::photo($r),
            'initials' => mb_strtoupper(mb_substr(trim($r->firstname), 0, 1) . mb_substr(trim($r->lastname), 0, 1)),
            'header' => 'data:image/png;base64,' . base64_encode(file_get_contents(static::header($brand, $accent))),
            'logo' => base_path('assets/frontend/images/logo.png'),
            'qr' => 'data:image/svg+xml;base64,' . base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->margin(0)->errorCorrection('M')->generate($verifyUrl)),
            'eventShort' => $event->event_short ?? 'BICS',
            'eventName' => $event->event_name ?? 'Beacon International Cancer Summit',
            'dates' => $event ? ($event->datesLabel() ?: null) : null,
            'venue' => ($event->event_venue ?? null) ?: $r->venue,
            'role' => static::role($r->profession),
            'brand' => $brand,
            'accent' => $accent,
            'organizer' => EmailTemplate::get('footer_note'),
        ];
    }

    /**
     * Largest bold font size (pt) that keeps the name on one line, else on two lines (card text width ≈ 262pt).
     * Widths are estimated from DejaVu Sans Bold proportions.
     */
    public static function fitName(string $name): array
    {
        $em = 0.0;
        foreach (mb_str_split($name) as $ch) {
            $em += $ch === ' ' ? 0.35 : (preg_match('/[A-Z0-9MW]/u', $ch) ? 0.76 : (preg_match('/[a-z]/u', $ch) ? 0.63 : 0.7));
        }
        foreach ([17, 16, 15, 14, 13] as $size) {
            if ($em * $size <= 255) return [$size, 1];
        }
        foreach ([14, 13, 12, 11] as $size) {
            if ($em * $size <= 2 * 235) return [$size, 2];
        }
        return [10, 3];
    }

    /** Ribbon text + colour by profession, so desk staff recognise roles at a glance. */
    public static function role(?string $profession): array
    {
        $p = strtolower((string) $profession);
        foreach ([
            ['doctor', '#0e4ca1'], ['physician', '#0e4ca1'], ['oncolog', '#0e4ca1'],
            ['nurs', '#178a83'], ['pharmac', '#4f8a2e'], ['patholog', '#6b3fa0'],
            ['government', '#8a5a14'], ['procurement', '#b5541c'], ['student', '#2b7bb9'],
        ] as [$k, $c]) {
            if (str_contains($p, $k)) return ['label' => mb_strtoupper($profession), 'color' => $c];
        }
        return ['label' => $profession ? mb_strtoupper($profession) : 'DELEGATE', 'color' => '#b22e6b'];
    }

    /**
     * Header artwork (gradient + soft circles + brand stripe) drawn with GD, cached per colour pair.
     * dompdf cannot draw CSS gradients, so the card uses this image instead.
     */
    public static function header(string $brand, string $accent): string
    {
        $file = storage_path('app/bics/card-header-' . md5($brand . $accent . 'v1') . '.png');
        if (is_file($file)) return $file;
        File::ensureDirectoryExists(dirname($file));

        [$w, $h] = [1240, 560];
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        $rgb = fn ($hex) => sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $dark = $rgb(EmailTemplate::shade($brand, -0.35));
        $mid = $rgb($brand);
        $end = $rgb($accent);
        // Diagonal gradient: dark brand → brand → accent
        for ($x = 0; $x < $w + $h; $x++) {
            $t = $x / ($w + $h);
            [$a, $b, $u] = $t < 0.55 ? [$dark, $mid, $t / 0.55] : [$mid, $end, ($t - 0.55) / 0.45];
            $c = imagecolorallocate($img, (int) ($a[0] + ($b[0] - $a[0]) * $u), (int) ($a[1] + ($b[1] - $a[1]) * $u), (int) ($a[2] + ($b[2] - $a[2]) * $u));
            imageline($img, $x, 0, $x - $h, $h, $c);
        }
        // Soft decorative circles
        $white = fn ($alpha) => imagecolorallocatealpha($img, 255, 255, 255, $alpha);
        imagefilledellipse($img, 1120, 40, 520, 520, $white(118));
        imagefilledellipse($img, 1180, 470, 300, 300, $white(120));
        imagefilledellipse($img, 90, 520, 380, 380, $white(122));
        imagesetthickness($img, 3);
        imageellipse($img, 1000, 330, 260, 260, $white(110));
        // Brand stripe (blue · magenta · green · teal) along the bottom
        foreach (['#0e4ca1', '#b22e6b', '#4f8a2e', '#519cbb'] as $i => $hex) {
            $c = $rgb($hex);
            imagefilledrectangle($img, (int) ($i * $w / 4), $h - 14, (int) (($i + 1) * $w / 4), $h, imagecolorallocate($img, ...$c));
        }
        imagepng($img, $file, 6);
        imagedestroy($img);
        return $file;
    }

    /** Embedded photo, or null (initials are shown) when missing, not a JPG/PNG/GIF, or a tiny placeholder. */
    private static function photo(object $r): ?string
    {
        $path = base_path('upload/images/' . $r->image);
        if (!$r->image || $r->image === 'no-photo.png' || !is_file($path)) return null;
        $size = @getimagesize($path);
        if (!$size || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true) || min($size[0], $size[1]) < 40) return null;
        return 'data:' . $size['mime'] . ';base64,' . base64_encode(file_get_contents($path));
    }

    private static function color(?string $hex, string $default): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $hex) ? $hex : $default;
    }
}
