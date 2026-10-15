<?php

namespace App\Console\Commands;

use App\Support\CardFonts;
use Illuminate\Console\Command;

/** Download every Card Design Studio font for print files (run once on a server, or when print PDFs fall back to DejaVu Sans). */
class CardFontsCommand extends Command
{
    protected $signature = 'cards:fonts';
    protected $description = 'Download and prepare the Card Design Studio fonts used in card print PDFs';

    public function handle(): int
    {
        foreach (glob(CardFonts::dir() . '*.failed') ?: [] as $f) @unlink($f);
        $missing = 0;
        CardFonts::all(function ($font, $ok) use (&$missing) {
            $this->line(($ok ? '<info>✓</info> ' : '<comment>–</comment> ') . $font . ($ok ? '' : ' (not available — a nearby style is used)'));
            if (!$ok) $missing++;
        });
        $this->info('Done. Fonts are in ' . CardFonts::dir() . ($missing ? " ($missing styles do not exist for their family)" : ''));
        return self::SUCCESS;
    }
}
