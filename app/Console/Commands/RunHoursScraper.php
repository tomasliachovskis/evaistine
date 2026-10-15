<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrintsScraperRunSummary;
use App\Models\ScraperRun;
use App\Support\HoursScraperRunner;
use Illuminate\Console\Command;

class RunHoursScraper extends Command
{
    use PrintsScraperRunSummary;

    protected $signature = 'hours:scrape {store?} {--all}';

    protected $description = 'Scrape store working hours for one store, or every configured store in sequence, one at a time';

    public function handle(HoursScraperRunner $runner): int
    {
        $stores = array_keys(config('hours_scrapers'));

        if ($this->option('all')) {
            foreach ($stores as $store) {
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
    private function runOne(HoursScraperRunner $runner, string $store): ?ScraperRun
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->warn("An hours scraper run is already in progress, skipping {$store} this time.");

            return null;
        }

        $this->info("Scraping hours for {$store}...");

        $run = $runner->run($store, function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if ($run->status === ScraperRun::STATUS_SUCCESS) {
            $this->info("OK {$store}: {$run->items_count} active location(s)");
        } else {
            // The scraper's stderr was already echoed above; one line is enough.
            $this->error("FAILED {$store}: " . $this->mainProblemLine($run));
        }

        return $run;
    }
}
