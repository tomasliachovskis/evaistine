<?php

namespace App\Support;

// Editorial display order for store list/grid UI (home hero chips,
// /parduotuves directory) — the named chains first in this exact order
// (brand recognizability, not raw discount count), then every other store
// with active discounts sorted by discounts_count desc. Stores with zero
// active discounts are dropped entirely, not just pushed to the end.
class StoreListPriority
{
    private const PRIORITY_SLUGS = [
        'maxima', 'lidl', 'iki', 'rimi', 'norfa',
        'aibe', 'express-market', 'silas', 'cia', 'kubas',
    ];

    /**
     * @param  array<int, array{slug: string, discounts_count?: int}>  $stores
     * @return array<int, array>
     */
    public static function sort(array $stores): array
    {
        $active = array_values(array_filter($stores, fn ($store) => ($store['discounts_count'] ?? 0) > 0));

        usort($active, function ($a, $b) {
            $rankA = array_search($a['slug'], self::PRIORITY_SLUGS, true);
            $rankB = array_search($b['slug'], self::PRIORITY_SLUGS, true);

            if ($rankA !== false || $rankB !== false) {
                return ($rankA === false ? PHP_INT_MAX : $rankA) <=> ($rankB === false ? PHP_INT_MAX : $rankB);
            }

            return $b['discounts_count'] <=> $a['discounts_count'];
        });

        return $active;
    }
}
