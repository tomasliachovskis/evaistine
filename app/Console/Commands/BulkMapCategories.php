<?php

namespace App\Console\Commands;

use App\Models\DiscountTemp;
use App\Services\CategoryMappingService;
use Illuminate\Console\Command;

class BulkMapCategories extends Command
{
    protected $signature = 'categories:bulk-map {store?}';
    protected $description = 'Bulk map product categories (name-match first, ChatGPT fallback) for every store with pending blank-category discount_temp rows';

    private CategoryMappingService $mappingService;

    public function __construct(CategoryMappingService $mappingService)
    {
        parent::__construct();
        $this->mappingService = $mappingService;
    }

    public function handle()
    {
        $store = $this->argument('store');

        if ($store) {
            $this->mapStore($store);

            return;
        }

        // Used to be a hardcoded 13-store list — silently missed any store
        // not on it (confirmed live: Promo Cash&Carry and Thomas Philipps
        // were never in it, so their blank-category discount_temp rows
        // could never resolve no matter what, independent of the OpenAI
        // 429s that were separately blocking the listed stores). Querying
        // for whoever actually has pending blank-category rows means a
        // future new store is covered automatically too.
        $stores = DiscountTemp::query()
            ->where('processed', false)
            ->where(function ($query) {
                $query->whereNull('category')->orWhere('category', '');
            })
            ->distinct()
            ->pluck('store')
            ->filter()
            ->values();

        if ($stores->isEmpty()) {
            $this->info('No stores with pending blank-category rows.');

            return;
        }

        foreach ($stores as $storeName) {
            $this->mapStore($storeName);
        }
    }

    private function mapStore(string $storeName): void
    {
        $this->info("Starting category mapping for {$storeName}...");

        if (!$this->mappingService->isConfigured()) {
            $this->warn("CategoryMappingService is not configured. Please set OPENAI_API_KEY environment variable.");
            return;
        }

        try {
            $this->mappingService->bulkMapStoreProductsWithExistingCategories($storeName);
            $this->info("Successfully completed mapping for {$storeName}");
        } catch (\Exception $e) {
            $this->error("Error mapping categories for {$storeName}: " . $e->getMessage());
        }
    }
}
