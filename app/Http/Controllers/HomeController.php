<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Services\HomePageMetaService;
use App\Services\HomePageSectionsService;
use App\Services\StoresPageMetaService;
use App\Support\CacheVersion;
use App\Support\PageHtmlCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __construct(
        private HomePageSectionsService $sectionsService,
        private HomePageMetaService $metaService,
        private StoresPageMetaService $storesMetaService,
    ) {}

    public function index(Request $request)
    {
        // HomePageSectionsService::build() runs ~45s uncached (it wasn't
        // written with its own caching — the JSON API only ever called it
        // from behind Cache::remember in ProductController::getFavoriteHome()).
        // Same fix as that method: cache the built data, not just the response.
        $cacheKey = 'home_page_sections_'.CacheVersion::suffix(['discounts']);
        $sections = Cache::remember($cacheKey, 1800, fn () => $this->sectionsService->build());

        $metaCacheKey = 'home_page_meta_'.CacheVersion::suffix(['discounts']);
        $pageMeta = Cache::remember($metaCacheKey, 1800, fn () => $this->metaService->build());

        // The real landing page (LandingHomePage) fetches stores separately
        // from the favorite-home sections, for the hero store slider —
        // mirrored here via ProductController::getStores()'s same formatter.
        $storesCacheKey = 'home_stores_'.CacheVersion::suffix(['discounts']);
        $stores = Cache::remember($storesCacheKey, 1800, function () {
            $stores = Store::select('id', 'name', 'slug')
                ->withCount(['discounts' => fn ($query) => $query->select(\DB::raw('count(distinct discounts.id)'))])
                ->get();

            return $this->storesMetaService->formatStore($stores);
        });

        // Hero chip row only makes sense for stores currently on sale —
        // named chains first (App\Support\StoreListPriority), then the rest
        // by discount count.
        $stores = \App\Support\StoreListPriority::sort($stores);

        // buildAllLeaflets() is already ordered current-per-store first, newest
        // valid_from first within that (StoreFlyer::scopeOrdered()), with
        // expired ones pushed to the end — but "pushed to the end" still
        // means an expired leaflet could land in the first 10 for a store
        // whose current leaflet isn't ready yet. The homepage should only
        // ever tease genuinely current leaflets, so filter expired out
        // entirely rather than relying on sort order alone. Reuses
        // ProductController::getAllLeaflets()'s own 1h cache, same in-process
        // pattern as AkcijosController/LeafletController.
        $leafletsPayload = json_decode(app(ProductController::class)->getAllLeaflets()->getContent(), true);
        $latestLeaflets = collect($leafletsPayload['leaflets'] ?? [])
            ->filter(fn ($leaflet) => ($leaflet['status'] ?? null) !== 'expired')
            ->take(10)
            ->values()
            ->all();

        $seo = $pageMeta['seo'];
        $title = $seo['meta_title'] ?: 'Akcijos ir nuolaidos Lietuvoje | SuperAkcijos.lt';
        $description = $seo['meta_description'] ?: 'Rask visas akcijas ir nuolaidas Lietuvoje. Naujausi Maxima, Lidl, Iki, Rimi ir Norfa leidiniai.';

        return PageHtmlCache::remember($request, '/', fn () => view('home', [
            'title' => $title,
            'description' => $description,
            'canonical' => url('/'),
            'sections' => $sections,
            'pageMeta' => $pageMeta,
            'stores' => $stores,
            'latestLeaflets' => $latestLeaflets,
        ]));
    }
}
