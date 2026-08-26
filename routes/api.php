<?php

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ScrapingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductWithSimilarController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\ProductAssistantController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Routing\Middleware\ThrottleRequests;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/oauth', [AuthController::class, 'oauth']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/scrapers', [ScrapingController::class, 'storeDiscountTemp']);
Route::get('/scrapers/active-discounts/{storeName}', [ScrapingController::class, 'getActiveDiscountsByStore']);
Route::post('/scrapers/check-discount', [ScrapingController::class, 'checkValidDiscount']);
Route::post('/scrapers/store-flyer', [ScrapingController::class, 'storeFlyer']);
Route::post('/scrapers/extract-flyer-info', [ScrapingController::class, 'extractFlyerInfo']);
Route::post('/scrapers/store-locations', [ScrapingController::class, 'storeLocations']);

Route::get('/discount', [ProductController::class, 'getAllDiscounts']);
Route::get('/discount/best-by-category', [ProductController::class, 'getBestDiscountsByCategory']);
Route::get('/discount/{store}/best-by-category', [ProductController::class, 'getBestDiscountsByCategoryForStore']);
Route::get('/discount/{storeOrCategory}', [ProductController::class, 'getDiscounts']);
Route::get('/discount/{storeOrCategory}/{category}', [ProductController::class, 'getDiscounts']);
Route::get('/leidiniai', [ProductController::class, 'getAllLeaflets']);
Route::get('/leidinys/{store}/{flyerSlug}', [ProductController::class, 'getStoreLeaflet']);
Route::get('/leidinys/{store}', [ProductController::class, 'getStoreLeafletHub']);

//Route::get('/product/{slug}', [ProductController::class, 'getProductBySlug']);
Route::get('/product/{slug}/with-similar', ProductWithSimilarController::class)
    ->withoutMiddleware(ThrottleRequests::class);
Route::get('/search/{query}', [ProductController::class, 'search']);

Route::get('/keywords', [KeywordPageController::class, 'index']);
Route::get('/keyword/{slug}', [KeywordPageController::class, 'show']);

Route::get('/categories', [ProductController::class, 'getCategories']);
Route::get('/stores', [ProductController::class, 'getStores']);
Route::get('/store-locations/{slug}', [ProductController::class, 'getStoreLocations']);
Route::get('/sitemap', [ProductController::class, 'getSitemap']);
Route::get('/sitemap/products', [ProductController::class, 'getSitemapProducts']);
Route::get('/favorite/product/{slug}', [ProductController::class, 'getFavoriteProduct']);
Route::get('/favorite/category/{id}', [ProductController::class, 'getFavoriteCategory']);
Route::get('/favorite/home', [ProductController::class, 'getFavoriteHome']);
Route::middleware('auth:sanctum')->post('/favorite/product', [ProductController::class, 'toggleFavorite']);
Route::middleware('auth:sanctum')->get('/favorite/list', [ProductController::class, 'getFavorites']);
Route::middleware('auth:sanctum')->get('/favorite/products', [ProductController::class, 'getFavoriteProducts']);

Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);

Route::post('/assistant/cart-comparison', [ProductAssistantController::class, 'cartComparison']);

