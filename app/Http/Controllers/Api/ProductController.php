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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
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

    public function getBestDiscountsByCategory()
    {
        $cacheKey = 'best_discounts_by_category_'.CacheVersion::suffix(['discounts']);

        return Cache::remember($cacheKey, 3600, function () {
            return response()->json($this->buildBestByCategorySections(null));
        });
    }

    public function getBestDiscountsByCategoryForStore($storeSlug)
    {
        $store = \App\Models\Store::where('slug', $storeSlug)->first();

        if (! $store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $cacheKey = "best_discounts_by_category_store_{$store->id}_".CacheVersion::suffix(['discounts']);

        return Cache::remember($cacheKey, 3600, function () use ($store) {
            return response()->json($this->buildBestByCategorySections($store->id));
        });
    }

    /**
     * Fixed display order for the /akcijos and /akcijos/{store} category carousels,
     * hand-picked for shopper interest rather than raw inventory count: everyday
     * food staples first (widest, most frequent deal-hunting audience), then
     * household/personal care, then narrower-audience categories last. Any
     * category not listed here falls back to the end, in name order.
     */
    private const CATEGORY_CAROUSEL_ORDER = [
        'bakaleja',
        'gerimai-kava-arbata',
        'pieno-produktai-ir-kiausiniai',
        'mesa-ir-zuvis',
        'duonos-gaminiai',
        'saldumynai-ir-uzkandziai',
        'saldytas-maistas-ir-ledai',
        'vaisiai-ir-darzoves',
        'kosmetika-ir-higiena',
        'buitine-chemija-valymo-priemones',
        'namu-ukio-ir-laisvalaikio-prekes',
        'gyvunu-prekes',
        'vaiku-ir-kudikiu-prekes',
        'augalai-geles',
    ];

    private function buildBestByCategorySections(?int $storeId, int $limit = 8)
    {
        $categories = Category::whereNull('parent_id')
            ->where('hide', false)
            ->withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->sortBy(function (Category $category) {
                $position = array_search($category->slug, self::CATEGORY_CAROUSEL_ORDER, true);

                return $position === false ? count(self::CATEGORY_CAROUSEL_ORDER) : $position;
            })
            ->values();

        return $categories->map(function (Category $category) use ($storeId, $limit) {
            $discounts = $this->homeDealPoolService->bestForCategory($category->id, $limit, $storeId);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'discounts' => $this->formatter->formatList($discounts),
            ];
        })->filter(fn (array $section) => count($section['discounts']) > 0)->values();
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
            $discounts = $this->buildDiscountQuery($query, $filters)->paginate(24);

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

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(24);

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

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(24);

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

            $fallbackDiscounts = $this->buildDiscountQuery($fallbackQuery, $filters)->paginate(24);

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
                ->get(['city', 'address', 'lat', 'lng', 'phone', 'hours']);

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
                $perPage = 24;

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
                        'total_results' => 0,
                    ]);
                } catch (\Exception $saveException) {
                    \Log::warning('Failed to store search result: '.$saveException->getMessage());
                }

                $explicitOrder = $this->hasExplicitOrder();

                $queryQb = Discount::searchByProductName($query)
                    ->with(['product', 'store']);

                $discounts = $this->buildDiscountQuery($queryQb, $filters, $explicitOrder)->paginate(24);

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
        // Bump this suffix whenever the cached payload shape changes — this
        // key is NOT wrapped in CacheVersion::suffix(['discounts']) like most
        // other caches here, so cache:clear-discounts does not invalidate it.
        // Its 7-day TTL means a stale shape (e.g. an image_url path change)
        // would otherwise linger for up to a week after deploy.
        return "product_with_similar_v10_{$slug}";
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
                $stemDiscounts = $this->meilisearchService
                    ->findSimilarDiscounts($nameStem, $product->id, $product->category_id, $similarLimit)
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
                $maxDiscount = $this->getMaxDiscountForCategory($entity);
                $storeNames = $this->getStoreNamesForCategory($entity);
                $countLabel = $this->formatCount($count);
                $lowerName = mb_strtolower($entity->name);

                // Some listings are full-catalog (price-only, no discount_percent
                // on any row) rather than discount-only — MAX(discount_percent)
                // is then NULL/0 across the board, and an unconditional "iki 0%
                // nuolaidos" would misrepresent real priced products as a fake
                // zero-value deal. Only claim a discount percentage when one
                // genuinely exists.
                return [
                    'seo_title' => $entity->name.' akcijos prekybos centruose',
                    'seo_description' => $entity->description,
                    'meta_title' => $entity->name.' akcijos – pigiausios kainos'.($maxDiscount > 0 ? ", iki {$maxDiscount}% nuolaidos" : ''),
                    'meta_description' => "Palyginkite {$lowerName} akcijas prekybos centruose – {$countLabel}+ pasiūlymų iš {$storeNames}.".($maxDiscount > 0 ? " Iki {$maxDiscount}% nuolaidos šią savaitę!" : ''),
                ];
            case 'store_leaflet':
                $count = $this->getDiscountCountForStore($entity);
                $countLabel = $this->formatCount($count);
                $validity = $this->resolveStoreValidity($entity);
                $validityLabel = $this->formatValidityRangeLabel($validity['valid_from'], $validity['valid_to']);
                $words = $this->getStoreLeafletWords($entity->slug);
                $storeUpper = mb_strtoupper($entity->name);
                $validityLong = $this->pageFreshnessService->formatLtDate($validity['valid_from'], true)
                    .' – '
                    .$this->pageFreshnessService->formatLtDate($validity['valid_to'], true);

                return [
                    'seo_title' => $entity->name.' '.$words['nominative'],
                    'seo_description' => $entity->description,
                    'meta_title' => "{$storeUpper} {$words['nominative']} – naujas savaitės leidinys, galioja {$validityLabel}",
                    'meta_description' => "Naujausias {$entity->name} akcijų {$words['nominative']} ir katalogas. {$countLabel}+ akcijų, PDF, savaitgalio pasiūlymai. Galioja {$validityLong}.",
                ];
            case 'store':
                $count = $this->getDiscountCountForStore($entity);
                $countLabel = $this->formatCount($count);
                $maxDiscount = (int) round(Discount::where('store_id', $entity->id)->max('discount_percent') ?? 0);
                $storeUpper = mb_strtoupper($entity->name);
                $words = $this->getStoreLeafletWords($entity->slug);
                // SXO audit finding: SERP competitors for "{store} akcijos šią
                // savaitę" all bake a date range into their title, this page's
                // title had none (evergreen-looking, no freshness signal) —
                // reuse the same validity resolution /leidinys/{store} already
                // uses so both page types read consistently.
                $validity = $this->resolveStoreValidity($entity);
                $validityLabel = $this->formatValidityRangeLabel($validity['valid_from'], $validity['valid_to']);

                return [
                    'seo_title' => $entity->name.' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => "{$storeUpper} akcijos {$validityLabel} – {$countLabel}+ pasiūlymų".($maxDiscount > 0 ? ", iki -{$maxDiscount}%" : ''),
                    'meta_description' => "Visos {$entity->name} akcijos ir nuolaidos (galioja {$validityLabel}). Filtruokite, rūšiuokite ir palyginkite kainas. Naujas {$words['nominative']}: /leidinys/{$entity->slug}",
                ];
            case 'store_category':
                $count = $this->getDiscountCountForStoreCategory($entity, $secondaryEntity);
                $maxDiscount = $this->getMaxDiscountForStoreCategory($entity, $secondaryEntity);
                $countLabel = $this->formatCount($count);
                $categoryLower = mb_strtolower($secondaryEntity->name);

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
                        'meta_title' => mb_strtoupper($entity->name).' '.$categoryLower.' – palyginkite kainas kitose parduotuvėse',
                        'meta_description' => "Šiuo metu {$entity->name} neturi aktyvių {$categoryLower} akcijų. Peržiūrėkite {$categoryLower} pasiūlymus kitose parduotuvėse.",
                    ];

                    return $seoData;
                }

                // Same full-catalog case as the 'category' branch above: real
                // products can exist here with no discount_percent on any of
                // them (price-only listing), so only claim a percentage when
                // one actually exists rather than defaulting to "iki 0%".
                $seoData = [
                    'seo_title' => $entity->name.' akcija '.$categoryLower,
                    'seo_description' => '',
                    'meta_title' => mb_strtoupper($entity->name).' akcija '.$categoryLower.($maxDiscount > 0 ? ' – iki '.$maxDiscount.'% nuolaidos' : ''),
                    'meta_description' => "Naujausios {$entity->name} {$categoryLower} akcijos".($maxDiscount > 0 ? " – iki {$maxDiscount}% nuolaidos" : '').", {$countLabel}+ prekių. Pasiūlymai galioja ribotą laiką parduotuvėse ir internetu.",
                ];

                if ($storeCategoryDescription && $storeCategoryDescription->top_products_html) {
                    $seoData['seo_description'] = $storeCategoryDescription->top_products_html;
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
                return [
                    'seo_title' => 'Akcijos ir nuolaidos Lietuvoje',
                    'seo_description' => 'Visi akcijų leidiniai vienoje vietoje – Maxima, Lidl, Iki, Rimi, Norfa ir kiti prekybos tinklai.',
                    'meta_title' => 'Akcijos ir nuolaidos Lietuvoje – Maxima, Lidl, Iki, Rimi, Norfa',
                    'meta_description' => 'Visi akcijų leidiniai vienoje vietoje. Naujausi Maxima, Lidl, Iki, Rimi ir Norfa leidiniai, savaitės ir savaitgalio akcijos.',
                ];
            case 'leaflets_index':
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

            return [
                'leaflets' => $leaflets,
                'total' => count($leaflets),
                'breadcrumbs' => $this->generateBreadcrumbs('leaflets_index'),
                'seo' => $this->generateSeoData('leaflets_index'),
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

            return [
                'listing_meta' => $listingMeta,
                'breadcrumbs' => $this->generateBreadcrumbs('store_flyer_detail', $storeModel, $flyer),
                'seo' => [
                    'seo_title' => $title,
                    'seo_description' => "{$title} – {$storeModel->name} akcijų leidinys.",
                    'meta_title' => $title,
                    'meta_description' => "{$title} – peržiūrėkite visus {$storeModel->name} leidinio puslapius.",
                ],
                'total_offers' => Discount::where('store_id', $storeModel->id)->count(),
            ];
        });

        return response()->json($payload);
    }

    private function sitemapProductsQuery()
    {
        // Only products with a currently active discount — not "ever had a
        // discount or price-history row" (the old ->orWhereHas('discountHistories')
        // matched almost any product, active or not). 36k/50k products have
        // no active discount at any given time and their pages 301-redirect
        // to the category listing; keeping them in the sitemap indefinitely
        // fed Google ~35k "Page with redirect" entries it kept re-crawling.
        return Product::query()
            ->whereHas('discounts', function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
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

    private function getStoreNamesForCategory($category)
    {
        $storeIds = Discount::whereHas('product', function ($q) use ($category) {
            $q->where('category_id', $category->id);
        })->distinct()->pluck('store_id');

        $stores = \App\Models\Store::whereIn('id', $storeIds)->pluck('name')->toArray();

        if (empty($stores)) {
            return '';
        }

        if (count($stores) === 1) {
            return $stores[0];
        }

        $lastStore = array_pop($stores);

        return implode(', ', $stores).' ir '.$lastStore;
    }
}
