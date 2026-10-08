<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\ContentFreshness;
use App\Support\FlyerStorage;
use App\Support\LithuanianDate;
use App\Support\LithuanianPlural;
use App\Support\PharmacyName;
use App\Support\StoreListPriority;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ListingPageMetaService
{
    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    // How recently a leaflet must have been uploaded to count as a store's
    // "newest" at the top of /leidiniai.
    private const FRESH_LEAFLET_DAYS = 5;

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
        // The leaflet hub links only to pharmacies that have a leaflet now;
        // the others' /leidinys/{slug} just redirects to their offers.
        $otherStores = $this->getOtherStores($store->id, withLeaflets: true);
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
                'label' => 'Sutaupymai su eVaistine.lt šią savaitę',
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
                'top_categories' => $topCategories,
                'available_categories' => $this->getAllCategoriesWithCountsForStore($store),
                'latest_leaflet' => $leaflets[0] ?? null,
                'leaflets' => $leaflets,
                'top_deals' => $this->getTopDealsForStore($store),
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
                // Genitive ("nereceptinių vaistų akcijas"): dropping the
                // nominative name into the sentence read as "Sekite
                // nereceptiniai vaistai kainas".
                'description' => $this->pickVariant($categorySlug, [
                    'Palyginkite ' . ($categoryGenitive = \App\Http\Controllers\Api\ProductController::categoryGenitiveLabel($categoryName)) . ' akcijas visose pagrindinėse vaistinėse. Matysite didžiausias nuolaidas ir aktyvių pasiūlymų skaičių kiekvienoje vaistinėje.',
                    'Visų didžiųjų vaistinių ' . $categoryGenitive . ' akcijos vienoje vietoje — palyginkite kainas ir rinkitės pigiausią pasiūlymą.',
                    'Sekite ' . $categoryGenitive . ' kainas ir nuolaidas kiekvienoje vaistinėje — čia matysite, kur šiuo metu didžiausios akcijos ir kiek galima sutaupyti.',
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
                    ['label' => 'Vaistinių', 'value' => (string) count($storeComparison), 'icon' => 'store', 'pill' => count($storeComparison) . ' ' . LithuanianPlural::storeWord(count($storeComparison))],
                ])),
                'freshness_label' => $this->freshnessLabel(ContentFreshness::forCategory($category->id)),
            ],
            'sections' => [
                'category_stats' => $stats,
                'top_brands' => $this->getTopBrandsForCategory($category),
                'weekly_deals' => $this->getWeeklyDealsForCategory($category, $categorySlug),
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
                // Genitive pharmacy and category ("Benu vaistinės nereceptinių
                // vaistų akcijos"), not nominatives dropped in mid-sentence.
                'description' => $this->pickVariant($store->slug . '/' . $category->slug, [
                    'Visos ' . ($storeGenitive = PharmacyName::phrase($storeName, 'genitive')) . ' ' . ($categoryGenitive = \App\Http\Controllers\Api\ProductController::categoryGenitiveLabel($categoryName)) . ' akcijos vienoje vietoje. Peržiūrėkite dabar galiojančius pasiūlymus ir palyginkite kainas su kitomis vaistinėmis.',
                    mb_ucfirst($storeGenitive) . ' ' . $categoryGenitive . ' akcijos — palyginkite kainas ir raskite geriausius pasiūlymus vienoje vietoje.',
                    'Naujausios ' . $storeGenitive . ' ' . $categoryGenitive . ' nuolaidos surinktos į vieną sąrašą — patikrinkite, ar ta pati prekė kitoje vaistinėje nekainuoja mažiau.',
                ]),
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'freshness_label' => $this->freshnessLabel(ContentFreshness::forStoreAndCategory($store->id, $category->id)),
            ],
            'sections' => [
                'top_categories' => $this->getTopCategoriesForStore($store),
                'faq' => $this->buildStoreFaq($store, $category, $this->getTopCategoriesForStore($store)),
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
        // /leidiniai index page. ordered() (per-store sort_order, then
        // valid_from desc) only decides which current leaflet per store gets
        // the "new" status below; the final order is set at the end.
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
                $item['flyer_id'] = $flyer->id;
                $item['uploaded_at'] = $flyer->created_at?->toIso8601String() ?? '';

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
        });

        // Order: the newest leaflet uploaded in the last 5 days of each main
        // chain (Maxima, Lidl, Iki, Rimi, Norfa, in that order), then the
        // newest recent one of every other store, then everything else by
        // upload date. Expired leaflets go last. uploaded_at is ISO 8601,
        // so string comparison sorts by time.
        $byUploadDesc = fn ($items) => $items->sortByDesc('uploaded_at')->values();

        [$expired, $current] = $leaflets->partition(fn (array $leaflet) => $leaflet['status'] === 'expired');
        $current = $byUploadDesc($current);

        $freshSince = now()->subDays(self::FRESH_LEAFLET_DAYS)->toIso8601String();
        $newestFreshByStore = $current
            ->filter(fn (array $leaflet) => $leaflet['uploaded_at'] >= $freshSince)
            ->groupBy('store_slug')
            ->map(fn ($group) => $group->first());

        $mainChains = array_slice(\App\Support\StoreListPriority::mainSlugs(), 0, 5);
        $mainNewest = collect($mainChains)
            ->map(fn (string $slug) => $newestFreshByStore->get($slug))
            ->filter();
        $otherNewest = $byUploadDesc($newestFreshByStore->except($mainChains));

        $picked = $mainNewest->concat($otherNewest);
        $pickedIds = $picked->pluck('flyer_id')->all();
        $rest = $current->reject(fn (array $leaflet) => in_array($leaflet['flyer_id'], $pickedIds, true));

        return $picked
            ->concat($rest)
            ->concat($byUploadDesc($expired))
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

    private function getTopCategoriesForStore(Store $store): array
    {
        $today = Carbon::today()->toDateString();

        $query = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereNull('categories.parent_id');

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
                'href' => "/{$store->slug}/{$row->slug}",
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

    private function getOtherStores(int $excludeStoreId, bool $withLeaflets = false): array
    {
        return Store::query()
            ->where('id', '!=', $excludeStoreId)
            ->when($withLeaflets, fn ($q) => $q->whereHas('flyers', fn ($f) => $f->active()->ready()->currentlyValid()))
            ->withCount('discounts')
            ->get()
            // Leaflet-only pharmacies (no e-shop offers) still belong in the
            // leaflet hub's list.
            ->filter(fn (Store $s) => $withLeaflets || $s->discounts_count > 0)
            ->sortByDesc('discounts_count')
            ->take($withLeaflets ? 10 : 5)
            ->map(fn (Store $s) => [
                'name' => $s->name,
                'name_genitive' => PharmacyName::phrase($s->name, 'genitive'),
                'slug' => $s->slug,
                'href' => "/leidinys/{$s->slug}",
                // Where a listing page should send a visitor: the store's
                // akcijos page when it has one, else its leaflets (the
                // akcijos URL just 301s there).
                'listing_href' => $s->showsDiscountsPage() ? "/{$s->slug}" : "/leidinys/{$s->slug}",
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
            // under-reported "N vaistinių". Display slices its own top 8.
            ->get();

        return $rows->map(function ($row) use ($category) {
            return [
                'store' => $row->store,
                'store_slug' => $row->store_slug,
                // store+category page only exists with show_discounts_page;
                // otherwise that URL 301s to the leaflets page, so link
                // there directly.
                'href' => $row->show_discounts_page ? "/{$row->store_slug}/{$category->slug}" : "/leidinys/{$row->store_slug}",
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
            'summary' => "Šiuo metu eVaistine.lt stebi {$totalOffers} aktyvių " . \App\Http\Controllers\Api\ProductController::categoryGenitiveLabel($categoryName) . " akcijų, kurias siūlo " . $storeCount . ' ' . LithuanianPlural::storeWord($storeCount) . ". Didžiausia aptikta nuolaida siekia {$maxDiscount} %, o vidutinis sutaupymas šioje kategorijoje – apie {$avgDiscount} %.",
            'highlights' => [
                ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers],
                ['label' => 'Vidutinė nuolaida', 'value' => $avgDiscount . ' %'],
                ['label' => 'Didžiausia nuolaida', 'value' => $maxDiscount . ' %'],
                ['label' => 'Vaistinių tinklai', 'value' => (string) $storeCount],
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
                    'href' => '/' . $formatted['product']['full_slug'],
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
            'href' => "/{$category->slug}",
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
                'href' => "/{$categorySlug}",
                'discount_percent' => (int) round($d->discount_percent),
                'store_name' => $d->store->name,
                'image_slug' => $categorySlug,
            ])
            ->values()
            ->all();
    }

    private function mapStoreDealRow(Discount $d, Store $store): array
    {
        $categorySlug = $d->product->category?->slug;
        $href = $categorySlug
            ? "/p/{$d->product->slug}"
            : "/{$store->slug}";

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
        $storeGenitive = PharmacyName::phrase($storeName, 'genitive');
        $leafletPhrase = $multipleLeaflets
            ? "naujausius {$storeGenitive} akcijų {$leafletNoun}"
            : "naujausią {$storeGenitive} akcijų {$leafletNoun}";

        $intro = "eVaistine.lt – patogi vieta, kur {$leafletPhrase}, didžiausias nuolaidas ir populiariausius pasiūlymus rasite be papildomų paieškų.";
        $detail = "Akcijų sąrašą atnaujiname pagal galiojantį leidinį, todėl čia matote, kas šiuo metu galioja vaistinėse. Jei domina naujas leidinys ar norite greitai palyginti nuolaidas – viršuje peržiūrėkite leidinių viršelius, o žemiau – atrinktas didžiausias nuolaidas su kainomis.";

        return "{$intro}\n\n{$detail}";
    }

    // Multiple headed content sections for the leidinys hub (/leidinys/{store}).
    // Intent here is strictly the LEIDINYS (catalog: cadence, format, pages,
    // PDF) — NOT akcijos/nuolaidos (discounts), which is the separate
    // /{store} hub's job. Copy must stay about the catalog itself —
    // how often it's published, what kinds exist, how to read/download it —
    // not savings/loyalty-card advice, which belongs on the other page.
    // pickVariant()-templated so each pharmacy's page stays distinct without
    // bespoke copy per chain. Pharmacy leaflets are usually monthly, so the
    // copy never promises a weekly cadence.
    private function buildStoreHubContent(Store $store, int $activeLeafletCount): array
    {
        $storeName = $store->name;
        $storeSlug = $store->slug;
        $storeGenitive = PharmacyName::phrase($storeName, 'genitive');
        $words = $this->getStoreLeafletWords($storeSlug);
        $leafletNoun = $activeLeafletCount > 1 ? $words['nominative_plural'] : $words['nominative'];
        $leafletNounSingular = $words['nominative'];
        $leafletNounAccusative = $words['accusative'];
        $leafletNounGenitive = $words['genitive'];

        $about = [
            $this->pickVariant($storeSlug . '/about/1', [
                "{$storeGenitive} {$leafletNounSingular} – tai reguliariai atnaujinamas akcijų katalogas, kuriame vaistinė pristato savo pasiūlymus su galiojimo datomis.",
                "{$storeGenitive} {$leafletNounSingular} – tai skaitmeninė šios vaistinės akcijų leidinio versija, kurią čia atnaujiname kiekvieną kartą, kai pasirodo naujas numeris.",
                "Šiame puslapyje rasite {$storeGenitive} akcijų {$leafletNoun} – tikrą vaistinės katalogą, o ne vien atrinktų prekių sąrašą.",
            ]),
            $this->pickVariant($storeSlug . '/about/2', [
                "Naujas {$storeGenitive} {$leafletNounSingular} skelbiamas reguliariai, o jame visada nurodyta, nuo kada iki kada jis galioja.",
                "Vaistinių leidiniai dažniausiai galioja kelias savaites ar mėnesį – ankstesni {$storeGenitive} numeriai lieka pasiekiami puslapio apačioje, pažymėti kaip pasibaigę.",
                "Kiekvienas {$storeGenitive} {$leafletNounSingular} turi savo galiojimo laikotarpį – taip lengva atskirti, kuris leidinys aktualus šiuo metu.",
            ]),
            $this->pickVariant($storeSlug . '/about/3', [
                "Kartais vaistinė vienu metu skelbia kelis leidinius (pvz. bendrą akcijų ir atskirą kosmetikos ar sezoninį) – visus aktyvius {$leafletNoun} rasite kartu šiame puslapyje.",
                "{$storeGenitive} {$leafletNounSingular} apima įvairias vaistinės prekes – nuo vitaminų ir maisto papildų iki kosmetikos, higienos ir prekių mamai ir vaikui.",
                "eVaistine.lt seka {$storeGenitive} skelbiamus leidinius ir kiekvieną naują numerį pridedame čia, kai tik jis pasirodo.",
            ]),
        ];

        $format = [
            $this->pickVariant($storeSlug . '/format/1', [
                "{$storeGenitive} {$leafletNounSingular} paprastai apima keliolika puslapių, suskirstytų pagal kategorijas – vitaminai, nereceptiniai vaistai, veido ir kūno priežiūra, higiena.",
                "Kiekviename {$storeGenitive} {$leafletNounGenitive} numeryje prekės išdėstytos taip pat, kaip oficialiame kataloge – puslapis po puslapio, pagal kategorijas.",
            ]),
            $this->pickVariant($storeSlug . '/format/2', [
                "Kai turime PDF nuorodą, {$storeGenitive} {$leafletNounAccusative} galima atsisiųsti ir peržiūrėti be interneto ryšio – nuoroda pateikiama prie leidinio viršelio.",
                "Jei prie leidinio yra PDF nuoroda, ją rasite šalia viršelio – patogu atsisiųsti ir peržiūrėti vėliau.",
            ]),
            $this->pickVariant($storeSlug . '/format/3', [
                "Skirtingai nei akcijų sąrašas, kuriame prekės surūšiuotos pagal nuolaidos dydį, {$leafletNounSingular} atkartoja tikrąją puslapių tvarką – patogu naršyti taip, tarsi turėtumėte popierinį leidinį rankose.",
                "{$storeGenitive} leidiniai archyvuojami šiame puslapyje – pasibaigę numeriai matomi atskirai, kad būtų aišku, kuris leidinys šiuo metu galioja.",
            ]),
        ];

        $tips = [
            $this->pickVariant($storeSlug . '/tips/1', [
                "Leidinyje nurodytas galiojimo laikotarpis – patikrinkite jį, kad įsitikintumėte, jog žiūrite aktualų, o ne jau pasibaigusį {$leafletNounAccusative}.",
                "Pirmiausia patikrinkite leidinio galiojimo datas – jos padės greitai suprasti, ar leidinys dar aktualus.",
            ]),
            $this->pickVariant($storeSlug . '/tips/2', [
                "Kai galioja keli {$storeGenitive} leidiniai vienu metu, peržiūrėkite visus aktyvius numerius šiame puslapyje, kad nepraleistumėte jus dominančios kategorijos.",
                "Kai galioja keli {$storeGenitive} leidiniai vienu metu, patogu peržiūrėti kiekvieną atskirai – taip lengviau rasti, kuriame numeryje yra jus dominanti kategorija.",
            ]),
            $this->pickVariant($storeSlug . '/tips/3', [
                "Tikslias kainas ir nuolaidų dydžius rasite {$storeGenitive} akcijų sąraše – šis puslapis skirtas peržiūrėti visą pasiūlymą taip, kaip jis pateikiamas oficialiame leidinyje.",
                "Jei ieškate konkrečios prekės kainos, o ne viso leidinio, patogiau naudotis {$storeGenitive} akcijų sąrašu – ten prekės surūšiuotos pagal nuolaidos dydį.",
            ]),
        ];

        return [
            'about' => [
                'heading' => mb_ucfirst("{$storeGenitive} {$leafletNounSingular}"),
                'paragraphs' => array_map('mb_ucfirst', $about),
            ],
            'format' => [
                'heading' => mb_ucfirst("{$storeGenitive} {$leafletNounGenitive} turinys"),
                'paragraphs' => array_map('mb_ucfirst', $format),
            ],
            'tips' => [
                'heading' => "Kur rasti naują {$storeGenitive} {$leafletNounAccusative}",
                'paragraphs' => array_map('mb_ucfirst', $tips),
            ],
        ];
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
        $listingUrl = $this->siteUrl("/{$storeSlug}");
        $storeGenitive = PharmacyName::phrase($storeName, 'genitive');
        $hubLink = $this->faqLink($this->siteUrl("/leidinys/{$storeSlug}"), "{$storeGenitive} leidinio puslapyje");
        $listingLink = $this->faqLink($listingUrl, "{$storeGenitive} akcijų sąraše");
        $currentAkcijosLink = $this->faqLink($listingUrl, "dabar galiojančias {$storeGenitive} akcijas");
        $categoryLink = $this->faqCategoryLink($store, $currentCategory, $topCategories, $this->faqLink($listingUrl, "visas {$storeGenitive} akcijas"));

        return [
            [
                'question' => "Kur rasti naują {$storeGenitive} {$words['accusative']}?",
                'answer' => "Naujausią {$storeGenitive} akcijų {$words['accusative']} rasite {$hubLink} – viršuje matote leidinį, PDF ir geriausius pasiūlymus. Visas akcijas rasite {$listingLink}.",
            ],
            [
                'question' => "Kiek laiko galioja {$storeGenitive} akcijos?",
                'answer' => "Vaistinių akcijos dažniausiai galioja kelias savaites ar visą mėnesį, o kai kurios – tik kelias dienas. Tikslias datas ir kainas matote prie kiekvienos prekės, peržiūrėję {$currentAkcijosLink}.",
            ],
            [
                'question' => "Kaip rasti {$storeGenitive} akcijas pagal kategoriją?",
                'answer' => "Visos akcijos surinktos {$listingLink}. Jei domina konkreti prekių grupė, peržiūrėkite {$categoryLink}.",
            ],
            [
                'question' => "Kaip dažnai atnaujinamos {$storeGenitive} akcijos?",
                'answer' => "Kainas ir akcijas tikriname kasdien, todėl sąraše matote, kas galioja šiuo metu. Naujas {$words['nominative']} pridedamas, kai tik vaistinė jį paskelbia.",
            ],
            [
                'question' => "Ar {$storeGenitive} akcijos galioja visose " . PharmacyName::phrase($storeName, 'locative_plural') . '?',
                'answer' => "Dažniausiai taip, tačiau kai kurios akcijos galioja tik e. vaistinėje arba tik fizinėse vaistinėse. Sąlygas visada nurodo pati vaistinė prie pasiūlymo ar leidinyje.",
            ],
            [
                'question' => "Ar galima atsisiųsti {$storeGenitive} {$words['accusative']} PDF formatu?",
                'answer' => "Jei turime PDF nuorodą, ją rasite {$hubLink} prie {$words['genitive']} viršelio.",
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
            $categoryGenitive = \App\Http\Controllers\Api\ProductController::categoryGenitiveLabel(trim($preferredCategory->name));

            return $this->faqLink(
                $this->siteUrl("/{$store->slug}/{$preferredCategory->slug}"),
                "{$categoryGenitive} akcijas " . PharmacyName::phrase($store->name, 'locative')
            );
        }

        $topCategory = $topCategories[0] ?? null;

        if (!$topCategory || empty($topCategory['href']) || empty($topCategory['slug'])) {
            return $listingLinkFallback;
        }

        $categoryGenitive = \App\Http\Controllers\Api\ProductController::categoryGenitiveLabel($topCategory['name']);

        return $this->faqLink(
            $this->siteUrl("/{$store->slug}/{$topCategory['slug']}"),
            "{$categoryGenitive} akcijas " . PharmacyName::phrase($store->name, 'locative')
        );
    }

    private function siteUrl(string $path): string
    {
        return 'https://evaistine.lt' . $path;
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
            'question' => "Kurioje vaistinėje daugiausia akcijų kategorijoje {$name}?",
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
                . $storeCount . ' ' . (LithuanianPlural::storeWord($storeCount) === 'vaistinė' ? 'vaistinėje' : 'vaistinėse') . '.',
        ];

        return $faq;
    }

    /**
     * Same idea as buildLiveCategoryFaq(), for a store's own page. Store
     * names stay undeclined (nominative subject / "{Store} vaistinėje"),
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
                // "vaistinėje galioja N pasiūlymai" (nominative) instead of
                // "turi N pasiūlymus", which would need an accusative form.
                'answer' => PharmacyName::phrase($storeName, 'locative') . " šiuo metu galioja " . LithuanianPlural::formatCount($totalOffers) . ' ' . LithuanianPlural::offerWord($totalOffers) . '.'
                    . ($topCategories !== '' ? " Daugiausia jų kategorijose: {$topCategories}." : ''),
            ];

            $maxRow = collect($categories)->sortByDesc('max_discount_percent')->first();
            if ($maxRow && ($maxRow['max_discount_percent'] ?? 0) > 0) {
                $faq[] = [
                    'question' => 'Kokios didžiausios nuolaidos ' . PharmacyName::phrase($storeName, 'locative') . '?',
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
