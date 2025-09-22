<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceComparisonService
{
    public function getPopularProductsByCategory(int $categoryId, int $limit = 5): Collection
    {
        return Product::where('category_id', $categoryId)
            ->whereHas('discounts')
            ->with(['discounts' => function ($query) {
                $query->where('end_at', '>=', now())
                    ->with('store')
                    ->orderBy('discount_percent', 'desc');
            }])
            ->withCount('discounts')
            ->orderBy('discounts_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCheapestProductsByCategory(int $categoryId, int $limit = 5): Collection
    {
        return Product::where('category_id', $categoryId)
            ->whereHas('discounts')
            ->with(['discounts' => function ($query) {
                $query->where('end_at', '>=', now())
                    ->with('store')
                    ->orderBy('discounted_price', 'asc');
            }])
            ->get()
            ->sortBy(function ($product) {
                return $product->discounts->min('discounted_price');
            })
            ->take($limit);
    }

    public function getPopularBrandsByCategory(int $categoryId, int $limit = 5): Collection
    {
        return Product::where('category_id', $categoryId)
            ->whereNotNull('brand')
            ->whereHas('discounts')
            ->select('brand', DB::raw('COUNT(*) as product_count'))
            ->groupBy('brand')
            ->orderBy('product_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCategoryPriceComparison(int $categoryId): array
    {
        $products = Product::where('category_id', $categoryId)
            ->whereHas('discounts')
            ->with(['discounts' => function ($query) {
                $query->where('end_at', '>=', now())
                    ->with('store');
            }])
            ->get();

        $comparison = [];
        
        foreach ($products as $product) {
            $cheapestDiscount = $product->discounts->sortBy('discounted_price')->first();
            $mostDiscount = $product->discounts->sortByDesc('discount_percent')->first();
            
            if ($cheapestDiscount && $mostDiscount) {
                $comparison[] = [
                    'product' => $product,
                    'cheapest_price' => $cheapestDiscount->discounted_price,
                    'cheapest_store' => $cheapestDiscount->store->name,
                    'highest_discount' => $mostDiscount->discount_percent,
                    'discount_store' => $mostDiscount->store->name,
                    'original_price' => $mostDiscount->original_price,
                    'savings' => $mostDiscount->original_price - $mostDiscount->discounted_price
                ];
            }
        }

        return collect($comparison)
            ->sortBy('cheapest_price')
            ->values()
            ->toArray();
    }

    public function generateComparisonContent(int $categoryId): array
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return [];
        }

        $popularProducts = $this->getPopularProductsByCategory($categoryId);
        $cheapestProducts = $this->getCheapestProductsByCategory($categoryId);
        $popularBrands = $this->getPopularBrandsByCategory($categoryId);
        $priceComparison = $this->getCategoryPriceComparison($categoryId);

        return [
            'category' => $category,
            'popular_products' => $popularProducts,
            'cheapest_products' => $cheapestProducts,
            'popular_brands' => $popularBrands,
            'price_comparison' => $priceComparison,
            'recommendations' => $this->generateRecommendations($category, $priceComparison)
        ];
    }

    private function generateRecommendations(Category $category, array $priceComparison): array
    {
        $recommendations = [];
        
        if (empty($priceComparison)) {
            return $recommendations;
        }

        $avgPrice = collect($priceComparison)->avg('cheapest_price');
        $maxSavings = collect($priceComparison)->max('savings');
        
        $recommendations[] = "Šioje kategorijoje geriausios kainos prasideda nuo " . number_format($avgPrice, 2) . "€";
        
        if ($maxSavings > 0) {
            $recommendations[] = "Didžiausias sutaupymas šioje kategorijoje: " . number_format($maxSavings, 2) . "€";
        }

        $storeCounts = collect($priceComparison)
            ->groupBy('cheapest_store')
            ->map->count()
            ->sortDesc();

        if ($storeCounts->isNotEmpty()) {
            $bestStore = $storeCounts->keys()->first();
            $recommendations[] = "Daugiausiai pigių prekių šioje kategorijoje: {$bestStore}";
        }

        return $recommendations;
    }

    public function getStoreComparison(int $categoryId): array
    {
        $stores = Store::with(['discounts' => function ($query) use ($categoryId) {
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })->where('end_at', '>=', now());
        }])->get();

        $comparison = [];
        
        foreach ($stores as $store) {
            $discounts = $store->discounts;
            
            if ($discounts->isNotEmpty()) {
                $comparison[] = [
                    'store' => $store,
                    'product_count' => $discounts->count(),
                    'avg_price' => $discounts->avg('discounted_price'),
                    'avg_discount' => $discounts->avg('discount_percent'),
                    'min_price' => $discounts->min('discounted_price'),
                    'max_discount' => $discounts->max('discount_percent')
                ];
            }
        }

        return collect($comparison)
            ->sortBy('avg_price')
            ->values()
            ->toArray();
    }
}
