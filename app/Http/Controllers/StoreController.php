<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use Illuminate\Support\Str;

// Ported from discount/src/app/parduotuves/{page,[slug]/page,[slug]/[city]/page}.tsx.
class StoreController extends Controller
{
    public function index(ProductController $api)
    {
        $payload = json_decode($api->getStores()->getContent(), true);
        $stores = $payload['data'];
        $pageMeta = $payload['page_meta'];
        $path = '/parduotuves';
        $faq = $pageMeta['faq'] ?? [];
        $breadcrumbs = [['name' => 'Akcijos', 'href' => '/akcijos'], ['name' => 'Parduotuvės', 'href' => $path]];

        return view('stores.index', [
            'stores' => $stores,
            'pageMeta' => $pageMeta,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'itemListSchema' => ItemListSchema::build(
                'Parduotuvių akcijos Lietuvoje',
                collect($stores)->map(fn ($s) => ['name' => $s['name'], 'href' => "/akcijos/{$s['slug']}"])->all()
            ),
            'faqSchema' => !empty($faq) ? FaqSchema::build($faq) : null,
        ]);
    }

    public function show(ProductController $api, string $slug, ?string $city = null)
    {
        $store = Store::where('slug', $slug)->firstOrFail();
        $response = $api->getStoreLocations($slug);

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $allLocations = collect($payload['locations']);
        $cities = $allLocations->pluck('city')->unique()->sort()->values();

        $locations = $allLocations;
        if ($city !== null) {
            $locations = $allLocations->filter(fn ($l) => Str::slug($l['city']) === $city)->values();
            if ($locations->isEmpty()) {
                abort(404);
            }
        }

        $path = $city ? "/parduotuves/{$slug}/{$city}" : "/parduotuves/{$slug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
            ['name' => $store->name, 'href' => "/parduotuves/{$slug}"],
        ];
        if ($city) {
            $cityName = $locations->first()['city'] ?? $city;
            $breadcrumbs[] = ['name' => $cityName, 'href' => $path];
        }

        // A single-city store's /{city} page shows the exact same locations as
        // the no-city overview (which already lists every city) — duplicate
        // content, so canonicalize to the parent instead of self and keep it
        // out of the index. Sitemap generation mirrors this (ProductController).
        $isSingleCityDuplicate = $city !== null && $cities->count() <= 1;
        $canonicalPath = $isSingleCityDuplicate ? "/parduotuves/{$slug}" : $path;

        return view('stores.show', [
            'store' => $store,
            'locations' => $locations,
            'cities' => $cities,
            'citySlug' => $city,
            'canonical' => CanonicalUrl::build($canonicalPath),
            'robots' => $isSingleCityDuplicate ? 'noindex, follow' : CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }
}
