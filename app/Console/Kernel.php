<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // old audit entries, visit counts and sign-in records (System › Platform settings)
        $schedule->command('bics:prune')->dailyAt('03:30');
        // birthday cards (Admin › Birthdays): sends from the chosen time on, once per person per year
        $schedule->command('bics:birthdays')->everyFifteenMinutes()->withoutOverlapping(30);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
