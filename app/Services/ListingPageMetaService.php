<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\ContentFreshness;
use App\Support\FoodCategorySlugs;
use App\Support\FlyerStorage;
use App\Support\LithuanianDate;
use App\Support\LithuanianPlural;
use App\Support\StoreListPriority;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ListingPageMetaService
{
    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    public function __construct(
        private PageFreshnessService $freshnessService,
        private StoreFlyerTitleBuilder $flyerTitleBuilder,
        private KeywordPageService $keywordPageService,
        private DiscountResponseFormatter $formatter,
    ) {
    }

    public function buildForStore(Store $store): array
    {
        $storeName = $store->name;
        $validity = $this->resolveStoreValidity($store);
        $topCategories = $this->getTopCategoriesForStore($store);
        $otherStores = $this->getOtherStores($store->id);
        $totalOffers = Discount::where('store_id', $store->id)->count();
        $maxDiscount = (int) round(Discount::where('store_id', $store->id)->max('discount_percent') ?? 0);
        $topDeal = Discount::query()
            ->with('product')
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->first();
        $avgDuration = $this->getAverageDealDurationDays($store);
        $avgDiscount = $this->getAverageDiscountPercentForStore($store);
        $totalSavings = $this->getTotalSavingsForStore($store);

        $leaflets = $this->buildLeaflets($store);
        $intro = $this->buildStoreIntro($storeName, $store->slug, $validity, count($leaflets));
        $intro['seo_about'] = $this->buildStoreHubSeoAbout($storeName, $store->slug, count($leaflets));
        $content = $this->buildStoreHubContent($store, count($leaflets));
        $newToday = Discount::query()
            ->where('store_id', $store->id)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $intro['quick_stats'] = [
            [
                'label' => 'Aktyvios akcijos',
                'value' => (string) $totalOffers,
                'sublabel' => $newToday > 0 ? '+' . $newToday . ' naujos šiandien' : null,
            ],
            [
                'label' => 'Vidutinė nuolaida šią savaitę',
                'value' => $avgDiscount > 0 ? '-' . $avgDiscount . '%' : '—',
            ],
            [
                'label' => 'Vid. akcijos trukmė iki pabaigos',
                'value' => $avgDuration !== null ? $avgDuration . ' d.' : '—',
            ],
            [
                'label' => 'Sutaupymai su SuperAkcijos šią savaitę',
                'value' => $totalSavings > 0
                    ? number_format($totalSavings, 2, ',', ' ') . ' €'
                    : '—',
            ],
        ];

        return [
            'type' => 'store',
            'store_slug' => $store->slug,
            'store_name' => $storeName,
            'locations_count' => $store->locations()->active()->count(),
            'intro' => $intro,
            'content' => $content,
            'leaflet_description' => $store->leaflet_description,
            'popular_this_week' => $this->mapPopularCategoriesForStore($store->slug, $topCategories),
            'popular_carousel_title' => 'Daugiausia sutaupoma šiandien',
            'popular_carousel_subtitle' => 'Pasirinkite kategoriją ir atraskite geriausias akcijas',
            'sections' => [
                'featured_category' => $this->getFeaturedFoodCategoryForStore($store),
                'top_categories' => $topCategories,
                'available_categories' => $this->getAllCategoriesWithCountsForStore($store),
                'latest_leaflet' => $leaflets[0] ?? null,
                'leaflets' => $leaflets,
                'top_deals' => $this->getTopDealsForStore($store),
                'most_saved' => $this->getMostSavedForStore($store),
                'expiring_soon' => $this->getExpiringDealsForStore($store),
                'weekend_deals' => $this->getWeekendDealsForStore($store),
                'other_stores' => $otherStores,
            ],
        ];
    }

    public function buildForStoreListing(Store $store): array
    {
        $leafletsCount = $this->activeLeafletsCount($store);
        $intro = $this->buildStoreIntro($store->name, $store->slug, $this->resolveStoreValidity($store), $leafletsCount);
        $intro['freshness_label'] = $this->freshnessLabel(ContentFreshness::forStore($store->id));

        return [
            'type' => 'store',
            'store_slug' => $store->slug,
            'store_name' => $store->name,
            'total_offers' => $totalOffers = Discount::where('store_id', $store->id)->count(),
            'leaflets_count' => $leafletsCount,
            'locations_count' => $store->locations()->active()->count(),
            'intro' => $intro,
            'popular_carousel_title' => 'TOP pasiūlymai pagal kategorijas',
            'sections' => [
                'top_categories' => $this->getTopCategoriesForStore($store),
                'featured_category' => $this->getFeaturedFoodCategoryForStore($store),
                'available_categories' => $availableCategories = $this->getAllCategoriesWithCountsForStore($store),
                'other_stores' => $this->getOtherStores($store->id),
                'faq' => [
                    ...$this->buildLiveStoreFaq($store, $totalOffers, $availableCategories, $leafletsCount),
                    ...array_values((array) ($store->faq ?? [])),
                ],
            ],
        ];
    }

    public function buildForCategory(Category $category): array
    {
        $categoryName = $category->name;
        $categorySlug = $category->slug;
        $validity = $this->freshnessService->getCurrentWeekRange();
        $storeComparison = $this->getStoreComparisonForCategory($category);
        $stats = $this->buildCategoryStats($category, $categoryName, $storeComparison);
        $totalOffers = $this->getDiscountCountForCategory($category);

        return [
            'type' => 'category',
            'category_slug' => $categorySlug,
            'category_name' => $categoryName,
            'keyword_pages' => $this->keywordPageService->listPublishedPagesForCategory($categorySlug),
            'intro' => [
                'description' => $this->pickVariant($categorySlug, [
                    'Palyginkite ' . mb_strtolower($categoryName) . ' akcijas visuose pagrindiniuose prekybos tinkluose. Matysite didžiausias nuolaidas ir aktyvių pasiūlymų skaičių kiekvienoje parduotuvėje.',
                    $categoryName . ' akcijos iš visų didžiųjų prekybos tinklų vienoje vietoje — palyginkite kainas ir rinkitės pigiausią pasiūlymą.',
                    'Sekite ' . mb_strtolower($categoryName) . ' kainas ir nuolaidas kiekviename prekybos tinkle — čia matysite, kur šiuo metu didžiausios akcijos ir kiek galima sutaupyti.',
                ]),
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                // 'pill' + 'icon' feed <x-hero-stats>; max discount comes from
                // the already-loaded per-store comparison, no extra query.
                'quick_stats' => array_values(array_filter([
                    ($categoryMaxDiscount = (int) collect($storeComparison)->max('max_discount_percent')) > 0
                        ? ['label' => 'Nuolaidos iki', 'value' => "-{$categoryMaxDiscount}%", 'icon' => 'percent', 'pill' => "nuolaidos iki -{$categoryMaxDiscount}%", 'mobile_first' => true]
                        : null,
                    ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers, 'icon' => 'tag', 'pill' => LithuanianPlural::formatCount($totalOffers) . ' ' . LithuanianPlural::offerWord($totalOffers)],
                    ['label' => 'Parduotuvių', 'value' => (string) count($storeComparison), 'icon' => 'store', 'pill' => count($storeComparison) . ' ' . LithuanianPlural::storeWord(count($storeComparison))],
                ])),
                'freshness_label' => $this->freshnessLabel(ContentFreshness::forCategory($category->id)),
            ],
            'sections' => [
                'category_stats' => $stats,
                'top_brands' => $this->getTopBrandsForCategory($category),
                'weekly_deals' => $this->getWeeklyDealsForCategory($category, $categorySlug),
                'seasonal_modules' => $this->getSeasonalModules($categorySlug),
                'faq' => [
                    ...$this->buildLiveCategoryFaq($categoryName, $storeComparison, $totalOffers, $stats['top_discounted_products'] ?? []),
                    ...$this->buildCategoryFaq($category),
                ],
            ],
        ];
    }

    public function buildForStoreCategory(Store $store, Category $category): array
    {
        $storeName = $store->name;
        $categoryName = $category->name;
        $validity = $this->resolveStoreValidity($store);

        return [
            'type' => 'store_category',
            'store_slug' => $store->slug,
            'store_name' => $storeName,
            'category_slug' => $category->slug,
            'category_name' => $categoryName,
            'keyword_pages' => $this->keywordPageService->listPublishedPagesForCategory($category->slug),
            'total_offers' => Discount::where('store_id', $store->id)->count(),
            'leaflets_count' => $this->activeLeafletsCount($store),
            'locations_count' => $store->locations()->active()->count(),
            'intro' => [
                'description' => $this->pickVariant($store->slug . '/' . $category->slug, [
                    "Visos {$storeName} " . mb_strtolower($categoryName) . ' akcijos vienoje vietoje. Peržiūrėkite savaitės pasiūlymus ir sutaupykite apsipirkdami sezoninius produktus.',
                    "{$storeName} " . mb_strtolower($categoryName) . ' akcijos šią savaitę — palyginkite kainas ir raskite geriausius pasiūlymus vienoje vietoje.',
                    "Naujausios {$storeName} " . mb_strtolower($categoryName) . ' nuolaidos surinktos į vieną sąrašą — sutaupykite apsipirkdami šios savaitės akcijų prekėmis.',
                ]),
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'freshness_label' => $this->freshnessLabel(ContentFreshness::forStoreAndCategory($store->id, $category->id)),
            ],
            'sections' => [
                'top_categories' => $this->getTopCategoriesForStore($store),
                'featured_category' => $this->getFeaturedFoodCategoryForStore($store),
                'faq' => $this->buildStoreFaq($store, $category, $this->getTopCategoriesForStore($store, false)),
                'available_categories' => $this->getAllCategoriesWithCountsForStore($store),
            ],
        ];
    }

    private function resolveStoreValidity(Store $store): array
    {
        $flyerValidity = $store->latestFlyerValidity();

        if ($flyerValidity) {
            return $flyerValidity;
        }

        $minStart = Discount::where('store_id', $store->id)->min('start_at');
        $maxEnd = Discount::where('store_id', $store->id)->max('end_at');

        if ($minStart && $maxEnd) {
            return [
                'valid_from' => Carbon::parse($minStart)->format('Y-m-d'),
                'valid_to' => Carbon::parse($maxEnd)->format('Y-m-d'),
            ];
        }

        return $this->freshnessService->getCurrentWeekRange();
    }

    private function buildStoreIntro(string $storeName, string $storeSlug, array $validity, int $activeLeafletCount): array
    {
        $words = $this->getStoreLeafletWords($storeSlug);
        $multipleLeaflets = $activeLeafletCount > 1;
        $headline = $multipleLeaflets ? 'Naujausi' : 'Naujausias';
        $leafletNoun = $multipleLeaflets ? $words['nominative_plural'] : $words['nominative'];
        $locationSuffix = $multipleLeaflets ? ' vienoje vietoje' : '';

        $description = $this->pickVariant($storeSlug, [
            "{$headline} {$storeName} akcijų {$leafletNoun}{$locationSuffix}. Peržiūrėkite šios savaitės nuolaidas, specialius pasiūlymus ir populiariausias akcijas.",
            "Rinkitės iš {$storeName} akcijų {$leafletNoun}{$locationSuffix} — čia rasite šios savaitės nuolaidas, ribotus pasiūlymus ir daugiausiai perkamas prekes su nuolaida.",
            "{$storeName} akcijos atnaujinamos kiekvieną savaitę — sekite naujausią {$leafletNoun}{$locationSuffix} ir nepraleiskite geriausių pasiūlymų bei populiariausių prekių nuolaidų.",
        ]);

        return [
            'description' => $description,
            'valid_from' => $validity['valid_from'],
            'valid_to' => $validity['valid_to'],
        ];
    }

    // Deterministic (not random) so the same page always renders the same
    // copy across requests/cache warms, but different stores/categories land
    // on different phrasing — cuts near-duplicate-content risk across the
    // large store x category combinatorial page set without needing fully
    // hand-written copy per page.
    private function pickVariant(string $seed, array $variants): string
    {
        $index = crc32($seed) % count($variants);

        return $variants[$index];
    }

    public function buildAllLeaflets(): array
    {
        // Mirrors buildLeaflets() but across every store at once, for the
        // /leidiniai index page. sort_order is per-store (0 = current), so
        // ordering globally by it still groups each store's current leaflet
        // first, tie-broken by valid_from desc.
        $leaflets = StoreFlyer::query()
            ->active()
            ->ready()
            ->with('store')
            ->withCount('pages')
            ->ordered()
            ->get()
            ->filter(fn (StoreFlyer $flyer) => $flyer->store !== null)
            ->map(function (StoreFlyer $flyer) {
                $item = $this->flyerTitleBuilder->toListingArray($flyer, $flyer->store);
                $item['store_name'] = $flyer->store->name;
                $item['store_slug'] = $flyer->store->slug;

                return $item;
            })
            ->filter(function (array $leaflet) {
                return ($leaflet['image_url'] || $leaflet['pdf_url'])
                    && ($leaflet['pages_count'] > 0 || $leaflet['image_url']);
            })
            ->values();

        $sawActiveForStore = [];

        $leaflets = $leaflets->map(function (array $leaflet) use (&$sawActiveForStore) {
            if ($leaflet['status'] === 'active' && empty($sawActiveForStore[$leaflet['store_slug']])) {
                $sawActiveForStore[$leaflet['store_slug']] = true;
                $leaflet['status'] = 'new';
            }

            return $leaflet;
        })
            // Expired leaflets pushed to the end — sortBy is stable (PHP 8+),
            // so within "still valid" and "expired" each keeps the ordered()
            // relative order (per-store sort_order, then valid_from desc)
            // instead of expired leaflets from an early-sort_order store
            // interleaving with active leaflets from a later one.
            ->sortBy(fn (array $leaflet) => $leaflet['status'] === 'expired' ? 1 : 0)
            ->values();

        // Group the stores themselves into the same editorial order the
        // /leidiniai toolbar now uses (App\Support\StoreListPriority) —
        // named chains first, then everyone else by active-leaflet count —
        // instead of leaving them in whatever order the flat query
        // happened to fetch rows in. Per-store internal order (current
        // leaflet first, then history) is preserved by the groupBy below.
        $byStore = $leaflets->groupBy('store_slug');
        $storeOrder = \App\Support\StoreListPriority::sortKeepingZero(
            $byStore->map(fn ($group, $slug) => [
                'slug' => $slug,
                'discounts_count' => $group->where('status', '!=', 'expired')->count(),
            ])->values()->all()
        );

        return collect($storeOrder)
            ->flatMap(fn (array $store) => $byStore[$store['slug']])
            ->values()
            ->all();
    }

    // Same is_active-is-unreliable caveat as StoreFlyerTitleBuilder::
    // toListingArray() — derive current/expired from valid_to directly
    // instead. Used for the "{Store} leidiniai (N)" badges site-wide, which
    // should read as "how many can I browse right now", not the full
    // all-time archive count (confirmed live 2026-09-18: showed 13 for Iki
    // when only a couple were actually still valid).
    private function activeLeafletsCount(Store $store): int
    {
        return $store->flyers()->ready()->currentlyValid()->count();
    }

    private function buildLeaflets(Store $store): array
    {
        // is_active isn't a reliable current/expired signal (see
        // StoreFlyerTitleBuilder::toListingArray), so every ready flyer is
        // listed here — newest first, current + full history — with status
        // computed from its own dates instead of that flag.
        $leaflets = $store->flyers()
            ->ready()
            ->withCount('pages')
            ->ordered()
            ->get()
            ->map(fn ($flyer) => $this->flyerTitleBuilder->toListingArray($flyer, $store))
            ->filter(function (array $leaflet) {
                return ($leaflet['image_url'] || $leaflet['pdf_url'])
                    && ($leaflet['pages_count'] > 0 || $leaflet['image_url']);
            })
            ->values();

        $sawActive = false;

        return $leaflets->map(function (array $leaflet) use (&$sawActive) {
            if ($leaflet['status'] === 'active' && !$sawActive) {
                $sawActive = true;
                $leaflet['status'] = 'new';
            }

            return $leaflet;
        })->all();
    }

    public function buildForStoreFlyer(Store $store, StoreFlyer $flyer): array
    {
        $flyer->loadMissing(['pages', 'store']);
        $title = $this->flyerTitleBuilder->build($flyer, $store);
        $leaflets = $this->buildLeaflets($store);

        return [
            'type' => 'store_flyer',
            'store_slug' => $store->slug,
            'store_name' => $store->name,
            // Not count($leaflets) — that list intentionally includes full
            // history for browsing (see buildLeaflets()'s own comment), but
            // the "(N)" badge next to the "{Store} leidiniai" link should
            // only count the ones actually still valid today.
            'leaflets_count' => $this->activeLeafletsCount($store),
            'leaflets' => $leaflets,
            'top_categories' => $this->getTopCategoriesForStore($store),
            'flyer' => [
                'title' => $title,
                'slug' => $flyer->slug,
                'image_url' => FlyerStorage::normalizePublicUrl($flyer->image_url ?? '') ?? '',
                'pdf_url' => $flyer->pdf_url && $flyer->pdf_url !== '#'
                    ? FlyerStorage::normalizePublicUrl($flyer->pdf_url)
                    : null,
                'valid_from' => $flyer->valid_from?->format('Y-m-d') ?? '',
                'valid_to' => $flyer->valid_to?->format('Y-m-d') ?? '',
                'view_url' => "/leidinys/{$store->slug}/{$flyer->slug}",
            ],
            'pages' => $flyer->pages->map(fn ($page) => [
                'page_number' => $page->page_number,
                'image_url' => FlyerStorage::normalizePublicUrl($page->image_url),
            ])->values()->all(),
        ];
    }

    private function getFeaturedFoodCategoryForStore(Store $store): ?array
    {
        $today = Carbon::today()->toDateString();

        $aggregate = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereIn('categories.slug', FoodCategorySlugs::FOOD)
            ->selectRaw('COUNT(discounts.id) as offers_count')
            ->selectRaw('MAX(discounts.discount_percent) as max_discount_percent')
            ->selectRaw(
                'SUM(CASE WHEN discounts.end_at IS NOT NULL AND DATE(discounts.end_at) = ? THEN 1 ELSE 0 END) as expiring_today_count',
                [$today]
            )
            ->first();

        $offersCount = (int) ($aggregate->offers_count ?? 0);
        if ($offersCount <= 0) {
            return null;
        }

        $topSlugRow = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereIn('categories.slug', FoodCategorySlugs::FOOD)
            ->select('categories.slug', DB::raw('COUNT(discounts.id) as offers_count'))
            ->groupBy('categories.slug')
            ->orderByDesc('offers_count')
            ->first();

        $imageSlug = $topSlugRow->slug ?? 'bakaleja';

        return [
            'name' => 'Maisto prekės',
            'href' => "/akcijos/{$store->slug}",
            'max_discount_percent' => (int) round($aggregate->max_discount_percent ?? 0),
            'image_slug' => $imageSlug,
            'offers_count' => $offersCount,
            'expiring_today_count' => (int) ($aggregate->expiring_today_count ?? 0),
        ];
    }

    private function getTopCategoriesForStore(Store $store, bool $excludeFood = true): array
    {
        $today = Carbon::today()->toDateString();

        $query = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereNull('categories.parent_id');

        if ($excludeFood) {
            $query->whereNotIn('categories.slug', FoodCategorySlugs::FOOD);
        } else {
            $query->whereIn('categories.slug', FoodCategorySlugs::ALL);
        }

        $rows = $query
            ->select(
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent')
            )
            ->selectRaw(
                'SUM(CASE WHEN discounts.end_at IS NOT NULL AND DATE(discounts.end_at) = ? THEN 1 ELSE 0 END) as expiring_today_count',
                [$today]
            )
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('offers_count')
            ->limit(8)
            ->get();

        return $rows->map(function ($row) use ($store) {
            return [
                'name' => trim($row->name),
                'slug' => $row->slug,
                'href' => "/akcijos/{$store->slug}/{$row->slug}",
                'max_discount_percent' => (int) round($row->max_discount_percent ?? 0),
                'image_slug' => $row->slug,
                'offers_count' => (int) $row->offers_count,
                'expiring_today_count' => (int) ($row->expiring_today_count ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Every root category this store currently has at least one active discount in,
     * for the store listing page's category sidebar filter (unlike getTopCategoriesForStore,
     * this isn't limited to 8 or restricted to non-food categories).
     */
    private function getAllCategoriesWithCountsForStore(Store $store): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereNull('categories.parent_id')
            ->select(
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('offers_count')
            ->get();

        return $rows->map(function ($row) {
            return [
                'name' => trim($row->name),
                'slug' => $row->slug,
                'offers_count' => (int) $row->offers_count,
                'max_discount_percent' => (int) round($row->max_discount_percent ?? 0),
            ];
        })->values()->all();
    }

    private function mapPopularCategoriesForStore(string $storeSlug, array $topCategories): array
    {
        return array_slice(array_map(function ($cat) use ($storeSlug) {
            return [
                'label' => $cat['name'],
                'href' => $cat['href'],
                'discounts_count' => $cat['offers_count'] ?? 0,
                'image_slug' => $cat['image_slug'],
            ];
        }, $topCategories), 0, 6);
    }

    private function getOtherStores(int $excludeStoreId): array
    {
        return Store::query()
            ->where('id', '!=', $excludeStoreId)
            ->withCount('discounts')
            ->get()
            ->filter(fn (Store $s) => $s->discounts_count > 0)
            ->sortByDesc('discounts_count')
            ->take(5)
            ->map(fn (Store $s) => [
                'name' => $s->name,
                'slug' => $s->slug,
                'href' => "/leidinys/{$s->slug}",
                // Where a listing page should send a visitor: the store's
                // akcijos page when it has one, else its leaflets (the
                // akcijos URL just 301s there).
                'listing_href' => $s->showsDiscountsPage() ? "/akcijos/{$s->slug}" : "/leidinys/{$s->slug}",
                'has_discounts_page' => $s->showsDiscountsPage(),
                'discounts_count' => $s->discounts_count,
            ])
            ->values()
            ->all();
    }

    private function getStoreComparisonForCategory(Category $category): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('stores', 'stores.id', '=', 'discounts.store_id')
            ->where('products.category_id', $category->id)
            ->select(
                'stores.name as store',
                'stores.slug as store_slug',
                'stores.show_discounts_page',
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent'),
                DB::raw('ROUND(AVG(discounts.discount_percent), 0) as avg_discount_percent')
            )
            ->groupBy('stores.id', 'stores.name', 'stores.slug', 'stores.show_discounts_page')
            ->orderByDesc('offers_count')
            // No limit: at most one row per store (~47), and the real store
            // count (quick_stats, FAQ) needs all of them — the old limit(8)
            // under-reported "N parduotuvių". Display slices its own top 8.
            ->get();

        return $rows->map(function ($row) use ($category) {
            return [
                'store' => $row->store,
                'store_slug' => $row->store_slug,
                // store+category page only exists with show_discounts_page;
                // otherwise that URL 301s to the leaflets page, so link
                // there directly.
                'href' => $row->show_discounts_page ? "/akcijos/{$row->store_slug}/{$category->slug}" : "/leidinys/{$row->store_slug}",
                'offers_count' => (int) $row->offers_count,
                'max_discount_percent' => (int) round($row->max_discount_percent ?? 0),
                'avg_discount_percent' => (int) round($row->avg_discount_percent ?? 0),
            ];
        })->values()->all();
    }

    private function buildCategoryStats(Category $category, string $categoryName, array $storeComparison): array
    {
        $totalOffers = $this->getDiscountCountForCategory($category);
        $maxDiscount = $this->getMaxDiscountForCategory($category);
        $avgDiscount = (int) round(
            Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->avg('discount_percent') ?? 0
        );
        $storeCount = count($storeComparison);

        return [
            'title' => $categoryName . ' akcijų statistika',
            'summary' => "Šiuo metu SuperAkcijos.lt stebi {$totalOffers} aktyvių " . mb_strtolower($categoryName) . " akcijų {$storeCount} prekybos tinkluose. Didžiausia aptikta nuolaida siekia {$maxDiscount} %, o vidutinis sutaupymas šioje kategorijoje – apie {$avgDiscount} %.",
            'highlights' => [
                ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers],
                ['label' => 'Vidutinė nuolaida', 'value' => $avgDiscount . ' %'],
                ['label' => 'Didžiausia nuolaida', 'value' => $maxDiscount . ' %'],
                ['label' => 'Prekybos tinklai', 'value' => (string) $storeCount],
            ],
            'store_comparison' => $storeComparison,
            'top_discounted_products' => $this->getTopDiscountedProductsForCategory($category),
            'updated_at' => Carbon::now()->format('Y-m-d'),
        ];
    }

    private function getTopDiscountedProductsForCategory(Category $category): array
    {
        return Discount::query()
            ->with(['product.category', 'store'])
            ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->limit(5)
            ->get()
            ->map(function (Discount $d) {
                $formatted = $this->formatter->formatListDiscount($d);

                return [
                    'name' => $d->product->name,
                    'store' => $d->store->name,
                    'store_slug' => $d->store->slug,
                    'discount_percent' => (int) round($d->discount_percent),
                    'price' => (float) $d->discounted_price,
                    'original_price' => (float) $d->original_price,
                    'image_url' => $formatted['product']['image_url'] ?? null,
                    'href' => '/akcijos/' . $formatted['product']['full_slug'],
                ];
            })
            ->values()
            ->all();
    }

    private function getTopBrandsForCategory(Category $category): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->where('products.category_id', $category->id)
            ->whereNotNull('products.brand')
            ->where('products.brand', '!=', '')
            ->select('products.brand as name', DB::raw('COUNT(discounts.id) as deals_count'))
            ->groupBy('products.brand')
            ->orderByDesc('deals_count')
            ->limit(5)
            ->get();

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'href' => "/akcijos/{$category->slug}",
            'deals_count' => (int) $row->deals_count,
        ])->values()->all();
    }

    private function getWeeklyDealsForCategory(Category $category, string $categorySlug): array
    {
        return Discount::query()
            ->with(['product', 'store'])
            ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->limit(4)
            ->get()
            ->map(fn (Discount $d) => [
                'name' => $d->product->name,
                'href' => "/akcijos/{$categorySlug}",
                'discount_percent' => (int) round($d->discount_percent),
                'store_name' => $d->store->name,
                'image_slug' => $categorySlug,
            ])
            ->values()
            ->all();
    }

    private function getSeasonalModules(string $categorySlug): array
    {
        $modules = config("listing.seasonal_modules.{$categorySlug}", []);

        return array_map(function ($module) use ($categorySlug) {
            $hrefSuffix = $module['href_suffix'] ?? '';
            $href = $hrefSuffix !== ''
                ? "/akcijos/{$hrefSuffix}"
                : "/akcijos/{$categorySlug}";

            return [
                'title' => $module['title'],
                'description' => $module['description'],
                'href' => $href,
                'image_slug' => $module['image_slug'] ?? $categorySlug,
                'tag' => $module['tag'] ?? null,
            ];
        }, $modules);
    }

    private function mapStoreDealRow(Discount $d, Store $store): array
    {
        $categorySlug = $d->product->category?->slug;
        $href = $categorySlug
            ? "/akcijos/{$categorySlug}/{$d->product->slug}"
            : "/akcijos/{$store->slug}";

        return [
            'name' => $d->product->name,
            'href' => $href,
            'product_id' => $d->product->id,
            'discount_percent' => (int) round($d->discount_percent),
            'discounted_price' => (float) $d->discounted_price,
            'original_price' => (float) $d->original_price,
            'unit_price' => null,
            'image_url' => $d->product->image_url,
            'category_slug' => $categorySlug,
            'valid_to' => $d->end_at ? $d->end_at->format('Y-m-d') : '',
        ];
    }

    private function getTopDealsForStore(Store $store): array
    {
        $query = Discount::query()
            ->with(['product.category'])
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent');

        $this->applyTopProductFilters($query);

        return $query
            ->orderByDesc('discount_percent')
            ->limit(8)
            ->get()
            ->map(fn (Discount $d) => $this->mapStoreDealRow($d, $store))
            ->values()
            ->all();
    }

    private function getExpiringDealsForStore(Store $store, int $limit = 4): array
    {
        // startOfDay(): end_at is a DATE stored at midnight ("valid through
        // this day") — comparing against the exact current moment wrongly
        // excluded a discount expiring today for the rest of today.
        $now = Carbon::now()->startOfDay();
        $cutoff = $now->copy()->addDays(3);

        return Discount::query()
            ->with(['product.category'])
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', $now)
            ->where('end_at', '<=', $cutoff)
            ->orderBy('end_at')
            ->limit($limit)
            ->get()
            ->map(fn (Discount $d) => $this->mapStoreDealRow($d, $store))
            ->values()
            ->all();
    }

    private function getWeekendDealsForStore(Store $store, int $limit = 6): array
    {
        // startOfDay(): same reasoning as getExpiringDealsForStore() above.
        $now = Carbon::now()->startOfDay();
        $endOfWeekend = Carbon::now()->startOfWeek()->addDays(6)->endOfDay();

        return Discount::query()
            ->with(['product.category'])
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', $now)
            ->where('end_at', '<=', $endOfWeekend)
            ->orderByDesc('discount_percent')
            ->limit($limit)
            ->get()
            ->map(fn (Discount $d) => $this->mapStoreDealRow($d, $store))
            ->values()
            ->all();
    }

    private function applyTopProductFilters($query): void
    {
        $query
            ->whereNotNull('discounted_price')
            ->where('discounted_price', '>=', self::MIN_TOP_PRODUCT_PRICE)
            ->whereHas('product.category', function ($categoryQuery) {
                $categoryQuery->whereNotIn('slug', self::EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS);
            });
    }

    private function getMostSavedForStore(Store $store): array
    {
        $foodSlugs = FoodCategorySlugs::FOOD;
        $chemistrySlugs = [
            'buitine-chemija-valymo-priemones',
            'kosmetika-ir-higiena',
        ];
        $homeSlugs = [
            'namu-ukio-ir-laisvalaikio-prekes',
            'vaiku-ir-kudikiu-prekes',
        ];

        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereNull('categories.parent_id')
            ->whereColumn('discounts.original_price', '>', 'discounts.discounted_price')
            ->select(
                'categories.slug as category_slug',
                DB::raw('SUM(discounts.original_price - discounts.discounted_price) as savings_amount')
            )
            ->groupBy('categories.slug')
            ->get();

        $segments = [
            [
                'label' => 'Maisto prekėms',
                'slugs' => $foodSlugs,
                'value' => 0.0,
            ],
            [
                'label' => 'Chemijai',
                'slugs' => $chemistrySlugs,
                'value' => 0.0,
            ],
            [
                'label' => 'Namams',
                'slugs' => $homeSlugs,
                'value' => 0.0,
            ],
        ];

        foreach ($rows as $row) {
            $slug = (string) $row->category_slug;
            $amount = (float) ($row->savings_amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            foreach ($segments as $index => $segment) {
                if (!in_array($slug, $segment['slugs'], true)) {
                    continue;
                }
                $segments[$index]['value'] += $amount;
                break;
            }
        }

        $segments = array_values(array_filter($segments, fn ($segment) => $segment['value'] > 0));
        usort($segments, fn ($a, $b) => $b['value'] <=> $a['value']);
        $segments = array_slice($segments, 0, 3);

        return array_map(function ($segment) {
            return [
                'label' => $segment['label'],
                'value' => round((float) $segment['value'], 2),
                'unit' => 'EUR',
            ];
        }, $segments);
    }

    private function getAverageDealDurationDays(Store $store): ?int
    {
        $discounts = Discount::query()
            ->where('store_id', $store->id)
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->get(['start_at', 'end_at']);

        if ($discounts->isEmpty()) {
            return null;
        }

        $totalDays = $discounts->sum(function (Discount $d) {
            return Carbon::parse($d->start_at)->diffInDays(Carbon::parse($d->end_at));
        });

        return (int) max(1, round($totalDays / $discounts->count()));
    }

    private function getAverageDiscountPercentForStore(Store $store): int
    {
        return (int) round(
            Discount::where('store_id', $store->id)
                ->whereNotNull('discount_percent')
                ->avg('discount_percent') ?? 0
        );
    }

    private function getTotalSavingsForStore(Store $store): float
    {
        $discounts = Discount::query()
            ->where('store_id', $store->id)
            ->whereColumn('original_price', '>', 'discounted_price')
            ->get(['original_price', 'discounted_price']);

        return round(
            $discounts->sum(fn (Discount $d) => (float) $d->original_price - (float) $d->discounted_price),
            2
        );
    }

    private function getDiscountCountForCategory(Category $category): int
    {
        return Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))->count();
    }

    private function getMaxDiscountForCategory(Category $category): int
    {
        return (int) round(
            Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->max('discount_percent') ?? 0
        );
    }

    private function getStoreLeafletWords(string $storeSlug): array
    {
        if ($storeSlug === 'iki') {
            return [
                'accusative' => 'leidynį',
                'nominative' => 'leidynys',
                'nominative_plural' => 'leidyniai',
                'genitive' => 'leidynio',
            ];
        }

        return [
            'accusative' => 'leidinį',
            'nominative' => 'leidinys',
            'nominative_plural' => 'leidiniai',
            'genitive' => 'leidinio',
        ];
    }

    private function buildStoreHubSeoAbout(string $storeName, string $storeSlug, int $activeLeafletCount): string
    {
        $words = $this->getStoreLeafletWords($storeSlug);
        $multipleLeaflets = $activeLeafletCount > 1;
        $leafletNoun = $multipleLeaflets ? $words['nominative_plural'] : $words['nominative'];
        $leafletPhrase = $multipleLeaflets
            ? "naujausius {$storeName} akcijų {$leafletNoun}"
            : "naujausią {$storeName} akcijų {$leafletNoun}";

        $intro = "SuperAkcijos.lt – patogi vieta, kur {$leafletPhrase}, didžiausias savaitės nuolaidas ir populiariausius pasiūlymus rasite be papildomų paieškų.";
        $detail = "Kas savaitę atnaujiname akcijų sąrašą pagal galiojantį leidinį, todėl čia matote, kas šiuo metu galioja parduotuvėse. Jei domina naujas leidinys, šios savaitės akcijos ar norite greitai palyginti nuolaidas – viršuje peržiūrėkite leidinių viršelius, o žemiau – atrinktas didžiausias nuolaidas su kainomis.";

        return "{$intro}\n\n{$detail}";
    }

    // Multiple headed content sections for the leidinys hub (/leidinys/{store}).
    // Intent here is strictly the LEIDINYS (catalog: cadence, format, pages,
    // PDF) — NOT akcijos/nuolaidos (discounts), which is the separate
    // /akcijos/{store} hub's job. Copy must stay about the catalog itself —
    // how often it's published, what kinds exist, how to read/download it —
    // not savings/loyalty-card advice, which belongs on the other page.
    // Priority stores (StoreListPriority::PRIORITY_SLUGS) get hand-written,
    // factual paragraphs; every other store falls back to a richer
    // pickVariant()-templated version so pages stay distinct without needing
    // bespoke copy for all ~40 stores.
    private function buildStoreHubContent(Store $store, int $activeLeafletCount): array
    {
        $storeName = $store->name;
        $storeSlug = $store->slug;
        $words = $this->getStoreLeafletWords($storeSlug);
        $leafletNoun = $activeLeafletCount > 1 ? $words['nominative_plural'] : $words['nominative'];
        $leafletNounSingular = $words['nominative'];
        $leafletNounAccusative = $words['accusative'];
        $leafletNounGenitive = $words['genitive'];
        $isPriority = in_array($storeSlug, StoreListPriority::PRIORITY_SLUGS, true);

        $about = $isPriority ? $this->getPriorityStoreAbout($storeSlug) : [
            $this->pickVariant($storeSlug . '/about/1', [
                "{$storeName} {$leafletNounSingular} – tai kiekvieną savaitę atnaujinamas katalogas, kuriame {$storeName} pristato savo naujausią prekių pasiūlymą su galiojimo datomis ir viršelio nuoroda į pilną turinį.",
                "{$storeName} {$leafletNounSingular} – tai skaitmeninė šio tinklo prekybos leidinio versija, kurią čia atnaujiname kiekvieną kartą, kai pasirodo naujas numeris.",
                "Šiame puslapyje rasite {$storeName} akcijų {$leafletNoun} – tikrą, savaitinį šio prekybos tinklo katalogą, o ne vien atrinktų prekių sąrašą.",
            ]),
            $this->pickVariant($storeSlug . '/about/2', [
                "Naujas {$storeName} {$leafletNounSingular} skelbiamas reguliariai, o jo viršelyje visada nurodyta, nuo kada iki kada jis galioja – tai patogu žinoti prieš planuojant, kada apsilankyti parduotuvėje.",
                "{$storeName} paprastai skelbia naują leidinio numerį kas savaitę – ankstesni numeriai lieka pasiekiami puslapio apačioje, pažymėti kaip pasibaigę.",
                "Kiekvienas {$storeName} {$leafletNounSingular} turi savo unikalų numerį ir galiojimo laikotarpį, nurodytą viršelyje – taip lengva atskirti, kuris leidinys aktualus šiuo metu.",
            ]),
            $this->pickVariant($storeSlug . '/about/3', [
                "Kai kada {$storeName} vienu metu skelbia kelis skirtingus leidinius (pvz. bendrą savaitinį ir siauresnės kategorijos numerį) – visus aktyvius {$leafletNoun} rasite kartu šiame puslapyje.",
                "{$storeName} {$leafletNounSingular} apima platų prekių spektrą – nuo maisto iki buities ir namų apyvokos prekių, suskirstytą į atskirus puslapius pagal kategorijas.",
                "SuperAkcijos.lt seka {$storeName} skelbiamus leidinius ir kiekvieną naują numerį pridedame čia iškart, kai jis pasirodo.",
            ]),
        ];

        $format = $isPriority ? $this->getPriorityStoreFormat($storeSlug) : [
            $this->pickVariant($storeSlug . '/format/1', [
                "{$storeName} {$leafletNounSingular} paprastai apima keliolika ar keliasdešimt puslapių, suskirstytų pagal kategorijas – nuo šviežių maisto produktų iki buities chemijos ir namų apyvokos prekių.",
                "Kiekviename {$storeName} {$leafletNounSingular} numeryje prekės išdėstytos taip pat, kaip spausdintame ar oficialiame skaitmeniniame kataloge – puslapis po puslapio, pagal kategorijas.",
            ]),
            $this->pickVariant($storeSlug . '/format/2', [
                "Kai turime PDF nuorodą, {$storeName} {$leafletNounSingular} galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Jei prie leidinio yra PDF nuoroda, ją rasite šalia viršelio – patogu atsisiųsti ir peržiūrėti vėliau, be interneto ryšio.",
            ]),
            $this->pickVariant($storeSlug . '/format/3', [
                "Skirtingai nei akcijų sąrašas, kuriame prekės surūšiuotos pagal nuolaidos dydį, {$leafletNounSingular} atkartoja tikrąją numerio puslapių tvarką – todėl patogu naršyti taip, tarsi turėtumėte popierinį leidinį rankose.",
                "{$storeName} {$leafletNoun} archyvuojami šiame puslapyje – pasibaigę numeriai matomi atskirai, kad būtų aišku, kuris leidinys šiuo metu galiojantis, o kuris jau nebeaktualus.",
            ]),
        ];

        $tips = $isPriority ? $this->getPriorityStoreTips($storeSlug) : [
            $this->pickVariant($storeSlug . '/tips/1', [
                "Leidinio viršelyje visada nurodytas galiojimo laikotarpis – patikrinkite jį prieš peržiūrėdami puslapius, kad įsitikintumėte, jog žiūrite aktualų, o ne jau pasibaigusį {$leafletNounSingular}.",
                "Pirmiausia patikrinkite leidinio viršelį – jame nurodytas numeris ir tikslios galiojimo datos padės greitai suprasti, ar leidinys dar aktualus.",
            ]),
            $this->pickVariant($storeSlug . '/tips/2', [
                "Jei domina konkreti kategorija, {$storeName} {$leafletNoun} dažniausiai turi kelis skirtingus numerius vienu metu – peržiūrėkite visus aktyvius leidinius šiame puslapyje, kad nepraleistumėte jus dominančios dalies.",
                "Kai galioja keli {$storeName} leidiniai vienu metu, patogu peržiūrėti kiekvieną atskirai – taip lengviau rasti, kuriame numeryje yra jus dominanti kategorija.",
            ]),
            $this->pickVariant($storeSlug . '/tips/3', [
                "Tikslias kainas ir nuolaidų dydžius rasite {$storeName} akcijų sąraše – šis puslapis pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą taip, kaip jis pateikiamas oficialiame leidinyje.",
                "Jei ieškate konkrečios prekės kainos, o ne viso leidinio, patogiau naudotis {$storeName} akcijų sąrašu – ten prekės surūšiuotos pagal nuolaidos dydį.",
            ]),
        ];

        return [
            'about' => [
                'heading' => "Apie {$storeName} {$leafletNounAccusative}",
                'paragraphs' => $about,
            ],
            'format' => [
                'heading' => "{$storeName} {$leafletNounGenitive} turinys",
                'paragraphs' => $format,
            ],
            'tips' => [
                'heading' => "Kaip skaityti {$storeName} {$leafletNounAccusative}",
                'paragraphs' => $tips,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getPriorityStoreAbout(string $storeSlug): array
    {
        return match ($storeSlug) {
            'maxima' => [
                "Maxima leidinys – tai didžiausio Lietuvos prekybos tinklo, veikiančio nuo 1992 metų ir turinčio daugiau nei 200 parduotuvių visoje šalyje, savaitinis prekių katalogas, kuriame pristatomas naujausias tos savaitės asortimentas.",
                "Kiekvieną savaitę Maxima skelbia naują AČIŪ leidinio numerį, kurio viršelyje visada nurodytos tikslios galiojimo datos – tai leidžia iš anksto žinoti, kada leidinys nustos galioti ir bus pakeistas nauju.",
                "Be pagrindinio savaitinio leidinio, Maxima retkarčiais išleidžia ir atskirus teminius numerius – pavyzdžiui, švenčių, sezoninių ar namų apyvokos prekių kolekcijas, kurios galioja lygiagrečiai su savaitiniu leidiniu.",
            ],
            'lidl' => [
                "Lidl leidinys – tai Vokietijos kilmės tarptautinio prekybos tinklo, Lietuvoje veikiančio nuo 2016 metų, savaitinis prekių katalogas, kuriame pristatomas naujausias savaitės asortimentas.",
                "Naujas Lidl leidinio numeris paprastai skelbiamas pirmadieniais, o viršelyje visada nurodytos tikslios galiojimo datos, iki kada konkretus numeris aktualus.",
                "Lidl dažnai vienu metu skelbia kelis atskirus leidinius – atskirai maisto ir ne maisto prekių, o kartais ir specialius teminius numerius (pvz. sodo, sporto ar namų prekių) – visus aktyvius numerius rasite šiame puslapyje.",
            ],
            'iki' => [
                "Iki leidynys – tai vieno seniausių šiuolaikinių Lietuvos prekybos tinklų, veikiančio nuo 1992 metų, savaitinis prekių katalogas, kuriame pristatomas naujausias savaitės asortimentas.",
                "Populiariausias Iki savaitinio leidinio pavadinimas yra „Iki savaitėlė“ – naujas numeris skelbiamas kiekvieną savaitę, o viršelyje nurodytos tikslios galiojimo datos.",
                "Be pagrindinio savaitinio leidinio, Iki kartais skelbia ir atskirus kategorijų ar sezoninius numerius – visus aktyvius leidinius rasite kartu šiame puslapyje.",
            ],
            'rimi' => [
                "Rimi leidinys – tai Baltijos šalyse veikiančios Rimi Baltic grupės savaitinis prekių katalogas, kiekvieną savaitę pristatantis naują numerį su tos savaitės asortimentu.",
                "Rimi valdo tiek didesnio formato Rimi Hyper, tiek mažesnes Rimi Super parduotuves – jų leidinio turinys gali šiek tiek skirtis priklausomai nuo formato.",
                "Naujas Rimi leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje visada nurodytos tikslios galiojimo datos.",
            ],
            'norfa' => [
                "Norfa leidinys – tai Lietuvos kapitalo prekybos tinklo, atstovaujamo tiek didžiuosiuose miestuose, tiek mažesniuose miesteliuose ir kaimuose, savaitinis prekių katalogas.",
                "Naujas Norfa leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje visada nurodytos tikslios galiojimo datos.",
                "Kadangi Norfa parduotuvių tinklas platus ir apima įvairaus dydžio parduotuves, leidinio turinys paprastai orientuotas į plataus vartojimo prekes, aktualias didžiajai daliai tinklo.",
            ],
            'aibe' => [
                "Aibė leidinys – tai bendras savaitinis katalogas, kurį skelbia po Aibės vardu veikiantis nepriklausomų prekybininkų tinklas, ypač gausiai atstovaujamas mažesniuose miestuose ir kaimo vietovėse.",
                "Naujas Aibė leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje nurodytos tikslios galiojimo datos.",
                "Kadangi kiekviena Aibė parduotuvė priklauso skirtingam savininkui, leidinio turinys paprastai galioja didžiojoje dalyje tinklo parduotuvių, bet verta patikrinti konkrečios parduotuvės informaciją vietoje.",
            ],
            'express-market' => [
                "Express Market leidinys – tai UAB Kilminė valdomo kompaktiško formato parduotuvių tinklo savaitinis katalogas, pristatantis naują prekių pasiūlymą kiekvieną savaitę.",
                "Naujas Express Market leidinio numeris skelbiamas reguliariai, o viršelyje nurodytos tikslios galiojimo datos.",
                "Kadangi Express Market parduotuvės orientuotos į greitą, patogų apsipirkimą arti namų, jų leidinio turinys paprastai koncentruojasi į kasdienes, dažnai perkamas prekes.",
            ],
            'silas' => [
                "Šilas leidinys – tai Kauno ir Vilniaus regionuose veikiančio, nuo 1992 metų istoriją skaičiuojančio prekybos tinklo savaitinis prekių katalogas.",
                "Naujas Šilas leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje nurodytos tikslios galiojimo datos.",
                "Šilas leidinyje ypač daug dėmesio skiriama šviežioms daržovėms, vaisiams, pieno ir mėsos gaminiams – tai atsispindi ir jo turinio struktūroje.",
            ],
            'cia' => [
                "Čia Market leidinys – tai iš Žemaitijos kilusio, nuo 1996 metų veikiančio prekybos tinklo savaitinis katalogas, gausiai atstovaujamas Žemaitijos regione.",
                "Naujas Čia Market leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje nurodytos tikslios galiojimo datos.",
                "Čia Market išaugo iš pieno produktų parduotuvių tinklo, todėl jo leidinyje dažnai matoma stipri pieno gaminių dalis šalia įprasto maisto ir buities prekių asortimento.",
            ],
            'kubas' => [
                "Kubas leidinys – tai 2000 metais Šiauliuose įkurto prekybos tinklo, šiandien veikiančio keliuose Lietuvos miestuose, savaitinis prekių katalogas.",
                "Naujas Kubas leidinio numeris skelbiamas reguliariai kiekvieną savaitę, o viršelyje nurodytos tikslios galiojimo datos.",
                "Kubas leidinyje pateikiamos tiek maisto, tiek pramoninių prekių dalys viename numeryje.",
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function getPriorityStoreFormat(string $storeSlug): array
    {
        return match ($storeSlug) {
            'maxima' => [
                "Maxima leidinio turinys paprastai apima kelias dešimtis puslapių, suskirstytų pagal kategorijas – nuo šviežių maisto produktų iki buities chemijos ir namų apyvokos prekių.",
                "Kai kurie Maxima leidinio numeriai turi ir PDF versiją, kurią galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda, jei ji yra, pateikiama prie leidinio viršelio šiame puslapyje.",
                "Skirtingai nei akcijų sąrašas, kuriame prekės rūšiuojamos pagal nuolaidos dydį, leidinys atkartoja tikrąją numerio puslapių tvarką – todėl patogu naršyti taip, tarsi turėtumėte popierinį leidinį rankose.",
            ],
            'lidl' => [
                "Lidl leidinio turinys atspindi tinklui būdingą glaustą, kruopščiai atrinktą asortimentą – kiekviename numeryje dažniausiai pristatoma keliasdešimt prekių, suskirstytų pagal kategorijas.",
                "Kai turime PDF nuorodą, Lidl leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Ne maisto prekių Lidl leidiniai dažnai būna riboto kiekio – juose pristatomos sezoninės ar specialios prekės, kurios gali greitai baigtis parduotuvėse, todėl verta peržiūrėti leidinį iš anksto.",
            ],
            'iki' => [
                "Iki leidinio turinys suskirstytas pagal kategorijas – nuo šviežių maisto produktų, kepyklos gaminių ir mėsos iki buities chemijos bei namų apyvokos prekių.",
                "Kai turime PDF nuorodą, Iki leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką, todėl patogu naršyti taip pat, kaip naršytumėte popieriniame ar oficialiame skaitmeniniame kataloge.",
            ],
            'rimi' => [
                "Rimi leidinio turinys suskirstytas pagal kategorijas – nuo šviežių produktų iki buities ir namų apyvokos prekių, dažniausiai apimant keliasdešimt puslapių.",
                "Kai turime PDF nuorodą, Rimi leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką, todėl patogu naršyti jį taip, kaip naršytumėte oficialiame Rimi kataloge.",
            ],
            'norfa' => [
                "Norfa leidinio turinys suskirstytas pagal kategorijas – nuo šviežių maisto produktų iki buities chemijos ir namų apyvokos prekių.",
                "Kai turime PDF nuorodą, Norfa leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte oficialiame Norfa kataloge.",
            ],
            'aibe' => [
                "Aibė leidinio turinys apima plataus vartojimo maisto ir buities prekes, suskirstytas pagal kategorijas.",
                "Kai turime PDF nuorodą, Aibė leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte spausdintame ar oficialiame skaitmeniniame kataloge.",
            ],
            'express-market' => [
                "Kadangi Express Market parduotuvės nedidelės, jų leidinys paprastai trumpesnis nei didesnių tinklų – patogu greitai peržiūrėti visą turinį prieš apsilankymą.",
                "Kai turime PDF nuorodą, Express Market leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte oficialiame kataloge.",
            ],
            'silas' => [
                "Šilas leidinio turinys suskirstytas pagal kategorijas, su ypatingu akcentu šviežiems produktams.",
                "Kai turime PDF nuorodą, Šilas leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte oficialiame kataloge.",
            ],
            'cia' => [
                "Čia Market leidinio turinys suskirstytas pagal kategorijas, apimant maisto ir kasdienes buities prekes.",
                "Kai turime PDF nuorodą, Čia Market leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte oficialiame kataloge.",
            ],
            'kubas' => [
                "Kubas leidinio turinys suskirstytas pagal kategorijas – nuo maisto produktų iki pramoninių ir kasdienių buities prekių.",
                "Kai turime PDF nuorodą, Kubas leidinį galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Leidinys atkartoja tikrąją numerio puslapių tvarką – patogu naršyti taip pat, kaip naršytumėte oficialiame kataloge.",
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function getPriorityStoreTips(string $storeSlug): array
    {
        return match ($storeSlug) {
            'maxima' => [
                "Prieš peržiūrėdami leidinio puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį – taip įsitikinsite, kad žiūrite aktualų, o ne jau pasibaigusį numerį.",
                "Jei šiuo metu galioja keli Maxima leidiniai vienu metu (pvz. savaitinis ir teminis), abu rasite šiame puslapyje atskirai – patogu palyginti, kuriame yra jus dominanti prekių kategorija.",
                "Norėdami sužinoti tikslias kainas ir nuolaidų dydžius, o ne tik peržiūrėti leidinio puslapius, apsilankykite Maxima akcijų sąraše – ten prekės surūšiuotos pagal nuolaidos dydį su tiksliomis kainomis.",
            ],
            'lidl' => [
                "Prieš peržiūrėdami leidinio puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Jei ieškote konkrečios kategorijos, patikrinkite, ar šiuo metu galioja atskiras maisto ar ne maisto prekių Lidl leidinys – jie dažnai skelbiami lygiagrečiai.",
                "Tikslias kainas ir nuolaidų dydžius rasite Lidl akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą tos savaitės pasiūlymą taip, kaip jis pateikiamas oficialiame kataloge.",
            ],
            'iki' => [
                "Prieš peržiūrėdami puslapius, patikrinkite leidinio viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Jei šiuo metu galioja keli Iki leidiniai vienu metu, abu rasite šiame puslapyje atskirai – patogu palyginti, kuriame yra jus dominanti kategorija.",
                "Tikslias kainas ir nuolaidų dydžius rasite Iki akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą taip, kaip jis pateikiamas oficialiame numeryje.",
            ],
            'rimi' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį ir įsitikinkite, kad leidinys atitinka jums artimiausios parduotuvės formatą (Rimi Hyper ar Rimi Super).",
                "Jei ieškote konkrečios kategorijos, patogu naršyti leidinį puslapis po puslapio – jis atkartoja spausdinto numerio struktūrą.",
                "Tikslias kainas ir nuolaidų dydžius rasite Rimi akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'norfa' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Kadangi Norfa parduotuvės skiriasi dydžiu, verta patikrinti, ar leidinyje esanti prekė tikrai pasiekiama jums artimiausioje parduotuvėje.",
                "Tikslias kainas ir nuolaidų dydžius rasite Norfa akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'aibe' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Kadangi Aibė vienija atskirus savininkus, verta patikrinti, ar konkreti prekė ir kaina galioja jūsų artimiausioje Aibė parduotuvėje.",
                "Tikslias kainas ir nuolaidų dydžius rasite Aibė akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'express-market' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Kadangi leidinys trumpesnis, jį galima peržiūrėti per kelias minutes prieš trumpą apsipirkimą pakeliui namo.",
                "Tikslias kainas ir nuolaidų dydžius rasite Express Market akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'silas' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Kadangi Šilas veikia tik Kauno ir Vilniaus regionuose, patogu iš anksto patikrinti, ar leidinio prekės pasiekiamos jums artimiausioje parduotuvėje.",
                "Tikslias kainas ir nuolaidų dydžius rasite Šilas akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'cia' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Čia Market leidinys ypač aktualus Žemaitijos regiono gyventojams – jei gyvenate šioje Lietuvos dalyje, verta reguliariai sekti naują numerį.",
                "Tikslias kainas ir nuolaidų dydžius rasite Čia Market akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu.",
            ],
            'kubas' => [
                "Prieš peržiūrėdami puslapius, patikrinkite viršelyje nurodytą galiojimo laikotarpį, kad įsitikintumėte, jog žiūrite aktualų numerį.",
                "Kubas parduotuvės išsibarsčiusios keliuose miestuose – patogu iš anksto patikrinti, ar leidinio prekės pasiekiamos jums artimiausioje parduotuvėje.",
                "Tikslias kainas ir nuolaidų dydžius rasite Kubas akcijų sąraše – leidinys pirmiausia skirtas peržiūrėti visą savaitės pasiūlymą kataloginiu formatu, ne tik maisto skiltį.",
            ],
            default => [],
        };
    }

    private function freshnessLabel(?Carbon $date): ?string
    {
        return $date ? LithuanianDate::relative($date) : null;
    }

    private function buildStoreFaq(Store $store, ?Category $currentCategory, array $topCategories): array
    {
        $storeName = $store->name;
        $storeSlug = $store->slug;
        $words = $this->getStoreLeafletWords($storeSlug);
        $listingUrl = $this->siteUrl("/akcijos/{$storeSlug}");
        $hubLink = $this->faqLink($this->siteUrl("/leidinys/{$storeSlug}"), "{$storeName} leidinio puslapyje");
        $listingLink = $this->faqLink($listingUrl, "{$storeName} akcijų sąraše");
        $weeklyAkcijosLink = $this->faqLink($listingUrl, "šią savaitę galiojančias {$storeName} akcijas");
        $categoryLink = $this->faqCategoryLink($store, $currentCategory, $topCategories, $listingLink);

        return [
            [
                'question' => "Kur rasti {$storeName} naują {$words['nominative']}?",
                'answer' => "Naujausią {$storeName} akcijų {$words['nominative']} rasite {$hubLink} – viršuje matote leidinį, PDF ir geriausius pasiūlymus. Visas akcijas rasite {$listingLink}.",
            ],
            [
                'question' => "Nuo kada galioja {$storeName} akcijos šią savaitę?",
                'answer' => "{$storeName} savaitės akcijos paprastai galioja nuo pirmadienio iki sekmadienio. Tikslias datas ir kainas matote {$weeklyAkcijosLink}.",
            ],
            [
                'question' => "Ar yra {$storeName} savaitgalio akcijos?",
                'answer' => "Taip – savaitgalio pasiūlymus dažniausiai rasite {$listingLink}. Jei norite filtruoti pagal kategoriją, peržiūrėkite {$categoryLink}.",
            ],
            [
                'question' => "Kaip dažnai atnaujinamos {$storeName} akcijos?",
                'answer' => "{$storeName} akcijos atnaujinamos kasdien. Naujas savaitės {$words['nominative']} skelbiamas kiekvieną savaitę, o akcijų kainos syncinamos automatiškai.",
            ],
            [
                'question' => "Ar {$storeName} akcijos galioja visose parduotuvėse?",
                'answer' => "Dažniausiai taip – savaitės akcijos galioja visame {$storeName} tinkle Lietuvoje, nebent leidinyje nurodyta kitaip.",
            ],
            [
                'question' => "Ar galima atsisiųsti {$storeName} {$words['accusative']} PDF formatu?",
                'answer' => "Jei turime PDF nuorodą, ją rasite {$hubLink} prie {$words['nominative']} viršelio.",
            ],
        ];
    }

    private function faqLink(string $url, string $label): string
    {
        return '<a href="' . e($url, false) . '">' . e($label) . '</a>';
    }

    private function faqCategoryLink(Store $store, ?Category $preferredCategory, array $topCategories, string $listingLinkFallback): string
    {
        if ($preferredCategory) {
            $categoryName = mb_strtolower(trim($preferredCategory->name));

            return $this->faqLink(
                $this->siteUrl("/akcijos/{$store->slug}/{$preferredCategory->slug}"),
                "{$categoryName} akcijas {$store->name}"
            );
        }

        $topCategory = $topCategories[0] ?? null;

        if (!$topCategory || empty($topCategory['href']) || empty($topCategory['slug'])) {
            return $listingLinkFallback;
        }

        $categoryName = mb_strtolower($topCategory['name']);

        return $this->faqLink(
            $this->siteUrl("/akcijos/{$store->slug}/{$topCategory['slug']}"),
            "{$categoryName} akcijas {$store->name}"
        );
    }

    private function siteUrl(string $path): string
    {
        return 'https://superakcijos.lt' . $path;
    }

    /**
     * Live Q&A from data this page already loaded — answers what a
     * "{kategorija} akcijos" searcher asks, with this week's real numbers,
     * ahead of the category's own static admin FAQ. Phrased around the
     * category's name in quotes ("kategorijoje „Pieno produktai…“") so no
     * declension map is needed. Answers are rendered as HTML
     * (<x-faq-accordion>), so every dynamic string is escaped.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function buildLiveCategoryFaq(string $categoryName, array $storeComparison, int $totalOffers, array $topDiscounted): array
    {
        if ($totalOffers <= 0 || $storeComparison === []) {
            return [];
        }

        $name = '„' . e($categoryName) . '“';
        $storeCount = count($storeComparison);
        $offersPhrase = fn (int $n) => LithuanianPlural::formatCount($n) . ' ' . LithuanianPlural::offerWord($n);
        $faq = [];

        $top = collect($storeComparison)->take(3)
            ->map(fn (array $row) => e($row['store']) . ' (' . $offersPhrase($row['offers_count']) . ')')
            ->values()->all();
        $faq[] = [
            'question' => "Kurioje parduotuvėje daugiausia akcijų kategorijoje {$name}?",
            'answer' => 'Daugiausia pasiūlymų šiuo metu turi ' . array_shift($top)
                . ($top !== [] ? ', toliau ' . implode(' ir ', $top) : '') . '.',
        ];

        if (!empty($topDiscounted[0])) {
            $best = $topDiscounted[0];
            $faq[] = [
                'question' => "Kokia didžiausia nuolaida kategorijoje {$name} dabar?",
                'answer' => "Didžiausia šiuo metu galiojanti nuolaida — -{$best['discount_percent']}% prekei "
                    . e($best['name']) . ' (' . e($best['store']) . ').',
            ];
        }

        $faq[] = [
            'question' => "Kiek akcijų yra kategorijoje {$name}?",
            'answer' => 'Šiuo metu galioja ' . $offersPhrase($totalOffers) . ' '
                . $storeCount . ' ' . (LithuanianPlural::storeWord($storeCount) === 'parduotuvė' ? 'parduotuvėje' : 'parduotuvėse') . '.',
        ];

        return $faq;
    }

    /**
     * Same idea as buildLiveCategoryFaq(), for a store's own page. Store
     * names stay undeclined (nominative subject / "{Store} parduotuvėje"),
     * since most are foreign brand names with no safe Lithuanian case.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function buildLiveStoreFaq(Store $store, int $totalOffers, array $categories, int $leafletsCount): array
    {
        $storeName = e($store->name);
        $faq = [];

        if ($totalOffers > 0) {
            $topCategories = collect($categories)->take(3)
                ->map(fn (array $row) => e($row['name']) . ' (' . LithuanianPlural::formatCount($row['offers_count']) . ')')
                // "; " — some category names contain commas themselves.
                ->implode('; ');
            $faq[] = [
                'question' => "Kiek akcijų šiuo metu turi {$storeName}?",
                // "parduotuvėje galioja N pasiūlymai" (nominative) instead of
                // "turi N pasiūlymus", which would need an accusative form.
                'answer' => "{$storeName} parduotuvėje šiuo metu galioja " . LithuanianPlural::formatCount($totalOffers) . ' ' . LithuanianPlural::offerWord($totalOffers) . '.'
                    . ($topCategories !== '' ? " Daugiausia jų kategorijose: {$topCategories}." : ''),
            ];

            $maxRow = collect($categories)->sortByDesc('max_discount_percent')->first();
            if ($maxRow && ($maxRow['max_discount_percent'] ?? 0) > 0) {
                $faq[] = [
                    'question' => "Kokios didžiausios nuolaidos {$storeName} parduotuvėje?",
                    'answer' => "Didžiausia šiuo metu galiojanti nuolaida — -{$maxRow['max_discount_percent']}%, kategorijoje „" . e($maxRow['name']) . '“.',
                ];
            }
        }

        if ($leafletsCount > 0) {
            $leafletWord = LithuanianPlural::leafletWord($leafletsCount);
            $faq[] = [
                'question' => "Ar {$storeName} turi galiojantį leidinį?",
                'answer' => "Taip — šiuo metu galioja {$leafletsCount} {$leafletWord}. Visus rasite puslapyje "
                    . '<a href="/leidinys/' . e($store->slug) . '">' . $storeName . ' leidiniai</a>.',
            ];
        }

        return $faq;
    }

    private function buildCategoryFaq(Category $category): array
    {
        return $category->faq ?? [];
    }
}
