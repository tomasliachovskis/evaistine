<?php

namespace App\Console\Commands;

use App\Models\Discount;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ArchiveExpiredDiscounts extends Command
{
    protected $signature = 'discounts:archive-expired';

    protected $description = 'Archive expired discounts to discount_histories table';

    public function handle(): int
    {
        $this->info('Starting to archive expired discounts...');

        $expiredQuery = $this->expiredDiscountsQuery();
        $count = (clone $expiredQuery)->count();

        if ($count === 0) {
            $this->info('No expired discounts found.');
            return 0;
        }

        $this->info("Found {$count} expired discounts to archive.");

        DB::transaction(function () use ($expiredQuery, $count) {
            DB::table('discount_histories')->insertUsing(
                [
                    'product_id',
                    'product_url',
                    'store_id',
                    'original_price',
                    'discounted_price',
                    'discount_percent',
                    'condition',
                    'card',
                    'start_at',
                    'end_at',
                    'created_at',
                    'updated_at',
                ],
                (clone $expiredQuery)->select(
                    'product_id',
                    'product_url',
                    'store_id',
                    'original_price',
                    'discounted_price',
                    'discount_percent',
                    'condition',
                    'card',
                    'start_at',
                    'end_at',
                    DB::raw('NOW() as created_at'),
                    DB::raw('NOW() as updated_at'),
                )
            );

            Discount::withoutEvents(function () use ($expiredQuery) {
                (clone $expiredQuery)->delete();
            });
        });

        $this->info("Successfully archived {$count} expired discounts.");
        $this->info("Removed {$count} expired discounts from main table.");

        return 0;
    }

    private function expiredDiscountsQuery(): Builder
    {
        return Discount::query()->where(function ($query) {
            $query
                ->where(function ($subQuery) {
                    $subQuery
                        ->whereNotNull('end_at')
                        ->where('end_at', '<', now()->endOfDay());
                })
                ->orWhere(function ($subQuery) {
                    $subQuery
                        ->where('updated_at', '<', now()->subHours(36))
                        ->whereNull('end_at');
                });
        });
    }
}
