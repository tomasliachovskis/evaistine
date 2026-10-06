<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
use App\Models\Category;
use App\Models\Discount;
use App\Models\KeywordPage;
use App\Models\Store;
use App\Support\BreadcrumbSchema;
use App\Support\CanonicalUrl;
use App\Support\PageUrl;
use App\Support\FaqSchema;
use App\Support\ItemListSchema;
use App\Support\PageHtmlCache;
use App\Support\ProductPageMeta;
use App\Support\ProductSchema;
use App\Support\ContentFreshness;
use App\Support\LithuanianDate;
use App\Support\MyStores;
use App\Services\HomePageMetaService;
use App\Support\CacheVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Ported from discount/src/app/page.tsx and .../[...slug]/page.tsx —
// the [...slug] catch-all's resolveRouteType() dispatch (store / category /
// store-category / keyword) becomes the branching in show() below; products
// have their own /p/{slug} route (product()).
// Every branch calls the existing Api\ProductController methods in-process
// (no HTTP hop) and decodes their JSON, reusing the exact same cached
// query/formatting logic the old JSON API used.
class AkcijosController extends Controller
{
    public function index(Request $request, ProductController $api, HomePageMetaService $metaService)
    {
        // Verified against production (not the invented category-tile grid
        // this used to be): the plain /akcijos hub looks exactly like a store
        // hub page — category sidebar + per-category "best deals" carousels
        // (getBestDiscountsByCategory(), the no-store version of the method
        // renderDiscountsListing() uses) — shown whenever no category/store
        // filter is active, same condition as a store page's carousels.
        MyStores::applyToRequest($request);
        $payload = json_decode($api->getAllDiscounts()->getContent(), true);

        $sections = [];
        $hubMeta = null;
        if (! $request->query('category') && ! $request->query('store')) {
            $sections = json_decode($api->getBestDiscountsByCategory()->getContent(), true);

            // Same cache key/service NewHomeController already warms — a real
            // genuine cache hit shared between "/" and the plain hub, not a
            // second computation of the same stores/categories stats.
            $pageMeta = Cache::remember(
                'new_home_meta_'.CacheVersion::suffix(['discounts']),
                1800,
                fn () => $metaService->build()
            );
            $freshnessDate = ContentFreshness::forAll();

            $hubMeta = [
                'total_deals_label' => $pageMeta['stats']['total_deals_label'] ?? null,
                'active_store_count' => $pageMeta['stats']['active_store_count'] ?? null,
                'freshness_label' => $freshnessDate ? LithuanianDate::relative($freshnessDate) : null,
            ];
        }

        return $this->renderListingPayload($request, $payload, '/akcijos', 'discounts', null, null, $sections, [], $hubMeta);
    }

    public function show(Request $request, ProductController $api, KeywordPageController $keywordApi, string $slug1, ?string $slug2 = null)
    {
        if ($redirect = $this->redirectToAsciiSlugs($request, $slug1, $slug2)) {
            return $redirect;
        }

        // A store's own flag decides whether it has an offers page, not
        // config/stores.php — leaflet-only stores (Jysk, Senukai, Avon...)
        // used to 404 here while the site itself linked to them.
        if ($slug2 === null && KeywordPage::published()->where('slug', $slug1)->exists()) {
            MyStores::applyToRequest($request);

            return $this->renderKeyword($request, $keywordApi, $slug1);
        }

        $store = Store::where('slug', $slug1)->first();
        if ($store && ! $store->showsDiscountsPage()) {
            return $this->redirectToLeafletHub($store->slug);
        }

        // Two segments are only ever pharmacy x category; products live at
        // /p/{slug}.
        if ($slug2 !== null) {
            if ($store) {
                return $this->renderDiscountsListing($request, $api, $slug1, $slug2);
            }

            abort(404);
        }

        if ($store || Category::where('slug', $slug1)->exists()) {
            // Category pages list every store: "Mano vaistinės" apply.
            if (! $store) {
                MyStores::applyToRequest($request);
            }

            return $this->renderDiscountsListing($request, $api, $slug1, null);
        }

        abort(404);
    }

    // Slug columns use utf8mb4_unicode_ci, so `uzkandžiai` or `IKI` match the
    // real `uzkandziai`/`iki` rows and would render a 200 duplicate whose
    // canonical is built from the requested path — Google reported
    // /iki/saldumynai-ir-uzkandžiai as "Google chose different
    // canonical than user". Every real slug is lowercase ASCII, so 301 any
    // other spelling to it.
    private function redirectToAsciiSlugs(Request $request, string $slug1, ?string $slug2): ?RedirectResponse
    {
        $slugs = array_filter([$slug1, $slug2], fn ($slug) => $slug !== null);
        $normalized = array_map(fn ($slug) => Str::lower(Str::ascii($slug, 'lt')), $slugs);

        if ($normalized === $slugs) {
            return null;
        }

        foreach ($normalized as $slug) {
            if (! preg_match('/^[a-z0-9_-]+$/', $slug)) {
                return null;
            }
        }

        $query = $request->getQueryString();

        return redirect()->to('/'.implode('/', $normalized).($query ? "?{$query}" : ''), 301);
    }

