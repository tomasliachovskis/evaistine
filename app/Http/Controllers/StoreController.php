<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;

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
        // Per-city subpages were a doorway-page pattern flagged by an SEO
        // audit (1,399 near-identical /parduotuves/{store}/{city} pages,
        // same title/H1, differing only by one address block) — the
        // no-city page below already groups and renders every city's
        // locations in one place, so a bookmarked/indexed city URL just
        // redirects to the store page instead of 404ing.
        if ($city !== null) {
            return redirect("/parduotuves/{$slug}", 301);
        }

        $store = Store::where('slug', $slug)->firstOrFail();
        $response = $api->getStoreLocations($slug);

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $locations = collect($payload['locations']);

        $path = "/parduotuves/{$slug}";
        $breadcrumbs = [
            ['name' => 'Akcijos', 'href' => '/akcijos'],
            ['name' => 'Parduotuvės', 'href' => '/parduotuves'],
            ['name' => $store->name, 'href' => $path],
        ];

        return view('stores.show', [
            'store' => $store,
            'locations' => $locations,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }
}
