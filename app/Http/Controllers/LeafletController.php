<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\ItemListSchema;
use App\Support\PriceComparison;
use App\Support\UnitPrice;
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
            'flyerOffers' => $payload['flyer_offers'] ?? null,
            'flyerOffersSchema' => ! empty($payload['flyer_offers']['offers']) ? ItemListSchema::build(
                ($payload['seo']['seo_title'] ?? 'Leidinys').' – akcijos',
                collect($payload['flyer_offers']['offers'])->map(fn ($d) => [
                    'name' => $d['product']['name'],
                    'href' => '/akcijos/'.$d['product']['full_slug'],
                    'image' => $d['product']['image_url'],
                    'price' => $d['discounted_price'] ?? null,
                    'price_valid_until' => $d['to_date'] ?? null,
                ])->all(),
                $payload['flyer_offers']['total']
            ) : null,
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
        // Interactive flyer prototype (clickable products, lenses, shopping
        // list), shown only with ?beta=1 until it is switched on for all.
        $betaConfig = request()->boolean('beta')
            ? $this->leafletBetaConfig($payload['flyer_offers'] ?? [], $store, $flyerSlug, $payload['listing_meta']['store_name'] ?? $store)
            : null;
        $breadcrumbs = $this->mapBreadcrumbs($payload['breadcrumbs']);

        return view('leaflets.show', [
            'listingMeta' => $payload['listing_meta'],
            'seo' => $payload['seo'],
            'totalOffers' => $payload['total_offers'],
            'storeSlug' => $store,
            'showsDiscountsPage' => (bool) Store::where('slug', $store)->value('show_discounts_page'),
            'canonical' => CanonicalUrl::build($path),
            'robots' => $betaConfig ? 'noindex, nofollow' : CanonicalUrl::robotsMeta($path),
            'breadcrumbs' => $breadcrumbs,
            'breadcrumbSchema' => BreadcrumbSchema::build($breadcrumbs),
            'flyerOffers' => $payload['flyer_offers'] ?? [],
            'flyerOffersTotal' => $payload['flyer_offers_total'] ?? 0,
            'flyerOffersIntro' => $payload['flyer_offers_intro'] ?? null,
            'betaConfig' => $betaConfig,
            'flyerOffersSchema' => ! empty($payload['flyer_offers']) ? ItemListSchema::build(
                ($payload['seo']['seo_title'] ?? 'Leidinys').' – akcijos',
                collect($payload['flyer_offers'])->map(fn ($d) => [
                    'name' => $d['product']['name'],
                    'href' => '/akcijos/'.$d['product']['full_slug'],
                    'image' => $d['product']['image_url'],
                    'price' => $d['discounted_price'] ?? null,
                    'price_valid_until' => $d['to_date'] ?? null,
                ])->all(),
                $payload['flyer_offers_total'] ?? null
            ) : null,
        ]);
    }

    /**
     * Hotspots (offers with a known page and box) plus the lens chips for
     * the interactive flyer viewer.
     */
    private function leafletBetaConfig(array $offers, string $storeSlug, string $flyerSlug, string $storeName): array
    {
        $hotspots = collect($offers)
            ->filter(fn ($d) => ! empty($d['flyer_page']) && is_array($d['flyer_box'] ?? null) && count($d['flyer_box']) === 4)
            ->map(function ($d) use ($storeSlug, $flyerSlug, $storeName) {
                $comparison = PriceComparison::forDeal($d, $storeSlug);

                return [
                    'id' => $d['id'],
                    'product_id' => $d['product']['id'],
                    'page' => (int) $d['flyer_page'],
                    'box' => array_map('intval', $d['flyer_box']),
                    'name' => $d['product']['name'],
                    'image' => $d['product']['image_url'],
                    'href' => '/akcijos/'.$d['product']['full_slug'],
                    'flyer_href' => "/leidinys/{$storeSlug}/{$flyerSlug}?beta=1#psl-{$d['flyer_page']}",
                    'price' => (float) ($d['discounted_price'] ?? 0),
                    'original' => (float) ($d['original_price'] ?? 0),
                    'percent' => $d['discount_percent'] ? (int) round(abs($d['discount_percent'])) : null,
                    'unit' => UnitPrice::label($d['unit_price'] ?? null, $d['unit_price_basis'] ?? null),
                    'category' => $d['product']['category']['slug'] ?? null,
                    'comparison' => $comparison,
                    'signal' => $d['deal_signal'] ?? null,
                    'store' => $storeName,
                    'store_slug' => $storeSlug,
                    'search' => mb_strtolower($d['product']['name']),
                ];
            })
            ->values();

        $categoryLenses = $hotspots
            ->filter(fn ($h) => $h['category'])
            ->groupBy('category')
            ->map(fn ($group, $slug) => [
                'key' => 'cat:'.$slug,
                'label' => (function () use ($offers, $slug) {
                    $name = collect($offers)->firstWhere('product.category.slug', $slug)['product']['category']['name'] ?? $slug;

                    return ProductController::SHORT_CATEGORY_LABELS[$name] ?? $name;
                })(),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values();

        $specialLenses = collect([
            ['key' => 'cheapest', 'label' => 'Pigiausia', 'count' => $hotspots->filter(fn ($h) => $h['comparison']['cheapest'] ?? false)->count()],
            ['key' => 'big', 'label' => 'Nuolaida nuo 40 %', 'count' => $hotspots->filter(fn ($h) => ($h['percent'] ?? 0) >= 40)->count()],
        ])->filter(fn ($l) => $l['count'] > 0);

        return [
            'hotspots' => $hotspots->all(),
            'lenses' => $specialLenses->merge($categoryLenses)->values()->all(),
        ];
    }

    private function mapBreadcrumbs(array $breadcrumbs): array
    {
        return collect($breadcrumbs)->map(fn ($b) => [
            'name' => $b['name'],
            'href' => $b['slug'] === '/' ? '/' : '/' . ltrim($b['slug'], '/'),
        ])->all();
    }
}
