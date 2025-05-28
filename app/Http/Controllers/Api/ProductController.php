<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        if ($category) {
            return $this->getDiscountsByStoreAndCategory($storeOrCategory, $category);
        }

        return $this->getDiscountsByStoreOrCategory($storeOrCategory);
    }

    private function getDiscountsByStoreOrCategory($storeOrCategory)
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

        $discounts = $query->with('product')->paginate(10);
        return response()->json($this->formatter->format($discounts));
    }

    private function getDiscountsByStoreAndCategory($store, $category)
    {
        $store = \App\Models\Store::where('slug', $store)->firstOrFail();
        $category = \App\Models\Category::where('slug', $category)->firstOrFail();

        $discounts = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($q) use ($category) {
                $q->where('category_id', $category->id);
            })
            ->with('product')
            ->paginate(10);

        return response()->json($this->formatter->format($discounts));
    }

    public function getCategories()
    {
        $categories = \App\Models\Category::whereNull('parent_id')->withCount([
            'discounts' => function ($query) {
                $query->select(\DB::raw('count(distinct discounts.id)'));
            }
        ])->get();

        return response()->json($categories);
    }

    public function getStores()
    {
        $stores = \App\Models\Store::withCount([
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
            $query->where('slug', $slug);
        })
            ->with(['product', 'store'])
            ->limit(4)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteCategory($id)
    {
        $discounts = Discount::whereHas('product', function ($q) use ($id) {
            $q->where('category_id', $id);
        })
            ->with(['product', 'store'])
            ->limit(4)
            ->get();

        return response()->json($this->formatter->format($discounts));
    }

    public function getFavoriteHome()
    {
        $discounts = Discount::with(['product', 'store'])
            ->orderBy('created_at', 'desc')
            ->limit(4)
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
