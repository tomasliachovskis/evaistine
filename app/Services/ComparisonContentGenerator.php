<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

class ComparisonContentGenerator
{
    private PriceComparisonService $priceComparisonService;

    public function __construct(PriceComparisonService $priceComparisonService)
    {
        $this->priceComparisonService = $priceComparisonService;
    }

    public function generateCategoryPageContent(int $categoryId): array
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return [];
        }

        $comparison = $this->priceComparisonService->generateComparisonContent($categoryId);
        $storeComparison = $this->priceComparisonService->getStoreComparison($categoryId);

        return [
            'title' => "Kokie yra populiariausi {$category->name}?",
            'category' => $category,
            'popular_products' => $this->formatPopularProducts($comparison['popular_products']),
            'cheapest_products' => $this->formatCheapestProducts($comparison['cheapest_products']),
            'popular_brands' => $this->formatPopularBrands($comparison['popular_brands']),
            'price_comparison' => $this->formatPriceComparison($comparison['price_comparison']),
            'store_comparison' => $this->formatStoreComparison($storeComparison),
            'recommendations' => $comparison['recommendations'],
            'buying_guide' => $this->generateBuyingGuide($category, $comparison['price_comparison']),
            'popular_searches' => $this->generatePopularSearches($categoryId)
        ];
    }

    private function formatPopularProducts(Collection $products): array
    {
        return $products->map(function ($product) {
            $bestDiscount = $product->discounts->sortByDesc('discount_percent')->first();
            return [
                'name' => $product->name,
                'brand' => $product->brand,
                'price' => $bestDiscount ? number_format($bestDiscount->discounted_price, 2) . ' €' : 'N/A',
                'discount' => $bestDiscount ? $bestDiscount->discount_percent . '%' : null,
                'store' => $bestDiscount ? $bestDiscount->store->name : null,
                'original_price' => $bestDiscount ? number_format($bestDiscount->original_price, 2) . ' €' : null
            ];
        })->toArray();
    }

    private function formatCheapestProducts(Collection $products): array
    {
        return $products->map(function ($product) {
            $cheapestDiscount = $product->discounts->sortBy('discounted_price')->first();
            return [
                'name' => $product->name,
                'brand' => $product->brand,
                'price' => $cheapestDiscount ? number_format($cheapestDiscount->discounted_price, 2) . ' €' : 'N/A',
                'discount' => $cheapestDiscount ? $cheapestDiscount->discount_percent . '%' : null,
                'store' => $cheapestDiscount ? $cheapestDiscount->store->name : null,
                'original_price' => $cheapestDiscount ? number_format($cheapestDiscount->original_price, 2) . ' €' : null
            ];
        })->toArray();
    }

    private function formatPopularBrands(Collection $brands): array
    {
        return $brands->map(function ($brand) {
            return [
                'name' => $brand->brand,
                'product_count' => $brand->product_count
            ];
        })->toArray();
    }

    private function formatPriceComparison(array $comparison): array
    {
        return array_map(function ($item) {
            return [
                'product' => $item['product']->name,
                'brand' => $item['product']->brand,
                'cheapest_price' => number_format($item['cheapest_price'], 2) . ' €',
                'cheapest_store' => $item['cheapest_store'],
                'highest_discount' => $item['highest_discount'] . '%',
                'discount_store' => $item['discount_store'],
                'original_price' => number_format($item['original_price'], 2) . ' €',
                'savings' => number_format($item['savings'], 2) . ' €'
            ];
        }, $comparison);
    }

    private function formatStoreComparison(array $comparison): array
    {
        return array_map(function ($item) {
            return [
                'store' => $item['store']->name,
                'product_count' => $item['product_count'],
                'avg_price' => number_format($item['avg_price'], 2) . ' €',
                'avg_discount' => number_format($item['avg_discount'], 1) . '%',
                'min_price' => number_format($item['min_price'], 2) . ' €',
                'max_discount' => $item['max_discount'] . '%'
            ];
        }, $comparison);
    }

    private function generateBuyingGuide(Category $category, array $priceComparison): array
    {
        $guide = [];
        
        if (empty($priceComparison)) {
            return $guide;
        }

        $avgPrice = collect($priceComparison)->avg('cheapest_price');
        $maxSavings = collect($priceComparison)->max('savings');
        $storeCounts = collect($priceComparison)->groupBy('cheapest_store')->map->count()->sortDesc();

        $guide[] = [
            'title' => "Kaip išsirinkti {$category->name}?",
            'content' => $this->getCategorySpecificAdvice($category->name, $avgPrice, $maxSavings, $storeCounts)
        ];

        if ($storeCounts->isNotEmpty()) {
            $bestStore = $storeCounts->keys()->first();
            $guide[] = [
                'title' => "Geriausia parduotuvė {$category->name}",
                'content' => "Daugiausiai pigių prekių šioje kategorijoje siūlo {$bestStore}. Vidutinė kaina: " . number_format($avgPrice, 2) . "€. Didžiausias sutaupymas: " . number_format($maxSavings, 2) . "€."
            ];
        }

        return $guide;
    }

    private function getCategorySpecificAdvice(string $categoryName, float $avgPrice, float $maxSavings, Collection $storeCounts): string
    {
        $advice = "Renkantis {$categoryName}, svarbu atkreipti dėmesį į kainų skirtumus tarp parduotuvių. ";
        
        if ($avgPrice > 0) {
            $advice .= "Vidutinė kaina šioje kategorijoje yra " . number_format($avgPrice, 2) . "€. ";
        }
        
        if ($maxSavings > 0) {
            $advice .= "Didžiausias sutaupymas gali siekti " . number_format($maxSavings, 2) . "€. ";
        }
        
        if ($storeCounts->isNotEmpty()) {
            $bestStore = $storeCounts->keys()->first();
            $advice .= "Daugiausiai pigių prekių siūlo {$bestStore}. ";
        }
        
        $advice .= "Rekomenduojame palyginti kainas ir pasirinkti geriausią pasiūlymą.";
        
        return $advice;
    }

    private function generatePopularSearches(int $categoryId): array
    {
        $products = Product::where('category_id', $categoryId)
            ->whereHas('discounts')
            ->select('name')
            ->limit(10)
            ->get();

        return $products->pluck('name')->toArray();
    }

    public function generateComparisonText(int $categoryId): string
    {
        $content = $this->generateCategoryPageContent($categoryId);
        
        if (empty($content)) {
            return '';
        }

        $text = "Kokie yra populiariausi {$content['category']->name}?\n\n";
        
        if (!empty($content['popular_products'])) {
            $text .= "Populiariausi produktai:\n";
            foreach ($content['popular_products'] as $product) {
                $text .= "• {$product['name']} - {$product['price']} ({$product['store']})\n";
            }
            $text .= "\n";
        }

        if (!empty($content['cheapest_products'])) {
            $text .= "Pigiausi produktai:\n";
            foreach ($content['cheapest_products'] as $product) {
                $text .= "• {$product['name']} - {$product['price']} ({$product['store']})\n";
            }
            $text .= "\n";
        }

        if (!empty($content['popular_brands'])) {
            $text .= "Populiariausi gamintojai:\n";
            foreach ($content['popular_brands'] as $brand) {
                $text .= "• {$brand['name']} ({$brand['product_count']} produktų)\n";
            }
            $text .= "\n";
        }

        if (!empty($content['recommendations'])) {
            $text .= "Rekomendacijos:\n";
            foreach ($content['recommendations'] as $recommendation) {
                $text .= "• {$recommendation}\n";
            }
        }

        return $text;
    }
}
