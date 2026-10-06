<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FoodCategorySlugs;
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
        // Interactive flyer (clickable products, filters, shopping list).
        // Started as a ?beta=1 prototype; on for everyone since 2026-10-01.
        $betaConfig = ! empty($payload['listing_meta']['pages'])
            ? $this->leafletBetaConfig($payload['flyer_offers'] ?? [], $payload['listing_meta']['pages'] ?? [], $store, $flyerSlug, $payload['listing_meta']['store_name'] ?? $store)
            : null;
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
            'flyerOffersIntro' => $payload['flyer_offers_intro'] ?? null,
            'betaConfig' => $betaConfig,
            'sidebarLeaflets' => $this->sidebarLeaflets($payload['listing_meta'], $store),
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
     * "Taip pat žiūrėkite kitus leidinius" in the leaflet page's side
     * column: this store's other current leaflets first, then one current
     * leaflet per other store (the main chains first), up to 6.
     */
    private function sidebarLeaflets(array $listingMeta, string $storeSlug): array
    {
        $limit = 6;
        $currentSlug = $listingMeta['flyer']['slug'] ?? null;

        $sameStore = collect($listingMeta['leaflets'] ?? [])
            ->filter(fn ($l) => ($l['slug'] ?? null) !== $currentSlug && ($l['status'] ?? null) !== 'expired')
            ->map(fn ($l) => $l + ['store_name' => $listingMeta['store_name'] ?? $storeSlug, 'store_slug' => $storeSlug]);

        $mainStores = \App\Support\StoreListPriority::mainSlugs();
        $otherStores = collect(json_decode(app(ProductController::class)->getAllLeaflets()->getContent(), true)['leaflets'] ?? [])
            ->filter(fn ($l) => ($l['status'] ?? null) !== 'expired' && ($l['store_slug'] ?? null) !== $storeSlug)
            ->groupBy('store_slug')
            ->map(fn ($leaflets) => $leaflets->first())
            ->sortBy(fn ($l) => ($i = array_search($l['store_slug'], $mainStores, true)) === false ? count($mainStores) : $i);

        return $sameStore->concat($otherStores)->take($limit)->values()->all();
    }

    /**
     * Hotspots (offers with a known page and box) plus the lens chips for
     * the interactive flyer viewer.
     */
    private function leafletBetaConfig(array $offers, array $pages, string $storeSlug, string $flyerSlug, string $storeName): array
    {
        // The page image per page, for the card's magnifier (a zoomed crop
        // of the product straight from the flyer page).
        $pageImages = collect($pages)->mapWithKeys(fn ($p) => [(int) $p['page_number'] => $p['image_url']]);

        $hotspots = collect($offers)
            ->filter(fn ($d) => ! empty($d['flyer_page']) && is_array($d['flyer_box'] ?? null) && count($d['flyer_box']) === 4)
            ->map(fn ($d) => [
                'id' => $d['id'],
                'product_id' => $d['product']['id'],
                'page' => (int) $d['flyer_page'],
                'page_image' => $pageImages[(int) $d['flyer_page']] ?? null,
                'box' => array_map('intval', $d['flyer_box']),
                'name' => $d['product']['name'],
                'image' => $d['product']['image_url'],
                'href' => '/akcijos/'.$d['product']['full_slug'],
                'flyer_href' => "/leidinys/{$storeSlug}/{$flyerSlug}#psl-{$d['flyer_page']}",
                'price' => (float) ($d['discounted_price'] ?? 0),
                'original' => (float) ($d['original_price'] ?? 0),
                'percent' => $d['discount_percent'] ? (int) round(abs($d['discount_percent'])) : null,
                'unit' => UnitPrice::label($d['unit_price'] ?? null, $d['unit_price_basis'] ?? null),
                'category' => $d['product']['category']['slug'] ?? null,
                'comparison' => PriceComparison::forDeal($d, $storeSlug),
                'signal' => $d['deal_signal'] ?? null,
                'store' => $storeName,
            ])
            ->values();

        // At most 4 filters, in plain words: cheapest here, big discounts,
        // and the flyer's 2 most common food categories. More choices at
        // once is harder to scan, especially for older readers.
        $categoryFilters = $hotspots
            ->filter(fn ($h) => in_array($h['category'], FoodCategorySlugs::FOOD, true))
            ->groupBy('category')
            ->map(function ($group, $slug) use ($offers) {
                $name = collect($offers)->firstWhere('product.category.slug', $slug)['product']['category']['name'] ?? $slug;

                return [
                    'key' => 'cat:'.$slug,
                    'label' => ProductController::SHORT_CATEGORY_LABELS[$name] ?? $name,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(2)
            ->values();

        $specialFilters = collect([
            ['key' => 'cheapest', 'label' => 'Pigiausios', 'count' => $hotspots->filter(fn ($h) => $h['comparison']['cheapest'] ?? false)->count()],
            ['key' => 'big', 'label' => 'Didelės nuolaidos', 'count' => $hotspots->filter(fn ($h) => ($h['percent'] ?? 0) >= 40)->count()],
        ])->filter(fn ($f) => $f['count'] > 0);

        return [
            'hotspots' => $hotspots->all(),
            'lenses' => $specialFilters->merge($categoryFilters)->values()->all(),
            'store' => $storeName,
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
