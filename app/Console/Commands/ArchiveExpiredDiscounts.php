<?php

namespace App\Console\Commands;

use App\Models\Discount;
use App\Models\DiscountHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveExpiredDiscounts extends Command
{
    protected $signature = 'discounts:archive-expired';
    protected $description = 'Archive expired discounts to discount_histories table';

    public function handle()
    {
        $this->info('Starting to archive expired discounts...');

        $expiredDiscounts = Discount::where(function ($query) {
            $query->where(function ($subQuery) {
                $subQuery->whereRaw('DATE(end_at) < DATE(?)', [now()])
                         ->whereNotNull('end_at');
            })->orWhere('updated_at', '<', now()->subHours(36));
        })->get();

        if ($expiredDiscounts->isEmpty()) {
            $this->info('No expired discounts found.');
            return;
        }

        $this->info("Found {$expiredDiscounts->count()} expired discounts to archive.");

        $archivedCount = 0;
        $deletedCount = 0;

        DB::transaction(function () use ($expiredDiscounts, &$archivedCount, &$deletedCount) {
            foreach ($expiredDiscounts as $discount) {
                DiscountHistory::create([
                    'product_id' => $discount->product_id,
                    'product_url' => $discount->product_url,
                    'store_id' => $discount->store_id,
                    'original_price' => $discount->original_price,
                    'discounted_price' => $discount->discounted_price,
                    'discount_percent' => $discount->discount_percent,
                    'condition' => $discount->condition,
                    'card' => $discount->card,
                    'start_at' => $discount->start_at,
                    'end_at' => $discount->end_at,
                ]);

                $discount->delete();
                $archivedCount++;
                $deletedCount++;
            }
        });

        $this->info("Successfully archived {$archivedCount} expired discounts.");
        $this->info("Removed {$deletedCount} expired discounts from main table.");
    }
}
