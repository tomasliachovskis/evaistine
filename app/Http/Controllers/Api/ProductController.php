<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StoreCategoryDescription;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Models\Discount;
use App\Models\SearchResult;
use App\Models\ProductFavorite;
use App\Services\DiscountResponseFormatter;
use App\Services\HomePageMetaService;
use App\Services\HomePageSectionsService;
use App\Services\ListingPageMetaService;
use App\Services\MeilisearchService;
use App\Services\PageFreshnessService;
use App\Services\StoresPageMetaService;
use Carbon\Carbon;

class ProductController extends Controller
{
    protected $formatter;
    protected $meilisearchService;
    protected $listingPageMetaService;
    protected $storesPageMetaService;
    protected $homePageMetaService;
    protected $homePageSectionsService;
    protected $pageFreshnessService;

    public function __construct(
        DiscountResponseFormatter $formatter,
        MeilisearchService $meilisearchService,
        ListingPageMetaService $listingPageMetaService,
        StoresPageMetaService $storesPageMetaService,
        HomePageMetaService $homePageMetaService,
        HomePageSectionsService $homePageSectionsService,
        PageFreshnessService $pageFreshnessService
    ) {
        $this->formatter = $formatter;
        $this->meilisearchService = $meilisearchService;
        $this->listingPageMetaService = $listingPageMetaService;
        $this->storesPageMetaService = $storesPageMetaService;
        $this->homePageMetaService = $homePageMetaService;
        $this->homePageSectionsService = $homePageSectionsService;
        $this->pageFreshnessService = $pageFreshnessService;
    }

    public function getDiscounts($storeOrCategory, $category = null)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateDiscountsCacheKey(
            $storeOrCategory,
            $category,
            $this->normalizeFiltersForCacheKey($filters, $storeOrCategory, $category)
        );

