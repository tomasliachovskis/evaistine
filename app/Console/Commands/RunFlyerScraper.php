<?php

namespace App\Console\Commands;

use App\Models\ScraperRun;
use App\Support\FlyerScraperRunner;
use Illuminate\Console\Command;

class RunFlyerScraper extends Command
{
    protected $signature = 'flyers:scrape {store?} {--all}';

    protected $description = 'Scrape current leaflet PDFs for one store, or every configured store in sequence, one at a time';

    public function handle(FlyerScraperRunner $runner): int
    {
        $stores = array_keys(config('flyer_scrapers'));

        if ($this->option('all')) {
            foreach ($stores as $store) {
                $this->runOne($runner, $store);
            }

            return 0;
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

        $this->runOne($runner, $matched);

        return 0;
    }

    private function runOne(FlyerScraperRunner $runner, string $store): void
    {
        if ($runner->isAnotherRunInProgress()) {
            $this->warn("A flyer scraper run is already in progress, skipping {$store} this time.");

            return;
        }

        $this->info("Scraping flyers for {$store}...");

        $run = $runner->run($store, function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if ($run->status === ScraperRun::STATUS_SUCCESS) {
            $this->info("✓ {$store}: {$run->items_count} new flyer(s)");
        } else {
            $this->error("✗ {$store} failed: {$run->error}");
        }
    }
}
