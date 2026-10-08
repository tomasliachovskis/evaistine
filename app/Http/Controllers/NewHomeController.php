<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\HomePageMetaService;
use App\Services\KeywordPageService;
use App\Services\StoresPageMetaService;
use App\Support\CacheVersion;
use App\Support\HomeLatestLeaflets;
use App\Support\StoreListPriority;
use Illuminate\Support\Facades\Cache;

/**
 * Live homepage ("Variant B — kainų palyginimas"), replacing HomeController's
 * "home" view — reviewed at /nauja-pradzia before the switch.
 */
class NewHomeController extends Controller
{
    // The homepage always shows exactly two blocks (food / non-food), never
    // an automatic "first N categories" pick — but which 2 keyword pages
    // from each candidate pool actually render is randomized per request
    // (see the random pick below) so the same two items aren't stuck
    // showing on every load. Candidates themselves come from
    // KeywordPageService::topCandidatesByCategoryGroup() (ranked by each
    // page's own matching_offers_count), not a hand-picked slug list.
    private const HOMEPAGE_ITEMS_PER_BLOCK = 3;


    public function __construct(
        private HomePageMetaService $metaService,
        private StoresPageMetaService $storesMetaService,
        private KeywordPageService $keywordPageService,
    ) {}

    public function index()
    {
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
        $stores = collect(StoreListPriority::sort($stores))->take(5)->values()->all();

        // Each block's items are real keyword pages now (not a GenericProduct
        // basket) — topCandidatesByCategoryGroup() ranks by each page's own
        // matching_offers_count and is itself cached, so only the random-2
        // pick and the ~4 buildHomeTeaser() calls it triggers run fresh per
        // request; buildHomeTeaser() is cached per keyword page too, so a
        // repeat pick across requests doesn't recompute its comparison.
        $candidates = $this->keywordPageService->topCandidatesByCategoryGroup();
        $buildComparisonBlock = function (string $name, array $pages) {
            // Random 2 of the pool that still have a live match right now
            // (not just the pool's first 2 by offer count) — a fixed slice
            // always showed the exact same two items on every load. Shuffle
            // + a small buffer before calling buildHomeTeaser() (instead of
            // mapping the whole candidate pool) keeps this to a handful of
            // calls even though the pool itself can hold up to 20 pages.
            $items = collect($pages)
                ->shuffle()
                ->take(self::HOMEPAGE_ITEMS_PER_BLOCK + 2)
                ->map(fn ($page) => $this->keywordPageService->buildHomeTeaser($page, 5))
                ->filter()
                ->take(self::HOMEPAGE_ITEMS_PER_BLOCK)
                ->values();

            return ['name' => $name, 'total_items' => $items->count(), 'items' => $items->all()];
        };
        $comparisonCategories = collect([
            $buildComparisonBlock('Vaistų ir papildų kainų palyginimas', $candidates['medicines']),
            $buildComparisonBlock('Kosmetikos ir higienos prekių kainų palyginimas', $candidates['care']),
        ])->filter(fn (array $block) => !empty($block['items']))->values()->all();

        $latestLeaflets = HomeLatestLeaflets::pick();

        // Reuses the same root-category data HomePageMetaService already
        // computes for the footer links list — no new query needed, just
        // filtered to categories with real live offers and ranked by count.
        $categories = collect($pageMeta['all_category_footer_links'])
            ->filter(fn (array $category) => ($category['discounts_count'] ?? 0) > 0)
            ->sortByDesc('discounts_count')
            ->values()
            ->all();

        $seo = $pageMeta['seo'];

        // Deliberately NOT wrapped in PageHtmlCache — per explicit product
        // decision, the homepage changes too often (comparison teasers,
        // leaflets, stats) to serve a 24h-stale cached copy in production;
        // every other request already covers its own real cost via the
        // versioned Cache::remember() calls inside buildHomeTeaser()/
        // getStores()/etc. this view's own data already goes through.
        return view('new-home', [
            'title' => $seo['meta_title'] ?: 'Vaistų kainų palyginimas ir vaistinių akcijos | eVaistine.lt',
            'description' => $seo['meta_description'] ?: 'Palyginkite nereceptinių vaistų, vitaminų ir kosmetikos kainas Lietuvos vaistinėse vienoje vietoje.',
            'seo' => $seo,
            'canonical' => url('/'),
            'robots' => null,
            'stats' => $pageMeta['stats'],
            'stores' => $stores,
            'categories' => $categories,
            'comparisonCategories' => $comparisonCategories,
            'latestLeaflets' => $latestLeaflets,
        ]);
    }
}
