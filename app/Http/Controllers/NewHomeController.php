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

        // Reuses /pigiausios-prekes' own live comparison — just the first
        // two categories, one item each, and only each store's single
        // cheapest match (not the "+N kiti" expansion) — a teaser, not a
        // duplicate of the full page.
        $priceIndexData = $this->priceIndexService->getPageData();
        $comparisonCategories = collect($priceIndexData['items'] ?? [])
            ->groupBy('category')
            ->take(2)
            ->map(function ($items, $category) {
                return [
                    'name' => $category,
                    'total_items' => $items->count(),
                    'items' => $items->take(2)->values()->all(),
                ];
            })
            ->values()
            ->all();

        // Same source/filtering as HomeController — only genuinely current
        // leaflets, never an expired one that "pushed to the end" still
        // happened to land in the first 10.
        $leafletsPayload = json_decode(app(ProductController::class)->getAllLeaflets()->getContent(), true);
        $latestLeaflets = collect($leafletsPayload['leaflets'] ?? [])
            ->filter(fn ($leaflet) => ($leaflet['status'] ?? null) !== 'expired')
            ->take(10)
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