        return Cache::tags(['discounts', $storeOrCategory, $category ?: 'all'])
            ->remember($cacheKey, 3600, function () use ($storeOrCategory, $category, $filters) {
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

        return Cache::tags(['discounts', 'all'])
            ->remember($cacheKey, 3600, function () use ($filters) {
                $query = Discount::with(['product', 'store']);
                $discounts = $this->buildDiscountQuery($query, $filters)->paginate(24);

                return response()->json([
                    'data' => $this->formatter->format($discounts),
                    'breadcrumbs' => $this->generateBreadcrumbs('all_discounts'),
                    'seo' => $this->generateSeoData('all_discounts')
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
        $query = $query->with(['product.category', 'store']);

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
            if (!empty($categorySlugs)) {
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

        if (!$applyOrder) {
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

        return $query->orderByRaw('FIELD(discounts.id, ' . $placeholders . ')', $discountIds);
    }

    private function hasExplicitOrder(): bool
    {
        if (!request()->has('order')) {
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
        $categories = \App\Models\Category::whereNull('parent_id')
            ->select('id', 'name', 'slug', 'description', 'hide')
            ->withCount([
                'discounts' => function ($query) {
                    $query->select(\DB::raw('count(distinct discounts.id)'));
                }
            ])->get();

        return response()->json($categories);
    }

    public function getStores()
    {
        $stores = \App\Models\Store::select('id', 'name', 'slug')
            ->withCount([
                'discounts' => function ($query) {
                    $query->select(\DB::raw('count(distinct discounts.id)'));
                }
            ])->get();

        return response()->json([
            'data' => $this->storesPageMetaService->formatStore($stores),
            'page_meta' => $this->storesPageMetaService->buildPageMeta($stores),
        ]);
    }

    public function search(Request $request, $query)
    {
        $filters = $this->getFilters();
        $cacheKey = 'search_' . md5($query . serialize($this->normalizeFiltersForCacheKey($filters)));

        return Cache::tags(['discounts', 'search'])
            ->remember($cacheKey, 1800, function () use ($query, $filters) {
                try {
                    $page = $filters['page'] ?? 1;
                    $perPage = 24;

                    $meilisearchFilters = [];
                    if ($filters['store']) {
                        $storeSlugs = explode(',', $filters['store']);
                        $storeIds = \App\Models\Store::whereIn('slug', $storeSlugs)->pluck('id')->toArray();
                        if (!empty($storeIds)) {
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

                        if (!$explicitOrder) {
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
                        \Log::warning('Failed to store search result: ' . $e->getMessage());
                    }

                    return response()->json([
                        'data' => $this->formatter->format($paginator),
                        'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
                        'seo' => $this->generateSeoData('search', $query, '-')
                    ]);
                } catch (\Exception $e) {
                    try {
                        SearchResult::create([
                            'query' => $query,
                            'total_results' => 0,
                        ]);
                    } catch (\Exception $saveException) {
                        \Log::warning('Failed to store search result: ' . $saveException->getMessage());
                    }

                    $explicitOrder = $this->hasExplicitOrder();

                    $queryQb = Discount::searchByProductName($query)
                        ->with(['product', 'store']);

                    $discounts = $this->buildDiscountQuery($queryQb, $filters, $explicitOrder)->paginate(24);

                    return response()->json([
                        'data' => $this->formatter->format($discounts),
                        'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
                        'seo' => $this->generateSeoData('search', $query, '-')
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

        return Cache::tags(['discounts', 'favorites', 'product', $slug])
            ->remember($cacheKey, 86400, function () use ($slug, $filters) {
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

        return Cache::tags(['discounts', 'favorites', 'category', $id])
            ->remember($cacheKey, 7200, function () use ($id, $filters) {
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
        $cacheKey = $this->generateFavoriteHomeCacheKey($this->normalizeFiltersForCacheKey($filters)) . '_v9';

        return Cache::tags(['discounts', 'favorites', 'home'])
            ->remember($cacheKey, 7200, function () {
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

        $cacheKey = "product_slug_{$slug}";

        return Cache::tags(['discounts', 'product', $slug])
            ->remember($cacheKey, 86400, function () use ($slug) {
                $product = Product::where('slug', $slug)
                    ->with([
                        'discounts.store',
                        'category'
                    ])
                    ->firstOrFail();

                return response()->json([
                    'data' => $this->formatter->format($product->discounts),
                    'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
                    'seo' => $this->generateSeoData('product', $product)
                ]);
            });
    }

    public static function productWithSimilarCacheKey(string $slug): string
    {
        return "product_with_similar_v4_{$slug}";
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

        $similarDiscounts = Discount::query()
            ->join('products', 'discounts.product_id', '=', 'products.id')
            ->where('products.category_id', $product->category_id)
            ->where('products.slug', '!=', $slug)
            ->select('discounts.*')
            ->with(['product.category', 'product.discounts.store', 'store'])
            ->orderByRaw("RAND({$randomSeed})")
            ->limit(7)
            ->get();

        if ($product->discounts->isEmpty()) {
            $data = $this->formatter->formatProduct($product);
        } else {
            $data = $this->formatter->formatProductDiscounts($product);
        }

        $responseData = [
            'data' => $data,
            'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
            'seo' => $this->generateSeoData('product', $product),
            'similar' => $this->formatter->formatList($similarDiscounts)
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
                'type' => 'home'
            ]
        ];

        switch ($type) {
            case 'store_leaflet':
                $words = $this->getStoreLeafletWords($entity->slug);
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/' . $entity->slug,
                    'type' => 'store',
                ];
                $breadcrumbs[] = [
                    'name' => ucfirst($words['nominative']),
                    'slug' => 'leidinys/' . $entity->slug,
                    'type' => 'store_leaflet',
                ];
                break;
            case 'store':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/' . $entity->slug,
                    'type' => 'store'
                ];
                break;
            case 'category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/' . $entity->slug,
                    'type' => 'category'
                ];
                break;
            case 'store_category':
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/' . $entity->slug,
                    'type' => 'store'
                ];
                $breadcrumbs[] = [
                    'name' => $secondaryEntity->name,
                    'slug' => 'akcijos/' . $entity->slug . '/' . $secondaryEntity->slug,
                    'type' => 'category'
                ];
                break;
            case 'product':
                if ($entity->category) {
                    $breadcrumbs[] = [
                        'name' => $entity->category->name,
                        'slug' => 'akcijos/' . $entity->category->slug,
                        'type' => 'category'
                    ];
                }
                $breadcrumbs[] = [
                    'name' => $entity->name,
                    'slug' => 'akcijos/' . ($entity->category ? $entity->category->slug . '/' : '') . $entity->slug,
                    'type' => 'product'
                ];
                break;
            case 'search':
                $breadcrumbs[] = [
                    'name' => 'Paieška',
                    'slug' => 'akcijos/paieska/' . $entity,
                    'type' => 'search'
                ];
            case 'all_discounts':
                $breadcrumbs[] = [
                    'name' => 'Visos akcijos',
                    'slug' => 'akcijos',
                    'type' => 'all_discounts'
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

                return [
                    'seo_title' => $entity->name . ' akcijos prekybos centruose',
                    'seo_description' => $entity->description,
                    'meta_title' => $entity->name . " akcijos – pigiausios kainos, iki {$maxDiscount}% nuolaidos",
                    'meta_description' => "Palyginkite {$lowerName} akcijas prekybos centruose – {$countLabel}+ pasiūlymų iš {$storeNames}. Iki {$maxDiscount}% nuolaidos šią savaitę!",
                ];
            case 'store_leaflet':
                $count = $this->getDiscountCountForStore($entity);
                $countLabel = $this->formatCount($count);
                $validity = $this->resolveStoreValidity($entity);
                $validityLabel = $this->formatValidityRangeLabel($validity['valid_from'], $validity['valid_to']);
                $words = $this->getStoreLeafletWords($entity->slug);
                $storeUpper = mb_strtoupper($entity->name);
                $validityLong = $this->pageFreshnessService->formatLtDate($validity['valid_from'], true)
                    . ' – '
                    . $this->pageFreshnessService->formatLtDate($validity['valid_to'], true);

                return [
                    'seo_title' => $entity->name . ' ' . $words['nominative'],
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

                return [
                    'seo_title' => $entity->name . ' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => "{$storeUpper} akcijos – {$countLabel}+ pasiūlymų" . ($maxDiscount > 0 ? ", iki -{$maxDiscount}%" : ''),
                    'meta_description' => "Visos {$entity->name} akcijos ir nuolaidos. Filtruokite, rūšiuokite ir palyginkite kainas. Naujas {$words['nominative']}: /leidinys/{$entity->slug}",
                ];
            case 'store_category':
                $count = $this->getDiscountCountForStoreCategory($entity, $secondaryEntity);
                $maxDiscount = $this->getMaxDiscountForStoreCategory($entity, $secondaryEntity);
                $countLabel = $this->formatCount($count);
                $categoryLower = mb_strtolower($secondaryEntity->name);

                $storeCategoryDescription = StoreCategoryDescription::where('store_id', $entity->id)
                    ->where('category_id', $secondaryEntity->id)
                    ->first();

                $seoData = [
                    'seo_title' => $entity->name . ' akcija ' . $categoryLower,
                    'seo_description' => "",
                    'meta_title' => mb_strtoupper($entity->name) . ' akcija ' . $categoryLower . ' – iki ' . $maxDiscount . '% nuolaidos',
                    'meta_description' => "Naujausios {$entity->name} {$categoryLower} akcijos – iki {$maxDiscount}% nuolaidos, {$countLabel}+ prekių. Pasiūlymai galioja ribotą laiką parduotuvėse ir internetu.",
                ];

                if ($storeCategoryDescription && $storeCategoryDescription->top_products_html) {
                    $seoData['seo_description'] = $storeCategoryDescription->top_products_html;
                }

                return $seoData;
            case 'product':
                $minPrice = $entity->discounts->min('discounted_price');
                $formattedPrice = $minPrice ? (floor($minPrice) == $minPrice ? number_format($minPrice, 0, '.', '') : number_format($minPrice, 2, '.', '')) : null;
                $priceTextDesc = $formattedPrice ? $formattedPrice . ' €' : '';
                $storeNames = $this->getStoreNamesForProduct($entity);
                $storeSuffix = $storeNames ? " ({$storeNames})" : '';
                $productLower = mb_strtolower($entity->name);

                return [
                    'seo_title' => $entity->name,
                    'seo_description' => $entity->description ?? '',
                    'meta_title' => mb_ucfirst($productLower) . ' akcija' . ($priceTextDesc ? ' – kaina nuo ' . $priceTextDesc : '') . $storeSuffix,
                    'meta_description' => mb_ucfirst($entity->name) . ($priceTextDesc ? ' ✔ kaina nuo ' . $priceTextDesc . ', palygink akcijas prekybos centruose!' : ''),
                ];
            case 'search':
                return [
                    'seo_title' => $entity,
                    'seo_description' => 'Paieškos rezultatai pagal užklausą: ' . $entity,
                    'meta_title' => 'Paieškos rezultatai pagal užklausą: ' . $entity,
                    'meta_description' => 'Paieškos rezultatai pagal užklausą: ' . $entity,
                ];
            case 'all_discounts':
                return [
                    'seo_title' => 'Akcijos ir nuolaidos Lietuvoje',
                    'seo_description' => 'Visi akcijų leidiniai vienoje vietoje – Maxima, Lidl, Iki, Rimi, Norfa ir kiti prekybos tinklai.',
                    'meta_title' => 'Akcijos ir nuolaidos Lietuvoje – Maxima, Lidl, Iki, Rimi, Norfa',
                    'meta_description' => 'Visi akcijų leidiniai vienoje vietoje. Naujausi Maxima, Lidl, Iki, Rimi ir Norfa leidiniai, savaitės ir savaitgalio akcijos.',
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

    public function getStoreLeafletHub(string $store)
    {
        $storeModel = \App\Models\Store::where('slug', $store)->firstOrFail();
        $totalOffers = Discount::where('store_id', $storeModel->id)->count();

        return response()->json([
            'listing_meta' => $this->listingPageMetaService->buildForStore($storeModel),
            'breadcrumbs' => $this->generateBreadcrumbs('store_leaflet', $storeModel),
            'seo' => $this->generateSeoData('store_leaflet', $storeModel),
            'total_offers' => $totalOffers,
        ]);
    }

    public function getSitemap()
    {
        return Cache::tags(['sitemap'])->remember('sitemap_entries_v2', 3600, function () {
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

            $products = Product::query()
                ->whereHas('discounts')
                ->with('category:id,slug')
                ->select('slug', 'updated_at', 'category_id')
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

            return response()->json([
                'lastmod' => $defaultLastmod,
                'stores' => $stores,
                'leaflet_stores' => $stores,
                'categories' => $categories,
                'products' => $products,
                'blog_posts' => $blogPosts,
            ]);
        });
    }

    private function resolveStoreValidity($store): array
    {
        if ($store->flyer_valid_from && $store->flyer_valid_to) {
            return [
                'valid_from' => $store->flyer_valid_from->format('Y-m-d'),
                'valid_to' => $store->flyer_valid_to->format('Y-m-d'),
            ];
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
            . '–'
            . $this->pageFreshnessService->formatLtDate($validTo);
    }

    private function getStoreNamesForProduct($product): string
    {
        $names = $product->discounts
            ->pluck('store.name')
            ->filter()
            ->unique()
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

        return implode(', ', $names) . ', ' . $last;
    }

    private function generateDiscountsCacheKey($storeOrCategory, $category = null, $filters = [])
    {
        $key = "discounts_{$storeOrCategory}";

        if ($category) {
            $key .= "_{$category}";
        }

        if (!empty($filters)) {
            $key .= "_" . md5(serialize($filters));
        }

        return $key;
    }

    private function generateAllDiscountsCacheKey($filters = [])
    {
        return "all_discounts_" . md5(serialize($filters));
    }

    private function generateFavoriteProductCacheKey($slug, $filters = [])
    {
        $key = "favorite_product_{$slug}";

        if (!empty($filters)) {
            $key .= "_" . md5(serialize($filters));
        }

        return $key;
    }

    private function generateFavoriteCategoryCacheKey($id, $filters = [])
    {
        $key = "favorite_category_{$id}";

        if (!empty($filters)) {
            $key .= "_" . md5(serialize($filters));
        }

        return $key;
    }

    private function generateFavoriteHomeCacheKey($filters = [])
    {
        $key = "favorite_home";

        if (!empty($filters)) {
            $key .= "_" . md5(serialize($filters));
        }

        return $key;
    }

    public function toggleFavorite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
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
            'favorites' => $productIds
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
                'store_totals' => []
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

        $storeTotals = $this->calculateStoreTotals($productIds, $discounts);

        return response()->json([
            'products' => $formattedProducts,
            'store_totals' => $storeTotals
        ]);
    }

    private function calculateStoreTotals($productIds, $discounts)
    {
        $storeTotals = [];
        $storeInfo = [];

        foreach ($productIds as $productId) {
            $productDiscounts = $discounts->where('product_id', $productId);

            $storePrices = [];
            foreach ($productDiscounts as $discount) {
                $storeId = $discount->store_id;

                if (!isset($storeInfo[$storeId])) {
                    $storeInfo[$storeId] = $discount->store;
                }

                if (!isset($storePrices[$storeId])) {
                    $storePrices[$storeId] = $discount->discounted_price;
                } else {
                    $storePrices[$storeId] = min($storePrices[$storeId], $discount->discounted_price);
                }
            }

            foreach ($storePrices as $storeId => $price) {
                if (!isset($storeTotals[$storeId])) {
                    $storeTotals[$storeId] = [
                        'store_id' => $storeId,
                        'store_name' => $storeInfo[$storeId]->name,
                        'total_price' => 0,
                        'product_count' => 0
                    ];
                }
                $storeTotals[$storeId]['total_price'] += $price;
                $storeTotals[$storeId]['product_count']++;
            }
        }

        return array_values($storeTotals);
    }

    private function generateRandomSeed($slug)
    {
        return crc32($slug . date('Y-m-d'));
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
        return implode(', ', $stores) . ' ir ' . $lastStore;
    }
}

