<?php

namespace App\Support;

// Editorial display order for store list/grid UI (home hero chips,
// /parduotuves directory) — the main chains (config('stores.main_slugs'))
// first in that exact order (brand recognizability, not raw discount
// count), then every other store with active discounts sorted by
// discounts_count desc. Stores with zero active discounts are dropped
// entirely, not just pushed to the end.
class StoreListPriority
{
    /** @return array<int, string> */
    public static function mainSlugs(): array
    {
        return config('stores.main_slugs', []);
    }

    /**
     * @param  array<int, array{slug: string, discounts_count?: int}>  $stores
     * @return array<int, array>
     */
    public static function sort(array $stores): array
    {
        $active = array_values(array_filter($stores, fn ($store) => ($store['discounts_count'] ?? 0) > 0));

        usort($active, fn ($a, $b) => self::compare($a, $b));

        return $active;
    }

    // Same ranking as sort() above, minus the zero-count filter — for
    // callers (e.g. the /leidiniai toolbar + grid) that still need to show
    // a store with zero *active* items (an expired-only leaflet, say)
    // rather than dropping it from the page entirely.
    public static function sortKeepingZero(array $stores): array
    {
        $stores = array_values($stores);
        usort($stores, fn ($a, $b) => self::compare($a, $b));

        return $stores;
    }

    private static function compare(array $a, array $b): int
    {
        $mainSlugs = self::mainSlugs();
        $rankA = array_search($a['slug'], $mainSlugs, true);
        $rankB = array_search($b['slug'], $mainSlugs, true);

        if ($rankA !== false || $rankB !== false) {
            return ($rankA === false ? PHP_INT_MAX : $rankA) <=> ($rankB === false ? PHP_INT_MAX : $rankB);
        }

        return ($b['discounts_count'] ?? 0) <=> ($a['discounts_count'] ?? 0);
    }
}
