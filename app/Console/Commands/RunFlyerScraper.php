<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\PrintsScraperRunSummary;
use App\Models\ScraperRun;
use App\Support\FlyerScraperRunner;
use App\Support\QuietHours;
use Illuminate\Console\Command;

class RunFlyerScraper extends Command
{
    use PrintsScraperRunSummary;

    protected $signature = 'flyers:scrape {store?} {--all}';

    protected $description = 'Scrape current leaflet PDFs for one store, or every configured store in sequence, one at a time';

    public function handle(FlyerScraperRunner $runner): int
    {
        $stores = array_keys(config('flyer_scrapers'));

        if ($this->option('all')) {
            foreach ($stores as $i => $store) {
                // A night run that overruns stops at the owner's working hours.
                if (QuietHours::active()) {
                    foreach (array_slice($stores, $i) as $notRun) {
                        $this->recordForSummary($notRun, null, 'quiet hours, not run');
                    }
                    $this->warn("Quiet hours (".config('services.scrapers.quiet_hours')."), stopping before {$store}.");
                    \Illuminate\Support\Facades\Log::warning('flyers:scrape --all stopped for quiet hours', ['next' => $store]);
                    break;
                }
                $this->recordForSummary($store, $this->runOne($runner, $store));
            }

            $this->printSummary('new flyer(s)');

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
    private function runOne(FlyerScraperRunner $runner, string $store): ?ScraperRun
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->warn("A flyer scraper run is already in progress, skipping {$store} this time.");

            return null;
        }

        $this->info("Scraping flyers for {$store}...");

        $run = $runner->run($store, function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if ($run->status === ScraperRun::STATUS_SUCCESS) {
            $this->info("OK {$store}: {$run->items_count} new flyer(s)");
        } else {
            // The scraper's stderr was already echoed above; one line is enough.
            $this->error("FAILED {$store}: " . $this->mainProblemLine($run));
        }

        return $run;
    }
}
