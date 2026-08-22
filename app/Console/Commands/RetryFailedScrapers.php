<?php

namespace App\Console\Commands;

use App\Models\ScraperRun;
use App\Support\ScraperRunner;
use Illuminate\Console\Command;

class RetryFailedScrapers extends Command
{
    protected $signature = 'scrapers:retry-failed';

    protected $description = 'Re-run any store whose latest scraper run failed more than an hour ago';

    public function handle(ScraperRunner $runner): int
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->info('A scraper run is already in progress, skipping this check.');

            return 0;
        }

        foreach (array_keys(config('scrapers')) as $store) {
            $latestRun = ScraperRun::where('type', ScraperRun::TYPE_SCRAPE)
                ->where('store', $store)
                ->latest('started_at')
                ->first();

            if (!$latestRun || $latestRun->status !== ScraperRun::STATUS_FAILED) {
                continue;
            }

            if (!$latestRun->finished_at || $latestRun->finished_at->gt(now()->subHour())) {
                continue;
            }

            $this->info("Retrying failed scraper for {$store}...");

            $run = $runner->run($store);

            if ($run->status === ScraperRun::STATUS_SUCCESS) {
                $this->info("✓ {$store}: scraped {$run->items_count} row(s)");
            } else {
                $this->error("✗ {$store} failed again: {$run->error}");
            }

            // Only retry one store per tick — if it just ran, the "in
            // progress" guard above will naturally cover the rest next time.
            return 0;
        }

        return 0;
    }
}
