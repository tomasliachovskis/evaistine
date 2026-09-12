<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Ported from discount/src/app/leidiniai/page.tsx and leidinys/[store]/(page,[leafletSlug]/page).tsx.
class LeafletController extends Controller
{
    public function index(ProductController $api)
    {
        $payload = json_decode($api->getAllLeaflets()->getContent(), true);
        $path = '/leidiniai';
        $breadcrumbs = $this->mapBreadcrumbs($payload['breadcrumbs']);

        $freshnessDate = \App\Support\ContentFreshness::forAll();

        return view('leaflets.index', [
            'leaflets' => $payload['leaflets'],
            'seo' => $payload['seo'],
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'freshnessLabel' => $freshnessDate ? \App\Support\LithuanianDate::relative($freshnessDate) : null,
        ]);
    }

    public function hub(ProductController $api, string $store)
    {
        try {
            $response = $api->getStoreLeafletHub($store);
        } catch (ModelNotFoundException $e) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $path = "/leidinys/{$store}";
        $breadcrumbs = $this->mapBreadcrumbs($payload['breadcrumbs']);

        $topOffers = json_decode($api->getBestOffersForStore($store)->getContent(), true);

        return view('leaflets.hub', [
            'listingMeta' => $payload['listing_meta'],
            'seo' => $payload['seo'],
            'totalOffers' => $payload['total_offers'],
            'storeSlug' => $store,
            'topOffers' => $topOffers,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    public function show(ProductController $api, string $store, string $flyerSlug)
    {
        try {
            $response = $api->getStoreLeaflet($store, $flyerSlug);
        } catch (ModelNotFoundException $e) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $path = "/leidinys/{$store}/{$flyerSlug}";
        $breadcrumbs = $this->mapBreadcrumbs($payload['breadcrumbs']);

        return view('leaflets.show', [
            'listingMeta' => $payload['listing_meta'],
            'seo' => $payload['seo'],
            'totalOffers' => $payload['total_offers'],
            'storeSlug' => $store,
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
        ]);
    }

    private function mapBreadcrumbs(array $breadcrumbs): array
    {
        return collect($breadcrumbs)->map(fn ($b) => [
            'name' => $b['name'],
            'href' => $b['slug'] === '/' ? '/' : '/' . ltrim($b['slug'], '/'),
        ])->all();
    }
}
