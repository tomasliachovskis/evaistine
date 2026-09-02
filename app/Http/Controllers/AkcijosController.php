<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
use App\Models\Category;
use App\Models\KeywordPage;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use App\Support\PageHtmlCache;
use App\Support\ProductPageMeta;
use App\Support\ProductSchema;
use App\Support\StoreDisplayMeta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Ported from discount/src/app/akcijos/page.tsx and .../[...slug]/page.tsx —
// the [...slug] catch-all's resolveRouteType() dispatch (store / category /
// store-category / product / keyword) becomes the branching in show() below.
// Every branch calls the existing Api\ProductController methods in-process
// (no HTTP hop) and decodes their JSON, reusing the exact same cached
// query/formatting logic the old JSON API used.
class AkcijosController extends Controller
{
    public function index(Request $request, ProductController $api)
    {
        // Verified against production (not the invented category-tile grid
        // this used to be): the plain /akcijos hub looks exactly like a store
        // hub page — category sidebar + per-category "best deals" carousels
        // (getBestDiscountsByCategory(), the no-store version of the method
        // renderDiscountsListing() uses) — shown whenever no category/store
        // filter is active, same condition as a store page's carousels.
        $payload = json_decode($api->getAllDiscounts()->getContent(), true);

        $sections = [];
        if (! $request->query('category') && ! $request->query('store')) {
            $sections = json_decode($api->getBestDiscountsByCategory()->getContent(), true);
        }

        return $this->renderListingPayload($request, $payload, '/akcijos', 'discounts', null, null, $sections);
    }

    public function show(Request $request, ProductController $api, KeywordPageController $keywordApi, string $slug1, ?string $slug2 = null)
    {
        if ($slug2 !== null) {
            if (StoreDisplayMeta::isStoreSlug($slug1)) {
                return $this->renderDiscountsListing($request, $api, $slug1, $slug2);
            }

            return $this->renderProduct($request, $api, $slug1, $slug2);
        }

        if (KeywordPage::where('slug', $slug1)->exists()) {
            return $this->renderKeyword($request, $keywordApi, $slug1);
        }

        if (StoreDisplayMeta::isStoreSlug($slug1) || Category::where('slug', $slug1)->exists()) {
            return $this->renderDiscountsListing($request, $api, $slug1, null);
        }

        abort(404);
    }

