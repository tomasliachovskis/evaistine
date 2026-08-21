<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\FoodCategorySlugs;
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
            'intro' => $intro,
            'popular_this_week' => $this->mapPopularCategoriesForStore($store->slug, $topCategories),
            'popular_carousel_title' => 'Daugiausia sutaupoma šiandien',
            'popular_carousel_subtitle' => 'Pasirinkite kategoriją ir atraskite geriausias akcijas',
            'sections' => [
                'featured_category' => $this->getFeaturedFoodCategoryForStore($store),
                'top_categories' => $topCategories,
                'latest_leaflet' => $leaflets[0] ?? null,
                'leaflets' => $leaflets,
                'top_deals' => $this->getTopDealsForStore($store),
                'most_saved' => $this->getMostSavedForStore($store),
                'expiring_soon' => $this->getExpiringDealsForStore($store),
                'weekend_deals' => $this->getWeekendDealsForStore($store),
                'faq' => $this->buildStoreLeafletHubFaq($store, $this->getTopCategoriesForStore($store, false)),
                'other_stores' => $otherStores,
            ],
        ];
    }

    public function buildForStoreListing(Store $store): array
    {
        return [
            'type' => 'store',
            'store_slug' => $store->slug,
            'store_name' => $store->name,
            'intro' => [],
            'popular_carousel_title' => 'TOP pasiūlymai pagal kategorijas',
            'sections' => [
                'top_categories' => $this->getTopCategoriesForStore($store),
                'available_categories' => $this->getAllCategoriesWithCountsForStore($store),
                'faq' => $store->faq ?? [],
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
        $maxDiscount = $this->getMaxDiscountForCategory($category);
        $totalOffers = $this->getDiscountCountForCategory($category);

        return [
            'type' => 'category',
            'category_slug' => $categorySlug,
            'category_name' => $categoryName,
            'keyword_pages' => $this->keywordPageService->listPublishedPagesForCategory($categorySlug),
            'intro' => [
                'description' => 'Palyginkite ' . mb_strtolower($categoryName) . ' akcijas visuose pagrindiniuose prekybos tinkluose. Matysite didžiausias nuolaidas ir aktyvių pasiūlymų skaičių kiekvienoje parduotuvėje.',
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'quick_stats' => [
                    ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers],
                    ['label' => 'Didžiausia nuolaida', 'value' => '-' . (int) $maxDiscount . '%'],
                    ['label' => 'Parduotuvių', 'value' => (string) count($storeComparison)],
                ],
                'discovery_chips' => [
                    ['label' => 'Žemiausios kainos šiandien', 'href' => "/akcijos/{$categorySlug}?order=price"],
                    ['label' => 'Didžiausios nuolaidos', 'href' => "/akcijos/{$categorySlug}?order=discount"],
                    ['label' => 'Vasaros derlius', 'href' => "/akcijos/{$categorySlug}"],
                    ['label' => 'Ekologiški produktai', 'href' => "/akcijos/{$categorySlug}"],
                ],
            ],
            'popular_carousel_title' => 'Kur šiuo metu daugiausia akcijų',
            'popular_this_week' => array_map(function ($row) use ($categorySlug) {
                return [
                    'label' => $row['store'],
                    'href' => $row['href'],
                    'discounts_count' => $row['offers_count'],
                    'subtitle' => 'nuo -' . $row['max_discount_percent'] . '%',
                    'image_slug' => $categorySlug,
                ];
            }, $storeComparison),
            'sections' => [
                'category_stats' => $stats,
                'top_brands' => $this->getTopBrandsForCategory($category),
                'weekly_deals' => $this->getWeeklyDealsForCategory($category, $categorySlug),
                'seasonal_modules' => $this->getSeasonalModules($categorySlug),
                'faq' => $this->buildCategoryFaq($category),
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
            'intro' => [
                'description' => "Visos {$storeName} " . mb_strtolower($categoryName) . ' akcijos vienoje vietoje. Peržiūrėkite savaitės pasiūlymus ir sutaupykite apsipirkdami sezoninius produktus.',
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
            ],
            'popular_this_week' => [],
            'popular_carousel_title' => 'Populiaru šią savaitę',
            'sections' => [
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

        return [
            'description' => "{$headline} {$storeName} akcijų {$leafletNoun}{$locationSuffix}. Peržiūrėkite šios savaitės nuolaidas, specialius pasiūlymus ir populiariausias akcijas.",
            'valid_from' => $validity['valid_from'],
            'valid_to' => $validity['valid_to'],
        ];
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
            'leaflets_count' => count($leaflets),
            'leaflets' => $leaflets,
            'top_categories' => $this->getTopCategoriesForStore($store),
            'flyer' => [
                'title' => $title,
                'slug' => $flyer->slug,
                'image_url' => $flyer->image_url ?? '',
                'pdf_url' => $flyer->pdf_url && $flyer->pdf_url !== '#' ? $flyer->pdf_url : null,
                'valid_from' => $flyer->valid_from?->format('Y-m-d') ?? '',
                'valid_to' => $flyer->valid_to?->format('Y-m-d') ?? '',
                'view_url' => "/leidinys/{$store->slug}/{$flyer->slug}",
            ],
            'pages' => $flyer->pages->map(fn ($page) => [
                'page_number' => $page->page_number,
                'image_url' => $page->image_url,
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
                DB::raw('COUNT(discounts.id) as offers_count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('offers_count')
            ->get();

        return $rows->map(function ($row) {
            return [
                'name' => trim($row->name),
                'slug' => $row->slug,
                'offers_count' => (int) $row->offers_count,
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
                'href' => "/akcijos/{$s->slug}",
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
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent'),
                DB::raw('ROUND(AVG(discounts.discount_percent), 0) as avg_discount_percent')
            )
            ->groupBy('stores.id', 'stores.name', 'stores.slug')
            ->orderByDesc('offers_count')
            ->limit(8)
            ->get();

        return $rows->map(function ($row) use ($category) {
            return [
                'store' => $row->store,
                'href' => "/akcijos/{$row->store_slug}/{$category->slug}",
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
            ->with(['product', 'store'])
            ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->limit(5)
            ->get()
            ->map(fn (Discount $d) => [
                'name' => $d->product->name,
                'store' => $d->store->name,
                'discount_percent' => (int) round($d->discount_percent),
            ])
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
        $now = Carbon::now();
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
        $now = Carbon::now();
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
            ];
        }

        return [
            'accusative' => 'leidinį',
            'nominative' => 'leidinys',
            'nominative_plural' => 'leidiniai',
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

    private function buildStoreLeafletHubFaq(Store $store, array $topCategories): array
    {
        $storeName = $store->name;
        $storeSlug = $store->slug;
        $words = $this->getStoreLeafletWords($storeSlug);
        $listingUrl = $this->siteUrl("/akcijos/{$storeSlug}");
        $hubLink = $this->faqLink($this->siteUrl("/leidinys/{$storeSlug}"), "{$storeName} leidinio puslapyje");
        $listingLink = $this->faqLink($listingUrl, "{$storeName} akcijų sąraše");
        $weeklyAkcijosLink = $this->faqLink($listingUrl, "šią savaitę galiojančias {$storeName} akcijas");
        $nuolaidosLink = $this->faqLink($listingUrl, "aktualias {$storeName} nuolaidas");
        $categoryLink = $this->faqCategoryLink($store, null, $topCategories, $listingLink);

        return [
            [
                'question' => "Kur rasti naują {$storeName} leidinį?",
                'answer' => "Naujausią {$storeName} akcijų {$words['nominative']} rasite {$hubLink} – viršuje matote leidinio viršelį ir galiojimo datas. Visas akcijas su kainomis – {$listingLink}.",
            ],
            [
                'question' => "Kokios {$storeName} akcijos galioja šią savaitę?",
                'answer' => "{$storeName} savaitės akcijos paprastai galioja nuo pirmadienio iki sekmadienio. {$weeklyAkcijosLink} rasite su galiojimo datomis ir kainomis.",
            ],
            [
                'question' => "Ar galima atsisiųsti {$storeName} {$words['accusative']} PDF formatu?",
                'answer' => "Kai turime PDF nuorodą, ją rasite {$hubLink} prie leidinio viršelio – galite atsisiųsti ir peržiūrėti be interneto.",
            ],
            [
                'question' => "Kaip dažnai atnaujinamos {$storeName} nuolaidos?",
                'answer' => "{$storeName} akcijos ir nuolaidos SuperAkcijos.lt atnaujinamos kasdien. {$nuolaidosLink} galite peržiūrėti bet kuriuo metu, o populiariausias kategorijas – {$categoryLink}.",
            ],
        ];
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

    private function buildCategoryFaq(Category $category): array
    {
        return $category->faq ?? [];
    }
}
