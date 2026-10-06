<?php

namespace App\Support;

use App\Http\Controllers\Api\ProductController;

/**
 * The homepage's "Naujausi akcijų leidiniai" row (<x-home-latest-leaflets>),
 * shared by "/" (NewHomeController) and /pradzia-beta (HomeBetaController).
 */
class HomeLatestLeaflets
{
    // One leaflet per store, in this priority order, per explicit product
    // decision (was: whichever 10 leaflets happened to be newest overall).

    // One row: matches the grid's sm:grid-cols-4.
    private const LIMIT = 4;

    public static function pick(): array
    {
        // Same source/filtering as HomeController — only genuinely current
        // leaflets.
        $leafletsPayload = json_decode(app(ProductController::class)->getAllLeaflets()->getContent(), true);

        // groupBy()->map()->first(), not keyBy() (which keeps the LAST
        // match for a duplicate key, not the first) — leaflets arrive
        // newest-first, and a store with more than one current leaflet
        // should keep its newest, not whichever happened to load last.
        $currentLeafletsByStore = collect($leafletsPayload['leaflets'] ?? [])
            ->filter(fn ($leaflet) => ($leaflet['status'] ?? null) !== 'expired')
            ->groupBy('store_slug')
            ->map(fn ($leaflets) => $leaflets->first());

        return collect(StoreListPriority::mainSlugs())
            ->map(fn ($slug) => $currentLeafletsByStore->get($slug))
            ->filter()
            ->take(self::LIMIT)
            ->values()
            ->all();
    }
}
