<?php

namespace App\Services;

use App\Models\ProductFact;
use Illuminate\Support\Facades\DB;

// Finds the product behind a product_facts row through the store's offer for
// the same URL (discounts, then discount_histories for offers already gone).
// Scrapers post facts by URL; the offer that names the product may only be
// processed later, so unlinked rows are retried by product-facts:link.
class ProductFactLinker
{
    /**
     * @param  list<string>  $urls
     * @return array<string, int> product_url => product_id
     */
    public function productIdsFor(int $storeId, array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        $found = [];
        foreach (['discount_histories', 'discounts'] as $table) {
            // discounts last, so a current offer wins over an old one.
            foreach (array_chunk($urls, 500) as $chunk) {
                DB::table($table)
                    ->where('store_id', $storeId)
                    ->whereIn('product_url', $chunk)
                    ->whereNotNull('product_id')
                    ->orderBy('id')
                    ->get(['product_url', 'product_id'])
                    ->each(function ($row) use (&$found) {
                        $found[$row->product_url] = (int) $row->product_id;
                    });
            }
        }

        return $found;
    }

    /** Links every unlinked row it can. Returns the number linked. */
    public function linkPending(): int
    {
        $linked = 0;

        ProductFact::query()
            ->whereNull('product_id')
            ->select(['id', 'store_id', 'product_url'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$linked) {
                foreach ($rows->groupBy('store_id') as $storeId => $group) {
                    $ids = $this->productIdsFor((int) $storeId, $group->pluck('product_url')->all());
                    foreach ($group as $row) {
                        if (isset($ids[$row->product_url])) {
                            ProductFact::whereKey($row->id)->update(['product_id' => $ids[$row->product_url]]);
                            $linked++;
                        }
                    }
                }
            });

        return $linked;
    }
}
