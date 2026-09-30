<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\ItemListSchema;
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

        return view('leaflets.hub', [
            'listingMeta' => $payload['listing_meta'],
            'seo' => $payload['seo'],
            'totalOffers' => $payload['total_offers'],
            'storeSlug' => $store,
            'showsDiscountsPage' => (bool) Store::where('slug', $store)->value('show_discounts_page'),
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
            // An expired/deactivated flyer's URL stays indexed and linked
            // for weeks — send it to the store's current leaflets instead
            // of a 404 (Search Console listed these as 404s).
            if (Store::where('slug', $store)->exists()) {
                return redirect("/leidinys/{$store}", 301);
            }

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
            'showsDiscountsPage' => (bool) Store::where('slug', $store)->value('show_discounts_page'),
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'flyerOffers' => $payload['flyer_offers'] ?? [],
            'flyerOffersTotal' => $payload['flyer_offers_total'] ?? 0,
            'flyerOffersSchema' => ! empty($payload['flyer_offers']) ? ItemListSchema::build(
                ($payload['seo']['seo_title'] ?? 'Leidinys').' – akcijos',
                collect($payload['flyer_offers'])->map(fn ($d) => [
                    'name' => $d['product']['name'],
                    'href' => '/akcijos/'.$d['product']['full_slug'],
                    'image' => $d['product']['image_url'],
                    'price' => $d['discounted_price'] ?? null,
                ])->all(),
                $payload['flyer_offers_total'] ?? null
            ) : null,
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