    // /p/{slug}. The URL carries no category, so a product keeps it when its
    // category changes (superakcijos had ~500 "duplicate, Google chose a
    // different canonical" pages from category-in-URL product paths).
    public function product(Request $request, ProductController $api, string $slug)
    {
        $normalized = Str::lower(Str::ascii($slug, 'lt'));
        if ($normalized !== $slug && preg_match('/^[a-z0-9_-]+$/', $normalized)) {
            $query = $request->getQueryString();

            return redirect()->to(PageUrl::product($normalized).($query ? "?{$query}" : ''), 301);
        }

        return $this->renderProduct($request, $api, $slug);
    }

    // Permanent for search engines, but capped at a day in browsers — a
    // bare 301 is cached forever, so turning a store's discount extraction
    // on later would never reach returning visitors.
    private function redirectToLeafletHub(string $storeSlug): RedirectResponse
    {
        return redirect("/leidinys/{$storeSlug}", 301)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function searchForm()
    {
        $path = '/paieska';

        return view('akcijos.search-form', [
            'canonical' => CanonicalUrl::build($path),
            'robots' => CanonicalUrl::robotsMeta($path, ['order' => 'x']), // always noindex, matches search UX pages
        ]);
    }

    public function search(Request $request, ProductController $api, string $query)
    {
        MyStores::applyToRequest($request);
        $response = $api->search($request, $query);
        $payload = json_decode($response->getContent(), true);

        $path = "/paieska/{$query}";

        // Same site-wide store list/cache the discount-filters sidebar
        // already uses (Api\ProductController::getStores(), versioned cache)
        // — reused here rather than a fresh query, since search has no
        // store/category of its own to scope the list by.
        $allStores = json_decode($api->getStores()->getContent(), true)['data'] ?? [];

        return view('akcijos.search', [
            'query' => $query,
            'deals' => $payload['data']['data'] ?? [],
            'pagination' => $payload['data'] ?? null,
            'total' => $payload['data']['total'] ?? 0,
            'basePath' => $path,
            'allStores' => $allStores,
            'selectedStore' => $request->get('store'),
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
        $path = $category ? "/{$storeOrCategory}/{$category}" : "/{$storeOrCategory}";

        // Ported from akcijos/[...slug]/page.tsx's showStoreCarousels: a plain
        // store page (no path category segment, no ?category= filter) gets
        // per-category "best deals" carousels instead of the flat grid. The
        // sidebar/grid split lives inside <livewire:discount-filters> itself
        // (see its $sections handling) — listing.blade.php's chrome (breadcrumb,
        // h1, back-link, seoDescription, FAQ) is already identical to
        // CategoryCarouselsLayout's, so no separate view is needed.
        $sections = [];
        $topOffers = [];
        if ($category === null && ! $request->query('category') && Store::where('slug', $storeOrCategory)->exists()) {
            $sections = json_decode($api->getBestDiscountsByCategoryForStore($storeOrCategory)->getContent(), true);
            $topOffers = json_decode($api->getBestOffersForStore($storeOrCategory)->getContent(), true);
        }

        return $this->renderListingPayload($request, $payload, $path, 'discounts', $storeOrCategory, $category, $sections, $topOffers);
    }

    private function renderKeyword(Request $request, KeywordPageController $keywordApi, string $slug): View|Response
    {
        $response = $keywordApi->show($request, $slug);
        $payload = json_decode($response->getContent(), true);

        return $this->renderListingPayload($request, $payload, "/{$slug}", 'keyword', $slug, null);
    }

    private function renderListingPayload(Request $request, array $payload, string $path, string $filtersMode, ?string $filtersPrimarySlug, ?string $filtersSecondarySlug, array $sections = [], array $topOffers = [], ?array $hubMeta = null): View|Response
    {
        $data = $payload['data'] ?? [];
        $breadcrumbs = $payload['breadcrumbs'] ?? [];
        $seo = $payload['seo'] ?? [];
        $listingMeta = $payload['listing_meta'] ?? null;
        $query = MyStores::urlQuery($request);

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
        // here rather than duplicated in the backend. Category-only pages
        // still get the right text straight from seo_title.
        //
        // Deliberately generic, no injected offer count — explicit product
        // decision to keep the H1 plain (title/meta_description already
        // carry the real %/product data; akcijos/listing.blade.php's store
        // and store_category hero subtitles also already render
        // "{count} akcijos" right under this H1, so a count there too would
        // be a duplicate).
        if (($listingMeta['type'] ?? null) === 'store' && ! empty($listingMeta['store_name'])) {
            $pageTitle = "Visos {$listingMeta['store_name']} akcijos ir nuolaidos šią savaitę";
        } elseif (($listingMeta['type'] ?? null) === 'store_category' && ! empty($listingMeta['store_name']) && ! empty($seo['category_dative_label'])) {
            // Dative plural case ("akcijos duonos gaminiams") —
            // generateSeoData()'s seo_title used to concatenate two
            // nominative nouns ("Maxima akcija duonos gaminiai"), which
            // isn't grammatical Lithuanian.
            $pageTitle = "{$listingMeta['store_name']} akcijos {$seo['category_dative_label']} šią savaitę";
        } elseif (($listingMeta['type'] ?? null) === 'category' && ! empty($seo['category_genitive_label'])) {
            // Genitive case ("Visos buitinės chemijos akcijos ir nuolaidos
            // šią savaitę") — same shape as the store page's H1, swapping
            // the (undeclined) store name for the category in genitive
            // case; a category is a common noun and needs the case, unlike
            // a store's proper name.
            $pageTitle = "Visos {$seo['category_genitive_label']} akcijos ir nuolaidos šią savaitę";
        } else {
            $pageTitle = $seo['seo_title'] ?? $listingMeta['store_name'] ?? $listingMeta['category_name'] ?? $path;
        }

        // Per explicit product decision: category and store+category pages
        // show BOTH a "Vaistinės" and a "Kategorijos" filter (each
        // pre-highlighting whichever facet the URL already fixes), instead
        // of the old single-facet-only sidebar. Plain store pages now get
        // the same bar too (added 2026-09-16) — <x-store-nav-tabs> still owns
        // the "Akcijos/Leidiniai/categories" tab row above it, this bar is
        // purely the facet-switcher, same as every other listing page type.
        // No sort control there though (see $showSort below) — store pages
        // render curated carousels ($showCarousels), never the sortable flat
        // grid, so a sort dropdown would have nothing to act on. The plain
        // /akcijos hub and keyword pages keep their existing single-facet
        // behavior (categories-only / stores-only respectively).
        $headerTypeForFilters = $listingMeta['type'] ?? null;
        $showCategoryFilter = $filtersMode !== 'keyword';
        // The /akcijos hub (no primary slug) gets the store filter too, so
        // the same navigation bar (Vaistinės / Kategorija) is on every
        // listing (2026-10-02).
        $isHub = $filtersMode === 'discounts' && $filtersPrimarySlug === null;
        $showStoreFilter = $isHub || $filtersMode === 'keyword' || $headerTypeForFilters === 'category' || $headerTypeForFilters === 'store_category' || $headerTypeForFilters === 'store';
        $showSort = $headerTypeForFilters !== 'store';
        $activeCategorySlug = $headerTypeForFilters === 'store_category' ? $filtersSecondarySlug : ($headerTypeForFilters === 'category' ? $filtersPrimarySlug : null);
        $activeStoreSlug = ($headerTypeForFilters === 'store_category' || $headerTypeForFilters === 'store')
            ? $filtersPrimarySlug
            : ($filtersMode === 'keyword' ? $request->get('store') : null);

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
            'showStoreFilter' => $showStoreFilter,
            'showCategoryFilter' => $showCategoryFilter,
            'showSort' => $showSort,
            'activeStoreSlug' => $activeStoreSlug,
            'activeCategorySlug' => $activeCategorySlug,
            // Legal age gate (LT: alcohol deals require confirming the
            // visitor is 20+) — $activeCategorySlug already resolves
            // correctly for both /alkoholiniai-gerimai and
            // /{store}/alkoholiniai-gerimai, so this one check
            // covers both URL shapes.
            'requiresAgeVerification' => $activeCategorySlug === 'alkoholiniai-gerimai',
            'sections' => $sections,
            'topOffers' => $topOffers,
            'hubMeta' => $hubMeta,
            'canonical' => CanonicalUrl::build($path, $query),
            'robots' => CanonicalUrl::robotsMeta($path, $query),
            'breadcrumbSchema' => BreadcrumbSchema::build(
                collect($breadcrumbs)->map(fn ($b) => [
                    'name' => $b['name'],
                    'href' => $b['slug'] === '/' ? '/' : '/'.ltrim($b['slug'], '/'),
                ])->all()
            ),
            'faqSchema' => ! empty($faqItems) ? FaqSchema::build($faqItems) : null,
            // $data['total'] is the real total match count (paginator's own
            // total, not just this page's ~20-24 items) — ItemListSchema's
            // numberOfItems used to just count($itemListDeals), understating
            // a page's true size (confirmed live: a keyword page with 100
            // real matches reported "numberOfItems": 20).
            'itemListSchema' => ! empty($itemListDeals) ? ItemListSchema::build($pageTitle, collect($itemListDeals)->map(fn ($d) => [
                'name' => $d['product']['name'],
                'href' => '/'.$d['product']['full_slug'],
                'image' => $d['product']['image_url'],
                'price' => $d['discounted_price'] ?? $d['min_price'] ?? null,
            ])->all(), $data['total'] ?? null) : null,
        ]));
    }

