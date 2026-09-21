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
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    private const PER_PAGE = 20;

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
     * /akcijos/{store} and /leidinys/{store} pages — shown as one curated
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
        // hand-picked CATEGORY_CAROUSEL_ORDER display order: rows for a full
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
                return $query->orderByRaw('CASE WHEN products.category_id IN (1, 52, 121, 352, 380) THEN 0 ELSE 1 END');
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
        $cacheKey = 'stores_'.CacheVersion::suffix(['discounts']);

        $payload = Cache::remember($cacheKey, 3600, function () {
            $stores = \App\Models\Store::select('id', 'name', 'slug')
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
            $stores = \App\Models\Store::select('id', 'name', 'slug')
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
    // page's "Parduotuvė" filter previously listed pharmacies/cosmetics
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

            $stores = \App\Models\Store::select('id', 'name', 'slug')
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

        $cacheKey = "store_locations_{$store->id}_".CacheVersion::suffix(['discounts']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($store) {
            $locations = \App\Models\StoreLocation::where('store_id', $store->id)
                ->active()
                ->orderBy('city')
                ->orderBy('address')
                ->get(['city', 'address', 'slug', 'lat', 'lng', 'phone', 'hours']);

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

    public function getFavoriteProduct($slug)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateFavoriteProductCacheKey($slug, $this->normalizeFiltersForCacheKey($filters));

        if (! Product::where('slug', $slug)->exists()) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return Cache::remember($cacheKey, 86400, function () use ($slug, $filters) {
            $product = Product::where('slug', $slug)->firstOrFail();

            $query = Discount::whereHas('product', function ($query) use ($slug, $product) {
                $query->where('slug', '!=', $slug)
                    ->where('category_id', $product->category_id);
            })
                ->with(['product.category', 'store'])
                ->orderBy('discount_percent', 'desc')
                ->limit(10);

            $discounts = $this->buildDiscountQuery($query, $filters)->get();

            return response()->json($this->formatter->format($discounts));
        });
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
        return "product_with_similar_v14_{$slug}";
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

        // $product->discounts->isEmpty() alone is wrong here — it's true for
        // any product that has ever had a discount row, including one whose
        // only discount already ended (e.g. end_at yesterday). That silently
        // hid the alternatives block on expired-promo product pages even
        // though the page itself (product.blade.php's $isNoActivePromotion,
        // which checks end_at against now()) correctly showed "no active
        // promotion" UI — https://superakcijos.lt/akcijos/mesa-ir-zuvis/virtos-hot-dog-desreles-1-kg
        // was one such case. Match that same "is there a discount active
        // right now" check instead of "has a discount row ever existed".
        $hasActiveDiscount = $product->discounts->contains(
            fn ($discount) => empty($discount->end_at) || $discount->end_at->endOfDay()->gte(now())
        );

        $responseData = [
            'data' => $data,
            'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
            'seo' => $this->generateSeoData('product', $product),
            'similar' => $this->formatter->formatList($similarDiscounts),
            // MOCKUP (idea #10): when this exact product has no active
            // discount, the true "get this instead" pick is another Product
            // row sharing the same generic_product_id (the same real-world
            // item — e.g. every "agurkai" variant across stores/brands), not
            // a loosely-related $similar entry from the same broad category.
            // $similar surfaced blueberries for a cucumber page — same
            // category, no actual relation to the product itself.
            'generic_alternatives' => ! $hasActiveDiscount
                ? $this->activeGenericAlternatives($product)
                : [],
        ];

        $jsonString = json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Cache::put($cacheKey, $jsonString, now()->addDays(7));

        return response($jsonString, 200, ['Content-Type' => 'application/json'])
            ->header('X-Cache', 'MISS')
            ->header('X-Cache-Key', $cacheKey);
    }

    /**
     * MOCKUP (idea #10, not a permanent feature yet): up to $limit currently
     * active discounts among the product's generic-product siblings (same
     * real-world item across stores/pack sizes/brands, matched by
     * MatchGenericProducts), cheapest first, one per distinct sibling
     * product — the actual right "buy this instead" picks, not a loosely
     * related $similar entry from the same broad category.
     */
    private function activeGenericAlternatives(Product $product, int $limit = 3): array
    {
        if (! $product->generic_product_id) {
            return [];
        }

        $discounts = Discount::with(['store', 'product.category'])
            ->whereHas('product', function ($query) use ($product) {
                $query->where('generic_product_id', $product->generic_product_id)
                    ->where('id', '!=', $product->id);
            })
            ->where(function ($query) {
                // now()->startOfDay(): end_at is a DATE stored at midnight
                // ("valid through this day") — comparing against plain
                // now() wrongly expired a discount at the START of its last
                // valid day instead of the end of it.
                $query->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay());
            })
            ->orderByRaw('CASE WHEN discounted_price > 0 THEN discounted_price ELSE 999999 END')
            ->get()
            ->unique('product_id')
            ->take($limit)
            ->values();

        return $discounts->map(fn ($discount) => $this->formatter->formatListDiscount($discount))->all();
    }

    private function generateBreadcrumbs($type, $entity = null, $secondaryEntity = null)
    {
        $breadcrumbs = [
            [
                'name' => 'Akcijos',
                'slug' => '/',
                'type' => 'home',
            ],
        ];

        switch ($type) {
            case 'store_leaflet':
                $words = $this->getStoreLeafletWords($entity->slug);
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.$entity->slug,
                    'type' => 'store',
                ];
                $breadcrumbs[] = [
                    'name' => ucfirst($words['nominative']),
                    'slug' => 'leidinys/'.$entity->slug,
                    'type' => 'store_leaflet',
                ];
                break;
            case 'store_flyer_detail':
                $words = $this->getStoreLeafletWords($entity->slug);
                $flyerTitle = $secondaryEntity->title
                    ?: app(\App\Services\StoreFlyerTitleBuilder::class)->build($secondaryEntity, $entity);
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.$entity->slug,
                    'type' => 'store',
                ];
                $breadcrumbs[] = [
                    'name' => ucfirst($words['nominative']),
                    'slug' => 'leidinys/'.$entity->slug,
                    'type' => 'store_leaflet',
                ];
                $breadcrumbs[] = [
                    'name' => $flyerTitle,
                    'slug' => 'leidinys/'.$entity->slug.'/'.$secondaryEntity->slug,
                    'type' => 'store_flyer',
                ];
                break;
            case 'store':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.$entity->slug,
                    'type' => 'store',
                ];
                break;
            case 'category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.$entity->slug,
                    'type' => 'category',
                ];
                break;
            case 'store_category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.$entity->slug,
                    'type' => 'store',
                ];
                $breadcrumbs[] = [
                    'name' => $secondaryEntity->name,
                    'slug' => 'akcijos/'.$entity->slug.'/'.$secondaryEntity->slug,
                    'type' => 'category',
                ];
                break;
            case 'product':
                if ($entity->category) {
                    $breadcrumbs[] = [
                        'name' => $entity->category->name,
                        'slug' => 'akcijos/'.$entity->category->slug,
                        'type' => 'category',
                    ];
                }
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/'.($entity->category ? $entity->category->slug.'/' : '').$entity->slug,
                    'type' => 'product',
                ];
                break;
            case 'search':
                $breadcrumbs[] = [
                    'name' => 'Paieška',
                    'slug' => 'akcijos/paieska/'.$entity,
                    'type' => 'search',
                ];
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
                // pages this session (see SHORT_CATEGORY_LABELS's comment for
                // why the raw DB name breaks mid-sentence grammar).
                $categoryShortName = $this->shortenCategoryName($entity->name);
                $categoryGenitive = self::CATEGORY_GENITIVE_LABELS[$categoryShortName] ?? mb_strtolower($categoryShortName);
                $categoryDative = self::CATEGORY_DATIVE_LABELS[$categoryShortName] ?? mb_strtolower($categoryShortName);

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
                $validity = $this->resolveStoreValidity($entity);
                $words = $this->getStoreLeafletWords($entity->slug);
                $validFromDot = Carbon::parse($validity['valid_from'])->format('Y.m.d');
                $validToDot = Carbon::parse($validity['valid_to'])->format('Y.m.d');
                // "{store} leidinys – naujas savaitės leidinys" used to say
                // "leidinys" twice — drop the redundant first one, and
                // trade the "galioja {range}" clause for the flyer's own
                // issue number where we have one (a store's shoppers
                // recognize "Nr.37" from the print/PDF leaflet itself; not
                // every store numbers its flyers, so this only applies when
                // issue_number is actually set).
                $flyer = $entity->latestActiveFlyer();
                $issueLabel = $flyer && $flyer->issue_number ? ", Nr.{$flyer->issue_number}" : '';

                return [
                    'seo_title' => $entity->name.' '.$words['nominative'],
                    'seo_description' => $entity->description,
                    // Consumed by leaflets/hub.blade.php to append the real
                    // end date to its blade-computed H1 (that view builds
                    // its own $pageTitle, not via AkcijosController).
                    'leaflet_valid_to_label' => $validToDot,
                    // SXO audit finding (never fixed until now): every
                    // competitor title includes the FULL validity range —
                    // this only had the start date.
                    'meta_title' => "{$entity->name} naujas savaitės {$words['nominative']}{$issueLabel} {$validFromDot}–{$validToDot}",
                    'meta_description' => "Naujas {$entity->name} {$words['nominative']} galioja nuo {$validFromDot} iki {$validToDot}.",
                ];
            case 'store':
                // limit=2: title/H1 only ever use the first (best) category
                // (see $topCategory below), but the description has enough
                // character budget for a second one — see
                // SHORT_CATEGORY_LABELS's comment for why the length math
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
                // see CATEGORY_DATIVE_LABELS's comment. Computed for both
                // branches below (including the zero-offer one) so the H1
                // built from it in AkcijosController reads grammatically
                // correct either way. Store name stays plain nominative
                // everywhere (explicit product decision) — a locative form
                // ("Maximoje") would need a hand-verified map for all 47
                // stores, several of which are foreign/brand names with no
                // natural Lithuanian declension (Ikea, Jysk, AVS, Thomas
                // Philipps) — not worth the risk of an awkward-sounding form.
                $categoryShortName = $this->shortenCategoryName($secondaryEntity->name);
                $categoryDative = self::CATEGORY_DATIVE_LABELS[$categoryShortName] ?? mb_strtolower($categoryShortName);
                // Genitive for the title ("Maxima duonos gaminių akcijos") —
                // see CATEGORY_GENITIVE_LABELS's comment.
                $categoryGenitive = self::CATEGORY_GENITIVE_LABELS[$categoryShortName] ?? mb_strtolower($categoryShortName);
                // Concrete illustrative item examples (dative plural, e.g.
                // "duonai, bandelėms ir kruasanams" for Duonos gaminiai) —
                // explicit product decision to use hand-written, specific
                // sub-item examples in the description instead of the
                // single, more abstract category-level dative phrase, so a
                // searcher sees real item types, not just the category name
                // repeated. Falls back to the plain category dative if a
                // category has no examples authored yet. mb_ucfirst since
                // it opens the description sentence.
                $categoryItemExamples = mb_ucfirst(self::CATEGORY_ITEM_EXAMPLES[$categoryShortName] ?? $categoryDative);
                // Locative ("Maximoje") only for the one store it's been
                // verified for — see STORE_LOCATIVE_LABELS's comment. Every
                // other store falls back to a plain nominative phrase that's
                // always grammatically safe.
                $storePhrase = isset(self::STORE_LOCATIVE_LABELS[$entity->name])
                    ? "„".self::STORE_LOCATIVE_LABELS[$entity->name]."“"
                    : "„{$entity->name}“ parduotuvėje";

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
                        'meta_title' => $entity->name.' '.$categoryLower.' – palyginkite kainas kitose parduotuvėse',
                        'meta_description' => "Šiuo metu {$entity->name} neturi aktyvių {$categoryLower} akcijų. Peržiūrėkite {$categoryLower} pasiūlymus kitose parduotuvėse.",
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
                        ? "{$entity->name} {$categoryGenitive} akcijos iki {$endDateGenitive}"
                        : "{$entity->name} {$categoryGenitive} akcijos",
                    // Item examples + store lead the sentence, discount %
                    // right after — explicit product decision.
                    'meta_description' => $maxDiscount > 0
                        ? "{$categoryItemExamples} {$storePhrase} – iki {$maxDiscount} % nuolaida. Patikrinkite pasiūlymus, galiojančius iki {$endDateGenitive}!"
                        : "Peržiūrėkite {$categoryLower} pasiūlymus „{$entity->name}“ parduotuvėje.",
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
                $productLower = mb_strtolower($entity->name);

                return [
                    'seo_title' => $entity->name,
                    'seo_description' => $entity->description ?? '',
                    'meta_title' => mb_ucfirst($productLower).' akcija'.($priceTextDesc ? ' – kaina nuo '.$priceTextDesc : '').$storeSuffix,
                    'meta_description' => mb_ucfirst($entity->name).($priceTextDesc ? ' ✔ kaina nuo '.$priceTextDesc.', palygink akcijas prekybos centruose!' : ''),
                ];
            case 'search':
                return [
                    'seo_title' => $entity,
                    'seo_description' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                    'meta_title' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                    'meta_description' => 'Paieškos rezultatai pagal užklausą: '.$entity,
                ];
            case 'all_discounts':
                // Hand-written (not GPT-generated, unlike Store/Category::description) —
                // this is a single global page, not one of hundreds of per-entity rows,
                // so it doesn't need the generation pipeline. Grounded in real search
                // research (WebSearch, Sep 2026) into how people actually look for this
                // kind of page: "akcijos šią savaitę", "savaitės pasiūlymai", "akcijų
                // leidiniai", "palyginti kainas vienoje vietoje", "rask akciją" — mirrors
                // the real competitive space (akcijos.lt, kainos.lt, gudrusis.lt,
                // topakcijos.lt, raskakcija.lt) rather than generic aggregator copy.
                // "Rask akciją" specifically forced in below — confirmed high-volume
                // search phrase for this page type, not just a competitor's brand name.
                return [
                    'seo_title' => 'Visos akcijos ir nuolaidos Lietuvoje',
                    'seo_description' => '<div class="space-y-4">
  <h2 class="text-2xl md:text-3xl font-semibold leading-tight mb-3">Akcijos ir nuolaidos Lietuvoje – visi prekybos tinklai vienoje vietoje</h2>
  <p class="leading-relaxed">Norite greitai rasti akciją, o ne vartytis po kiekvieno prekybos tinklo puslapį atskirai? Čia rasite šios savaitės pasiūlymus iš <a href="/akcijos/maxima">Maxima</a>, <a href="/akcijos/lidl">Lidl</a>, <a href="/akcijos/iki">Iki</a>, <a href="/akcijos/rimi">Rimi</a>, <a href="/akcijos/norfa">Norfa</a> ir kitų parduotuvių sudėtus į vieną vietą – patogu palyginti kainas prieš perkant, o ne po to.</p>
  <p class="leading-relaxed">Akcijos rūšiuojamos pagal kategorijas, tad greičiau rasite tai, ko šiuo metu ieškote: <a href="/akcijos/vaisiai-ir-darzoves">vaisius ir daržoves</a>, <a href="/akcijos/mesa-ir-zuvis">mėsą ir žuvį</a>, <a href="/akcijos/buitine-chemija-valymo-priemones">buitinę chemiją</a>, <a href="/akcijos/kosmetika-ir-higiena">kosmetiką ir higienos prekes</a> ar <a href="/akcijos/namu-ukio-ir-laisvalaikio-prekes">namų ūkio prekes</a>. Kiekvienos kategorijos viduje matysite, kuris tinklas tuo metu siūlo geriausią kainą, be reikalo neapsiperkant kitur.</p>
  <p class="leading-relaxed">Pasiūlymai atnaujinami kiekvieną savaitę, kai prekybos tinklai išleidžia naujus akcijų leidinius – jei ieškote konkretaus tinklo savaitės leidinio, jį rasite ir čia, ir per <a href="/leidiniai">visų parduotuvių leidinių sąrašą</a>.</p>
</div>',
                    'meta_title' => 'Akcijos ir nuolaidos Lietuvoje – rask akciją iš Maxima, Lidl, Iki, Rimi, Norfa',
                    'meta_description' => 'Rask akciją greičiau – visi akcijų leidiniai vienoje vietoje. Naujausi Maxima, Lidl, Iki, Rimi ir Norfa leidiniai, savaitės ir savaitgalio akcijos.',
                ];
            case 'leaflets_index':
                // $entity is the real distinct-store count for this case
                // (passed by getAllLeaflets(), free from data already
                // fetched — see its own comment). Guarded: fall back to the
                // old generic copy rather than ever render "0+ parduotuvių".
                $storeCount = (int) $entity;

                if ($storeCount > 0) {
                    return [
                        'seo_title' => "Visi akcijų leidiniai – {$storeCount}+ parduotuvių",
                        'seo_description' => "Visų parduotuvių akcijų leidiniai ir katalogai vienoje vietoje – {$storeCount}+ prekybos tinklų, tarp jų Maxima, Lidl, Iki, Rimi, Norfa.",
                        // Consumed by leaflets/index.blade.php's H1 (that
                        // view hardcodes its own <h1>, not via seo_title).
                        'leaflet_store_count_label' => $storeCount,
                        'meta_title' => "Akcijų leidiniai – {$storeCount}+ parduotuvių savaitės katalogai",
                        'meta_description' => "Naujausi Maxima, Lidl, Iki, Rimi, Norfa ir kitų {$storeCount}+ parduotuvių akcijų leidiniai vienoje vietoje. Peržiūrėkite savaitės pasiūlymus PDF ir nuotraukose.",
                    ];
                }

                return [
                    'seo_title' => 'Visi akcijų leidiniai',
                    'seo_description' => 'Visų parduotuvių akcijų leidiniai ir katalogai vienoje vietoje – Maxima, Lidl, Iki, Rimi, Norfa ir kiti prekybos tinklai.',
                    'meta_title' => 'Akcijų leidiniai – visų parduotuvių savaitės katalogai',
                    'meta_description' => 'Naujausi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje. Peržiūrėkite savaitės pasiūlymus PDF ir nuotraukose.',
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
        $cacheKey = 'all_leaflets_'.CacheVersion::suffix(['discounts']);

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
        $cacheKey = "store_leaflet_hub_{$storeModel->id}_".CacheVersion::suffix(['discounts']);

        $payload = Cache::remember($cacheKey, 3600, function () use ($storeModel) {
            return [
                'listing_meta' => $this->listingPageMetaService->buildForStore($storeModel),
                'breadcrumbs' => $this->generateBreadcrumbs('store_leaflet', $storeModel),
                'seo' => $this->generateSeoData('store_leaflet', $storeModel),
                'total_offers' => Discount::where('store_id', $storeModel->id)->count(),
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

        $cacheKey = "store_leaflet_{$flyer->id}_".CacheVersion::suffix(['discounts']);

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
            // "Naujausias" instead of "Naujas" for the description only —
            // explicit product decision, H1/title keep "Naujas". Only swaps
            // a genuine leading match; flyers whose own scraped title
            // already names the store (see StoreFlyerTitleBuilder::
            // mentionsStore()) are returned bare with no "Naujas " prefix
            // at all, so this is a no-op for those.
            $descriptionTitle = preg_replace('/^Naujas /', 'Naujausias ', $title, 1);

            return [
                'listing_meta' => $listingMeta,
                'breadcrumbs' => $this->generateBreadcrumbs('store_flyer_detail', $storeModel, $flyer),
                'seo' => [
                    'seo_title' => $title,
                    'seo_description' => "{$title} – {$storeModel->name} akcijų leidinys.",
                    'meta_title' => "{$title}{$dateRangeLabel}",
                    'meta_description' => "{$descriptionTitle} – {$storeModel->name} leidinys, {$pagesCount} psl.{$validityClause}. Peržiūrėkite visus akcijų puslapius.",
                ],
                'total_offers' => Discount::where('store_id', $storeModel->id)->count(),
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

    public function getSitemap()
    {
        $cacheKey = 'sitemap_entries_v7_'.CacheVersion::suffix(['sitemap']);

        return Cache::remember($cacheKey, 3600, function () {
            $freshness = $this->pageFreshnessService->build();
            $defaultLastmod = Carbon::parse($freshness['updated_at'])->format('Y-m-d');

            $stores = \App\Models\Store::query()
                ->whereHas('discounts')
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

            // Just the store overview page now — no more per-city subpages
            // (/parduotuves/{store}/{city} was a doorway-page pattern, see
            // StoreController::show()'s redirect-to-parent handling; the
            // overview already groups and shows every city's locations).
            $storeLocationSlugs = \App\Models\StoreLocation::query()
                ->where('is_active', true)
                ->join('stores', 'stores.id', '=', 'store_locations.store_id')
                ->distinct()
                ->pluck('stores.slug')
                ->values()
                ->all();

            return response()->json([
                'lastmod' => $defaultLastmod,
                'store_location_slugs' => $storeLocationSlugs,
                'stores' => $stores,
                'leaflet_stores' => $stores,
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
        $cacheKey = "sitemap_products_v1_page_{$page}_".CacheVersion::suffix(['sitemap']);

        return Cache::remember($cacheKey, 3600, function () use ($page, $perPage) {
            $products = $this->sitemapProductsQuery()
                ->with('category:id,slug')
                ->select('id', 'slug', 'updated_at', 'category_id')
                ->orderBy('id')
                ->forPage($page, $perPage)
                ->get()
                ->map(function (Product $product) {
                    $categorySlug = $product->category?->slug;
                    $path = $categorySlug ? "{$categorySlug}/{$product->slug}" : $product->slug;

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
    private const MAIN_STORE_SLUGS = ['maxima', 'norfa', 'lidl', 'iki', 'rimi'];

    private function getStoreNamesForProduct($product): string
    {
        $names = $product->discounts
            ->pluck('store')
            ->filter()
            ->unique('id')
            ->sortBy(function ($store) {
                $rank = array_search($store->slug, self::MAIN_STORE_SLUGS, true);

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

    private function generateFavoriteProductCacheKey($slug, $filters = [])
    {
        $key = "favorite_product_{$slug}";

        if (! empty($filters)) {
            $key .= '_'.md5(serialize($filters));
        }

        return $key.'_'.CacheVersion::suffix(['discounts']);
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

    public function toggleFavorite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $productId = $request->input('product_id');

        $favorite = ProductFavorite::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($favorite) {
            $favorite->delete();
        } else {
            ProductFavorite::create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
        }

        $productIds = ProductFavorite::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('product_id');

        return response()->json([
            'status' => $favorite ? 'removed' : 'added',
            'favorites' => $productIds,
        ]);
    }

    public function getFavorites(Request $request)
    {
        $user = $request->user();

        $productIds = ProductFavorite::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->pluck('product_id');

        return response()->json($productIds);
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

    // A handful of root categories are stored as long "X ir Y prekės"-style
    // names — fine as a category-page/breadcrumb name, but they blow a meta
    // description's ~140-150 char budget once two of them have to appear
    // side by side alongside the store name, "iki X%" for each, the offer
    // count, and a CTA. Character-length testing during SEO research showed
    // 2 categories only fit the recommended length with these shortened —
    // a generic "split on comma/'ir'" rule breaks grammar for names with no
    // comma (e.g. splitting "Vaikų ir kūdikių prekės" on " ir " would drop
    // "prekės" entirely), so this is a small curated map instead. Categories
    // not listed here either already have a comma (handled by the fallback
    // explode(',', ...) below) or are already short enough as-is.
    private const SHORT_CATEGORY_LABELS = [
        'Pieno produktai ir kiaušiniai' => 'Pieno produktai',
        'Šaldytas maistas ir ledai' => 'Šaldyti produktai',
        'Vaikų ir kūdikių prekės' => 'Vaikų prekės',
        'Saldumynai ir užkandžiai' => 'Saldumynai',
        'Alkoholiniai gėrimai' => 'Alkoholis',
        'Kosmetika ir higiena' => 'Kosmetika',
        'Namų ūkio ir laisvalaikio prekės' => 'Namų prekės',
    ];

    // Lithuanian dative plural case ("akcijos duonos gaminiams", not the
    // grammatically broken "akcija duonos gaminiai" nominative-juxtaposition
    // the old store_category seo_title produced) for the 16 root
    // categories. Same reasoning as KeywordPage::grammar_dative
    // (app/Models/KeywordPage.php:18) — with this few, fixed categories and
    // no existing grammar column on `categories`, a small hand-verified map
    // is the right level of effort, not a migration or a declension
    // algorithm. Keyed by the SAME short name shortenCategoryName() already
    // produces, so one lookup covers both the shortened and full-name cases.
    private const CATEGORY_DATIVE_LABELS = [
        'Vaisiai ir daržovės' => 'vaisiams ir daržovėms',
        'Pieno produktai' => 'pieno produktams',
        'Duonos gaminiai' => 'duonos gaminiams',
        'Mėsa ir žuvis' => 'mėsai ir žuviai',
        'Šaldyti produktai' => 'šaldytiems produktams',
        'Bakalėja' => 'bakalėjai',
        'Vaikų prekės' => 'vaikų prekėms',
        'Saldumynai' => 'saldumynams',
        'Gėrimai' => 'gėrimams',
        'Nealkoholiniai gėrimai' => 'nealkoholiniams gėrimams',
        'Alkoholis' => 'alkoholiui',
        'Kosmetika' => 'kosmetikai',
        'Buitinė chemija' => 'buitinei chemijai',
        'Namų prekės' => 'namų prekėms',
        'Gyvūnų prekės' => 'gyvūnų prekėms',
        'Augalai' => 'augalams',
    ];

    // Lithuanian genitive plural case ("duonos gaminių akcijos") — used in
    // the store_category title. Same "small fixed set, hand-verified"
    // reasoning as CATEGORY_DATIVE_LABELS; the two maps coexist because
    // the title (genitive) and H1 (dative) need different cases for the
    // same category.
    private const CATEGORY_GENITIVE_LABELS = [
        'Vaisiai ir daržovės' => 'vaisių ir daržovių',
        'Pieno produktai' => 'pieno produktų',
        'Duonos gaminiai' => 'duonos gaminių',
        'Mėsa ir žuvis' => 'mėsos ir žuvies',
        'Šaldyti produktai' => 'šaldytų produktų',
        'Bakalėja' => 'bakalėjos',
        'Vaikų prekės' => 'vaikų prekių',
        'Saldumynai' => 'saldumynų',
        'Gėrimai' => 'gėrimų',
        'Nealkoholiniai gėrimai' => 'nealkoholinių gėrimų',
        'Alkoholis' => 'alkoholio',
        'Kosmetika' => 'kosmetikos',
        'Buitinė chemija' => 'buitinės chemijos',
        'Namų prekės' => 'namų prekių',
        'Gyvūnų prekės' => 'gyvūnų prekių',
        'Augalai' => 'augalų',
    ];

    // A store's locative case ("Maximoje" — "in/at Maxima") only for the
    // one store explicitly verified — NOT a full 47-store map. Several of
    // the other 46 stores are foreign/brand names with no safe, verified
    // Lithuanian declension (Ikea, Jysk, AVS, Thomas Philipps, ePromo);
    // guessing a form like "Jyske"/"Ikeoje" risks reading as broken rather
    // than natural, so every other store instead falls back to plain
    // nominative + "parduotuvėje" ("Iki" parduotuvėje") in the description,
    // which is always grammatically safe. Add more entries here only once
    // each one is actually verified, not guessed.
    private const STORE_LOCATIVE_LABELS = [
        'Maxima' => 'Maximoje',
    ];

    // Concrete, hand-written illustrative item-type examples (dative
    // plural, natural "X, Y ir Z" list form) per root category, e.g.
    // "duonai, bandelėms ir kruasanams" for Duonos gaminiai — used in the
    // store_category meta description so it names specific kinds of items
    // instead of just repeating the category name. Explicit product
    // decision over pulling real per-discount product names from the DB:
    // these are stable, always-representative examples of what the
    // category contains, not tied to whichever products happen to be
    // discounted right now. Same "small fixed set, hand-verified"
    // reasoning as CATEGORY_DATIVE_LABELS.
    private const CATEGORY_ITEM_EXAMPLES = [
        'Vaisiai ir daržovės' => 'vaisiams, daržovėms ir žalumynams',
        'Pieno produktai' => 'pienui, sūriams ir jogurtams',
        'Duonos gaminiai' => 'duonai, bandelėms ir kruasanams',
        'Mėsa ir žuvis' => 'mėsai, žuviai ir dešrelėms',
        'Šaldyti produktai' => 'šaldytoms daržovėms, picoms ir ledams',
        'Bakalėja' => 'makaronams, ryžiams ir konservams',
        'Vaikų prekės' => 'sauskelnėms, maisto mišiniams ir žaislams',
        'Saldumynai' => 'šokoladui, saldainiams ir traškučiams',
        'Gėrimai' => 'kavai, arbatai ir sultims',
        'Nealkoholiniai gėrimai' => 'vandeniui, limonadui ir sultims',
        'Alkoholis' => 'vynui, alui ir degtinei',
        'Kosmetika' => 'šampūnams, kremams ir dantų pastoms',
        'Buitinė chemija' => 'valikliams, skalbikliams ir servetėlėms',
        'Namų prekės' => 'indams, žvakėms ir tekstilei',
        'Gyvūnų prekės' => 'šunų ir kačių maistui bei priežiūros priemonėms',
        'Augalai' => 'gėlėms, trąšoms ir vazonams',
    ];

    // Shared by getTopDiscountCategoriesForStore() and the store_category
    // SEO case — a handful of root categories are stored as long "X ir Y
    // prekės"-style names (see SHORT_CATEGORY_LABELS's own comment); this
    // is the one place that shortening logic lives now.
    private function shortenCategoryName(string $name): string
    {
        $name = trim($name);

        return self::SHORT_CATEGORY_LABELS[$name] ?? trim(explode(',', $name)[0]);
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
                $rank = array_search($store->slug, self::MAIN_STORE_SLUGS, true);

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
