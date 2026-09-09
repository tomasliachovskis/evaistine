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
        // ProcessPdfFlyerJob is a queued job (ShouldQueue), so this only ever
        // dispatches — it never runs the actual work inline in the scheduler
        // process, no runInBackground() needed.
        $schedule->job(new ProcessPdfFlyerJob)
            ->everyMinute()
            ->withoutOverlapping();

        // Scrapers only ever run on the local dev machine (node/puppeteer
        // aren't installed on the production server, and scraping should
        // happen from the local IP, not prod's) — this codebase deploys to
        // both, so these two are explicitly restricted to the 'dev' env.
        //
        // Both runInBackground() (so a long scrape can't delay whatever else
        // is due in the same schedule:run tick — 03:00 and scrapers:retry-
        // failed's :00/:15/:30/:45 marks collide daily) and a bounded
        // withoutOverlapping() expiry (so a crashed/hung Puppeteer process
        // that never releases the lock can't wedge this command for the
        // default 24h — these are exactly the flaky, network-dependent
        // processes prone to hanging, per this repo's own scraper notes).
        $schedule->command('scrapers:run --all')
            ->dailyAt('03:00')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->environments(['dev']);

        $schedule->command('scrapers:retry-failed')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->environments(['dev']);

        $schedule->command('flyers:scrape --all')
            ->dailyAt('05:00')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->environments(['dev']);

        // flyers:store-flyer creates StoreFlyer rows (shared prod DB, hit
        // from local scrapers) with pdf_url left null and no job dispatched
        // — the PDF file itself only exists on whichever machine handled the
        // upload until deploy.sh rsyncs storage/app/public/flyers/pdfs/ to
        // production. This finalizes pdf_url (using production's own
        // APP_URL) and dispatches the page-split job once the file has
        // actually landed here — restricted to production so it never fires
        // against local's own copy of the file with local's own domain.
        // Only ever queues jobs (queue.default isn't 'sync'), so it's quick
        // — a short overlap expiry is enough.
        $schedule->command('flyers:process-pages --pending')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->environments(['production']);

        // Only queries + dispatches to the queue, no heavy inline work.
        $schedule->command('discounts:dispatch-store-processing')
            ->everyFiveMinutes()
            ->withoutOverlapping(5);

        // curated_deals (App\Services\DealPoolRefresher) is kept fresh
        // per-store — and, since refreshHomePools() now derives the home
        // page pools from the already-persisted global_category rows instead
        // of a separate ~19s keyword-page fan-out, the home pools too — by
        // discounts:process itself as stores change (see refreshAfterBatch()).
        // This nightly full refresh is only the safety net for what that
        // event-driven path can't cover: deal_score partly depends on NOW()
        // (the "expiring soon"/"added today" bonuses), so a discount's ideal
        // rank can drift over time with no new scrape data to trigger a
        // refresh.
        $schedule->command('deal-pool:refresh')
            ->dailyAt('04:30')
            ->withoutOverlapping(60);

        // Store working hours change rarely (unlike flyers/discounts), so
        // weekly is plenty. No file-transfer complexity here (unlike
        // flyers) — it's plain JSON straight into the shared DB — so this
        // just needs the ['dev'] restriction all scrapers share.
        $schedule->command('hours:scrape --all')
            ->weeklyOn(1, '06:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->environments(['dev']);

        $schedule->command('db:backup')
            ->dailyAt('02:00')
            ->withoutOverlapping(120)
            ->environments(['production']);

        // Hotlinked store-CDN product images hurt LCP (Clarity: 3.5-7.8s on
        // product pages) — this re-serves them from local storage instead.
        // Already scoped to only non-flyer products (image_from_flyer=false
        // — flyer-cropped images are already ours, nothing to cache) and
        // rate-limited per run (--sleep between downloads, its own
        // Cache::lock overlap guard) to avoid hammering any single store's
        // CDN. ~47k products still hotlinking as of 2026-09-09 — at
        // limit=100 every 5 minutes that's ~28.8k/day, clearing the backlog
        // in roughly 2 days, then just keeping up with new products.
        $schedule->command('products:cache-images --limit=100 --sleep=300')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->environments(['production']);
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
