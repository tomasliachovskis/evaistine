<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which flyer page a Gemini-extracted discount was printed on, so the
    // flyer page can list its offers page by page in the flyer's own order
    // instead of one flat list sorted by discount size. Null for e-shop
    // scraper rows.
    public function up(): void
    {
        foreach (['discount_temp', 'discounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('flyer_page')->nullable()->after('store_flyer_id');
            });
        }

        // discount_temp already knows the page: page_image_path is
        // "flyers/originals/{processId}_page_{N}.jpg".
        DB::table('discount_temp')
            ->whereNotNull('store_flyer_id')
            ->whereNotNull('page_image_path')
            ->orderBy('id')
            ->select(['id', 'page_image_path'])
            ->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    if (preg_match('/_page_(\d+)\.jpg$/', $row->page_image_path, $m)) {
                        DB::table('discount_temp')->where('id', $row->id)->update(['flyer_page' => (int) $m[1]]);
                    }
                }
            });

        // discounts has no link back to its temp row, so match within the
        // same flyer by price, then by closest name.
        DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->whereNotNull('discounts.store_flyer_id')
            ->orderBy('discounts.id')
            ->select(['discounts.id', 'discounts.store_flyer_id', 'discounts.discounted_price', 'products.name'])
            ->chunkById(500, function ($discounts) {
                $temps = DB::table('discount_temp')
                    ->whereIn('store_flyer_id', $discounts->pluck('store_flyer_id')->unique())
                    ->whereNotNull('flyer_page')
                    ->get(['store_flyer_id', 'discounted_price', 'name', 'flyer_page'])
                    ->groupBy('store_flyer_id');

                foreach ($discounts as $discount) {
                    $candidates = ($temps[$discount->store_flyer_id] ?? collect())
                        ->filter(fn ($t) => abs((float) $t->discounted_price - (float) $discount->discounted_price) < 0.005);

                    if ($candidates->isEmpty()) {
                        continue;
                    }

                    $productName = mb_strtolower($discount->name);
                    $best = $candidates->sortByDesc(function ($t) use ($productName) {
                        similar_text($productName, mb_strtolower($t->name), $percent);

                        return $percent;
                    })->first();

                    DB::table('discounts')->where('id', $discount->id)->update(['flyer_page' => $best->flyer_page]);
                }
            }, 'discounts.id', 'id');
    }

    public function down(): void
    {
        foreach (['discount_temp', 'discounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('flyer_page');
            });
        }
    }
};
