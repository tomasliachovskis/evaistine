<?php

namespace App\Console\Commands;

use App\Services\MeilisearchService;
use Illuminate\Console\Command;

class IndexDiscountsToMeilisearch extends Command
{
    protected $signature = 'discounts:index-meilisearch';
    protected $description = 'Index all active discounts to Meilisearch';

    protected $meilisearchService;

    public function __construct(MeilisearchService $meilisearchService)
    {
        parent::__construct();
        $this->meilisearchService = $meilisearchService;
    }

    public function handle()
    {
        $this->info('Starting to index active discounts to Meilisearch...');

        try {
            $count = $this->meilisearchService->indexAllActiveDiscounts();
            $this->info("Successfully indexed {$count} active discounts to Meilisearch.");
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to index discounts: ' . $e->getMessage());
            return 1;
        }
    }
}
