<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SeoController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/scrapers', [ProductController::class, 'storeDiscountTemp']);

Route::get('/discount', [ProductController::class, 'getAllDiscounts']);
Route::get('/discount/{storeOrCategory}', [ProductController::class, 'getDiscounts']);
Route::get('/discount/{storeOrCategory}/{category}', [ProductController::class, 'getDiscounts']);

Route::get('/product/{slug}', [ProductController::class, 'getProductBySlug']);
Route::get('/product/{slug}/with-similar', [ProductController::class, 'getProductWithSimilar']);

Route::get('/categories', [ProductController::class, 'getCategories']);
Route::get('/stores', [ProductController::class, 'getStores']);
Route::get('/search/{query}', [ProductController::class, 'search']);
Route::get('/favorite/product/{slug}', [ProductController::class, 'getFavoriteProduct']);
Route::get('/favorite/category/{id}', [ProductController::class, 'getFavoriteCategory']);
Route::get('/favorite/home', [ProductController::class, 'getFavoriteHome']);

Route::post('/cache/clear', [ProductController::class, 'clearCache']);
Route::post('/cache/product/{slug}/clear', [ProductController::class, 'clearProductCache']);
Route::post('/cache/store/{storeSlug}/clear', [ProductController::class, 'clearStoreCache']);
Route::post('/cache/category/{categorySlug}/clear', [ProductController::class, 'clearCategoryCache']);

// Performance test routes
Route::get('/test-simple', function () {
    $start = microtime(true);
    $data = ['test' => 'simple', 'timestamp' => time()];
    $end = microtime(true);
    
    return response()->json([
        'processing_time_ms' => ($end - $start) * 1000,
        'data' => $data
    ]);
});

Route::get('/test-db', function () {
    $start = microtime(true);
    
    $result = \DB::select('SELECT 1 as test');
    
    $end = microtime(true);
    
    return response()->json([
        'db_time_ms' => ($end - $start) * 1000,
        'result' => $result
    ]);
});

Route::get('/test-cache', function () {
    $start = microtime(true);
    
    $key = 'test_' . time();
    $data = ['test' => 'cache_data', 'timestamp' => time()];
    
    // Test cache put
    \Cache::put($key, $data, 60);
    
    // Test cache get
    $retrieved = \Cache::get($key);
    
    $end = microtime(true);
    
    return response()->json([
        'cache_time_ms' => ($end - $start) * 1000,
        'data' => $retrieved
    ]);
});

Route::get('/test-cache-tags', function () {
    $start = microtime(true);
    
    $key = 'test_tags_' . time();
    $data = ['test' => 'cache_tags_data', 'timestamp' => time()];
    
    // Test cache with tags (like your actual implementation)
    \Cache::tags(['test', 'performance'])->put($key, $data, 60);
    
    // Test cache get with tags
    $retrieved = \Cache::tags(['test', 'performance'])->get($key);
    
    $end = microtime(true);
    
    return response()->json([
        'cache_tags_time_ms' => ($end - $start) * 1000,
        'data' => $retrieved
    ]);
});

//Route::post('/breadcrumbs', [SeoController::class, 'getBreadcrumbs']);
//Route::post('/titles', [SeoController::class, 'getTitlesBySlug']);


///discount?page=1&order=popular&store=maxima,norfa,lidl,iki,rimi
