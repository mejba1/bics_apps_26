<?php

namespace App\Console\Commands;

use App\Models\PageSection;
use App\Support\Thumb;
use Illuminate\Console\Command;

/**
 * Pre-creates resized copies of every image used in page sections (galleries, sliders, banners),
 * so the first visitor does not wait for them. Safe to run any time; existing copies are skipped.
 */
class MakeThumbnails extends Command
{
    protected $signature = 'bics:thumbnails';

    protected $description = 'Create resized copies of the images used on website pages';

    public function handle(): int
    {
        $paths = [];
        // Gallery album photos
        try {
            foreach (\App\Models\GalleryPhoto::pluck('image') as $image) if (Thumb::clean($image)) $paths[$image] = true;
        } catch (\Throwable $e) {
        }
        // Images used in page sections (sliders, banners...)
        foreach (PageSection::all() as $section) {
            $content = $section->content ?? [];
            array_walk_recursive($content, function ($value, $key) use (&$paths) {
                if (is_string($value) && in_array($key, ['image'], true) && Thumb::clean($value)) $paths[$value] = true;
            });
        }

        $bar = $this->output->createProgressBar(count($paths) * count(Thumb::WIDTHS));
        $failed = 0;
        foreach (array_keys($paths) as $path) {
            foreach (Thumb::WIDTHS as $width) {
                if (!Thumb::make($path, $width)) $failed++;
                $bar->advance();
            }
            Thumb::placeholder($path); // blurred preview shown while the photo loads
        }
        $bar->finish();
        $this->newLine();
        $this->info(count($paths) . ' images processed' . ($failed ? ", $failed copies failed" : '') . '.');
        return self::SUCCESS;
    }
}
