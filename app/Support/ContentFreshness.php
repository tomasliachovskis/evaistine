<?php

namespace App\Support;

use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

// "Atnaujinta prieš N min" — newest-product-date freshness, scoped to a
// store, a category, or both. Distinct from StoreDataFreshness (which is
// store-only and based on ScraperRun completion time, not product data) —
// ScraperRun has no category dimension, so that mechanism can't answer this.
// Computed straight from existing Discount.created_at, no new table/column.
class ContentFreshness
{
    private const TTL_SECONDS = 1800;

    public static function forAll(): ?Carbon
    {
        $cacheKey = 'content_freshness_all_' . CacheVersion::suffix(['discounts']);

        $createdAt = Cache::remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => Discount::max('created_at')
        );

        return $createdAt ? Carbon::parse($createdAt) : null;
    }

    public static function forStore(int $storeId): ?Carbon
    {
        $cacheKey = 'content_freshness_store_' . $storeId . '_' . CacheVersion::suffix(['discounts']);

        $createdAt = Cache::remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => Discount::where('store_id', $storeId)->max('created_at')
        );

        return $createdAt ? Carbon::parse($createdAt) : null;
    }

    public static function forCategory(int $categoryId): ?Carbon
    {
        $cacheKey = 'content_freshness_category_' . $categoryId . '_' . CacheVersion::suffix(['discounts']);

        $createdAt = Cache::remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => Discount::whereHas('product', fn ($q) => $q->where('category_id', $categoryId))->max('created_at')
        );

        return $createdAt ? Carbon::parse($createdAt) : null;
    }

    public static function forStoreAndCategory(int $storeId, int $categoryId): ?Carbon
    {
        $cacheKey = 'content_freshness_store_category_' . $storeId . '_' . $categoryId . '_' . CacheVersion::suffix(['discounts']);

        $createdAt = Cache::remember(
            $cacheKey,
            self::TTL_SECONDS,
            fn () => Discount::where('store_id', $storeId)
                ->whereHas('product', fn ($q) => $q->where('category_id', $categoryId))
                ->max('created_at')
        );

        return $createdAt ? Carbon::parse($createdAt) : null;
    }
}
