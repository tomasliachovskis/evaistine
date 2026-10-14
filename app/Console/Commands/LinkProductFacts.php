<?php

namespace App\Console\Commands;

use App\Services\ProductFactLinker;
use Illuminate\Console\Command;

// Links product_facts rows to products through the store's offer for the same
// URL, for rows that arrived before that offer was processed
// (docs/product-facts.md).
class LinkProductFacts extends Command
{
    protected $signature = 'product-facts:link';

    protected $description = 'Link product facts rows to products by offer URL';

    public function handle(ProductFactLinker $linker): int
    {
        $this->info($linker->linkPending() . ' linked.');

        return 0;
    }
}
