<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\HomePageMetaService;
use App\Services\KeywordPageService;
use App\Services\StoresPageMetaService;
use App\Support\CacheVersion;
use App\Support\HomeLatestLeaflets;
use App\Support\StoreListPriority;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Simpler homepage for older readers, under review at /pradzia-beta next to
 * the live "/" (NewHomeController), which it doesn't touch. Three parts: a
 * search hero with the main stores, one card per everyday keyword showing
 * that keyword's cheapest current deal, and one "follow prices" call.
 * Mockup: https://claude.ai/artifact/EhLVNRhVj3NADCLHuJipgf
 */
class HomeBetaController extends Controller
{
    // Everyday groceries lead the card list when they're among the keyword
    // candidates; the rest follow by offer count.
    private const EVERYDAY_SLUGS = [
        'pienas', 'duona', 'kiausiniai', 'sviestas', 'suris', 'desra', 'kiauliena', 'kava',
        'bananai', 'varske', 'jogurtas', 'vistiena', 'makaronai', 'aliejus', 'cukrus', 'miltai',
    ];

    private const CARD_LIMIT = 16;

    private const STORE_TILES = 5;

    // Same static count the live homepage's hero and stats use, by product
    // decision ("40 vaistinės"), not derived from the store table.
    private const STORE_TOTAL = 40;

    public function __construct(
        private HomePageMetaService $metaService,
        private StoresPageMetaService $storesMetaService,
        private KeywordPageService $keywordPageService,
    ) {}

    public function index()
    {
        // Same cached payload the live homepage reads (NewHomeController).
        $pageMeta = Cache::remember(
            'new_home_meta_'.CacheVersion::suffix(['discounts']),
            1800,
            fn () => $this->metaService->build()
        );

        $stores = Cache::remember(
            'new_home_stores_'.CacheVersion::suffix(['discounts']),
            1800,
            function () {
                $stores = Store::select('id', 'name', 'slug', 'show_discounts_page')
                    ->withCount(['discounts' => fn ($query) => $query->select(\DB::raw('count(distinct discounts.id)'))])
                    ->get();

                return $this->storesMetaService->formatStore($stores);
            }
        );
        $storeTiles = collect(StoreListPriority::sort($stores))
            ->take(self::STORE_TILES)
            ->map(fn (array $store) => [
                'name' => $store['name'],
                'slug' => $store['slug'],
                // Same rule as <x-store-card>: the offers page only when the
                // store shows one and has offers, its leaflet hub otherwise.
                'href' => (($store['shows_discounts_page'] ?? true) && ($store['discounts_count'] ?? 0) > 0)
                    ? "/akcijos/{$store['slug']}"
                    : "/leidinys/{$store['slug']}",
            ])
            ->values()
            ->all();

        return view('home-beta', [
            'title' => 'Ką šiandien perkate? | eVaistine.lt',
            'description' => 'Kur šiandien pigiausia: kasdienių prekių kainos 40 vaistinių vienoje vietoje.',
            'canonical' => url('/pradzia-beta'),
            'robots' => 'noindex, nofollow',
            'storeTiles' => $storeTiles,
            'otherStoresCount' => self::STORE_TOTAL - count($storeTiles),
            'storeTotal' => self::STORE_TOTAL,
            'totalDeals' => (int) ($pageMeta['stats']['total_deals'] ?? 0),
            'totalDealsLabel' => ($pageMeta['stats']['total_deals'] ?? 0) > 0 ? $pageMeta['stats']['total_deals_label'] : null,
            'cards' => $this->keywordCards(),
            'latestLeaflets' => HomeLatestLeaflets::pick(),
        ]);
    }

    /**
     * One card per keyword page: the keyword, its cheapest current deal and
     * its offer count. Only the homepage keyword candidates are used, since
     * only their curated_deals teaser rows are kept fresh (DealPoolRefresher);
     * buildHomeTeaser() for any other page falls back to stale history.
     */
    private function keywordCards(): array
    {
        $candidates = collect($this->keywordPageService->topCandidatesByCategoryGroup()['food'] ?? []);
        $everydayRank = array_flip(self::EVERYDAY_SLUGS);
        $today = Carbon::today()->toDateString();

        return $candidates
            ->sortBy(fn ($page) => [$everydayRank[$page->slug] ?? PHP_INT_MAX, -(int) $page->matching_offers_count])
            ->map(function ($page) use ($today) {
                $teaser = $this->keywordPageService->buildHomeTeaser($page, 5);
                if (! $teaser) {
                    return null;
                }

                $best = collect($teaser['leading_deals'])
                    ->filter(fn (array $deal) => (float) ($deal['discounted_price'] ?? 0) > 0
                        && (empty($deal['to_date']) || $deal['to_date'] >= $today))
                    ->sortBy('discounted_price')
                    ->first();
                if (! $best) {
                    return null;
                }

                $store = $best['offers'][0]['store'] ?? null;

                return [
                    'label' => $teaser['label'],
                    'href' => $teaser['href'],
                    'count' => $teaser['matching_offers_count'],
                    'product_id' => $best['product']['id'] ?? null,
                    'product_name' => $best['product']['name'] ?? '',
                    'image_url' => $best['product']['image_url'] ?? null,
                    'price' => (float) $best['discounted_price'],
                    'discount_percent' => $best['discount_percent'] ?? null,
                    'store_name' => $store['name'] ?? null,
                    'store_slug' => $store['slug'] ?? null,
                ];
            })
            ->filter()
            ->take(self::CARD_LIMIT)
            ->values()
            ->all();
    }
}
