<?php

namespace App\Support;

// Editorial display order for store list/grid UI (home hero chips,
// /vaistines directory) — the main chains (config('stores.main_slugs'))
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

    // Display names of the main chains, same order, from config('stores.stores').
    /** @return array<string, string> slug => name */
    public static function mainNames(?int $limit = null): array
    {
        $names = array_column(config('stores.stores', []), 'name', 'slug');
        $main = [];
        foreach (self::mainSlugs() as $slug) {
            if (isset($names[$slug])) {
                $main[$slug] = $names[$slug];
            }
        }

        return $limit === null ? $main : array_slice($main, 0, $limit, true);
    }

    // "Eurovaistinė, Gintarinė vaistinė, Camelia, Benu vaistinė ir Apotheka"
    // for hand-written copy and meta that names the chains.
    public static function mainNamesText(?int $limit = null, string $conjunction = 'ir'): string
    {
        $names = array_values(self::mainNames($limit));
        if (count($names) < 2) {
            return $names[0] ?? '';
        }
        $last = array_pop($names);

        return implode(', ', $names) . " {$conjunction} {$last}";
    }

    // Pharmacies that have a current leaflet, in the editorial order, as
    // chain names in the genitive without "vaistinės", comma-separated
    // ("Eurovaistinės, Gintarinės, Camelia"), to be followed by "ir kitų
    // vaistinių leidiniai" on /leidiniai. Lists only chains with a leaflet,
    // unlike mainNamesText().
    public static function leafletChainsGenitiveText(int $limit = 3): string
    {
        $stores = \App\Models\Store::query()
            ->whereHas('flyers', fn ($q) => $q->active()->ready()->currentlyValid())
            ->get(['slug', 'name'])
            ->map(fn ($store) => ['slug' => $store->slug, 'name' => $store->name])
            ->all();

        $names = array_map(
            fn ($store) => preg_replace('/\s+vaistinės$/u', '', PharmacyName::phrase($store['name'], 'genitive')),
            array_slice(self::sortKeepingZero($stores), 0, $limit)
        );

        return implode(', ', $names);
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
