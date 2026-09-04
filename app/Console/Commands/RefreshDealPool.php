<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\DealPoolRefresher;
use Illuminate\Console\Command;

class RefreshDealPool extends Command
{
    protected $signature = 'deal-pool:refresh
                            {--store= : Only refresh this single store (by slug)}
                            {--home : Only refresh the home page pools}
                            {--global : Only refresh the cross-store global category buckets}';

    protected $description = 'Recompute curated_deals rows (store carousels, global categories, home pools)';

    public function handle(DealPoolRefresher $refresher): int
    {
        $storeSlug = $this->option('store');
        $onlyHome = (bool) $this->option('home');
        $onlyGlobal = (bool) $this->option('global');

        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)->first();
            if (! $store) {
                $this->error("Store not found: {$storeSlug}");

                return self::FAILURE;
            }

            $this->info("Refreshing curated deals for {$store->name}...");
            $refresher->refreshForStore($store->id);
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($onlyHome) {
            $this->info('Refreshing home page pools...');
            $refresher->refreshHomePools();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($onlyGlobal) {
            $this->info('Refreshing global category buckets...');
            $refresher->refreshGlobalCategoryBuckets();
            $this->info('Done.');

            return self::SUCCESS;
        }

        $stores = Store::count();
        $this->info("Refreshing curated deals for all {$stores} stores + global categories + home pools...");
        $refresher->refreshAll();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
