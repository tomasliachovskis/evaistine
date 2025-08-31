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

Route::get('/categories', [ProductController::class, 'getCategories']);
Route::get('/stores', [ProductController::class, 'getStores']);
Route::get('/search/{query}', [ProductController::class, 'search']);
Route::get('/favorite/product/{slug}', [ProductController::class, 'getFavoriteProduct']);
Route::get('/favorite/category/{id}', [ProductController::class, 'getFavoriteCategory']);
Route::get('/favorite/home', [ProductController::class, 'getFavoriteHome']);

//Route::post('/breadcrumbs', [SeoController::class, 'getBreadcrumbs']);
//Route::post('/titles', [SeoController::class, 'getTitlesBySlug']);


///discount?page=1&order=popular&store=maxima,norfa,lidl,iki,rimi
