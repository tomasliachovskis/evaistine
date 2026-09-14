<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Services\HomePageMetaService;
use App\Services\HomePageSectionsService;
use App\Services\PriceIndexService;
use App\Services\StoresPageMetaService;
use App\Support\CacheVersion;
use App\Support\PageHtmlCache;
use App\Support\StoreListPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Live homepage ("Variant B — kainų palyginimas"), replacing HomeController's
 * "home" view — reviewed at /nauja-pradzia before the switch.
 */
class NewHomeController extends Controller
{
    // Manually curated POOLS per explicit product decision — the homepage
    // always shows exactly two blocks (food / non-food), never an automatic
    // "first N categories" pick, but which 2 items from each pool actually
    // render is randomized per request (see buildComparisonBlock below) so
    // the same two items aren't stuck showing on every load. Rotate this
    // pool by hand occasionally; each slug must exist in
    // PriceIndexService::CANDIDATE_ITEM_SLUGS or it's silently dropped (no
    // match to show).
    private const HOMEPAGE_FOOD_SLUGS = ['aliejus', 'makaronai', 'ryziai', 'miltai', 'cukrus', 'kava'];

    private const HOMEPAGE_NONFOOD_SLUGS = ['skalbimo-milteliai', 'indaploviu-tabletes', 'dantu-pasta', 'sampunas', 'indu-ploviklis', 'muilas'];

    private const HOMEPAGE_ITEMS_PER_BLOCK = 2;

    // "Naujausi akcijų leidiniai" is one row (grid-cols-4 at sm+) — one
    // leaflet per store, in this priority order, per explicit product
    // decision (was: whichever 10 leaflets happened to be newest overall).
    private const HOMEPAGE_LEAFLET_STORE_PRIORITY = ['maxima', 'norfa', 'lidl', 'rimi', 'iki'];

    public function __construct(
        private HomePageSectionsService $sectionsService,
        private HomePageMetaService $metaService,
        private StoresPageMetaService $storesMetaService,
        private PriceIndexService $priceIndexService,
    ) {}

    public function index(Request $request)
    {
        $sections = Cache::remember(
            'new_home_sections_'.CacheVersion::suffix(['discounts']),
            1800,
            fn () => $this->sectionsService->build()
        );

        $pageMeta = Cache::remember(
            'new_home_meta_'.CacheVersion::suffix(['discounts']),
            1800,
            fn () => $this->metaService->build()
        );

        $stores = Cache::remember(
            'new_home_stores_'.CacheVersion::suffix(['discounts']),
            1800,
            function () {
                $stores = Store::select('id', 'name', 'slug')
                    ->withCount(['discounts' => fn ($query) => $query->select(\DB::raw('count(distinct discounts.id)'))])
                    ->get();

                return $this->storesMetaService->formatStore($stores);
            }
        );
        $stores = collect(StoreListPriority::sort($stores))->take(5)->values()->all();

        // Reuses /pigiausios-prekes' own live comparison, but two fixed
        // blocks (food / non-food) instead of "first 2 categories, whichever
        // those happen to be" — each block's items are hand-picked (see
        // HOMEPAGE_*_SLUGS above), only each store's single cheapest match
        // (not the "+N kiti" expansion) — a teaser, not a duplicate of the
        // full page.
        // getPageData() reads pre-warmed curated_deals rows (see
        // PriceIndexService::refreshPersistedIndex(), run from
        // DealPoolRefresher on the same schedule as every other curated_deals
        // scope) instead of computing ~34 items' worth of queries live —
        // that used to be the slowest part of loading this page (measured up
        // to 37s cold). Still ~10 queries to assemble (curated_deals ->
        // discounts -> products -> categories -> stores), and this dev DB is
        // the real shared remote instance (see CLAUDE.md), so each round
        // trip costs real network latency — cached here too so a warm
        // request doesn't re-pay ~1s of that on every single load. Random
        // pool selection below still runs fresh every request on top of
        // whichever result (fresh or cached) it gets, so randomization isn't
        // lost even on a cache hit.
        $priceIndexData = Cache::remember(
            'price_index_data_'.CacheVersion::suffix(['discounts']),
            1800,
            fn () => $this->priceIndexService->getPageData()
        );
        $itemsBySlug = collect($priceIndexData['items'] ?? [])->keyBy('key');
        $buildComparisonBlock = function (string $name, array $slugPool) use ($itemsBySlug) {
            // Random 2 of the pool that actually have a live match right now
            // (not just the pool's first 2) — a fixed slice always showed
            // the exact same two items on every load.
            $items = collect($slugPool)
                ->map(fn ($slug) => $itemsBySlug->get($slug))
                ->filter()
                ->shuffle()
                ->take(self::HOMEPAGE_ITEMS_PER_BLOCK)
                ->values();

            return ['name' => $name, 'total_items' => $items->count(), 'items' => $items->all()];
        };
        $comparisonCategories = collect([
            $buildComparisonBlock('Maisto prekių kainų palyginimas', self::HOMEPAGE_FOOD_SLUGS),
            $buildComparisonBlock('Ne maisto prekių kainų palyginimas', self::HOMEPAGE_NONFOOD_SLUGS),
        ])->filter(fn (array $block) => !empty($block['items']))->values()->all();

        // Same source/filtering as HomeController — only genuinely current
        // leaflets. One per store, in HOMEPAGE_LEAFLET_STORE_PRIORITY order,
        // capped at one row (4, matching the grid's sm:grid-cols-4) — not
        // "whichever 10 leaflets happen to be newest overall" as before.
        $leafletsPayload = json_decode(app(ProductController::class)->getAllLeaflets()->getContent(), true);
        // groupBy()->map()->first(), not keyBy() (which keeps the LAST
        // match for a duplicate key, not the first) — leaflets arrive
        // newest-first, and a store with more than one current leaflet
        // should keep its newest, not whichever happened to load last.
        $currentLeafletsByStore = collect($leafletsPayload['leaflets'] ?? [])
            ->filter(fn ($leaflet) => ($leaflet['status'] ?? null) !== 'expired')
            ->groupBy('store_slug')
            ->map(fn ($leaflets) => $leaflets->first());
        $latestLeaflets = collect(self::HOMEPAGE_LEAFLET_STORE_PRIORITY)
            ->map(fn ($slug) => $currentLeafletsByStore->get($slug))
            ->filter()
            ->take(4)
            ->values()
            ->all();

        $seo = $pageMeta['seo'];

        return PageHtmlCache::remember($request, '/', fn () => view('new-home', [
            'title' => $seo['meta_title'] ?: 'Daug akcijų ir nuolaidų Lietuvoje | SuperAkcijos.lt',
            'description' => $seo['meta_description'] ?: 'Visos akcijos ir nuolaidos Lietuvoje vienoje vietoje.',
            'canonical' => url('/'),
            'robots' => null,
            'stats' => $pageMeta['stats'],
            'stores' => $stores,
            'comparisonCategories' => $comparisonCategories,
            'deals' => array_slice($sections['best_pool'], 0, 8),
            'latestLeaflets' => $latestLeaflets,
        ]));
    }
}
