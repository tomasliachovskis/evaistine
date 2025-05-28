<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/scrapers', [ProductController::class, 'storeDiscountTemp']);
Route::get('/discount/{storeOrCategory}', [ProductController::class, 'getDiscounts']);
Route::get('/discount/{storeOrCategory}/{category}', [ProductController::class, 'getDiscounts']);
Route::get('/categories', [ProductController::class, 'getCategories']);
Route::get('/stores', [ProductController::class, 'getStores']);
Route::get('/search/{query}', [ProductController::class, 'search']);
Route::get('/favorite/product/{slug}', [ProductController::class, 'getFavoriteProduct']);
Route::get('/favorite/category/{id}', [ProductController::class, 'getFavoriteCategory']);
Route::get('/favorite/home', [ProductController::class, 'getFavoriteHome']);
Route::get('/product/{slug}', [ProductController::class, 'getProductBySlug']);
