<?php

namespace App\Http\Controllers;

use App\Support\Thumb;

class MediaController extends Controller
{
    /**
     * Resized image, created on first request and cached (see App\Support\Thumb).
     * Only images inside assets/ and upload/ can be resized, only to the allowed widths.
     */
    public function thumb(int $width, string $path)
    {
        abort_unless(in_array($width, Thumb::WIDTHS, true), 404);
        $file = Thumb::make($path, $width);
        abort_unless($file, 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
