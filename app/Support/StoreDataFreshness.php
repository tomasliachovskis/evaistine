<?php

namespace App\Support;

use App\Models\ScraperRun;
use Illuminate\Support\Facades\Cache;

// "Atnaujinta X" label for a store's stats line — when we last successfully
// pulled fresh data for that store, not when any individual Discount row
// happened to change (updated_at wouldn't bump on an unchanged price).
class StoreDataFreshness
{
    public static function lastUpdatedLabel(string $storeName): ?string
    {
        $cacheKey = 'store_last_updated_' . md5(mb_strtolower($storeName)) . '_' . CacheVersion::suffix(['discounts']);

        $finishedAt = Cache::remember($cacheKey, 1800, function () use ($storeName) {
            return ScraperRun::where('store', $storeName)
                ->whereIn('type', [ScraperRun::TYPE_SCRAPE, ScraperRun::TYPE_PROCESS])
                ->where('status', ScraperRun::STATUS_SUCCESS)
                ->max('finished_at');
        });

        if (!$finishedAt) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($finishedAt);

        return 'Atnaujinta ' . $date->day . ' ' . LithuanianDate::shortMonth($date);
    }
}
