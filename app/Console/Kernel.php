<?php

namespace App\Console;

use App\Jobs\ProcessPdfFlyerJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new ProcessPdfFlyerJob)
            ->everyMinute()
            ->withoutOverlapping();

        // Scrapers only ever run on the local dev machine (node/puppeteer
        // aren't installed on the production server, and scraping should
        // happen from the local IP, not prod's) — this codebase deploys to
        // both, so these two are explicitly restricted to the 'dev' env.
        $schedule->command('scrapers:run --all')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->environments(['dev']);

        $schedule->command('scrapers:retry-failed')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->environments(['dev']);

        $schedule->command('discounts:dispatch-store-processing')
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
