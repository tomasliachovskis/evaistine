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

        $schedule->command('flyers:scrape --all')
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->environments(['dev']);

        // flyers:store-flyer creates StoreFlyer rows (shared prod DB, hit
        // from local scrapers) with pdf_url left null and no job dispatched
        // — the PDF file itself only exists on whichever machine handled the
        // upload until deploy.sh rsyncs storage/app/public/flyers/pdfs/ to
        // production. This finalizes pdf_url (using production's own
        // APP_URL) and dispatches the page-split job once the file has
        // actually landed here — restricted to production so it never fires
        // against local's own copy of the file with local's own domain.
        $schedule->command('flyers:process-pages --pending')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->environments(['production']);

        $schedule->command('discounts:dispatch-store-processing')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Store working hours change rarely (unlike flyers/discounts), so
        // weekly is plenty. No file-transfer complexity here (unlike
        // flyers) — it's plain JSON straight into the shared DB — so this
        // just needs the ['dev'] restriction all scrapers share.
        $schedule->command('hours:scrape --all')
            ->weeklyOn(1, '06:00')
            ->withoutOverlapping()
            ->environments(['dev']);
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
