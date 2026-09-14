<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class DiscountResponseFormatter
{
    public function format($discounts)
    {
        if ($discounts instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $discounts->getCollection()->transform(function ($discount) {
                return $this->formatListDiscount($discount);
            });

            return $discounts;
        }

        return $discounts->map(function ($discount) {
            return $this->formatSingleDiscount($discount);
        });
    }

    public function formatList(Collection $discounts): Collection
    {
        return $discounts->map(function ($discount) {
            return $this->formatListDiscount($discount);
        });
    }

    public function formatExpiredList(Collection $histories): array
    {
        return $histories->map(function ($history) {
            return $this->formatExpiredHistory($history);
        })->values()->all();
    }

    protected function formatExpiredHistory($history): array
    {
        return [
            'id' => -1 * (int) $history->id,
            'store_id' => $history->store_id,
            'original_price' => (float) $history->original_price,
            'discounted_price' => (float) $history->discounted_price,
            'discount_percent' => $history->discount_percent !== null ? (float) $history->discount_percent : null,
            'condition' => $history->condition,
            'info' => $history->info ?? null,
            'card' => $history->card,
            'valid_date' => ($history->start_at ? $history->start_at->format('Y-m-d') : '') . ' - ' . ($history->end_at ? $history->end_at->format('Y-m-d') : ''),
            'from_date' => $history->start_at ? $history->start_at->format('Y-m-d') : null,
            'to_date' => $history->end_at ? $history->end_at->format('Y-m-d') : null,
            'offers' => [],
            'offer_count' => 0,
            'min_price' => (float) $history->discounted_price,
            'is_expired' => true,
            'product' => $this->formatProductData($history->product),
        ];
    }

    public function formatProductDiscounts(Product $product): Collection
    {
        $product->loadMissing(['discounts.store', 'discountHistories.store', 'category']);

        $productDiscounts = $this->getProductDiscounts($product);
        $productDiscountHistories = $this->getProductDiscountHistories($product);

        return $product->discounts->map(function ($discount) use ($product, $productDiscounts, $productDiscountHistories) {
            $discount->setRelation('product', $product);

            return $this->buildSingleDiscountPayload($discount, $productDiscounts, $productDiscountHistories);
        });
    }

    protected function formatSingleDiscount($discount)
    {
        $product = $discount->product;
        $productDiscounts = $this->getProductDiscounts($product);
        $productDiscountHistories = $this->getProductDiscountHistories($product);

        return $this->buildSingleDiscountPayload($discount, $productDiscounts, $productDiscountHistories);
    }

    protected function buildSingleDiscountPayload($discount, Collection $productDiscounts, Collection $productDiscountHistories): array
    {
        $offerCount = $productDiscounts->count();
        $minPrice = $productDiscounts->min('discounted_price');

        return [
            'id' => $discount->id,
            'store_id' => $discount->store_id,
            'original_price' => $discount->original_price,
            'discounted_price' => $discount->discounted_price,
            'discount_percent' => $discount->discount_percent,
            'condition' => $discount->condition,
            'info' => $discount->info,
            'unit_price' => $discount->unit_price !== null ? (float) $discount->unit_price : null,
            'unit_price_basis' => $discount->unit_price_basis,
            'card' => $discount->card,
            'from_date' => $discount->start_at ? $discount->start_at->format('Y-m-d') : null,
            'to_date' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
            'valid_date' => ($discount->start_at ? $discount->start_at->format('Y-m-d') : '') . ' - ' . ($discount->end_at ? $discount->end_at->format('Y-m-d') : ''),
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'home_keyword_slug' => $discount->getAttribute('home_keyword_slug'),
            'offers' => $productDiscounts->map(function ($offer) {
                return $this->formatOfferItem($offer);
            }),
            'history' => $productDiscountHistories->map(function ($history) {
                return $this->formatOfferItem($history);
            }),
            'product' => $this->formatProductData($discount->product),
        ];
    }

    protected function formatOfferItem($item)
    {
        return [
            'id' => $item->id,
            'store_id' => $item->store_id,
            'original_price' => $item->original_price,
            'discounted_price' => $item->discounted_price,
            'discount_percent' => $item->discount_percent,
            'condition' => $item->condition,
            'info' => $item->info ?? null,
            'card' => $item->card,
            'valid_date' => ($item->start_at ? $item->start_at->format('Y-m-d') : '') . ' - ' . ($item->end_at ? $item->end_at->format('Y-m-d') : ''),
            'from_date' => $item->start_at ? $item->start_at->format('Y-m-d') : null,
            'to_date' => $item->end_at ? $item->end_at->format('Y-m-d') : null,
            'store' => [
                'id' => $item->store->id,
                'name' => $item->store->name,
                'slug' => $item->store->slug
            ]
        ];
    }

    public function formatListDiscount($discount)
    {
        $productDiscounts = $this->getProductDiscounts($discount->product);
        $offerCount = $productDiscounts->count();
        $minPrice = $productDiscounts->min('discounted_price');

        return [
            'id' => $discount->id,
            'store_id' => $discount->store_id,
            'original_price' => (float) $discount->original_price,
            'discounted_price' => (float) $discount->discounted_price,
            'discount_percent' => $discount->discount_percent !== null ? (float) $discount->discount_percent : null,
            'condition' => $discount->condition,
            'info' => $discount->info,
            'unit_price' => $discount->unit_price !== null ? (float) $discount->unit_price : null,
            'unit_price_basis' => $discount->unit_price_basis,
            'card' => $discount->card,
            'valid_date' => ($discount->start_at ? $discount->start_at->format('Y-m-d') : '') . ' - ' . ($discount->end_at ? $discount->end_at->format('Y-m-d') : ''),
            'from_date' => $discount->start_at ? $discount->start_at->format('Y-m-d') : null,
            'to_date' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
            // Every store this product currently has a discount in — the
            // deal card shows one logo per store when there's more than one,
            // not just this one discount's own store. $productDiscounts is
            // already fetched above for offer_count/min_price, so this is free.
            // Falls back to this single $discount itself when the product
            // has no CURRENT discount at all (e.g. a DiscountHistory-sourced
            // last-resort fallback item — see KeywordPageService::
            // buildHomeTeaser()) — otherwise the card would show no store
            // logo whatsoever.
            'offers' => $productDiscounts->isNotEmpty()
                ? $productDiscounts->map(fn ($offer) => $this->formatOfferItem($offer))
                : collect([$this->formatOfferItem($discount)]),
            'offer_count' => $offerCount,
            'min_price' => (float) $minPrice,
            'home_keyword_slug' => $discount->getAttribute('home_keyword_slug'),
            'product' => $this->formatProductData($discount->product),
        ];
    }

    public function formatProduct($product)
    {
        $product->loadMissing(['discountHistories.store', 'category']);
        $productDiscountHistories = $this->getProductDiscountHistories($product);
        $lastKnownPrice = $productDiscountHistories->first()->discounted_price ?? null;

        return collect([
            [
                'id' => null,
                'store_id' => null,
                'original_price' => null,
                'discounted_price' => null,
                'discount_percent' => null,
                'condition' => null,
                'info' => null,
                'card' => null,
                'from_date' => null,
                'to_date' => null,
                'valid_date' => null,
                'offer_count' => 0,
                'min_price' => $lastKnownPrice !== null ? (float) $lastKnownPrice : null,
                'offers' => [],
                'is_expired' => true,
                'history' => $productDiscountHistories->map(function ($history) {
                    return $this->formatOfferItem($history);
                }),
                'product' => $this->formatProductData($product),
            ]
        ]);
    }

    protected function getProductDiscounts(Product $product): Collection
    {
        if ($product->relationLoaded('discounts')) {
            $product->loadMissing('discounts.store');

            return $product->discounts;
        }

        return $product->discounts()->with('store')->get();
    }

    protected function getProductDiscountHistories(Product $product): Collection
    {
        if ($product->relationLoaded('discountHistories')) {
            $product->loadMissing('discountHistories.store');

            return $product->discountHistories->sortByDesc('id')->values();
        }

        return $product->discountHistories()->with('store')->orderBy('id', 'desc')->get();
    }

    protected function formatProductData($product)
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'full_slug' => $product->category ? $product->category->slug . '/' . $product->slug : $product->slug,
            'category_id' => $product->category_id,
            'image_url' => $this->resolveProductImageUrl($product),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
        ];
    }

    protected function resolveProductImageUrl($product): ?string
    {
        if (empty($product->image_url)) {
            return null;
        }

        if (!$product->image_from_flyer) {
            return $product->image_url;
        }

        $path = parse_url($product->image_url, PHP_URL_PATH);
        $filename = basename($path ?: $product->image_url);

        if (empty($filename) || $filename === '.') {
            return $product->image_url;
        }

        // Keep the exact pre-migration URL shape — the old separate Next.js
        // frontend served this at '/assets/product/' + filename via its own
        // /var/www/images alias. Changing it to '/storage/products/' (this
        // app's own real storage:link path) would 404 every indexed
        // image-search URL and external hotlink/backlink pointing at the
        // old path, so `assets/product` is symlinked to the same files too
        // (see config/filesystems.php's `links`) and this keeps emitting
        // the old path rather than the new one.
        $relativePath = '/assets/product/' . $filename;

        // Dev-only: these flyer-extracted files only actually exist on the
        // real production storage, not in this local checkout — a relative
        // path 404s here (confirmed on a real phone testing against this
        // dev server) regardless of host, since there's no local copy to
        // serve. Point at the live site instead so local/dev browsing shows
        // the real image; production keeps emitting the relative path as
        // above.
        if (! app()->environment('production')) {
            return 'https://superakcijos.lt' . $relativePath;
        }

        return $relativePath;
    }
}
