<?php

namespace App\View\Composers;

use App\Http\Controllers\Api\ProductController;
use App\Support\CacheVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

// The mobile bottom nav's category/store sheets render on every page, so their
// data is cached the same way HomeController caches its own per-request calls
// into ProductController — small tables, but formatStore() does per-store
// queries and this runs on every single request.
class MobileNavComposer
{
    public function compose(View $view): void
    {
        $suffix = CacheVersion::suffix(['discounts']);

        $categories = Cache::remember("mobile_nav_categories_{$suffix}", 1800, function () {
            $response = App::make(ProductController::class)->getCategories();

            return collect($response->getData(true))
                ->reject(fn ($category) => $category['hide'] ?? false)
                ->values()
                ->all();
        });

        // Stores carry leaflets_count (header "Leidiniai (N)"), so a new
        // leaflet has to refresh this list too, not only new discounts.
        $storesSuffix = CacheVersion::suffix(['discounts', 'flyers']);
        $stores = Cache::remember("mobile_nav_stores_{$storesSuffix}", 1800, function () {
            $response = App::make(ProductController::class)->getStores();

            return $response->getData(true)['data'];
        });

        $view->with('categories', $categories)->with('stores', $stores);
    }
}
