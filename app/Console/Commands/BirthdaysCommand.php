<?php

namespace App\Console\Commands;

use App\Support\Birthdays;
use Illuminate\Console\Command;

/** Email today's birthday cards (scheduled every 15 minutes; see App\Support\Birthdays). */
class BirthdaysCommand extends Command
{
    protected $signature = 'bics:birthdays {--now : Ignore the send time (still once per person per year)} {--dry : Only list who would get a card}';
    protected $description = "Email today's birthday cards (Admin › Birthdays)";

    public function handle(): int
    {
        if ($this->option('dry')) {
            $people = Birthdays::on(Birthdays::now());
            $this->info(Birthdays::enabled() ? 'Birthday wishes are ON (send time ' . Birthdays::settings()['send_time'] . ', ' . Birthdays::settings()['timezone'] . ').' : 'Birthday wishes are OFF.');
            foreach ($people as $p) $this->line(" - {$p->code} {$p->first_name} {$p->last_name} <{$p->email}>");
            $this->info($people->count() . ' birthday(s) today.');
            return self::SUCCESS;
        }
        [$sent, $failed, $why] = Birthdays::run((bool) $this->option('now'));
        if ($why) { $this->line($why); return self::SUCCESS; }
        $this->info("Sent $sent, failed $failed.");
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
