<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Support\IdCard;
use Illuminate\Console\Command;

/** Regenerate attendee ID card PDFs (same file names, so links in sent emails keep working). */
class MakeIdCards extends Command
{
    protected $signature = 'bics:cards {--venue= : Event venue code (default: the current event)} {--all : Every event}';

    protected $description = 'Regenerate attendee ID cards with the current design';

    public function handle(): int
    {
        $venue = $this->option('venue') ?: \App\Support\EventSettings::get('venue_code', config('bics.venue'));
        $query = Registration::query()->when(!$this->option('all'), fn ($q) => $q->where('venue', $venue))->orderBy('id');
        $total = $query->count();
        $this->info('Regenerating ' . $total . ' ID card(s)' . ($this->option('all') ? ' for every event' : ' for ' . $venue) . '…');
        $bar = $this->output->createProgressBar($total);
        $failed = 0;
        $query->chunk(50, function ($rows) use ($bar, &$failed) {
            foreach ($rows as $r) {
                try { IdCard::make($r); } catch (\Throwable $e) { $failed++; $this->newLine(); $this->warn($r->qr_code . ': ' . $e->getMessage()); }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info('Done' . ($failed ? ", $failed failed" : '') . '.');
        return self::SUCCESS;
    }
}