    public function searchForm()
    {
        $path = '/akcijos/paieska';

        return view('akcijos.search-form', [
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path, ['order' => 'x']), // always noindex, matches search UX pages
        ]);
    }

    public function search(Request $request, ProductController $api, string $query)
    {
        $response = $api->search($request, $query);
        $payload = json_decode($response->getContent(), true);

        $path = "/akcijos/paieska/{$query}";

        return view('akcijos.search', [
            'query' => $query,
            'deals' => $payload['data']['data'] ?? [],
            'pagination' => $payload['data'] ?? null,
            'total' => $payload['data']['total'] ?? 0,
            'basePath' => $path,
            'canonical' => CanonicalUrl::build($path),
            // Search results are never indexed — matches getRobotsMeta()'s
            // "/paieska/" pathname check in the Next.js frontend.
            'robots' => 'noindex, nofollow, noarchive, nosnippet',
        ]);
    }

    private function renderDiscountsListing(Request $request, ProductController $api, string $storeOrCategory, ?string $category): View|Response
    {
        try {
            $response = $category ? $api->getDiscounts($storeOrCategory, $category) : $api->getDiscounts($storeOrCategory);
        } catch (ModelNotFoundException $e) {
            abort(404);
        }

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $path = $category ? "/akcijos/{$storeOrCategory}/{$category}" : "/akcijos/{$storeOrCategory}";

        // Ported from akcijos/[...slug]/page.tsx's showStoreCarousels: a plain
        // store page (no path category segment, no ?category= filter) gets
        // per-category "best deals" carousels instead of the flat grid. The
        // sidebar/grid split lives inside <livewire:discount-filters> itself
        // (see its $sections handling) — listing.blade.php's chrome (breadcrumb,
        // h1, back-link, seoDescription, FAQ) is already identical to
        // CategoryCarouselsLayout's, so no separate view is needed.
        $sections = [];
        if (StoreDisplayMeta::isStoreSlug($storeOrCategory) && $category === null && ! $request->query('category')) {
            $sections = json_decode($api->getBestDiscountsByCategoryForStore($storeOrCategory)->getContent(), true);
        }

        return $this->renderListingPayload($request, $payload, $path, 'discounts', $storeOrCategory, $category, $sections);
    }

    private function renderKeyword(Request $request, KeywordPageController $keywordApi, string $slug): View|Response
    {
        $response = $keywordApi->show($request, $slug);
        $payload = json_decode($response->getContent(), true);

        return $this->renderListingPayload($request, $payload, "/akcijos/{$slug}", 'keyword', $slug, null);
    }

    private function renderListingPayload(Request $request, array $payload, string $path, string $filtersMode, ?string $filtersPrimarySlug, ?string $filtersSecondarySlug, array $sections = []): View|Response
    {
        $data = $payload['data'] ?? [];
        $breadcrumbs = $payload['breadcrumbs'] ?? [];
        $seo = $payload['seo'] ?? [];
        $listingMeta = $payload['listing_meta'] ?? null;
        $query = $request->query();

        $faqItems = $listingMeta['sections']['faq'] ?? [];
        $deals = $data['data'] ?? [];

        // When a store+category combo has 0 real offers, the backend (see
        // getDiscountsByStoreAndCategory) fills this in with the same
        // category's live offers from other stores instead of leaving the
        // page blank — real, relevant content instead of a thin/empty page.
        $fallbackOtherStores = $payload['fallback_other_stores'] ?? null;
        $itemListDeals = $deals !== [] ? $deals : ($fallbackOtherStores['data']['data'] ?? []);

        // Verified against production (not just the local .tsx checkout, which
        // renders a different/older hero here): a plain store page reads
        // "{Store} akcijos šią savaitę" — generateSeoData('store')'s seo_title
        // is close ("{Store} akcijos") but missing that suffix, so it's added
        // here rather than duplicated in the backend. Every other case (store
        // category, category, keyword) already gets the right text straight
        // from seo_title (e.g. "Maxima akcija bakalėja").
        if (($listingMeta['type'] ?? null) === 'store' && ! empty($listingMeta['store_name'])) {
            $pageTitle = $listingMeta['store_name'].' akcijos šią savaitę';
        } else {
            $pageTitle = $seo['seo_title'] ?? $listingMeta['store_name'] ?? $listingMeta['category_name'] ?? $path;
        }

        // Sidebar shows the *other* facet than the one already fixed by the
        // URL: browsing a store → pick a category; browsing a category (or a
        // keyword page) → pick a store. Matches product-filter-controls.tsx's
        // filterMode semantics (categories-only vs stores-only). The plain
        // /akcijos hub (no primary slug at all — verified against production,
        // not the invented category-tile page this used to be) always shows
        // categories, same as a store page.
        $sidebarMode = 'categories';
        if ($filtersPrimarySlug !== null && ($filtersMode === 'keyword' || ! StoreDisplayMeta::isStoreSlug($filtersPrimarySlug))) {
            $sidebarMode = 'stores';
        }

        return PageHtmlCache::remember($request, $path, fn () => view('akcijos.listing', [
            'deals' => $deals,
            'pagination' => $data,
            'seo' => $seo,
            'pageTitle' => $pageTitle,
            'breadcrumbs' => $breadcrumbs,
            'listingMeta' => $listingMeta,
            'fallbackOtherStores' => $fallbackOtherStores,
            'basePath' => $path,
            'filtersMode' => $filtersMode,
            'filtersPrimarySlug' => $filtersPrimarySlug,
            'filtersSecondarySlug' => $filtersSecondarySlug,
            'sidebarMode' => $sidebarMode,
            'sections' => $sections,
            'canonical' => CanonicalUrl::build($path, $query),
            'robots' => CanonicalUrl::robotsMeta($path, $query),
            'breadcrumbSchema' => BreadcrumbSchema::build(
                collect($breadcrumbs)->map(fn ($b) => [
                    'name' => $b['name'],
                    'href' => $b['slug'] === '/' ? '/' : '/'.ltrim($b['slug'], '/'),
                ])->all()
            ),
            'faqSchema' => ! empty($faqItems) ? FaqSchema::build($faqItems) : null,
            'itemListSchema' => ! empty($itemListDeals) ? ItemListSchema::build($pageTitle, collect($itemListDeals)->map(fn ($d) => [
                'name' => $d['product']['name'],
                'href' => '/akcijos/'.$d['product']['full_slug'],
                'image' => $d['product']['image_url'],
            ])->all()) : null,
        ]));
    }

    private function renderProduct(Request $request, ProductController $api, string $categorySlug, string $productSlug): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        $response = $api->getProductWithSimilar($productSlug);

        if ($response->getStatusCode() === 404) {
            // Original permanently redirects a stale/removed product URL to its
            // category listing rather than a bare 404 — preserves link equity.
            if (Category::where('slug', $categorySlug)->exists()) {
                return redirect("/akcijos/{$categorySlug}", 301);
            }

            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $deals = $payload['data'] ?? [];
        $similar = $payload['similar'] ?? [];
        $breadcrumbs = $payload['breadcrumbs'] ?? [];
        $seo = $payload['seo'] ?? [];

        // The product's real, current category — from breadcrumbs (derived
        // from $product->category in ProductController::generateBreadcrumbs),
        // not from the request URL. A product's category can change after a
        // URL has already been shared/indexed (e.g. category re-mapping), so
        // $categorySlug (the route segment) can go stale while the product
        // itself still resolves fine by slug alone. Redirecting to the real
        // category here, and always building the canonical from it, stops
        // the same product being servable — and self-declaring itself
        // canonical — under multiple category URLs at once (seen in Search
        // Console as "Duplicate, Google chose different canonical than
        // user" across ~500 pages).
        $categoryCrumb = collect($breadcrumbs)->first(fn ($crumb) => ($crumb['type'] ?? null) === 'category');
        $realCategorySlug = $categoryCrumb ? Str::after($categoryCrumb['slug'], 'akcijos/') : null;

        if ($realCategorySlug && $realCategorySlug !== $categorySlug) {
            return redirect("/akcijos/{$realCategorySlug}/{$productSlug}", 301);
        }

        // pickPrimaryProductDiscount in product-page-meta.ts: the cheapest
        // active discount, not just the first row — $deals' order isn't
        // price-sorted.
        $active = array_values(array_filter($deals, fn ($d) => (float) ($d['discounted_price'] ?? $d['min_price'] ?? 0) > 0));
        $pool = $active !== [] ? $active : $deals;
        usort($pool, fn ($a, $b) => (float) ($a['min_price'] ?? $a['discounted_price'] ?? PHP_INT_MAX) <=> (float) ($b['min_price'] ?? $b['discounted_price'] ?? PHP_INT_MAX));
        $primaryDeal = $pool[0] ?? null;

        $path = '/akcijos/'.($realCategorySlug ?? $categorySlug)."/{$productSlug}";
        $canonicalUrl = CanonicalUrl::build($path);

        $productSchema = $primaryDeal
            ? ProductSchema::build($primaryDeal, $seo, $canonicalUrl, $primaryDeal['min_price'] ?? null)
            : null;

        $offers = $primaryDeal['offers'] ?? [];
        usort($offers, fn ($a, $b) => (float) ($a['discounted_price'] ?? PHP_INT_MAX) <=> (float) ($b['discounted_price'] ?? PHP_INT_MAX));
        $bestOffer = $offers[0] ?? null;
        $bestPrice = (float) ($bestOffer['discounted_price'] ?? $primaryDeal['discounted_price'] ?? 0);
        $offerCount = count($offers) ?: ($primaryDeal ? 1 : 0);

        $faqItems = $primaryDeal
            ? ProductPageMeta::faqItems($primaryDeal['product'], $bestPrice, $bestOffer['store']['name'] ?? null, $offerCount)
            : [];

        $faqSchema = $faqItems !== [] ? FaqSchema::build($faqItems) : null;

        $priceDealSignal = $primaryDeal
            ? ProductPageMeta::priceDealSignal($primaryDeal['history'] ?? [], $bestPrice)
            : null;

        return view('akcijos.product', [
            'deals' => $deals,
            'primaryDeal' => $primaryDeal,
            'offers' => $offers,
            'bestOffer' => $bestOffer,
            'bestPrice' => $bestPrice,
            'similar' => $similar,
            'genericAlternatives' => $payload['generic_alternatives'] ?? [],
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
            'canonical' => $canonicalUrl,
            'robots' => CanonicalUrl::robotsMeta($path),
            'breadcrumbSchema' => BreadcrumbSchema::build(
                collect($breadcrumbs)->map(fn ($b) => [
                    'name' => $b['name'],
                    'href' => $b['slug'] === '/' ? '/' : '/'.ltrim($b['slug'], '/'),
                ])->all()
            ),
            'productSchema' => $productSchema,
            'faqItems' => $faqItems,
            'faqSchema' => $faqSchema,
            'priceDealSignal' => $priceDealSignal,
        ]);
    }
}
