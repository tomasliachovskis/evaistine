<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Http\Controllers\Api\ProductController;

class CacheWarmingService
{
    protected $productController;

    public function __construct(ProductController $productController)
    {
        $this->productController = $productController;
    }

    public function warmCriticalCaches()
    {
        $this->warmStoreCaches();
        $this->warmCategoryCaches();
        $this->warmStoreCategoryCaches();
        $this->warmPopularProductsCache();
        $this->warmAllDiscountsCache();
    }

    public function warmAllDiscountsCache()
    {
        $filters = ['order' => 'popular'];
        $this->productController->getAllDiscounts();
    }

    public function warmStoreCaches()
    {
        $stores = Store::select('slug')->get();

        foreach ($stores as $store) {
            $this->productController->getDiscounts($store->slug);
        }
    }

    public function warmCategoryCaches()
    {
        $categories = Category::whereNull('parent_id')->select('slug')->get();

        foreach ($categories as $category) {
            $this->productController->getDiscounts($category->slug);
        }
    }

    public function warmPopularProductsCache()
    {
        $productsWithDiscounts = Product::withCount('discounts')
            ->having('discounts_count', '>=', 1)
            ->orderBy('discounts_count', 'desc')
            ->get();

        foreach ($productsWithDiscounts as $product) {
            $this->productController->getProductWithSimilar($product->slug);
        }
    }

    public function getProductsWithDiscountsCount()
    {
        return Product::withCount('discounts')
            ->having('discounts_count', '>=', 1)
            ->count();
    }

    public function warmStoreCategoryCaches()
    {
        foreach ($this->storeCategoryPairsQuery()->get() as $pair) {
            $this->productController->getDiscounts($pair->store_slug, $pair->category_slug);
        }
    }

    public function getStoreCategoryPairsCount(): int
    {
        return $this->storeCategoryPairsQuery()->get()->count();
    }

    protected function storeCategoryPairsQuery()
    {
        return Discount::query()
            ->join('products', 'discounts.product_id', '=', 'products.id')
            ->join('stores', 'discounts.store_id', '=', 'stores.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('stores.slug as store_slug', 'categories.slug as category_slug')
            ->distinct();
    }

    public function warmFavoritesCache()
    {
        $this->productController->getFavoriteHome();

        $categories = Category::whereNull('parent_id')->limit(10)->get();
        foreach ($categories as $category) {
            $this->productController->getFavoriteCategory($category->id);
        }
    }
}
