<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use App\Models\DiscountTemp;
use App\Models\Discount;
use App\Services\DiscountResponseFormatter;

class ProductController extends Controller
{
    protected $formatter;

    public function __construct(DiscountResponseFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function getDiscounts($storeOrCategory, $category = null)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateDiscountsCacheKey($storeOrCategory, $category, $filters);

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
        $cacheKey = $this->generateAllDiscountsCacheKey($filters);

        return Cache::tags(['discounts', 'all'])
            ->remember($cacheKey, 3600, function () use ($filters) {
                $query = Discount::with(['product', 'store']);
                $discounts = $this->buildDiscountQuery($query, $filters)->paginate(25);

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

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(25);

        return response()->json([
            'data' => $this->formatter->format($discounts),
            'breadcrumbs' => $this->generateBreadcrumbs($entityType, $entity),
            'seo' => $this->generateSeoData($entityType, $entity)
        ]);
    }

    private function getDiscountsByStoreAndCategory($store, $category, $filters)
    {
        $store = \App\Models\Store::where('slug', $store)->firstOrFail();
        $category = \App\Models\Category::where('slug', $category)->firstOrFail();

        $query = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(25);

        return response()->json([
            'data' => $this->formatter->format($discounts),
            'breadcrumbs' => $this->generateBreadcrumbs('store_category', $store, $category),
            'seo' => $this->generateSeoData('store_category', $store, $category)
        ]);
    }

    private function getFilters()
    {
        return [
            'order' => request()->get('order', 'popular'),
            'card' => request()->get('card'),
            'plus' => request()->get('plus'),
            'page' => request()->get('page'),
            'store' => request()->get('store'),
        ];
    }

    private function buildDiscountQuery($query, $filters)
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

        return $this->applySorting($query, $filters['order']);
    }

    private function applySorting($query, $order)
    {
        $query = $query->leftJoin('products', 'discounts.product_id', '=', 'products.id')
            ->select('discounts.*')
            ->orderByRaw('CASE WHEN products.category_id IN (1, 52, 121, 352, 380) THEN 0 ELSE 1 END');

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
            default:
                return $query;
        }
    }

    public function getCategories()
    {
        $categories = \App\Models\Category::whereNull('parent_id')
            ->select('id', 'name', 'slug')
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

        return response()->json($stores);
    }

    public function search(Request $request, $query)
    {
        $filters = $this->getFilters();
        $cacheKey = "search_" . md5($query . serialize($filters));

        return Cache::tags(['discounts', 'search'])
            ->remember($cacheKey, 1800, function () use ($query, $filters) {
                $queryQb = Discount::searchByProductName($query)
                    ->with(['product', 'store']);

                $discounts = $this->buildDiscountQuery($queryQb, $filters)->paginate(25);

                return response()->json([
                    'data' => $this->formatter->format($discounts),
                    'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
                    'seo' => $this->generateSeoData('search', $query, '-')
                ]);
            });
    }

    public function getFavoriteProduct($slug)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateFavoriteProductCacheKey($slug, $filters);

