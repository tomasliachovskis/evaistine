<?php

namespace App\Console\Commands;

use App\Models\Discount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateActiveDiscounts extends Command
{
    protected $signature = 'discounts:remove-duplicate-active';

    protected $description = 'Archive and remove older discounts when multiple exist for the same product in the same store';

    public function handle(): int
    {
        $this->info('Removing duplicate discounts...');

        $duplicateGroups = Discount::query()
            ->select('product_id', 'store_id')
            ->groupBy('product_id', 'store_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate discounts found.');
            return 0;
        }

        $idsToDelete = [];

        foreach ($duplicateGroups as $group) {
            $discounts = Discount::query()
                ->where('product_id', $group->product_id)
                ->where('store_id', $group->store_id)
                ->orderByDesc('id')
                ->pluck('id');

            $idsToDelete = array_merge($idsToDelete, $discounts->slice(1)->all());
        }

        $count = count($idsToDelete);
        $toArchiveQuery = Discount::query()->whereIn('id', $idsToDelete);

        DB::transaction(function () use ($toArchiveQuery) {
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
                (clone $toArchiveQuery)->select(
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

            Discount::withoutEvents(function () use ($toArchiveQuery) {
                (clone $toArchiveQuery)->delete();
            });
        });

        $this->info("Archived and removed {$count} duplicate discount(s).");

        return 0;
    }
}
