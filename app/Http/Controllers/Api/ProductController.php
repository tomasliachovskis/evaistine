<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\SearchResult;
use App\Models\StoreCategoryDescription;
use App\Services\DiscountResponseFormatter;
use App\Services\HomeDealPoolService;
use App\Services\HomePageMetaService;
use App\Services\HomePageSectionsService;
use App\Services\ListingPageMetaService;
use App\Services\MeilisearchService;
use App\Services\PageFreshnessService;
use App\Services\StoresPageMetaService;
use App\Support\CacheVersion;
use App\Support\LithuanianDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

    // Offers listed as text on a single flyer page (see getStoreLeaflet()).
    private const FLYER_OFFERS_LIMIT = 400;

    private const HUB_FLYER_OFFERS_LIMIT = 24;

    protected $formatter;

    protected $meilisearchService;

    protected $listingPageMetaService;

    protected $storesPageMetaService;

    protected $homePageMetaService;

    protected $homePageSectionsService;

    protected $pageFreshnessService;

    protected $homeDealPoolService;

    public function __construct(
        DiscountResponseFormatter $formatter,
        MeilisearchService $meilisearchService,
        ListingPageMetaService $listingPageMetaService,
        StoresPageMetaService $storesPageMetaService,
        HomePageMetaService $homePageMetaService,
        HomePageSectionsService $homePageSectionsService,
        PageFreshnessService $pageFreshnessService,
        HomeDealPoolService $homeDealPoolService
    ) {
        $this->formatter = $formatter;
        $this->meilisearchService = $meilisearchService;
        $this->listingPageMetaService = $listingPageMetaService;
        $this->storesPageMetaService = $storesPageMetaService;
        $this->homePageMetaService = $homePageMetaService;
        $this->homePageSectionsService = $homePageSectionsService;
        $this->pageFreshnessService = $pageFreshnessService;
        $this->homeDealPoolService = $homeDealPoolService;
    }

    /**
     * All three of these now read the persisted curated_deals table instead
     * of Cache::remember() — see App\Services\DealPoolRefresher, which keeps
     * these rows up to date as discounts change, scoped to whichever store(s)
     * actually changed rather than recomputing for every store on every
     * discounts:process batch.
     */
    public function getBestDiscountsByCategory()
    {
        return response()->json($this->buildBestByCategorySectionsFromPool(null, 'global_category'));
    }

    public function getBestDiscountsByCategoryForStore($storeSlug)
    {
        $store = \App\Models\Store::where('slug', $storeSlug)->first();

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        return response()->json($this->buildBestByCategorySectionsFromPool($store->id, 'store_category'));
    }

    /**
     * Flat, cross-category "Geriausi pasiūlymai" pool for a single store's
     * /{store} and /leidinys/{store} pages — shown as one curated
     * strip above the per-category carousels.
     */
    public function getBestOffersForStore($storeSlug)
    {
        $store = \App\Models\Store::where('slug', $storeSlug)->first();

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $discounts = $this->discountsForScope($store->id, 'store_top_offers');

        return response()->json($this->formatter->formatList($discounts));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string,slug:string,discounts:array}>
     */
    private function buildBestByCategorySectionsFromPool(?int $storeId, string $scope)
    {
        // Ordered by id (not category_id) to preserve DealPoolRefresher's
        // config('categories.roots') display order: rows for a full
        // refresh are always bulk-inserted in one pass, category-by-category
        // in that order, so ascending id reflects it — category_id ASC would
        // silently re-sort sections into numeric category-id order instead.
        $rows = \App\Models\CuratedDeal::query()
            ->where('store_id', $storeId)
            ->where('scope', $scope)
            ->with(['category', 'discount.product.category', 'discount.product.discounts.store', 'discount.product.discountHistories.store', 'discount.store'])
            ->orderBy('id')
            ->get();

        return $rows->groupBy('category_id')
            ->map(function ($categoryRows) {
                $category = $categoryRows->first()->category;
                $discounts = $categoryRows->pluck('discount')->filter()->values();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'discounts' => $this->formatter->formatList($discounts),
                ];
            })
            ->filter(fn (array $section) => count($section['discounts']) > 0)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Discount>
     */
    private function discountsForScope(?int $storeId, string $scope)
    {
        return \App\Models\CuratedDeal::query()
            ->where('store_id', $storeId)
            ->where('scope', $scope)
            ->with(['discount.product.category', 'discount.product.discounts.store', 'discount.product.discountHistories.store', 'discount.store'])
            ->orderBy('position')
            ->get()
            ->pluck('discount')
            ->filter()
            ->values();
    }

    public function getDiscounts($storeOrCategory, $category = null)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateDiscountsCacheKey(
            $storeOrCategory,
            $category,
            $this->normalizeFiltersForCacheKey($filters, $storeOrCategory, $category)
        );

        return Cache::remember($cacheKey, 3600, function () use ($storeOrCategory, $category, $filters) {
            if ($category) {
                return $this->getDiscountsByStoreAndCategory($storeOrCategory, $category, $filters);
            }

            return $this->getDiscountsByStoreOrCategory($storeOrCategory, $filters);
        });
    }

    public function getAllDiscounts()
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateAllDiscountsCacheKey($this->normalizeFiltersForCacheKey($filters));

        return Cache::remember($cacheKey, 3600, function () use ($filters) {
            $query = Discount::with(['product', 'store']);
            $discounts = $this->buildDiscountQuery($query, $filters)->paginate(self::PER_PAGE);

            return response()->json([
                'data' => $this->formatter->format($discounts),
                'breadcrumbs' => $this->generateBreadcrumbs('all_discounts'),
                'seo' => $this->generateSeoData('all_discounts'),
            ]);
        });
    }

    private function getDiscountsByStoreOrCategory($storeOrCategory, $filters)
    {
        $store = \App\Models\Store::where('slug', $storeOrCategory)->first();
        $category = \App\Models\Category::where('slug', $storeOrCategory)->first();

        if ($store) {
            $query = Discount::where('store_id', $store->id);
            $entity = $store;
            $entityType = 'store';
        } elseif ($category) {
            $query = Discount::whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });
            $entity = $category;
            $entityType = 'category';

            // Browsing a category (no store filter) shows one card per
            // product, not one per store — a product discounted at both
            // Maxima and Rimi was appearing as two separate grid cards.
            // Rank each product's discounts by price and keep only the
            // cheapest; the deal card's own store logos already show every
            // store it's available at (DiscountResponseFormatter::offers),
            // so nothing is hidden, just no longer duplicated. Products
            // belong to exactly one category, so ranking against the full
            // discounts table (not re-filtered by category) is still
            // correctly scoped. Skipped once a store filter narrows the
            // page to one store, where a product can't appear twice anyway.
            if (empty($filters['store'])) {
                $rankedDiscounts = DB::table('discounts')
                    ->select('id')
                    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY discounted_price ASC, id ASC) as rn')
                    ->when($filters['card'], fn ($q) => $q->where('card', true))
                    ->when($filters['plus'], fn ($q) => $q->where('condition', '1+1'));

                $query->select('discounts.*')
                    ->joinSub($rankedDiscounts, 'ranked_discounts', function ($join) {
                        $join->on('discounts.id', '=', 'ranked_discounts.id');
                    })
                    ->where('ranked_discounts.rn', 1);
            }
        } else {
            return response()->json(['error' => 'Store or category not found'], 404);
        }

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(self::PER_PAGE);

        $payload = [
            'data' => $this->formatter->format($discounts),
            'breadcrumbs' => $this->generateBreadcrumbs($entityType, $entity),
            'seo' => $this->generateSeoData($entityType, $entity),
        ];

        $payload = $this->appendListingMeta($payload, $entityType, $entity, null, $filters);

        return response()->json($payload);
    }

    private function getDiscountsByStoreAndCategory($store, $category, $filters)
    {
        $store = \App\Models\Store::where('slug', $store)->firstOrFail();
        $category = \App\Models\Category::where('slug', $category)->firstOrFail();

        $query = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(self::PER_PAGE);

        $payload = [
            'data' => $this->formatter->format($discounts),
            'breadcrumbs' => $this->generateBreadcrumbs('store_category', $store, $category),
            'seo' => $this->generateSeoData('store_category', $store, $category),
        ];

        // Store genuinely has no current offers in this category — coverage
        // varies per store since categories are scraped independently. A
        // blank grid or noindex would throw away real SEO value for this
        // store+category keyword combination, so show the same category's
        // live offers from other stores instead of nothing.
        if ($discounts->total() === 0) {
            $fallbackQuery = Discount::whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })->where('store_id', '!=', $store->id);

            $fallbackDiscounts = $this->buildDiscountQuery($fallbackQuery, $filters)->paginate(self::PER_PAGE);

            $payload['fallback_other_stores'] = [
                'store_name' => $store->name,
                'category_name' => $category->name,
                'data' => $this->formatter->format($fallbackDiscounts),
            ];
        }

        $payload = $this->appendListingMeta($payload, 'store_category', $store, $category, $filters);

        return response()->json($payload);
    }

    private function appendListingMeta(array $payload, string $entityType, $entity, $secondaryEntity, array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);

        if ($page !== 1) {
            return $payload;
        }

        if ($entityType === 'category') {
            $payload['listing_meta'] = $this->listingPageMetaService->buildForCategory($entity);
        } elseif ($entityType === 'store') {
            $payload['listing_meta'] = $this->listingPageMetaService->buildForStoreListing($entity);
        } elseif ($entityType === 'store_category' && $secondaryEntity) {
            $payload['listing_meta'] = $this->listingPageMetaService->buildForStoreCategory($entity, $secondaryEntity);
        }

        return $payload;
    }

    private function getFilters()
    {
        return [
            'order' => request()->get('order', 'popular'),
            'card' => request()->get('card'),
            'plus' => request()->get('plus'),
            'page' => request()->get('page'),
            'store' => request()->get('store'),
            'category' => request()->get('category'),
        ];
    }

    private function normalizeFiltersForCacheKey(array $filters, ?string $storeOrCategory = null, ?string $category = null): array
    {
        $normalized = $filters;

        if (empty($normalized['page']) || (int) $normalized['page'] === 1) {
            unset($normalized['page']);
        }

        if (($normalized['order'] ?? 'popular') === 'popular') {
            unset($normalized['order']);
        }

        if (! empty($normalized['store'])) {
            $storeSlugs = array_values(array_filter(array_map('trim', explode(',', (string) $normalized['store']))));

            if ($category !== null && count($storeSlugs) === 1 && $storeSlugs[0] === $storeOrCategory) {
                unset($normalized['store']);
            } elseif ($category === null && count($storeSlugs) === 1 && $storeSlugs[0] === $storeOrCategory && \App\Models\Store::where('slug', $storeOrCategory)->exists()) {
                unset($normalized['store']);
            }
        }

        if (! empty($normalized['category'])) {
            $categorySlugs = array_values(array_filter(array_map('trim', explode(',', (string) $normalized['category']))));
            $pathCategory = $category ?? $storeOrCategory;
            $isCategoryRoute = $category !== null || ! \App\Models\Store::where('slug', $storeOrCategory)->exists();

            if ($isCategoryRoute && count($categorySlugs) === 1 && $categorySlugs[0] === $pathCategory) {
                unset($normalized['category']);
            }
        }

        return array_filter($normalized, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function buildDiscountQuery($query, $filters, bool $applyOrder = true)
    {
        // product.discounts.store is eager-loaded here for the same reason as
        // HomeDealPoolService::baseQuery() — DiscountResponseFormatter::getProductDiscounts()
        // falls back to one query per product (to compute offer_count/min_price/offers)
        // when the relation isn't already loaded, so a 24-item listing page
        // fires ~24 extra queries without it.
        $query = $query->with(['product.category', 'product.discounts.store', 'store']);

        if ($filters['card']) {
            $query = $query->where('card', true);
        }

        if ($filters['plus']) {
            $query = $query->where('condition', '1+1');
        }

        if ($filters['store']) {
            $storeSlugs = explode(',', $filters['store']);
            $query = $query->whereHas('store', function ($storeQuery) use ($storeSlugs) {
                $storeQuery->whereIn('slug', $storeSlugs);
            });
        }

        if ($filters['category']) {
            $categorySlugs = array_values(array_filter(array_map('trim', explode(',', $filters['category']))));
            if (! empty($categorySlugs)) {
                $categoryIds = \App\Models\Category::whereIn('slug', $categorySlugs)->pluck('id')->all();
                if (empty($categoryIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('product', function ($q) use ($categoryIds) {
                        $q->whereIn('category_id', $categoryIds);
                    });
                }
            }
        }

        if (! $applyOrder) {
            return $query;
        }

        return $this->applySorting($query, $filters['order']);
    }

    private function orderDiscountsByMeilisearchIds($query, array $discountIds)
    {
        if (empty($discountIds)) {
            return $query;
        }

        $placeholders = implode(',', array_fill(0, count($discountIds), '?'));

        return $query->orderByRaw('FIELD(discounts.id, '.$placeholders.')', $discountIds);
    }

    private function hasExplicitOrder(): bool
    {
        if (! request()->has('order')) {
            return false;
        }

        return request()->get('order') !== 'popular';
    }

    private function applySorting($query, $order)
    {
        $query = $query->leftJoin('products', 'discounts.product_id', '=', 'products.id')
            ->select('discounts.*');

        switch ($order) {
            case 'price_min':
                return $query->orderBy('discounted_price', 'asc');
            case 'price_max':
                return $query->orderBy('discounted_price', 'desc');
            case 'price_discount_max':
                return $query->orderByRaw('(original_price - discounted_price) DESC');
            case 'price_discount_proc_max':
                return $query->orderByRaw('discount_percent DESC');
            case 'popular':
                return $query->orderByRaw('CASE WHEN products.category_id IN (' . implode(',', \App\Models\Category::popularIds() ?: [0]) . ') THEN 0 ELSE 1 END');
            default:
                return $query;
        }
    }

    public function getCategories()
    {
        $cacheKey = 'categories_'.CacheVersion::suffix(['discounts']);

        $categories = Cache::remember($cacheKey, 3600, function () {
            return \App\Models\Category::whereNull('parent_id')
                ->select('id', 'name', 'slug', 'description', 'hide')
                ->withCount([
                    'discounts' => function ($query) {
                        $query->select(\DB::raw('count(distinct discounts.id)'));
                    },
                ])->get();
        });

        return response()->json($categories);
    }

    public function getStores()
    {
        $cacheKey = 'stores_'.CacheVersion::suffix(['discounts', 'flyers']);

        $payload = Cache::remember($cacheKey, 3600, function () {
            $stores = \App\Models\Store::select('id', 'name', 'slug', 'show_discounts_page')
                ->withCount([
                    'discounts' => function ($query) {
                        $query->select(\DB::raw('count(distinct discounts.id)'));
                    },
                ])->get();

            return [
                'data' => $this->storesPageMetaService->formatStore($stores),
                'page_meta' => $this->storesPageMetaService->buildPageMeta($stores),
            ];
        });

        return response()->json($payload);
    }

    // Scoped sidebar variants of getCategories()/getStores(): production only
    // lists the categories a given store actually has discounts in (and vice
    // versa for a category's stores) rather than every category/store site-wide.
    public function getCategoriesForStore(string $storeSlug)
    {
        $store = \App\Models\Store::where('slug', $storeSlug)->first();

        if (! $store) {
            return response()->json([]);
        }

        $cacheKey = "categories_for_store_{$store->id}_".CacheVersion::suffix(['discounts']);

        $categories = Cache::remember($cacheKey, 3600, function () use ($store) {
            return Category::whereNull('parent_id')
                ->select('id', 'name', 'slug', 'description', 'hide')
                ->withCount([
                    'discounts' => function ($query) use ($store) {
                        $query->select(\DB::raw('count(distinct discounts.id)'))
                            ->where('discounts.store_id', $store->id);
                    },
                ])
                ->having('discounts_count', '>', 0)
                ->get();
        });

        return response()->json($categories);
    }

    public function getStoresForCategory(string $categorySlug)
    {
        $category = Category::where('slug', $categorySlug)->first();

        if (! $category) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "stores_for_category_{$category->id}_".CacheVersion::suffix(['discounts']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($category) {
            $stores = \App\Models\Store::select('id', 'name', 'slug', 'show_discounts_page')
                ->withCount([
                    'discounts' => function ($query) use ($category) {
                        $query->select(\DB::raw('count(distinct discounts.id)'))
                            ->whereHas('product', function ($productQuery) use ($category) {
                                $productQuery->where('category_id', $category->id);
                            });
                    },
                ])
                ->having('discounts_count', '>', 0)
                ->get();

            return ['data' => $this->storesPageMetaService->formatStore($stores)];
        });

        return response()->json($payload);
    }

    // Same scoping idea as getStoresForCategory() above, but for a keyword
    // page: only stores that actually have an active discount on one of the
    // page's mapped products, instead of every store site-wide (a keyword
    // page's "Vaistinė" filter previously listed pharmacies/cosmetics
    // chains alongside grocery stores for something like "duona").
    public function getStoresForKeyword(string $keywordSlug)
    {
        $page = \App\Models\KeywordPage::where('slug', $keywordSlug)->first();

        if (! $page) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "stores_for_keyword_{$page->id}_".CacheVersion::suffix(['discounts', 'keywords']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($page) {
            $productIds = \App\Models\KeywordPageProduct::where('keyword_page_id', $page->id)->pluck('product_id');

            $stores = \App\Models\Store::select('id', 'name', 'slug', 'show_discounts_page')
                ->withCount([
                    'discounts' => function ($query) use ($productIds) {
                        $query->select(\DB::raw('count(distinct discounts.id)'))
                            ->whereIn('product_id', $productIds);
                    },
                ])
                ->having('discounts_count', '>', 0)
                ->get();

            return ['data' => $this->storesPageMetaService->formatStore($stores)];
        });

        return response()->json($payload);
    }

    public function getStoreLocations($slug)
    {
        $store = \App\Models\Store::where('slug', $slug)->first();

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $cacheKey = "store_locations_v2_{$store->id}_".CacheVersion::suffix(['discounts']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($store) {
            // city_slug: the chain page's map and nearest-store finder load
            // this endpoint client-side and link each result to its
            // /vaistines/{store}/{city} page.
            $locations = \App\Models\StoreLocation::where('store_id', $store->id)
                ->active()
                ->orderBy('city')
                ->orderBy('address')
                ->get(['city', 'address', 'slug', 'lat', 'lng', 'phone', 'hours'])
                ->map(fn ($location) => [...$location->toArray(), 'city_slug' => Str::slug($location->city)]);

            return [
                'store' => ['name' => $store->name, 'slug' => $store->slug],
                'locations' => $locations,
                'total' => $locations->count(),
            ];
        });

        return response()->json($payload);
    }

    public function search(Request $request, $query)
    {
        $filters = $this->getFilters();
        $cacheKey = 'search_'.md5($query.serialize($this->normalizeFiltersForCacheKey($filters)))
            .'_'.CacheVersion::suffix(['discounts']);

        return Cache::remember($cacheKey, 1800, function () use ($query, $filters) {
            try {
                $page = $filters['page'] ?? 1;
                $perPage = self::PER_PAGE;

                $meilisearchFilters = [];
                if ($filters['store']) {
                    $storeSlugs = explode(',', $filters['store']);
                    $storeIds = \App\Models\Store::whereIn('slug', $storeSlugs)->pluck('id')->toArray();
                    if (! empty($storeIds)) {
                        $meilisearchFilters['store_ids'] = $storeIds;
                    }
                }

                $explicitOrder = $this->hasExplicitOrder();
                $sort = $explicitOrder ? $this->getMeilisearchSort($filters['order']) : [];

                $searchResults = $this->meilisearchService->search($query, $meilisearchFilters, $sort, $page, $perPage);

                if (empty($searchResults['hits'])) {
                    \Log::warning('Meilisearch returned 0 results', [
                        'query' => $query,
                        'filters' => $meilisearchFilters,
                        'sort' => $sort,
                    ]);
                    $discounts = collect();
                } else {
                    $discountIds = collect($searchResults['hits'])->pluck('id')->toArray();
                    $discountQuery = Discount::whereIn('discounts.id', $discountIds);
                    $discountQuery = $this->buildDiscountQuery($discountQuery, $filters, $explicitOrder);

                    if (! $explicitOrder) {
                        $discountQuery = $this->orderDiscountsByMeilisearchIds($discountQuery, $discountIds);
                    }

                    $discounts = $discountQuery->get();
                }

                $paginator = new LengthAwarePaginator(
                    $discounts,
                    $searchResults['total'],
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                try {
                    SearchResult::create([
                        'query' => $query,
                        'ip_address' => request()->ip(),
                        'country_code' => $this->searchCountryCode(),
                        'total_results' => $searchResults['total'],
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to store search result: '.$e->getMessage());
                }

                return response()->json([
                    'data' => $this->formatter->format($paginator),
                    'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
                    'seo' => $this->generateSeoData('search', $query, '-'),
                ]);
            } catch (\Exception $e) {
                try {
                    SearchResult::create([
                        'query' => $query,
                        'ip_address' => request()->ip(),
                        'country_code' => $this->searchCountryCode(),
                        'total_results' => 0,
                    ]);
                } catch (\Exception $saveException) {
                    \Log::warning('Failed to store search result: '.$saveException->getMessage());
                }

                $explicitOrder = $this->hasExplicitOrder();

                $queryQb = Discount::searchByProductName($query)
                    ->with(['product', 'store']);

                $discounts = $this->buildDiscountQuery($queryQb, $filters, $explicitOrder)->paginate(self::PER_PAGE);

                return response()->json([
                    'data' => $this->formatter->format($discounts),
                    'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
                    'seo' => $this->generateSeoData('search', $query, '-'),
                ]);
            }
        });
    }

    private function getMeilisearchSort($order)
    {
        switch ($order) {
            case 'price_min':
                return ['discounted_price:asc'];
            case 'price_max':
                return ['discounted_price:desc'];
            case 'price_discount_proc_max':
                return ['discount_percent:desc'];
            case 'price_discount_max':
                return ['savings_amount:desc'];
            case 'popular':
            default:
                return [];
        }
    }

    public function getFavoriteCategory($id)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateFavoriteCategoryCacheKey($id, $this->normalizeFiltersForCacheKey($filters));

        return Cache::remember($cacheKey, 7200, function () use ($id, $filters) {
            $query = Discount::whereHas('product', function ($q) use ($id) {
                $q->where('category_id', $id);
            })
                ->with(['product', 'store']);

            $discounts = $this->buildDiscountQuery($query, $filters)
                ->inRandomOrder()
                ->limit(10)
                ->get();

            return response()->json($this->formatter->format($discounts));
        });
    }

    public function getFavoriteHome()
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateFavoriteHomeCacheKey($this->normalizeFiltersForCacheKey($filters)).'_v15';

        return Cache::remember($cacheKey, 7200, function () {
            return response()->json([
                'sections' => $this->homePageSectionsService->build(),
                'page_meta' => $this->homePageMetaService->build(),
            ]);
        });
    }

    public function getProductBySlug($slug)
    {
        if (! Product::where('slug', $slug)->exists()) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $cacheKey = "product_slug_{$slug}_".CacheVersion::suffix(['discounts']);

        return Cache::remember($cacheKey, 86400, function () use ($slug) {
            $product = Product::where('slug', $slug)
                ->with([
                    'discounts.store',
                    'category',
                ])
                ->firstOrFail();

            return response()->json([
                'data' => $this->formatter->format($product->discounts),
                'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
                'seo' => $this->generateSeoData('product', $product),
            ]);
        });
    }

    public static function productWithSimilarCacheKey(string $slug): string
    {
        // Bump this suffix whenever the cached payload shape OR its
        // generation logic changes — this key is NOT wrapped in
        // CacheVersion::suffix(['discounts']) like most other caches here,
        // so cache:clear-discounts does not invalidate it. Its 7-day TTL
        // means stale data (a shape change, or a fixed bug in what gets
        // cached — v13->v14: similar-products tier 1+2 could combine past
        // the intended 10-item cap with nothing to trim it back down)
        // would otherwise linger for up to a week after deploy.
        return "product_with_similar_v15_{$slug}";
    }

    public function resolveDiscountsCacheKey($storeOrCategory, $category = null): string
    {
        $filters = $this->getFilters();

        return $this->generateDiscountsCacheKey(
            $storeOrCategory,
            $category,
            $this->normalizeFiltersForCacheKey($filters, $storeOrCategory, $category)
        );
    }

    public function resolveAllDiscountsCacheKey(): string
    {
        $filters = $this->getFilters();

        return $this->generateAllDiscountsCacheKey($this->normalizeFiltersForCacheKey($filters));
    }

    public function resolveFavoriteHomeCacheKey(): string
    {
        $filters = $this->getFilters();

        return $this->generateFavoriteHomeCacheKey($this->normalizeFiltersForCacheKey($filters)).'_v15';
    }

    public function resolveFavoriteCategoryCacheKey($id): string
    {
        $filters = $this->getFilters();

        return $this->generateFavoriteCategoryCacheKey($id, $this->normalizeFiltersForCacheKey($filters));
    }

    public function resolveDiscountsCacheLabel($storeOrCategory, $category = null): string
    {
        if ($category) {
            return "/discount/{$storeOrCategory}/{$category}";
        }

        if (\App\Models\Store::where('slug', $storeOrCategory)->exists()) {
            return "/discount/{$storeOrCategory}";
        }

        return "/discount/{$storeOrCategory}";
    }

    public static function resolveCachedProductWithSimilar(string $slug): ?array
    {
        $cacheKey = self::productWithSimilarCacheKey($slug);
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse === null) {
            return null;
        }

        return [
            'body' => $cachedResponse,
            'status' => 'HIT',
            'key' => $cacheKey,
        ];
    }

    public static function cachedProductWithSimilarResponse(array $cached): \Illuminate\Http\Response
    {
        return response($cached['body'], 200, ['Content-Type' => 'application/json'])
            ->header('X-Cache', $cached['status'])
            ->header('X-Cache-Key', $cached['key']);
    }

    public function getProductWithSimilar($slug)
    {
        $cached = self::resolveCachedProductWithSimilar($slug);

        if ($cached !== null) {
            return self::cachedProductWithSimilarResponse($cached);
        }

        $cacheKey = self::productWithSimilarCacheKey($slug);

        $product = Product::where('slug', $slug)
            ->with(['discounts.store', 'discountHistories.store', 'category'])
            ->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $randomSeed = $this->generateRandomSeed($slug);

        // 10 = 2 rows at the similar-products grid's desktop column count (5);
        // the Blade view further hides items past the 4th on mobile (2 cols),
        // so both breakpoints show at most 2 rows.
        $similarLimit = 10;
        $excludeProductIds = [$product->id];
        $similarDiscounts = collect();

        // Tier 1: same category + same brand (strongest match)
        if (! empty($product->brand)) {
            $brandDiscounts = Discount::query()
                ->join('products', 'discounts.product_id', '=', 'products.id')
                ->where('products.category_id', $product->category_id)
                ->where('products.brand', $product->brand)
                ->whereNotIn('products.id', $excludeProductIds)
                ->select('discounts.*')
                ->with(['product.category', 'product.discounts.store', 'store'])
                ->orderByRaw("RAND({$randomSeed})")
                ->limit($similarLimit)
                ->get();

            $similarDiscounts = $similarDiscounts->concat($brandDiscounts);
            $excludeProductIds = array_merge($excludeProductIds, $brandDiscounts->pluck('product_id')->all());
        }

        // Tier 2: Meilisearch name-stem match within the same category
        if ($similarDiscounts->count() < $similarLimit) {
            $nameStem = MeilisearchService::buildNameStem($product->name);

            if ($nameStem !== '') {
                // Remaining slots, not the full $similarLimit again — passing
                // the full limit here let tier 1 (brand match) + tier 2
                // combine past $similarLimit with nothing left to trim them
                // back down (seen live: 3 brand + 8 stem = 11 shown on a
                // "kavos pupelės" page against the intended cap of 10).
                $stemDiscounts = $this->meilisearchService
                    ->findSimilarDiscounts($nameStem, $product->id, $product->category_id, $similarLimit - $similarDiscounts->count())
                    ->whereNotIn('product_id', $excludeProductIds)
                    ->values();

                $similarDiscounts = $similarDiscounts->concat($stemDiscounts);
                $excludeProductIds = array_merge($excludeProductIds, $stemDiscounts->pluck('product_id')->all());
            }
        }

        // Tier 3: same category, random, to fill up whatever is still missing
        if ($similarDiscounts->count() < $similarLimit) {
            $fallbackDiscounts = Discount::query()
                ->join('products', 'discounts.product_id', '=', 'products.id')
                ->where('products.category_id', $product->category_id)
                ->whereNotIn('products.id', $excludeProductIds)
                ->select('discounts.*')
                ->with(['product.category', 'product.discounts.store', 'store'])
                ->orderByRaw("RAND({$randomSeed})")
                ->limit($similarLimit - $similarDiscounts->count())
                ->get();

            $similarDiscounts = $similarDiscounts->concat($fallbackDiscounts);
        }

        // Defense-in-depth: each tier above is meant to only fill remaining
        // slots, but nothing enforced the combined total actually stayed at
        // $similarLimit — take() here guarantees it regardless of how any
        // individual tier's own math works out.
        $similarDiscounts = $similarDiscounts->take($similarLimit);

        if ($product->discounts->isEmpty()) {
            $data = $this->formatter->formatProduct($product);
        } else {
            $data = $this->formatter->formatProductDiscounts($product);
        }

        $responseData = [
            'data' => $data,
            'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
            'seo' => $this->generateSeoData('product', $product),
            'similar' => $this->formatter->formatList($similarDiscounts),
        ];

        $jsonString = json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Cache::put($cacheKey, $jsonString, now()->addDays(7));

        return response($jsonString, 200, ['Content-Type' => 'application/json'])
            ->header('X-Cache', 'MISS')
            ->header('X-Cache-Key', $cacheKey);
    }

    private function generateBreadcrumbs($type, $entity = null, $secondaryEntity = null)
    {
        $breadcrumbs = [
            [
                'name' => 'Pradžia',
                'slug' => '/',
                'type' => 'home',
            ],
        ];

        switch ($type) {
            case 'store_leaflet':
                // Leidiniai section has its own root ("Leidiniai" -> /leidiniai)
                // instead of the generic "Pradžia" home crumb every other case
                // uses — this section isn't reached via /akcijos at all, and a
                // visitor on /leidinys/{store} should be able to hop back to
                // the full leaflets index, not the discount listing.
                $words = $this->getStoreLeafletWords($entity->slug);
                $leafletNounPlural = substr($words['nominative'], 0, -2).'iai';
                $breadcrumbs = [
                    [
                        'name' => 'Leidiniai',
                        'slug' => 'leidiniai',
                        'type' => 'leaflets_index',
                    ],
                    [
                        'name' => \App\Support\PharmacyName::phrase($entity->name, 'genitive').' '.$leafletNounPlural,
                        'slug' => 'leidinys/'.$entity->slug,
                        'type' => 'store_leaflet',
                    ],
                ];
                break;
            case 'store_flyer_detail':
                $words = $this->getStoreLeafletWords($entity->slug);
                $leafletNounPlural = substr($words['nominative'], 0, -2).'iai';
                $flyerTitle = app(\App\Services\StoreFlyerTitleBuilder::class)->build($secondaryEntity, $entity);
                $breadcrumbs = [
                    [
                        'name' => 'Leidiniai',
                        'slug' => 'leidiniai',
                        'type' => 'leaflets_index',
                    ],
                    [
                        'name' => \App\Support\PharmacyName::phrase($entity->name, 'genitive').' '.$leafletNounPlural,
                        'slug' => 'leidinys/'.$entity->slug,
                        'type' => 'store_leaflet',
                    ],
                    [
                        'name' => $flyerTitle,
                        'slug' => 'leidinys/'.$entity->slug.'/'.$secondaryEntity->slug,
                        'type' => 'store_flyer',
                    ],
                ];
                break;
            case 'store':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'type' => 'store',
                ];
                break;
            case 'category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'type' => 'category',
                ];
                break;
            case 'store_category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'type' => 'store',
                ];
                $breadcrumbs[] = [
                    'name' => $secondaryEntity->name,
                    'slug' => $entity->slug.'/'.$secondaryEntity->slug,
                    'type' => 'category',
                ];
                break;
            case 'product':
                if ($entity->category) {
                    $breadcrumbs[] = [
                        'name' => $entity->category->name,
                        'slug' => $entity->category->slug,
                        'type' => 'category',
                    ];
                }
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => ltrim(\App\Support\PageUrl::product($entity->slug), '/'),
                    'type' => 'product',
                ];
                break;
            case 'search':
                $breadcrumbs[] = [
                    'name' => 'Paieška',
                    'slug' => 'paieska/'.$entity,
                    'type' => 'search',
                ];
                break;
            case 'all_discounts':
                $breadcrumbs[] = [
                    'name' => 'Visos akcijos',
                    'slug' => 'akcijos',
                    'type' => 'all_discounts',
                ];
                break;
            case 'leaflets_index':
                $breadcrumbs[] = [
                    'name' => 'Leidiniai',
                    'slug' => 'leidiniai',
                    'type' => 'leaflets_index',
                ];
                break;
        }

        return $breadcrumbs;
    }

    private function generateSeoData($type, $entity = null, $secondaryEntity = null)
    {
        switch ($type) {
            case 'category':
                $count = $this->getDiscountCountForCategory($entity);
                $maxDiscount = $this->roundDownDiscountPercent($this->getMaxDiscountForCategory($entity));
                $storeNames = $this->getStoreNamesForCategory($entity);
                $countLabel = $this->formatCount($count);

                // Shortened category name ("Buitinė chemija" not "Buitinė
                // chemija, valymo priemonės") plus genitive/dative case —
                // same maps/helper already built for the store/store_category
                // pages this session (see config/categories.php short_labels for
                // why the raw DB name breaks mid-sentence grammar).
                $categoryShortName = $this->shortenCategoryName($entity->name);
                $categoryGenitive = self::categoryGenitiveLabel($entity->name);
                $categoryDative = config('categories.dative_labels')[$categoryShortName] ?? mb_strtolower($categoryShortName);

                // Some listings are full-catalog (price-only, no discount_percent
                // on any row) rather than discount-only — MAX(discount_percent)
                // is then NULL/0 across the board, and an unconditional "iki 0%
                // nuolaidos" would misrepresent real priced products as a fake
                // zero-value deal. Only claim a discount percentage when one
                // genuinely exists.
                $categoryGenitiveCap = mb_ucfirst($categoryGenitive);

                return [
                    'seo_title' => "{$categoryGenitiveCap} akcijos",
                    'seo_description' => $entity->description,
                    // Consumed by AkcijosController for the H1 (lowercase
                    // there — it follows "Visos", not sentence-initial).
                    'category_genitive_label' => $categoryGenitive,
                    'meta_title' => $maxDiscount > 0
                        ? "{$categoryGenitiveCap} akcijos šiandien – nuolaidos iki {$maxDiscount}%"
                        : "{$categoryGenitiveCap} akcijos šiandien",
                    'meta_description' => $maxDiscount > 0
                        ? "Iki {$maxDiscount}% nuolaidos {$categoryDative} iš {$storeNames}. {$countLabel}+ pasiūlymų šią savaitę!"
                        : "Palyginkite {$categoryGenitive} pasiūlymus iš {$storeNames}. {$countLabel}+ prekių šią savaitę!",
                ];
            case 'store_leaflet':
                $words = $this->getStoreLeafletWords($entity->slug);
                // "leidinys"/"leidynys" -> "leidiniai"/"leidyniai" (nominative
                // plural) / "leidinius"/"leidynius" (accusative plural) are
                // stem swaps (drop "ys", add "iai"/"ius"), not plain suffix
                // appends — same transform as hub.blade.php's own
                // $leafletNounPlural, extended here for the accusative form
                // ("...akcijų leidinius", object of "peržiūrėkite").
                $leafletNounPlural = substr($words['nominative'], 0, -2).'iai';
                $leafletNounAccusativePlural = substr($words['nominative'], 0, -2).'ius';
                // "Benu vaistinės", "Eurovaistinės": the chain name is never
                // left in the nominative next to "leidiniai"/"katalogai".
                $storeGenitive = \App\Support\PharmacyName::phrase($entity->name, 'genitive');

                // The single newest currently-valid flyer (not the unreliable
                // is_active flag — same "expired means valid_to < today" rule
                // as StoreFlyerTitleBuilder::toListingArray()/
                // ListingPageMetaService::buildLeaflets()) — named by its own
                // real title so the description says what's actually current
                // instead of a generic date-range sentence.
                $currentFlyer = $entity->flyers()
                    ->ready()
                    ->where(function ($q) {
                        $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->startOfDay());
                    })
                    ->orderByDesc('valid_from')
                    ->first();

                if ($currentFlyer) {
                    $currentLabel = $currentFlyer->metaLabel();

                    $metaDescription = $entity->showsDiscountsPage()
                        ? "Dabar galioja „{$currentLabel}“. Peržiūrėkite leidinį ir kitus naujausius {$storeGenitive} akcijų {$leafletNounAccusativePlural}."
                        : "Dabar galioja „{$currentLabel}“. Peržiūrėkite {$storeGenitive} akcijas ir kitus naujausius akcijų {$leafletNounAccusativePlural}.";
                } else {
                    // No currently-valid flyer found at all — fall back to
                    // the previous generic validity-range sentence (same
                    // source resolveStoreValidity() already used for the
                    // old title/description).
                    $validity = $this->resolveStoreValidity($entity);
                    $validFromDot = Carbon::parse($validity['valid_from'])->format('Y.m.d');
                    $validToDot = Carbon::parse($validity['valid_to'])->format('Y.m.d');
                    $metaDescription = "{$storeGenitive} {$words['nominative']} galioja nuo {$validFromDot} iki {$validToDot}.";
                }

                return [
                    'seo_title' => $storeGenitive.' '.$words['nominative'],
                    'seo_description' => $entity->description,
                    // No date/issue-number in the title anymore — see
                    // meta_description instead.
                    // "katalogai" in the title, not "leidiniai" (owner's
                    // call, 2026-10-02); the H1 keeps "leidiniai".
                    'meta_title' => $entity->showsDiscountsPage()
                        ? "Naujausi {$storeGenitive} akcijų katalogai"
                        : "{$storeGenitive} akcijos ir naujausi katalogai",
                    'meta_description' => $metaDescription,
                    // A store without its own offers page (/{slug}
                    // 301s here) has its offers only in the leaflets, so
                    // this page is the one for "{store} akcijos" searches.
                    'h1' => $entity->showsDiscountsPage()
                        ? "Visi {$storeGenitive} akcijų {$leafletNounPlural}"
                        : "{$storeGenitive} akcijos ir akcijų {$leafletNounPlural}",
                ];
            case 'store':
                // limit=2: title/H1 only ever use the first (best) category
                // (see $topCategory below), but the description has enough
                // character budget for a second one — see
                // config/categories.php short_labels for why the length math
                // only works with shortened names.
                $topCategories = $this->getTopDiscountCategoriesForStore($entity->id, 2);
                $topCategory = $topCategories[0] ?? null;
                $maxDiscount = $topCategory['max_discount_percent']
                    ?? $this->roundDownDiscountPercent(Discount::where('store_id', $entity->id)->max('discount_percent') ?? 0);
                $words = $this->getStoreLeafletWords($entity->slug);
                // SXO audit finding: SERP competitors for "{store} akcijos šią
                // savaitę" all bake a date range into their title, this page's
                // title had none (evergreen-looking, no freshness signal) —
                // reuse the same validity resolution /leidinys/{store} already
                // uses so both page types read consistently.
                $validity = $this->resolveStoreValidity($entity);
                $validityLabel = $this->formatValidityRangeLabel($validity['valid_from'], $validity['valid_to']);

                // Description gets up to 2 categories (title/H1 stay plain,
                // no category name — explicit product decision), e.g.
                // "Buitinė chemija iki 60%, Namų prekės iki 50%".
                // $endDateLabel is just the end date, not the full validity
                // range, to keep this within the ~140-150 char meta
                // description sweet spot.
                $categoryLabelsForDescription = implode(', ', array_map(
                    fn ($c) => "{$c['name']} iki {$c['max_discount_percent']}%",
                    $topCategories
                ));
                // Numeric Y.m.d ("2026.09.14") instead of formatLtDate()'s
                // abbreviated-month form ("14 rugs.") — unambiguous and
                // matches the app's existing date convention elsewhere
                // (e.g. formatValidityRangeLabel()'s own callers' surrounding
                // copy, leaflet issue dates).
                $endDateLabel = Carbon::parse($validity['valid_to'])->format('Y.m.d');

                return [
                    'seo_title' => $entity->name.' akcijos',
                    'seo_description' => $entity->description,
                    // Consumed by AkcijosController::renderListingPayload() to
                    // build the store page's H1 — not rendered directly here.
                    'top_discount_category' => $topCategory,
                    // No category name and no offer count in the title —
                    // explicit product decision to keep it to just the store
                    // name + a plain "nuolaidos iki X%" hook; category+count
                    // still live in meta_description.
                    'meta_title' => $maxDiscount > 0
                        ? "{$entity->name} akcijos šiandien – nuolaidos iki {$maxDiscount}%"
                        : "{$entity->name} akcijos šiandien",
                    // Offer count and the "{Store} akcijos:" lead-in both
                    // dropped (was "{count}+ pasiūlymų" / store name prefix)
                    // to free up character budget — explicit product
                    // decision; category names + store's own page context
                    // already carry the store identity without repeating it.
                    'meta_description' => $categoryLabelsForDescription !== ''
                        ? "{$categoryLabelsForDescription}. Galioja iki {$endDateLabel}. Palyginkite ir sutaupykite!"
                        : "Visos {$entity->name} akcijos ir nuolaidos (galioja {$validityLabel}). Filtruokite, rūšiuokite ir palyginkite kainas. Naujas {$words['nominative']}: /leidinys/{$entity->slug}",
                ];
            case 'store_category':
                $count = $this->getDiscountCountForStoreCategory($entity, $secondaryEntity);
                $maxDiscount = $this->roundDownDiscountPercent($this->getMaxDiscountForStoreCategory($entity, $secondaryEntity));
                $categoryLower = mb_strtolower($secondaryEntity->name);

                // Dative plural ("akcijos duonos gaminiams") instead of the
                // old nominative-juxtaposition ("akcija duonos gaminiai") —
                // see config/categories.php dative_labels. Computed for both
                // branches below (including the zero-offer one) so the H1
                // built from it in AkcijosController reads grammatically
                // correct either way. Store name stays plain nominative
                // everywhere (explicit product decision) — a locative form
                // ("Maximoje") would need a hand-verified map for all 47
                // stores, several of which are foreign/brand names with no
                // natural Lithuanian declension (Ikea, Jysk, AVS, Thomas
                // Philipps) — not worth the risk of an awkward-sounding form.
                $categoryShortName = $this->shortenCategoryName($secondaryEntity->name);
                $categoryDative = config('categories.dative_labels')[$categoryShortName] ?? mb_strtolower($categoryShortName);
                // Genitive for the title ("Maxima duonos gaminių akcijos") —
                // see config/categories.php genitive_labels. The category is
                // $secondaryEntity here ($entity is the store).
                $categoryGenitive = self::categoryGenitiveLabel($secondaryEntity->name);
                // Concrete illustrative item examples (dative plural, e.g.
                // "duonai, bandelėms ir kruasanams" for Duonos gaminiai) —
                // explicit product decision to use hand-written, specific
                // sub-item examples in the description instead of the
                // single, more abstract category-level dative phrase, so a
                // searcher sees real item types, not just the category name
                // repeated. Falls back to the plain category dative if a
                // category has no examples authored yet. mb_ucfirst since
                // it opens the description sentence.
                $categoryItemExamples = mb_ucfirst(config('categories.item_examples')[$categoryShortName] ?? $categoryDative);
                // "Benu vaistinėje", "Camelia vaistinėje", "Eurovaistinėje".
                $storePhrase = \App\Support\PharmacyName::phrase($entity->name, 'locative');

                $storeCategoryDescription = StoreCategoryDescription::where('store_id', $entity->id)
                    ->where('category_id', $secondaryEntity->id)
                    ->first();

                // SEO audit finding: with 0 offers this unconditionally read
                // "iki 0% nuolaidos, 0+ prekių" — a template artifact that
                // misrepresented an empty result as a real (if tiny) deal.
                // The page itself still shows other stores' offers for this
                // category (see getDiscountsByStoreAndCategory's fallback),
                // so title/description describe that instead of a fake 0%.
                if ($count === 0) {
                    $seoData = [
                        'seo_title' => $entity->name.' '.$categoryLower,
                        'seo_description' => '',
                        'category_dative_label' => $categoryDative,
                        'meta_title' => $entity->name.' '.$categoryLower.' – palyginkite kainas kitose vaistinėse',
                        'meta_description' => "Šiuo metu {$entity->name} neturi aktyvių {$categoryLower} akcijų. Peržiūrėkite {$categoryLower} pasiūlymus kitose vaistinėse.",
                    ];

                    return $seoData;
                }

                // "iki rugsėjo 30 d." — natural running-text date, not the
                // numeric "2026.09.30" used elsewhere (e.g. the store page).
                $endDateGenitive = LithuanianDate::dayMonthGenitive(
                    Carbon::parse($this->resolveStoreValidity($entity)['valid_to'])
                );

                $seoData = [
                    'seo_title' => "{$entity->name} akcijos {$categoryDative}",
                    'seo_description' => '',
                    // Consumed by AkcijosController for the H1.
                    'category_dative_label' => $categoryDative,
                    'meta_title' => $maxDiscount > 0
                        ? \App\Support\PharmacyName::phrase($entity->name, 'genitive') . " {$categoryGenitive} akcijos iki {$endDateGenitive}"
                        : \App\Support\PharmacyName::phrase($entity->name, 'genitive') . " {$categoryGenitive} akcijos",
                    // Item examples + store lead the sentence, discount %
                    // right after — explicit product decision.
                    'meta_description' => $maxDiscount > 0
                        ? "{$categoryItemExamples} {$storePhrase} – iki {$maxDiscount} % nuolaida. Patikrinkite pasiūlymus, galiojančius iki {$endDateGenitive}!"
                        : "Peržiūrėkite {$categoryLower} pasiūlymus {$storePhrase}.",
                ];

                if ($storeCategoryDescription && $storeCategoryDescription->intro_html) {
                    $seoData['seo_description'] = $storeCategoryDescription->intro_html;
                }

                return $seoData;
            case 'product':
                $minPrice = $entity->discounts->min('discounted_price');
                $formattedPrice = $minPrice ? (floor($minPrice) == $minPrice ? number_format($minPrice, 0, '.', '') : number_format($minPrice, 2, '.', '')) : null;
                $priceTextDesc = $formattedPrice ? $formattedPrice.' €' : '';
                $storeNames = $this->getStoreNamesForProduct($entity);
                $storeSuffix = $storeNames ? " ({$storeNames})" : '';
                $displayName = \App\Support\ProductPageMeta::displayName($entity->name);

                // Google cuts titles at ~60 chars and long product names
                // (up to 100+) pushed the price — the part that earns the
                // click — out of view. Keep "{name} akcija – kaina nuo X €"
                // within ~65 chars by trimming the name at a word boundary;
                // the "(Store)" suffix only when it still fits.
                $titleTail = ' akcija'.($priceTextDesc ? ' – kaina nuo '.$priceTextDesc : '');
                $titleName = $displayName;
                $nameBudget = 65 - mb_strlen($titleTail);
                if (mb_strlen($titleName) > $nameBudget) {
                    $titleName = rtrim(preg_replace('/\s+\S*$/u', '', mb_substr($titleName, 0, $nameBudget)), ' ,.;:–-');
                    // A cut that separated a number from its unit ("…, 40 g"
                    // → "…, 40") leaves a meaningless bare number — drop it.
                    $titleName = rtrim(preg_replace('/[\s,]+\d+(?:[.,]\d+)?$/u', '', $titleName), ' ,.;:–-');
                }
                $metaTitle = $titleName.$titleTail;
                if ($storeSuffix !== '' && mb_strlen($metaTitle.$storeSuffix) <= 65) {
                    $metaTitle .= $storeSuffix;
                }

                // Same 90-day window and 3-point minimum as
                // ProductPageMeta::historyFacts() (the FAQ answer this repeats).
                $isLowestIn90Days = false;
                if ($minPrice > 0) {
                    $recent = $entity->discountHistories()
                        ->where('discounted_price', '>', 0)
                        ->where(fn ($q) => $q->where('end_at', '>=', now()->subDays(90)->startOfDay())
                            ->orWhere(fn ($q) => $q->whereNull('end_at')->where('start_at', '>=', now()->subDays(90)->startOfDay())))
                        ->selectRaw('COUNT(*) as points, MIN(discounted_price) as min_price')
                        ->first();
                    $isLowestIn90Days = $recent && $recent->points >= 3 && $minPrice <= (float) $recent->min_price;
                }

                return [
                    'seo_title' => $entity->name,
                    'seo_description' => $entity->description ?? '',
                    'meta_title' => $metaTitle,
                    'meta_description' => $displayName.($priceTextDesc
                        ? ' akcija – kaina nuo '.$priceTextDesc.($storeNames ? " ({$storeNames})" : '').'. '
                            .($isLowestIn90Days ? 'Mažiausia kaina per 90 d. ' : '').'Palyginkite kainas vaistinėse!'
                        : ' – palyginkite kainas vaistinėse.'),
                ];
            case 'search':
                return [
                    'seo_title' => $entity,
                    'seo_description' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                    'meta_title' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                    'meta_description' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                ];
            case 'all_discounts':
                // Hand-written (not GPT-generated, unlike Store/Category::description):
                // one global page. Chains and categories come from config, so the
                // copy follows config('stores.main_slugs') and the category roots.
                $pharmacyLinks = [];
                foreach (\App\Support\StoreListPriority::mainNames() as $slug => $name) {
                    $pharmacyLinks[] = '<a href="'.\App\Support\PageUrl::listing($slug).'">'.e($name).'</a>';
                }
                $lastPharmacyLink = array_pop($pharmacyLinks);
                $pharmacyLinksText = $pharmacyLinks ? implode(', ', $pharmacyLinks).' ir '.$lastPharmacyLink : (string) $lastPharmacyLink;

                $categoryLinks = [];
                $rootSlugs = array_flip(config('categories.roots', []));
                foreach (config('categories.popular_slugs', []) as $slug) {
                    if (isset($rootSlugs[$slug])) {
                        $categoryLinks[] = '<a href="'.\App\Support\PageUrl::listing($slug).'">'.e(mb_strtolower(self::shortCategoryLabel($rootSlugs[$slug]))).'</a>';
                    }
                }
                $lastCategoryLink = array_pop($categoryLinks);
                $categoryLinksText = $categoryLinks ? implode(', ', $categoryLinks).' ar '.$lastCategoryLink : (string) $lastCategoryLink;

                return [
                    'seo_title' => 'Visos vaistinių akcijos ir nuolaidos',
                    'seo_description' => '<div class="space-y-4">
  <h2 class="text-2xl md:text-3xl font-semibold leading-tight mb-3">Vaistinių akcijos ir nuolaidos – visos vaistinės vienoje vietoje</h2>
  <p class="leading-relaxed">Ta pati prekė skirtingose vaistinėse dažnai kainuoja nevienodai, o akcijos keičiasi kas kelias savaites. Čia rasite dabar galiojančius '.$pharmacyLinksText.' ir kitų vaistinių pasiūlymus vienoje vietoje – patogu palyginti kainas prieš perkant.</p>
  <p class="leading-relaxed">Akcijos suskirstytos pagal kategorijas: '.$categoryLinksText.'. Kiekvienoje kategorijoje matysite, kurioje vaistinėje ta pati prekė šiuo metu pigiausia.</p>
  <p class="leading-relaxed">Pasiūlymai atnaujinami kasdien, kai vaistinės keičia kainas ir paskelbia naujas akcijas. Jei ieškote konkrečios vaistinės akcijų leidinio, jį rasite <a href="/leidiniai">vaistinių leidinių sąraše</a>.</p>
</div>',
                    'meta_title' => 'Vaistinių akcijos ir nuolaidos – '.\App\Support\StoreListPriority::mainNamesText(3),
                    'meta_description' => 'Visos vaistinių akcijos vienoje vietoje: '.\App\Support\StoreListPriority::mainNamesText().'. Palyginkite vitaminų, kosmetikos ir nereceptinių vaistų kainas.',
                ];
            case 'leaflets_index':
                // Named after the chains that have a leaflet now, in the
                // genitive ("Gintarinės, Camelia, Benu ir kitų vaistinių").
                $chains = \App\Support\StoreListPriority::leafletChainsGenitiveText();
                $description = 'Peržiūrėkite naujausius '.$chains.' ir kitų vaistinių akcijų leidinius. Visi galiojantys leidiniai vienoje vietoje.';

                return [
                    // H1 is plain/static — no store count in it anymore
                    // (leaflets/index.blade.php renders this directly, no
                    // longer needs its own conditional 'leaflet_store_count_label').
                    'seo_title' => 'Naujausi akcijų leidiniai iš visų vaistinių',
                    'seo_description' => $description,
                    'meta_title' => $chains !== ''
                        ? "{$chains} ir kitų vaistinių akcijų leidiniai"
                        : 'Vaistinių akcijų leidiniai',
                    'meta_description' => $description,
                ];
            default:
                return [
                    'seo_title' => '',
                    'seo_description' => '',
                    'meta_title' => '',
                    'meta_description' => '',
                ];
        }
    }

    public function getAllLeaflets()
    {
        $cacheKey = 'all_leaflets_'.CacheVersion::suffix(['discounts', 'flyers']);

        $payload = Cache::remember($cacheKey, 3600, function () {
            $leaflets = $this->listingPageMetaService->buildAllLeaflets();
            // Free real number — no new query, $leaflets is already fetched
            // above; mirrors the same dedupe the blade view does to build
            // its store-chip pill bar, just counting instead of listing.
            $storeCount = collect($leaflets)->pluck('store_slug')->unique()->count();

            return [
                'leaflets' => $leaflets,
                'total' => count($leaflets),
                'store_count' => $storeCount,
                'breadcrumbs' => $this->generateBreadcrumbs('leaflets_index'),
                'seo' => $this->generateSeoData('leaflets_index', $storeCount),
            ];
        });

        return response()->json($payload);
    }

    public function getStoreLeafletHub(string $store)
    {
        $storeModel = \App\Models\Store::where('slug', $store)->firstOrFail();
        $cacheKey = "store_leaflet_hub_{$storeModel->id}_".CacheVersion::suffix(['discounts', 'flyers']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($storeModel) {
            $seo = $this->generateSeoData('store_leaflet', $storeModel);
            $flyerOffers = $this->currentFlyerOffersSummary($storeModel);

            if ($flyerOffers !== null) {
                $seo['meta_description'] = $flyerOffers['meta_description'];
                unset($flyerOffers['meta_description']);
            }

            return [
                'listing_meta' => $this->listingPageMetaService->buildForStore($storeModel),
                'breadcrumbs' => $this->generateBreadcrumbs('store_leaflet', $storeModel),
                'seo' => $seo,
                'total_offers' => Discount::where('store_id', $storeModel->id)->count(),
                'flyer_offers' => $flyerOffers,
            ];
        });

        return response()->json($payload);
    }

    public function getStoreLeaflet(string $store, string $flyerSlug)
    {
        $storeModel = \App\Models\Store::where('slug', $store)->firstOrFail();
        $flyer = \App\Models\StoreFlyer::query()
            ->where('store_id', $storeModel->id)
            ->where('slug', $flyerSlug)
            ->where('is_active', true)
            ->where('processing_status', \App\Models\StoreFlyer::STATUS_READY)
            ->with('pages')
            ->firstOrFail();

        $cacheKey = "store_leaflet_{$flyer->id}_".CacheVersion::suffix(['discounts', 'flyers']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($storeModel, $flyer) {
            $listingMeta = $this->listingPageMetaService->buildForStoreFlyer($storeModel, $flyer);
            $title = $listingMeta['flyer']['title'];

            // Real data this page didn't use at all before: the flyer's own
            // validity dates (title/description only had them when the
            // flyer had NO scraped title of its own — most do) and its real
            // page count ($flyer->pages already eager-loaded above, so this
            // is free — unlike $totalOffers below, which is the whole
            // store's discount count, not scoped to this specific flyer).
            $validFromDot = $flyer->valid_from ? Carbon::parse($flyer->valid_from)->format('Y.m.d') : null;
            $validToDot = $flyer->valid_to ? Carbon::parse($flyer->valid_to)->format('Y.m.d') : null;
            $hasValidity = $validFromDot && $validToDot;
            $dateRangeLabel = $hasValidity ? " – {$validFromDot}–{$validToDot}" : '';
            $validityClause = $hasValidity ? ", galioja {$validFromDot}–{$validToDot}" : '';
            $pagesCount = $flyer->pages->count();
            $descriptionTitle = $title;

            // Offers Gemini extracted from this exact flyer (store_flyer_id,
            // set by PdfFlyerProcessingService), so the page carries
            // crawlable product names and prices, not only page images.
            // In the flyer's own order: by page (flyer_page), then by id,
            // which follows Gemini's reading order within a page. Rows
            // without a page (extracted before flyer_page existed and not
            // matched by the backfill) go last. Capped since some flyers
            // hold 300+.
            $flyerDiscounts = $this->flyerOffersQuery([$flyer->id])
                ->with('product.discountHistories')
                ->orderByRaw('flyer_page IS NULL')
                ->orderBy('flyer_page')
                ->orderBy('id')
                ->get();
            $flyerOffers = $flyerDiscounts
                ->unique('product_id')
                ->take(self::FLYER_OFFERS_LIMIT)
                ->map(fn ($discount) => $this->formatter->formatListDiscount($discount) + [
                    'flyer_page' => $discount->flyer_page,
                    // [ymin, xmin, ymax, xmax] 0-1000: the clickable zone
                    // for this offer on its flyer page.
                    'flyer_box' => $discount->flyer_box,
                    'deal_signal' => $this->flyerOfferDealSignal($discount),
                ])
                ->values()
                ->all();
            $flyerOffersTotal = $flyerDiscounts->unique('product_id')->count();
            $topClause = $this->topDiscountsClause($this->topFlyerDiscounts($flyerDiscounts->unique('product_id')));
            $flyerOffersIntro = $flyerOffersTotal > 0
                ? "Iš šio ".\App\Support\PharmacyName::phrase($storeModel->name, 'genitive')." leidinio surinkome {$flyerOffersTotal} akcijų "
                    .\App\Support\LithuanianPlural::offerWordAccusative($flyerOffersTotal)
                    .' su kainomis. Jie išdėstyti taip pat kaip leidinyje, puslapis po puslapio.'
                    .($topClause !== '' ? " Geriausi pasiūlymai: {$topClause}." : '')
                : null;
            $offersClause = $flyerOffersTotal > 0
                ? ", {$flyerOffersTotal} ".\App\Support\LithuanianPlural::discountWord($flyerOffersTotal)
                : '';

            return [
                'listing_meta' => $listingMeta,
                'breadcrumbs' => $this->generateBreadcrumbs('store_flyer_detail', $storeModel, $flyer),
                'seo' => [
                    'seo_title' => $title,
                    'seo_description' => "{$title} – ".\App\Support\PharmacyName::phrase($storeModel->name, 'genitive').' akcijų leidinys.',
                    // StoreFlyerTitleBuilder already names the pharmacy.
                    'meta_title' => $title.$dateRangeLabel,
                    'meta_description' => "{$descriptionTitle} – ".\App\Support\PharmacyName::phrase($storeModel->name, 'genitive')." leidinys, {$pagesCount} psl.{$offersClause}{$validityClause}. Peržiūrėkite visus akcijų puslapius.",
                ],
                'total_offers' => Discount::where('store_id', $storeModel->id)->count(),
                'flyer_offers' => $flyerOffers,
                'flyer_offers_total' => $flyerOffersTotal,
                'flyer_offers_intro' => $flyerOffersIntro,
            ];
        });

        return response()->json($payload);
    }

    private function sitemapProductsQuery()
    {
        // A product with no active discount still renders its own 200 page
        // (an "Akcija nebegalioja" state with price history) — it does NOT
        // 301-redirect, that only happens when the Product row itself is
        // gone. So pruning purely on "no active discount right now" (an
        // earlier version of this method did that) drops ~36k pages that
        // work fine and may get discounted again. Instead, prune only the
        // clearly one-off/stale case: at most 1 discount ever (current +
        // archived combined) and the most recent one ended over 2 weeks
        // ago. Everything else — active now, multiple discounts ever, or a
        // single discount that ended recently — stays in the sitemap.
        $staleCutoff = now()->subWeeks(2);

        return Product::query()
            ->where(function ($query) {
                $query->whereHas('discounts')->orWhereHas('discountHistories');
            })
            ->whereRaw('(
                select count(*) from (
                    select end_at from discounts where discounts.product_id = products.id
                    union all
                    select end_at from discount_histories where discount_histories.product_id = products.id
                ) as all_discounts
            ) >= 2 or exists (
                select 1 from discounts where discounts.product_id = products.id
                    and (discounts.end_at is null or discounts.end_at >= ?)
                union all
                select 1 from discount_histories where discount_histories.product_id = products.id
                    and discount_histories.end_at >= ?
            )', [now(), $staleCutoff]);
    }

    /**
     * Searcher's country for search_results, from Cloudflare's CF-IPCountry
     * header (2-letter ISO code). "XX" is Cloudflare's "unknown"; "T1" (Tor)
     * is kept. No header (local/dev, or not via Cloudflare) → null.
     */
    private function searchCountryCode(): ?string
    {
        $code = strtoupper(trim((string) request()->header('CF-IPCountry', '')));

        return preg_match('/^[A-Z0-9]{2}$/', $code) && $code !== 'XX' ? $code : null;
    }

    public function getSitemap()
    {
        $cacheKey = 'sitemap_entries_v9_'.CacheVersion::suffix(['sitemap']);

        return Cache::remember($cacheKey, 3600, function () {
            $freshness = $this->pageFreshnessService->build();
            $defaultLastmod = Carbon::parse($freshness['updated_at'])->format('Y-m-d');

            // Leaflet-only stores' /akcijos URL 301s to their leaflet hub —
            // never list a redirecting URL.
            $stores = \App\Models\Store::query()
                ->where('show_discounts_page', true)
                ->whereHas('discounts')
                ->pluck('slug')
                ->all();

            // Every store with a live leaflet has a /leidinys/{slug} hub,
            // with or without its own offers — reusing $stores here left
            // leaflet-only stores' hubs (Jysk, Senukai...) out entirely.
            // Same rule as LeafletController::hub(): a pharmacy without a
            // current leaflet has its /leidinys/{slug} redirected.
            $leafletStores = \App\Models\Store::query()
                ->whereHas('flyers', fn ($q) => $q->active()->ready()->currentlyValid())
                ->pluck('slug')
                ->all();

            $categories = \App\Models\Category::query()
                ->whereNull('parent_id')
                ->whereHas('discounts')
                ->pluck('slug')
                ->all();

            $productsTotal = $this->sitemapProductsQuery()->count();

            $blogPosts = \App\Models\BlogPost::published()
                ->select('slug', 'updated_at', 'published_at')
                ->get()
                ->map(function ($post) {
                    $date = $post->updated_at ?? $post->published_at;

                    return [
                        'slug' => $post->slug,
                        'lastmod' => $date?->format('Y-m-d'),
                    ];
                })
                ->values()
                ->all();

            $keywords = \App\Models\KeywordPage::query()
                ->published()
                ->orderBy('sort_order')
                ->pluck('slug')
                ->all();

            $leafletEntries = \App\Models\StoreFlyer::query()
                ->where('is_active', true)
                ->where('processing_status', \App\Models\StoreFlyer::STATUS_READY)
                ->whereNotNull('slug')
                ->with('store:id,slug')
                ->select('slug', 'updated_at', 'store_id')
                ->get()
                ->map(function ($flyer) {
                    return [
                        'path' => "leidinys/{$flyer->store->slug}/{$flyer->slug}",
                        'lastmod' => $flyer->updated_at?->format('Y-m-d'),
                    ];
                })
                ->values()
                ->all();

            // Store overview pages (/vaistines/{store}).
            $storeLocationSlugs = \App\Models\StoreLocation::query()
                ->where('is_active', true)
                ->join('stores', 'stores.id', '=', 'store_locations.store_id')
                ->distinct()
                ->pluck('stores.slug')
                ->values()
                ->all();

            // Per-city pages (/vaistines/{store}/{city}) — real pages since
            // 2026-09-10 (StoreController::showCity(), replacing the old
            // per-address doorway pages), but only cities with 3+ active
            // locations are listed: most of the ~1,400 are one-address towns,
            // too thin to actively put forward. The rest stay indexable and
            // linked from the store overview.
            $storeCityPages = \App\Models\StoreLocation::query()
                ->where('store_locations.is_active', true)
                ->join('stores', 'stores.id', '=', 'store_locations.store_id')
                ->select('stores.slug as store_slug', 'store_locations.city', DB::raw('COUNT(*) as locations_count'))
                ->groupBy('stores.slug', 'store_locations.city')
                ->having('locations_count', '>=', 3)
                ->get()
                ->map(fn ($row) => "{$row->store_slug}/" . Str::slug($row->city))
                ->unique()
                ->values()
                ->all();

            // Store+category listings (/{store}/{category}) with live
            // offers, for stores that have an akcijos page at all (otherwise
            // the URL 301s to the leaflets hub). Categories are root-only.
            $storeCategoryPages = DB::table('discounts')
                ->join('products', 'products.id', '=', 'discounts.product_id')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->join('stores', 'stores.id', '=', 'discounts.store_id')
                ->where('stores.show_discounts_page', true)
                ->whereNull('categories.parent_id')
                ->select('stores.slug as store_slug', 'categories.slug as category_slug')
                ->distinct()
                ->get()
                ->map(fn ($row) => "{$row->store_slug}/{$row->category_slug}")
                ->values()
                ->all();

            return response()->json([
                'lastmod' => $defaultLastmod,
                'store_location_slugs' => $storeLocationSlugs,
                'store_city_pages' => $storeCityPages,
                'store_category_pages' => $storeCategoryPages,
                'stores' => $stores,
                'leaflet_stores' => $leafletStores,
                'leaflets' => $leafletEntries,
                'categories' => $categories,
                'products_total' => $productsTotal,
                'blog_posts' => $blogPosts,
                'keywords' => $keywords,
            ]);
        });
    }

    public function getSitemapProducts(Request $request)
    {
        $perPage = 20000;
        $page = max(1, (int) $request->query('page', 1));
        $cacheKey = "sitemap_products_v2_page_{$page}_".CacheVersion::suffix(['sitemap']);

        return Cache::remember($cacheKey, 3600, function () use ($page, $perPage) {
            $products = $this->sitemapProductsQuery()
                ->select('id', 'slug', 'updated_at', 'category_id')
                ->orderBy('id')
                ->forPage($page, $perPage)
                ->get()
                ->map(function (Product $product) {
                    $path = ltrim(\App\Support\PageUrl::product($product->slug), '/');

                    return [
                        'path' => $path,
                        'lastmod' => $product->updated_at?->format('Y-m-d') ?? null,
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'products' => $products,
            ]);
        });
    }

    private function resolveStoreValidity($store): array
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

        return $this->pageFreshnessService->getCurrentWeekRange();
    }

    // Active discounts extracted from (or linked to) the given flyers.
    private function flyerOffersQuery(array $flyerIds)
    {
        return Discount::with(['store', 'product.category', 'product.discounts.store'])
            ->whereIn('store_flyer_id', $flyerIds)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay()));
    }

    /**
     * The store's currently valid flyers' offers, for the evergreen
     * /leidinys/{store} hub. That URL is the one that ranks for
     * "{store} leidinys" (flyer URLs change every issue and 301 to the hub
     * once expired), and until now it carried no product text at all, only
     * cover images, same as competitors' image-only leaflet pages.
     *
     * @return array{offers: array, total: int, main_flyer: array, intro: string, meta_description: string}|null
     */
    private function currentFlyerOffersSummary(\App\Models\Store $store): ?array
    {
        $flyers = $store->flyers()->active()->ready()->currentlyValid()->get();

        if ($flyers->isEmpty()) {
            return null;
        }

        // Priced offers first (blanket "-30% visai avalynei" rows have no
        // price), then the biggest discount.
        $discounts = $this->flyerOffersQuery($flyers->pluck('id')->all())
            ->orderByRaw('CASE WHEN discounted_price > 0 THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(discount_percent, 0) DESC')
            ->orderBy('id')
            ->get()
            ->unique('product_id')
            ->values();

        if ($discounts->isEmpty()) {
            return null;
        }

        $total = $discounts->count();
        $mainFlyer = $flyers
            ->sortByDesc(fn ($flyer) => $discounts->where('store_flyer_id', $flyer->id)->count())
            ->first();
        $mainFlyerTotal = $discounts->where('store_flyer_id', $mainFlyer->id)->count();
        $label = $mainFlyer->metaLabel();
        $validity = $mainFlyer->valid_from && $mainFlyer->valid_to
            ? Carbon::parse($mainFlyer->valid_from)->format('m.d').'–'.Carbon::parse($mainFlyer->valid_to)->format('m.d')
            : null;

        $top = $this->topFlyerDiscounts($discounts);
        $topClause = $this->topDiscountsClause($top);
        $offerWord = \App\Support\LithuanianPlural::offerWord($total);
        $offerWordAccusative = \App\Support\LithuanianPlural::offerWordAccusative($total);

        $intro = $flyers->count() > 1
            ? "Galiojančiuose {$store->name} leidiniuose surinkome {$total} akcijų {$offerWordAccusative} su kainomis."
            : "„{$label}“".($validity ? " galioja {$validity}." : '.')." Iš jo surinkome {$total} akcijų {$offerWordAccusative} su kainomis.";
        if ($topClause !== '') {
            $intro .= " Geriausi pasiūlymai: {$topClause}.";
        }

        // Meta: the most valuable food deals, short names first so two fit
        // the ~155-char snippet (the layout cuts anything longer); fewer
        // examples when they don't.
        $metaCandidates = $this->topFlyerDiscounts($discounts, 8);
        $maxPercent = (int) round((float) $metaCandidates->max('discount_percent'));
        $examples = $metaCandidates
            ->filter(fn ($d) => mb_strlen($d->product->name) <= 38)
            ->take(2)
            ->map(fn ($d) => $d->product->name.' – '.number_format($d->discounted_price, 2, ',', '').' €')
            ->values();
        $head = "„{$label}“".($validity ? " galioja {$validity}" : ' galioja dabar')
            .": {$total} akcijų {$offerWord}".($maxPercent > 0 ? ", nuolaidos iki -{$maxPercent} %" : '');
        $metaDescription = "{$head}. Peržiūrėkite visą katalogą.";
        foreach ([[2, true], [1, true], [1, false]] as [$count, $withCta]) {
            if ($examples->count() < $count) {
                continue;
            }
            $candidate = "{$head}, pvz. ".$examples->take($count)->implode(', ').'.'.($withCta ? ' Peržiūrėkite visą katalogą.' : '');
            if (mb_strlen($candidate) <= 155) {
                $metaDescription = $candidate;
                break;
            }
        }

        return [
            'offers' => $discounts
                ->take(self::HUB_FLYER_OFFERS_LIMIT)
                ->map(fn ($discount) => $this->formatter->formatListDiscount($discount))
                ->all(),
            'total' => $total,
            'main_flyer' => [
                'title' => $label,
                'href' => "/leidinys/{$store->slug}/{$mainFlyer->slug}",
                'total' => $mainFlyerTotal,
            ],
            'intro' => $intro,
            'meta_description' => $metaDescription,
        ];
    }

    // The flyer's most valuable food/drink deals for intro and meta text:
    // ranked like the "Geriausi pasiūlymai" pool (deal_score), not by raw
    // percent, which surfaced books and household goods. Falls back to every
    // category for a flyer with no food offers (Pepco, Jysk...).
    // "Gera kaina!" style signal for a flyer hotspot card, only when the
    // product has real price history: with none, the current price alone
    // would always read as "one of the lowest".
    private function flyerOfferDealSignal(Discount $discount): ?array
    {
        $history = $discount->product?->discountHistories
            ?->map(fn ($h) => ['discounted_price' => (float) $h->discounted_price])
            ->all() ?? [];

        if ($history === [] || $discount->discounted_price <= 0) {
            return null;
        }

        return \App\Support\ProductPageMeta::priceDealSignal($history, (float) $discount->discounted_price);
    }

    private function topFlyerDiscounts($discounts, int $limit = 3)
    {
        return $discounts
            ->filter(fn ($d) => $d->discounted_price > 0 && $d->discount_percent > 0)
            ->sortByDesc(fn ($d) => \App\Services\HomeDealPoolService::staticDealScore($d))
            ->take($limit)
            ->values();
    }

    // "Magnis B6 (-41 %), Vitaminas D3 (-36 %)" for intro text.
    private function topDiscountsClause($top): string
    {
        return $top->map(fn ($d) => $d->product->name.' (-'.(int) round($d->discount_percent).' %)')->implode(', ');
    }

    private function getStoreLeafletWords(string $storeSlug): array
    {
        if ($storeSlug === 'iki') {
            return ['accusative' => 'leidynį', 'nominative' => 'leidynys'];
        }

        return ['accusative' => 'leidinį', 'nominative' => 'leidinys'];
    }

    private function formatValidityRangeLabel(string $validFrom, string $validTo): string
    {
        return $this->pageFreshnessService->formatLtDate($validFrom)
            .'–'
            .$this->pageFreshnessService->formatLtDate($validTo);
    }

    // The 5 nationally recognizable chains — when a product is on sale at
    // both one of these and a smaller/less-known store, the title favors
    // showing the recognizable name(s) first rather than whatever order the
    // discounts relation happens to load in.

    private function getStoreNamesForProduct($product): string
    {
        $names = $product->discounts
            ->pluck('store')
            ->filter()
            ->unique('id')
            ->sortBy(function ($store) {
                $rank = array_search($store->slug, \App\Support\StoreListPriority::mainSlugs(), true);

                return $rank === false ? 99 : $rank;
            })
            ->pluck('name')
            ->take(4)
            ->values()
            ->all();

        if (count($names) === 0) {
            return '';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) === 2) {
            return "{$names[0]}, {$names[1]}";
        }

        $last = array_pop($names);

        return implode(', ', $names).', '.$last;
    }

    private function generateDiscountsCacheKey($storeOrCategory, $category = null, $filters = [])
    {
        $key = "discounts_{$storeOrCategory}";

        if ($category) {
            $key .= "_{$category}";
        }

        if (! empty($filters)) {
            $key .= '_'.md5(serialize($filters));
        }

        return $key.'_'.CacheVersion::suffix(['discounts']);
    }

    private function generateAllDiscountsCacheKey($filters = [])
    {
        return 'all_discounts_'.md5(serialize($filters)).'_'.CacheVersion::suffix(['discounts']);
    }

    private function generateFavoriteCategoryCacheKey($id, $filters = [])
    {
        $key = "favorite_category_{$id}";

        if (! empty($filters)) {
            $key .= '_'.md5(serialize($filters));
        }

        return $key.'_'.CacheVersion::suffix(['discounts']);
    }

    private function generateFavoriteHomeCacheKey($filters = [])
    {
        $key = 'favorite_home';

        if (! empty($filters)) {
            $key .= '_'.md5(serialize($filters));
        }

        return $key.'_'.CacheVersion::suffix(['discounts']);
    }

    public function getFavoriteProducts(Request $request)
    {
        $user = $request->user();

        $productIds = ProductFavorite::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return response()->json([
                'products' => [],
                'store_totals' => [],
            ]);
        }

        $discounts = Discount::whereIn('product_id', $productIds)
            ->with(['product.category', 'store'])
            ->get();

        $formattedProducts = $discounts->map(function (Discount $discount) {
            $formatted = $this->formatter->format(collect([$discount]))->first();
            $change = $this->homePageSectionsService->resolvePriceChangeAmount($discount);
            if ($change !== null) {
                $formatted['price_change_amount'] = $change;
            }

            return $formatted;
        })->values();

        $productIdsWithoutDiscount = $productIds->diff($discounts->pluck('product_id')->unique());

        if ($productIdsWithoutDiscount->isNotEmpty()) {
            $productsWithoutDiscount = Product::whereIn('id', $productIdsWithoutDiscount)
                ->with(['category', 'discountHistories.store'])
                ->get();

            foreach ($productsWithoutDiscount as $product) {
                $formattedProducts->push($this->formatter->formatProduct($product)->first());
            }
        }

        $productIdOrder = $productIds->values()->all();
        $formattedProducts = $formattedProducts
            ->sortBy(function ($item) use ($productIdOrder) {
                return array_search($item['product']['id'], $productIdOrder);
            })
            ->values();

        $storeTotals = $this->calculateStoreTotals($productIds, $discounts);

        return response()->json([
            'products' => $formattedProducts,
            'store_totals' => $storeTotals,
        ]);
    }

    private function calculateStoreTotals($productIds, $discounts)
    {
        $storeTotals = [];
        $storeInfo = [];

        foreach ($productIds as $productId) {
            $productDiscounts = $discounts->where('product_id', $productId);

            // Keep the whole cheapest discount per store (not just its price)
            // so total_savings below can compare it against its own
            // original_price — same cheapest-offer selection product_count
            // already uses, just carrying one more field along.
            $storeDiscounts = [];
            foreach ($productDiscounts as $discount) {
                $storeId = $discount->store_id;

                if (! isset($storeInfo[$storeId])) {
                    $storeInfo[$storeId] = $discount->store;
                }

                if (! isset($storeDiscounts[$storeId]) || $discount->discounted_price < $storeDiscounts[$storeId]->discounted_price) {
                    $storeDiscounts[$storeId] = $discount;
                }
            }

            foreach ($storeDiscounts as $storeId => $discount) {
                if (! isset($storeTotals[$storeId])) {
                    $storeTotals[$storeId] = [
                        'store_id' => $storeId,
                        'store_name' => $storeInfo[$storeId]->name,
                        'total_price' => 0,
                        'total_savings' => 0,
                        'product_count' => 0,
                    ];
                }
                $storeTotals[$storeId]['total_price'] += $discount->discounted_price;
                if ($discount->original_price > $discount->discounted_price) {
                    $storeTotals[$storeId]['total_savings'] += $discount->original_price - $discount->discounted_price;
                }
                $storeTotals[$storeId]['product_count']++;
            }
        }

        return array_values($storeTotals);
    }

    private function generateRandomSeed($slug)
    {
        return crc32($slug.date('Y-m-d'));
    }

    private function getDiscountCountForCategory($category)
    {
        return Discount::whereHas('product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })->count();
    }

    private function getMaxDiscountForCategory($category)
    {
        return Discount::whereHas('product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })->max('discount_percent') ?: 0;
    }

    private function getDiscountCountForStore($store)
    {
        return Discount::where('store_id', $store->id)->count();
    }

    private function getMaxDiscountForStore($store)
    {
        return Discount::where('store_id', $store->id)->max('discount_percent') ?: 0;
    }

    private function getDiscountCountForStoreCategory($store, $category)
    {
        return Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })->count();
    }

    private function getMaxDiscountForStoreCategory($store, $category)
    {
        return Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })->max('discount_percent') ?: 0;
    }

    private function getMinDiscountForCategory($category)
    {
        return Discount::whereHas('product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })->min('discount_percent') ?: 10;
    }

    private function getMinDiscountForStore($store)
    {
        return Discount::where('store_id', $store->id)->min('discount_percent') ?: 10;
    }

    private function getMinDiscountForStoreCategory($store, $category)
    {
        return Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })->min('discount_percent') ?: 10;
    }

    private function formatCount($count)
    {
        if ($count >= 1000) {
            return floor($count / 100) * 100;
        } elseif ($count >= 100) {
            return floor($count / 10) * 10;
        } else {
            return $count;
        }
    }

    // Round DOWN to the nearest 10 — "iki 51%"/"iki 65%" are oddly specific
    // numbers for a meta title/description to advertise, real competitor
    // copy overwhelmingly uses clean 10s, and rounding up (e.g. to 60%)
    // would overstate a real discount, which "iki X%" ("up to X%") must
    // never do.
    private function roundDownDiscountPercent($percent)
    {
        return (int) (floor($percent / 10) * 10);
    }

    // Category short names and Lithuanian case forms (genitive, dative,
    // item examples) live in config/categories.php, keyed by short name.
    public static function shortCategoryLabel(string $name): string
    {
        $name = trim($name);

        return config('categories.short_labels')[$name] ?? trim(explode(',', $name)[0]);
    }

    // Shared by getTopDiscountCategoriesForStore() and the store_category
    // SEO case — a handful of root categories are stored as long "X ir Y
    // prekės"-style names (see config/categories.php short_labels); this
    // is the one place that shortening logic lives now.
    private function shortenCategoryName(string $name): string
    {
        return self::shortCategoryLabel($name);
    }

    // "nereceptinių vaistų" for a root category's full DB name, lowercased
    // nominative of the short name when no hand-checked form exists.
    // Shared with ListingPageMetaService's category intro copy.
    public static function categoryGenitiveLabel(string $name): string
    {
        $short = self::shortCategoryLabel($name);

        return config('categories.genitive_labels')[$short] ?? mb_strtolower($short);
    }

    // "Which root categories have this store's best discounts, and what are
    // they" — distinct from getMaxDiscountForStore() (one scalar, no
    // category context) and getMaxDiscountForStoreCategory() (needs the
    // category already known). Returns [] when there's no positive discount
    // to report at all, so callers must fall back to generic copy rather
    // than render a "0%"/empty-category string — the kaina24.lt "Nuo 0 €"
    // and gudrusis.lt "0 pasiūlymų" live bugs found during SEO research are
    // the concrete precedent for why this guard matters. $limit=1 (the H1/
    // title use case) and $limit=2 (the meta description use case, which has
    // more character budget for a second category) share this one query.
    private function getTopDiscountCategoriesForStore($storeId, int $limit = 1): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $storeId)
            ->whereNull('categories.parent_id')
            ->select('categories.name', DB::raw('MAX(discounts.discount_percent) as max_discount_percent'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('max_discount_percent')
            ->limit($limit)
            ->get();

        return $rows
            ->filter(fn ($row) => (int) $row->max_discount_percent > 0)
            ->map(fn ($row) => [
                'name' => $this->shortenCategoryName($row->name),
                'max_discount_percent' => $this->roundDownDiscountPercent($row->max_discount_percent),
            ])
            ->values()
            ->all();
    }

    private function getMaxEndAtForStore($store)
    {
        $endAt = Discount::where('store_id', $store->id)->max('end_at');

        return $endAt ? Carbon::parse($endAt)->format('Y-m-d') : null;
    }

    private function getMaxEndAtForStoreCategory($store, $category)
    {
        $endAt = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })->max('end_at');

        return $endAt ? Carbon::parse($endAt)->format('Y-m-d') : null;
    }

    // Prominence-sorted + capped, same as getStoreNamesForProduct() —
    // the old plain whereIn()->pluck('name') had no ordering or limit at
    // all, so a category with a dozen stores carrying deals listed all of
    // them raw (confirmed live: "iš Maxima, Iki, Lidl, Norfa, Rimi, Aibė,
    // Šilas, Čia, Gulbelė, Kubas, Thomas Philipps ir Promo Cash&Carry").
    private function getStoreNamesForCategory($category)
    {
        $storeIds = Discount::whereHas('product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })->distinct()->pluck('store_id');

        $names = \App\Models\Store::whereIn('id', $storeIds)
            ->get(['name', 'slug'])
            ->sortBy(function ($store) {
                $rank = array_search($store->slug, \App\Support\StoreListPriority::mainSlugs(), true);

                return $rank === false ? 99 : $rank;
            })
            ->pluck('name')
            ->take(4)
            ->values()
            ->all();

        if (count($names) === 0) {
            return '';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        $lastStore = array_pop($names);

        return implode(', ', $names).' ir '.$lastStore;
    }
}
