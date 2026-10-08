<?php

namespace App\Console\Commands;

use App\Services\ProductSearchIndex;
use Illuminate\Console\Command;

class IndexProductsToMeilisearch extends Command
{
    protected $signature = 'products:index-meilisearch';

    protected $description = 'Reindex every product into the Meilisearch "products" index (keyword page mapping)';

    public function handle(ProductSearchIndex $index): int
    {
        if (! ProductSearchIndex::enabled()) {
            $this->warn('MEILISEARCH_HOST is not set; nothing indexed.');

            return self::FAILURE;
        }

        $count = $index->indexAll();
        $this->info("Indexed {$count} products.");

        return self::SUCCESS;
    }
}
