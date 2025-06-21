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
        $filters = $this->getFilters();

        if ($category) {
            return $this->getDiscountsByStoreAndCategory($storeOrCategory, $category, $filters);
        }

        return $this->getDiscountsByStoreOrCategory($storeOrCategory, $filters);
    }

    private function getDiscountsByStoreOrCategory($storeOrCategory, $filters)
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

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(10);

        return response()->json($this->formatter->format($discounts));
    }

    private function getDiscountsByStoreAndCategory($store, $category, $filters)
    {
        $store = \App\Models\Store::where('slug', $store)->firstOrFail();
        $category = \App\Models\Category::where('slug', $category)->firstOrFail();

        $query = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            });

        $discounts = $this->buildDiscountQuery($query, $filters)->paginate(10);

        return response()->json($this->formatter->format($discounts));
    }

    private function getFilters()
    {
        return [
            'order' => request()->get('order', 'popular'),
            'card' => request()->get('card'),
            'plus' => request()->get('plus'),
        ];
    }

    private function buildDiscountQuery($query, $filters)
    {
        return $query->with('product')
            ->when($filters['card'], function ($q) {
                return $q->where('card', true);
            })
            ->when($filters['plus'], function ($q) {
                return $q->where('condition', '1+1');
            })
            ->when($filters['order'], function ($q, $order) {
                return $this->applySorting($q, $order);
            });
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
}

