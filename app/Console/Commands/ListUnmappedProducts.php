<?php

namespace App\Console\Commands;

use App\Models\UnmappedProduct;
use Illuminate\Console\Command;

class ListUnmappedProducts extends Command
{
    protected $signature = 'unmapped:list {--store=} {--status=} {--limit=50}';
    protected $description = 'List products where category mapping failed';

    public function handle()
    {
        $store = $this->option('store');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');

        $query = UnmappedProduct::query();

        if ($store) {
            $query->where('store', $store);
        }

        if ($status) {
            $query->where('mapping_status', $status);
        }

        $products = $query->orderBy('attempted_at', 'desc')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No unmapped products found.');
            return;
        }

        $this->info("Found {$products->count()} unmapped products:");
        $this->newLine();

        $headers = ['ID', 'Name', 'Store', 'Status', 'Error', 'Attempted At'];
        $rows = [];

        foreach ($products as $product) {
            $rows[] = [
                $product->id,
                $product->name,
                $product->store,
                $product->mapping_status,
                $product->error_message ?: 'N/A',
                $product->attempted_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Total unmapped products: " . UnmappedProduct::count());
        
        if ($store) {
            $this->info("For store '{$store}': " . UnmappedProduct::where('store', $store)->count());
        }
    }
}