    private function renderProduct(Request $request, ProductController $api, string $productSlug): \Illuminate\View\View
    {
        $response = $api->getProductWithSimilar($productSlug);

        if ($response->getStatusCode() === 404) {
            abort(404);
        }

        $payload = json_decode($response->getContent(), true);
        $deals = $payload['data'] ?? [];
        $similar = $payload['similar'] ?? [];
        $breadcrumbs = $payload['breadcrumbs'] ?? [];
        $seo = $payload['seo'] ?? [];

        // pickPrimaryProductDiscount in product-page-meta.ts: the cheapest
        // active discount, not just the first row — $deals' order isn't
        // price-sorted.
        $active = array_values(array_filter($deals, fn ($d) => (float) ($d['discounted_price'] ?? $d['min_price'] ?? 0) > 0));
        $pool = $active !== [] ? $active : $deals;
        usort($pool, fn ($a, $b) => (float) ($a['min_price'] ?? $a['discounted_price'] ?? PHP_INT_MAX) <=> (float) ($b['min_price'] ?? $b['discounted_price'] ?? PHP_INT_MAX));
        $primaryDeal = $pool[0] ?? null;

        $path = PageUrl::product($productSlug);
        $canonicalUrl = CanonicalUrl::build($path);

        $productSchema = $primaryDeal
            ? ProductSchema::build($primaryDeal, $seo, $canonicalUrl, $primaryDeal['min_price'] ?? null)
            : null;

        $offers = $primaryDeal['offers'] ?? [];
        usort($offers, fn ($a, $b) => (float) ($a['discounted_price'] ?? PHP_INT_MAX) <=> (float) ($b['discounted_price'] ?? PHP_INT_MAX));
        $bestOffer = $offers[0] ?? null;
        $bestPrice = (float) ($bestOffer['discounted_price'] ?? $primaryDeal['discounted_price'] ?? 0);

        // Offers printed in a flyer link to that flyer, opened on the page
        // the offer is on: product pages point at the current leaflet and
        // the leaflet lists the products back. Keyed by discount id.
        $flyerLinks = $offers === [] ? collect() : Discount::query()
            ->whereIn('id', collect($offers)->pluck('id'))
            ->whereNotNull('store_flyer_id')
            ->with(['store', 'storeFlyer' => fn ($q) => $q->active()->ready()->currentlyValid()])
            ->get()
            ->filter(fn ($discount) => $discount->storeFlyer && $discount->store)
            ->mapWithKeys(fn ($discount) => [$discount->id => [
                'title' => $discount->storeFlyer->metaLabel(),
                'page' => $discount->flyer_page,
                'href' => "/leidinys/{$discount->store->slug}/{$discount->storeFlyer->slug}"
                    .($discount->flyer_page ? "#psl-{$discount->flyer_page}" : ''),
            ]]);
        $offerCount = count($offers) ?: ($primaryDeal ? 1 : 0);

        $historyFacts = $primaryDeal
            ? ProductPageMeta::historyFacts($primaryDeal['history'] ?? [], $bestPrice, $bestOffer['store']['name'] ?? null)
            : null;

        $faqItems = $primaryDeal
            ? ProductPageMeta::faqItems($primaryDeal['product'], $bestPrice, $bestOffer['store']['name'] ?? null, $offerCount, ProductPageMeta::offersHeading(), $historyFacts)
            : [];

        $faqSchema = $faqItems !== [] ? FaqSchema::build($faqItems) : null;

        $priceDealSignal = $primaryDeal
            ? ProductPageMeta::priceDealSignal($primaryDeal['history'] ?? [], $bestPrice)
            : null;

        // The one internal-link direction that never existed before —
        // category pages already link to keyword pages, and keyword pages
        // now link to each other (see KeywordPageService::buildRelatedPages),
        // but a product page linked to neither. Cached per product identity
        // inside the service itself, so this is cheap on every page load
        // after the first.
        $relatedKeywordPages = $primaryDeal
            ? app(\App\Services\KeywordPageService::class)->relatedPagesForProduct($primaryDeal['product']['id'] ?? null)
            : [];

        return view('akcijos.product', [
            'deals' => $deals,
            'primaryDeal' => $primaryDeal,
            'offers' => $offers,
            'bestOffer' => $bestOffer,
            'bestPrice' => $bestPrice,
            'flyerLinks' => $flyerLinks,
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
            'relatedKeywordPages' => $relatedKeywordPages,
        ]);
    }
}
