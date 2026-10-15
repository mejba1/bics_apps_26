<?php

namespace App\Console\Commands;

use App\Support\PlatformSettings;
use Illuminate\Console\Command;

/** Removes old audit entries, visit counts and sign-in records (periods: System › Platform settings). Runs daily. */
class PruneCommand extends Command
{
    protected $signature = 'bics:prune';
    protected $description = 'Delete old audit log entries, profile visit counts and sign-in records';

    public function handle(): int
    {
        foreach (PlatformSettings::prune() as $what => $n) $this->line("$what: $n removed");
        return self::SUCCESS;
    }
}
