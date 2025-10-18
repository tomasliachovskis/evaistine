<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
        $this->warmAllDiscountsCache();
        $this->warmStoreCaches();
        $this->warmCategoryCaches();
        $this->warmPopularProductsCache();
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
        $popularProducts = Product::withCount('discounts')
            ->orderBy('discounts_count', 'desc')
            ->limit(20)
            ->get();

        foreach ($popularProducts as $product) {
            $this->productController->getProductBySlug($product->slug);
            $this->productController->getProductWithSimilar($product->slug);
        }
    }

    public function warmStoreCategoryCaches()
    {
        $stores = Store::select('slug')->get();
        $categories = Category::whereNull('parent_id')->select('slug')->get();

        foreach ($stores as $store) {
            foreach ($categories as $category) {
                $this->productController->getDiscounts($store->slug, $category->slug);
            }
        }
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
