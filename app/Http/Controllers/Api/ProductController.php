<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function storeDiscountTemp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            '*.name' => 'nullable|string',
            '*.store' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $discountTemps = [];
        foreach ($request->all() as $product) {
            $discountTemps[] = DiscountTemp::create($product);
        }

        return response()->json($discountTemps, 201);
    }

    public function getDiscounts($storeOrCategory, $category = null)
    {
        $filters = $this->getFilters();
        $cacheKey = $this->generateDiscountsCacheKey($storeOrCategory, $category, $filters);

        return Cache::remember($cacheKey, 0, function () use ($storeOrCategory, $category, $filters) {
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

        return Cache::remember($cacheKey, 0, function () use ($filters) {
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
        $query = $query->with('product');

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
                return $query->orderBy('created_at', 'desc');
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
        $queryQb = Discount::searchByProductName($query)
            ->with(['product', 'store']);

        $discounts = $this->buildDiscountQuery($queryQb, $filters)->paginate(25);

        return response()->json([
            'data' => $this->formatter->format($discounts),
            'breadcrumbs' => $this->generateBreadcrumbs('search', $query, '-'),
            'seo' => $this->generateSeoData('search', $query, '-')
        ]);
    }

    public function getFavoriteProduct($slug)
    {
        $product = \App\Models\Product::where('slug', $slug)->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $filters = $this->getFilters();
        $query = Discount::whereHas('product', function ($query) use ($slug, $product) {
            $query->where('slug', '!=', $slug)
                  ->where('category_id', $product->category_id);
        })
            ->with(['product', 'store']);

        $discounts = $this->buildDiscountQuery($query, $filters)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteCategory($id)
    {
        $filters = $this->getFilters();
        $query = Discount::whereHas('product', function ($q) use ($id) {
            $q->where('category_id', $id);
        })
            ->with(['product', 'store']);

        $discounts = $this->buildDiscountQuery($query, $filters)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteHome()
    {
        $filters = $this->getFilters();
        $query = Discount::with(['product', 'store']);

        $discounts = $this->buildDiscountQuery($query, $filters)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getProductBySlug($slug)
    {
        $cacheKey = "product_slug_{$slug}";

        return Cache::remember($cacheKey, 0, function () use ($slug) {
            $product = \App\Models\Product::where('slug', $slug)
                ->with([
                    'discounts' => function ($query) {
                        $query->with('store')
                            ->orderBy('created_at', 'desc');
                    },
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
                return [
                    'seo_title' => $entity->name . ' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => $entity->name . " akcijos – naujausi leidiniai, nuolaidos & specialūs pasiūlymai",
                    'meta_description' => "Peržiūrėkite naujausias " . mb_strtolower($entity->name) . " akcijas",
                ];
            case 'store':
                return [
                    'seo_title' => $entity->name . ' akcijos',
                    'seo_description' => $entity->description,
                    'meta_title' => mb_strtoupper($entity->name) . " akcijos – naujausi leidiniai, nuolaidos & specialūs pasiūlymai",
                    'meta_description' => "Peržiūrėkite naujausias" . $entity->name . " akcijas, savaitinius leidinius ir specialius pasiūlymus – sutaupykite su " . $entity->name . "! Galioja parduotuvėse ir internetu.",
                ];
            case 'store_category':
                return [
                    'seo_title' => $entity->name . ' akcija ' . mb_strtolower($secondaryEntity->name),
                    'seo_description' => "",
                    'meta_title' => mb_strtoupper($entity->name) . ' akcija ' . mb_strtolower($secondaryEntity->name),
                    'meta_description' => "Atraskite naujausias " . ucfirst($entity->name) . " akcijas " . mb_strtolower($secondaryEntity->name) . " – švieži, kokybiški produktai su puikiomis nuolaidomis. Pirkite pigiau šią savaitę!“",
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
                    'seo_title' => 'Paieškos rezultatai pagal užklausą: ' . $entity,
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

    public function clearDiscountsCache($storeOrCategory = null, $category = null)
    {
        if ($storeOrCategory) {
            $filters = $this->getFilters();
            $cacheKey = $this->generateDiscountsCacheKey($storeOrCategory, $category, $filters);
            Cache::forget($cacheKey);
        } else {
            Cache::flush();
        }
    }
}

