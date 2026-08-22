<?php

namespace App\Console\Commands;

use App\Models\ScraperRun;
use App\Support\ScraperRunner;
use Illuminate\Console\Command;

class RetryFailedScrapers extends Command
{
    protected $signature = 'scrapers:retry-failed';

    protected $description = 'Re-run any store whose latest scraper run failed more than 30 minutes ago';

    public function handle(ScraperRunner $runner): int
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->info('A scraper run is already in progress, skipping this check.');

            return 0;
        }

        // Find the latest scrape run per store, keep only the ones currently
        // failed, then retry whichever has been failing the longest without
        // a retry — not just the first one in config order. Iterating
        // config('scrapers') in fixed order and returning on the first match
        // meant a store that fails every time (e.g. Vynoteka) hogged every
        // retry tick forever, starving every other failed store.
        $oldestFailedStore = collect(array_keys(config('scrapers')))
            ->map(function (string $store) {
                return ScraperRun::where('type', ScraperRun::TYPE_SCRAPE)
                    ->where('store', $store)
                    ->latest('started_at')
                    ->first();
            })
            ->filter(fn ($run) => $run && $run->status === ScraperRun::STATUS_FAILED)
            ->filter(function ($run) {
                return $run->finished_at && $run->finished_at->lte(now()->subMinutes(30));
            })
            ->sortBy('started_at')
            ->first();

        if (!$oldestFailedStore) {
            return 0;
        }

        $store = $oldestFailedStore->store;

        $this->info("Retrying failed scraper for {$store}...");

        $run = $runner->run($store);

        if ($run->status === ScraperRun::STATUS_SUCCESS) {
            $this->info("✓ {$store}: scraped {$run->items_count} row(s)");
        } else {
            $this->error("✗ {$store} failed again: {$run->error}");
        }

        return 0;
    }
}
