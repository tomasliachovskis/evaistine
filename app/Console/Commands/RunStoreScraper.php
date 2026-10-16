<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrintsScraperRunSummary;
use App\Models\ScraperRun;
use App\Support\ScraperRunner;
use App\Support\QuietHours;
use Illuminate\Console\Command;

class RunStoreScraper extends Command
{
    use PrintsScraperRunSummary;

    protected $signature = 'scrapers:run {store?} {--all}
                            {--failed : Re-run each store whose latest scrape failed (the nightly script, after flyers)}';

    protected $description = 'Run a scraper for one store, or every configured store in sequence, one at a time';

    public function handle(ScraperRunner $runner): int
    {
        $stores = array_keys(config('scrapers'));

        if ($this->option('failed')) {
            $stores = array_values(array_filter($stores, fn (string $store) => ScraperRun::where('type', ScraperRun::TYPE_SCRAPE)
                ->where('store', $store)
                ->latest('started_at')
                ->value('status') === ScraperRun::STATUS_FAILED));

            if (!$stores) {
                $this->info('No failed scrapers to re-run.');

                return 0;
            }
        }

        if ($this->option('all') || $this->option('failed')) {
            foreach ($stores as $i => $store) {
                // A night run that overruns stops at the owner's working hours.
                if (QuietHours::active()) {
                    foreach (array_slice($stores, $i) as $notRun) {
                        $this->recordForSummary($notRun, null, 'quiet hours, not run');
                    }
                    $this->warn("Quiet hours (".config('services.scrapers.quiet_hours')."), stopping before {$store}.");
                    \Illuminate\Support\Facades\Log::warning('scrapers:run --all stopped for quiet hours', ['next' => $store]);
                    break;
                }
                $this->recordForSummary($store, $this->runOne($runner, $store));
            }

            $this->printSummary();

            return $this->hasFailedRun() ? 1 : 0;
        }

        $store = $this->argument('store');

        if (!$store) {
            $this->error('Provide a store name or use --all.');

            return 1;
        }

        $matched = collect($stores)->first(fn ($s) => strtolower($s) === strtolower($store));

        if (!$matched) {
            $this->error("Unknown store \"{$store}\". Known stores: " . implode(', ', $stores));

            return 1;
        }

        $run = $this->runOne($runner, $matched);

        return $run?->status === ScraperRun::STATUS_FAILED ? 1 : 0;
    }

    /** Null when skipped because another run is in progress. */
    private function runOne(ScraperRunner $runner, string $store): ?ScraperRun
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->warn("A scraper run is already in progress, skipping {$store} this time.");

            return null;
        }

        $this->info("Running scraper for {$store}...");

        $run = $runner->run($store, function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if ($run->status === ScraperRun::STATUS_SUCCESS) {
            $this->info("OK {$store}: scraped {$run->items_count} row(s)");
        } else {
            // The scraper's stderr was already echoed above; one line is enough.
            $this->error("FAILED {$store}: " . $this->mainProblemLine($run));
        }

        return $run;
    }
}