        return Cache::tags(['discounts', 'favorites', 'product', $slug])
            ->remember($cacheKey, 7200, function () use ($slug, $filters) {
                $product = \App\Models\Product::where('slug', $slug)->first();

                if (!$product) {
                    return response()->json(['error' => 'Product not found'], 404);
                }

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
        $cacheKey = $this->generateFavoriteCategoryCacheKey($id, $filters);

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
        $cacheKey = $this->generateFavoriteHomeCacheKey($filters);

        return Cache::tags(['discounts', 'favorites', 'home'])
            ->remember($cacheKey, 7200, function () use ($filters) {
                $query = Discount::with(['product', 'store']);

                $discounts = $this->buildDiscountQuery($query, $filters)
                    ->inRandomOrder()
                    ->limit(10)
                    ->get();

                return response()->json($this->formatter->format($discounts));
            });
    }

    public function getProductBySlug($slug)
    {
        $cacheKey = "product_slug_{$slug}";

        return Cache::tags(['discounts', 'product', $slug])
            ->remember($cacheKey, 7200, function () use ($slug) {
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

    public function getProductWithSimilar($slug)
    {
        $filters = $this->getFilters();
        $filtersHash = md5(json_encode($filters));
        $cacheKey = "product_with_similar_{$slug}_{$filtersHash}";
        $cacheTags = ['discounts', 'product', $slug, 'similar'];

        $cachedResponse = Cache::tags($cacheTags)->get($cacheKey);

        if ($cachedResponse !== null) {
            return response($cachedResponse, 200, ['Content-Type' => 'application/json']);
        }

        $product = Product::where('slug', $slug)
            ->with(['discounts.store', 'category'])
            ->firstOrFail();

        $randomSeed = $this->generateRandomSeed($slug);

        $similarProducts = Discount::whereHas('product', function ($query) use ($slug, $product) {
            $query->where('slug', '!=', $slug)
                ->where('category_id', $product->category_id);
        })
            ->with(['product.category', 'store'])
            ->orderByRaw("RAND({$randomSeed})")
            ->limit(7)
            ->get();

        $responseData = [
            'data' => $this->formatter->format($product->discounts),
            'breadcrumbs' => $this->generateBreadcrumbs('product', $product),
            'seo' => $this->generateSeoData('product', $product),
            'similar' => $this->formatter->format($similarProducts)
        ];

        $jsonString = json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Cache::tags($cacheTags)->put($cacheKey, $jsonString, 7200);

        return response($jsonString, 200, ['Content-Type' => 'application/json']);
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
                $minDiscount = $this->getMinDiscountForCategory($entity);
                return [
                    'seo_title' => $entity->name . ' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => $entity->name . " akcijos – " . $this->formatCount($count) . "+ prekių nuolaidos {$minDiscount}-{$maxDiscount}%",
                    'meta_description' => "Peržiūrėkite naujausias " . mb_strtolower($entity->name) . " akcijas",
                ];
            case 'store':
                $count = $this->getDiscountCountForStore($entity);
                $maxDiscount = $this->getMaxDiscountForStore($entity);
                $minDiscount = $this->getMinDiscountForStore($entity);
                return [
                    'seo_title' => $entity->name . ' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => mb_strtoupper($entity->name) . " akcijos – " . $this->formatCount($count) . "+ prekių nuolaidos {$minDiscount}-{$maxDiscount}%",
                    'meta_description' => "Peržiūrėkite naujausias" . $entity->name . " akcijas, savaitinius leidinius ir specialius pasiūlymus – sutaupykite su " . $entity->name . "! Galioja parduotuvėse ir internetu.",
                ];
            case 'store_category':
                $count = $this->getDiscountCountForStoreCategory($entity, $secondaryEntity);
                $maxDiscount = $this->getMaxDiscountForStoreCategory($entity, $secondaryEntity);
                $minDiscount = $this->getMinDiscountForStoreCategory($entity, $secondaryEntity);
                return [
                    'seo_title' => $entity->name . ' akcija ' . mb_strtolower($secondaryEntity->name),
                    'seo_description' => "",
                    'meta_title' => mb_strtoupper($entity->name) . ' akcija ' . mb_strtolower($secondaryEntity->name) . " – " . $this->formatCount($count) . "+ prek. nuolaidos {$minDiscount}-{$maxDiscount}%",
                    'meta_description' => "Atraskite naujausias " . ucfirst($entity->name) . " akcijas " . mb_strtolower($secondaryEntity->name) . " – švieži, kokybiški produktai su puikiomis nuolaidomis. Pirkite pigiau šią savaitę!",
                ];
            case 'product':
                return [
                    'seo_title' => $entity->name,
                    'seo_description' => $entity->description ?? '',
                    'meta_title' => 'Akcija ' . mb_strtolower($entity->name),
                    'meta_description' => "Atraskite akciją " . mb_strtolower($entity->name) . " – puiki proga įsigyti produktus už mažesnę kainą. Pasinaudokite specialiais pasiūlymais ir sutaupykite šiandien!“"
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
                    'seo_title' => 'Visos akcijos',
                    'seo_description' => 'Visos akcijos',
                    'meta_title' => 'Visos akcijos',
                    'meta_description' => 'Visos akcijos',
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

    public function clearCache()
    {
        Cache::tags(['discounts'])->flush();

        return response()->json(['message' => 'Cache cleared successfully']);
    }

    public function clearProductCache($slug)
    {
        Cache::tags(['discounts', 'product', $slug])->flush();

        return response()->json(['message' => "Product cache cleared for {$slug}"]);
    }

    public function clearStoreCache($storeSlug)
    {
        Cache::tags(['discounts', $storeSlug])->flush();

        return response()->json(['message' => "Store cache cleared for {$storeSlug}"]);
    }

    public function clearCategoryCache($categorySlug)
    {
        Cache::tags(['discounts', $categorySlug])->flush();

        return response()->json(['message' => "Category cache cleared for {$categorySlug}"]);
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
}

