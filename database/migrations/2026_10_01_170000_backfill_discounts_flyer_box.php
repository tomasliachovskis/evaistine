<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Fills discounts.flyer_box for offers already linked to a flyer before
    // the column existed. discounts has no link back to its temp row, so
    // match within the same flyer and page by price, then by closest name
    // (same approach as the flyer_page backfill).
    public function up(): void
    {
        DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->whereNotNull('discounts.store_flyer_id')
            ->whereNull('discounts.flyer_box')
            ->orderBy('discounts.id')
            ->select(['discounts.id', 'discounts.store_flyer_id', 'discounts.flyer_page', 'discounts.discounted_price', 'products.name'])
            ->chunkById(500, function ($discounts) {
                $temps = DB::table('discount_temp')
                    ->whereIn('store_flyer_id', $discounts->pluck('store_flyer_id')->unique())
                    ->whereNotNull('box')
                    ->get(['store_flyer_id', 'flyer_page', 'discounted_price', 'name', 'box'])
                    ->groupBy('store_flyer_id');

                foreach ($discounts as $discount) {
                    $candidates = ($temps[$discount->store_flyer_id] ?? collect())
                        ->filter(fn ($t) => abs((float) $t->discounted_price - (float) $discount->discounted_price) < 0.005)
                        ->filter(fn ($t) => $discount->flyer_page === null || (int) $t->flyer_page === (int) $discount->flyer_page);

                    if ($candidates->isEmpty()) {
                        continue;
                    }

                    $productName = mb_strtolower($discount->name);
                    $best = $candidates->sortByDesc(function ($t) use ($productName) {
                        similar_text($productName, mb_strtolower($t->name), $percent);

                        return $percent;
                    })->first();

                    $box = json_decode($best->box, true);
                    if (! is_array($box) || count($box) !== 4) {
                        continue;
                    }

                    DB::table('discounts')->where('id', $discount->id)->update([
                        'flyer_box' => json_encode(array_map('intval', $box)),
                        'flyer_page' => $discount->flyer_page ?? $best->flyer_page,
                    ]);
                }
            }, 'discounts.id', 'id');
    }

    public function down(): void
    {
        // Data backfill only; the column itself is dropped by
        // 2026_10_01_160000_add_flyer_box_to_discounts.
    }
};
