<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Discount;
use App\Models\DiscountHistory;

class CleanupNullCategoryDiscounts extends Command
{
    protected $signature = 'discounts:cleanup-null-categories';
    protected $description = 'Delete discounts where product category is null';

    public function handle()
    {
        $this->info('Cleaning up discounts with null product categories...');

        $discountsDeleted = Discount::whereHas('product', function($query) {
            $query->whereNull('category_id');
        })->delete();

        $historiesDeleted = DiscountHistory::whereHas('product', function($query) {
            $query->whereNull('category_id');
        })->delete();

        $this->info("Deleted {$discountsDeleted} discounts with null categories");
        $this->info("Deleted {$historiesDeleted} discount histories with null categories");
        $this->info('Cleanup completed successfully!');
    }
} 