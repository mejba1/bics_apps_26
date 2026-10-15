<?php

namespace App\Support;

/**
 * Resized copies of site images (galleries, sliders) so pages never download 6000px camera originals.
 *
 * Copies are made once with GD and stored in upload/cache/{width}/..., then served as plain files.
 * Until a copy exists, url() points to the /media/thumb route, which creates it on first request.
 */
class Thumb
{
    /** Allowed output widths (anything else is rounded up to the next one). */
    public const WIDTHS = [480, 800, 1600];

    private const ROOTS = ['assets/', 'upload/'];

    /** Public URL of a resized copy (or the original for external / unsupported images). */
    public static function url(?string $path, int $width): string
    {
        $path = static::clean($path);
        if (!$path) return Cms::image((string) $path);
        $width = static::width($width);
        $cached = static::cachePath($path, $width);
        if ($cached && is_file(base_path($cached))) return asset($cached);
        return url('media/thumb/' . $width . '/' . $path);
    }

    /** "w480 480w, ..." for <img srcset>. */
    public static function srcset(?string $path, array $widths = [480, 800]): string
    {
        if (!static::clean($path)) return '';
        return implode(', ', array_map(fn ($w) => static::url($path, $w) . ' ' . $w . 'w', $widths));
    }

    /** Width & height of the original (cached), to reserve space and avoid layout jumps. */
    public static function size(?string $path): ?array
    {
        $path = static::clean($path);
        if (!$path) return null;
        $file = base_path($path);
        $key = 'thumb.size.' . md5($path . '|' . @filemtime($file));
        return cache()->rememberForever($key, function () use ($file) {
            $s = @getimagesize($file);
            return $s ? [$s[0], $s[1]] : null;
        });
    }

    /**
     * Make (if needed) the resized copy and return its file path, or null if it cannot be made.
     */
    public static function make(string $path, int $width): ?string
    {
        $path = static::clean($path);
        if (!$path) return null;
        $width = static::width($width);
        $target = static::cachePath($path, $width);
        $abs = base_path($target);
        if (is_file($abs)) return $abs;

        $source = base_path($path);
        $info = @getimagesize($source);
        if (!$info) return null;
        [$w, $h, $type] = $info;

        // Never enlarge: small images are simply copied
        if ($w <= $width) {
            @mkdir(dirname($abs), 0755, true);
            return @copy($source, $abs) ? $abs : null;
        }

        @mkdir(dirname($abs), 0755, true);
        return static::resize($source, $abs, $width) ? $abs : null;
    }

    /**
     * Shrink an uploaded photo in place so its longest side is at most $max px
     * (camera originals are 6000px / several MB). Also applies the camera's rotation.
     * Returns [width, height] of the result, or null if the file is not a readable image.
     */
    public static function shrink(string $source, string $target, int $max = 2400): ?array
    {
        $info = @getimagesize($source);
        if (!$info) return null;
        [$w, $h] = $info;
        $width = max($w, $h) > $max ? ($w >= $h ? $max : (int) round($w * $max / $h)) : $w;
        if (!static::resize($source, $target, $width, 85)) return null;
        $info = @getimagesize($target);
        return $info ? [$info[0], $info[1]] : null;
    }

    /** Resize $source to $width px wide and save it to $target (JPEG, or PNG for .png targets). */
    private static function resize(string $source, string $target, int $width, int $quality = 82): bool
    {
        $info = @getimagesize($source);
        if (!$info) return false;
        [$w, $h, $type] = $info;

        @ini_set('memory_limit', '1024M'); // camera photos need room to decode
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($source),
            default => false,
        };
        if (!$img) return false;

        // Respect the camera's rotation flag
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle) {
                $img = imagerotate($img, $angle, 0);
                [$w, $h] = [imagesx($img), imagesy($img)];
                if ($width > $w) $width = $w;
            }
        }

        $newH = (int) round($h * $width / $w);
        $out = imagecreatetruecolor($width, $newH);
        $png = str_ends_with(strtolower($target), '.png');
        if ($png) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        } else {
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // transparent areas become white in JPEG
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $width, $newH, $w, $h);
        imagedestroy($img);

        $ok = $png ? imagepng($out, $target, 7) : imagejpeg($out, $target, $quality);
        imagedestroy($out);
        return $ok;
    }

    /**
     * Tiny blurred preview (about 600 bytes, as a data: URI) shown while the real photo loads.
     * Made from the 480px copy, so it is quick; cached until the original changes.
     */
    public static function placeholder(?string $path): ?string
    {
        $path = static::clean($path);
        if (!$path) return null;
        $key = 'thumb.ph.' . md5($path . '|' . @filemtime(base_path($path)));
        $uri = cache()->rememberForever($key, function () use ($path) {
            $small = static::make($path, 480);
            if (!$small) return '';
            $tmp = tempnam(sys_get_temp_dir(), 'ph');
            $ok = static::resize($small, $tmp . '.jpg', 24, 60);
            $data = $ok ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($tmp . '.jpg')) : '';
            @unlink($tmp); @unlink($tmp . '.jpg');
            return $data;
        });
        return $uri ?: null;
    }

    /** Delete the resized copies of an image (call before deleting the image itself). */
    public static function forget(?string $path): void
    {
        $path = static::clean($path);
        if (!$path) return;
        foreach (static::WIDTHS as $w) {
            $file = base_path(static::cachePath($path, $w));
            if (is_file($file)) @unlink($file);
        }
    }

    /** Safe relative path inside assets/ or upload/, or null. */
    public static function clean(?string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('#^(https?:|//|data:)#i', $path)) return null;
        if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) return null;
        foreach (static::ROOTS as $root) {
            if (str_starts_with($path, $root)) return is_file(base_path($path)) ? $path : null;
        }
        return null;
    }

    private static function width(int $width): int
    {
        foreach (static::WIDTHS as $w) if ($width <= $w) return $w;
        return static::WIDTHS[count(static::WIDTHS) - 1];
    }

    /** upload/cache/800/<hash>-<name>.jpg (hash changes when the original changes). */
    private static function cachePath(string $path, int $width): ?string
    {
        $file = base_path($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
        $name = \Illuminate\Support\Str::slug(pathinfo($path, PATHINFO_FILENAME)) ?: 'img';
        return 'upload/cache/' . $width . '/' . substr(md5($path . '|' . @filemtime($file)), 0, 10) . '-' . $name . '.' . $ext;
    }
}
