<?php

namespace App\Console\Commands;

use App\Services\CategoryMappingService;
use Illuminate\Console\Command;

class BulkMapCategories extends Command
{
    protected $signature = 'categories:bulk-map {store?}';
    protected $description = 'Bulk map product categories using ChatGPT API for Norfa and Lidl';

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
        } else {
            $this->mapStore('Norfa');
            $this->mapStore('Lidl');
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
            $this->mappingService->bulkMapStoreProducts($storeName);
            $this->info("Successfully completed mapping for {$storeName}");
        } catch (\Exception $e) {
            $this->error("Error mapping categories for {$storeName}: " . $e->getMessage());
        }
    }
}
