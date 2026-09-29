<?php

namespace App\Services;

use App\Models\Discount;
use Illuminate\Support\Facades\DB;

class DuplicateDiscountRemover
{
    // Archives and removes older discounts when multiple active rows
    // exist for the same product+store (keeps the newest, per id DESC,
    // preferring a row with a product_url — a web-scraped offer links to
    // the store's product page, a flyer one has nothing to link to).
    // Formerly the standalone discounts:remove-duplicate-active command —
    // folded in here since it was only ever invoked from
    // FinalizeScrapedStoresJob/ProcessScrapingFlow, never scheduled or
    // run standalone (no dry-run/target args, unlike its neighbors).
    public function remove(): int
    {
        $duplicateGroups = Discount::query()
            ->select('product_id', 'store_id')
            ->groupBy('product_id', 'store_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateGroups->isEmpty()) {
            return 0;
        }

        $idsToDelete = [];

        foreach ($duplicateGroups as $group) {
            $discounts = Discount::query()
                ->where('product_id', $group->product_id)
                ->where('store_id', $group->store_id)
                ->orderByRaw('product_url IS NULL')
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

        return $count;
    }
}
