<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearDiscountsCache extends Command
{
    protected $signature = 'cache:clear-discounts {--store=} {--category=}';
    protected $description = 'Clear discounts cache';

    public function handle()
    {
        $store = $this->option('store');
        $category = $this->option('category');

        if ($store) {
            $this->info("Clearing cache for store: {$store}" . ($category ? " and category: {$category}" : ""));
        }
        
        Cache::flush();
        $this->info('All cache cleared successfully');

        return 0;
    }
}
