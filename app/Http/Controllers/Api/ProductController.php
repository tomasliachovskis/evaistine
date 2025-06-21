<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\DiscountTemp;
use App\Models\Discount;
use App\Services\DiscountResponseFormatter;
use App\Models\Category;
use App\Models\Store;

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
        $order = request()->get('order', 'popular');

        if ($category) {
            return $this->getDiscountsByStoreAndCategory($storeOrCategory, $category, $order);
        }

        return $this->getDiscountsByStoreOrCategory($storeOrCategory, $order);
    }

    private function getDiscountsByStoreOrCategory($storeOrCategory, $order = 'popular')
    {
        $store = \App\Models\Store::where('slug', $storeOrCategory)->first();
        $category = \App\Models\Category::where('slug', $storeOrCategory)->first();

        if ($store) {
            $query = Discount::where('store_id', $store->id);
        } elseif ($category) {
            $query = Discount::whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });
        } else {
            return response()->json(['error' => 'Store or category not found'], 404);
        }

        $discounts = $this->applySorting($query, $order)->with('product')->paginate(10);

        return response()->json($this->formatter->format($discounts));
    }

    private function getDiscountsByStoreAndCategory($store, $category, $order = 'popular')
    {
        $store = \App\Models\Store::where('slug', $store)->firstOrFail();
        $category = \App\Models\Category::where('slug', $category)->firstOrFail();

        $query = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });

        $discounts = $this->applySorting($query, $order)->with('product')->paginate(10);

        return response()->json($this->formatter->format($discounts));
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
        $discounts = Discount::searchByProductName($query)
            ->with(['product', 'store'])
            ->paginate(10);

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteProduct($slug)
    {
        $discounts = Discount::whereHas('product', function ($query) use ($slug) {
            $query->where('slug', '!=', $slug);
        })
            ->with(['product', 'store'])
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteCategory($id)
    {
        $discounts = Discount::whereHas('product', function ($q) use ($id) {
            $q->where('category_id', $id);
        })
            ->with(['product', 'store'])
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteHome()
    {
        $discounts = Discount::with(['product', 'store'])
            ->orderBy('created_at', 'desc')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getProductBySlug($slug)
    {
        $product = \App\Models\Product::where('slug', $slug)
            ->with([
                'discounts' => function ($query) {
                    $query->with('store')
                        ->orderBy('created_at', 'desc');
                }
            ])
            ->firstOrFail();

        return response()->json($this->formatter->format($product->discounts));
    }

    public function getBreadcrumbs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $breadcrumbs = [
            [
                'name' => 'Akcijos',
                'slug' => '/',
                'type' => 'home'
            ]
        ];

        $segments = explode('/', trim($request->url, '/'));

        if ($segments[0] !== 'akcijos') {
            return response()->json(['error' => 'Invalid URL format'], 404);
        }

        if (count($segments) === 3) {
            $store = \App\Models\Store::where('slug', $segments[1])->first();
            if ($store) {
                $breadcrumbs[] = [
                    'name' => $store->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'store'
                ];

                $product = \App\Models\Product::where('slug', $segments[2])->first();
                if ($product) {
                    $breadcrumbs[] = [
                        'name' => $product->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'product'
                    ];

                    return response()->json($breadcrumbs);
                }

                $category = \App\Models\Category::where('slug', $segments[2])->first();
                if ($category) {
                    $breadcrumbs[] = [
                        'name' => $category->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'category'
                    ];

                    return response()->json($breadcrumbs);
                }
            }

            $category = \App\Models\Category::where('slug', $segments[1])->first();
            if ($category) {
                $breadcrumbs[] = [
                    'name' => $category->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'category'
                ];

                $product = \App\Models\Product::where('slug', $segments[2])->first();
                if ($product) {
                    $breadcrumbs[] = [
                        'name' => $product->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'product'
                    ];
                }

                return response()->json($breadcrumbs);
            }
        }

        if (count($segments) === 2) {
            if ($segments[1] === '') {
                return response()->json($breadcrumbs);
            }

            $category = \App\Models\Category::where('slug', $segments[1])->first();
            if ($category) {
                $breadcrumbs[] = [
                    'name' => $category->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'category'
                ];

                return response()->json($breadcrumbs);
            }

            $store = \App\Models\Store::where('slug', $segments[1])->first();
            if ($store) {
                $breadcrumbs[] = [
                    'name' => $store->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'store'
                ];

                return response()->json($breadcrumbs);
            }
        }

        return response()->json(['error' => 'Entity not found'], 404);
    }

    public function getTitlesBySlug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segments = explode('/', trim($request->url, '/'));

        if ($segments[0] !== 'akcijos') {
            return response()->json(['error' => 'Invalid URL format'], 404);
        }

        if (count($segments) === 2) {
            $store = Store::where('slug', $segments[1])->first();
            if ($store) {
                return response()->json([
                    'seo_title' => $store->name,
                    'seo_description' => $store->description,
                    'meta_title' => $store->name,
                    'meta_description' => $store->description,
                ]);
            }

            $category = Category::where('slug', $segments[1])->first();
            if ($category) {
                return response()->json([
                    'seo_title' => $category->name,
                    'seo_description' => $category->description,
                    'meta_title' => $category->name,
                    'meta_description' => $category->description,
                ]);
            }
        }

        if (count($segments) === 3) {
            $store = Store::where('slug', $segments[1])->first();
            if ($store) {
                $category = Category::where('slug', $segments[2])->first();
                if ($category) {
                    return response()->json([
                        'seo_title' => $store->name  . ' akcija ' . strtolower($category->name),
                        'seo_description' => $category->description,
                        'meta_title' => $store->name  . ' akcija ' . strtolower($category->name),
                        'meta_description' => $category->description,
                    ]);
                }
            }
        }

        return response()->json(['error' => 'Entity not found'], 404);
    }
}

