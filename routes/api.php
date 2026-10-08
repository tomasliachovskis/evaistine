<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ScrapingController;
use Illuminate\Support\Facades\Route;

// The HTTP API left after the old Next.js frontend's JSON API was removed
// (2026-10-08): the local Node scrapers POST to /api/scrapers*, and the store
// page's map (resources/js/app.js) loads /api/store-locations/{slug}. Blade
// pages otherwise call the Api\* controllers' methods directly as PHP.
Route::post('/scrapers', [ScrapingController::class, 'storeDiscountTemp']);
Route::get('/scrapers/active-discounts/{storeName}', [ScrapingController::class, 'getActiveDiscountsByStore']);
Route::post('/scrapers/check-discount', [ScrapingController::class, 'checkValidDiscount']);
Route::post('/scrapers/store-flyer', [ScrapingController::class, 'storeFlyer']);
Route::post('/scrapers/extract-flyer-info', [ScrapingController::class, 'extractFlyerInfo']);
Route::post('/scrapers/store-locations', [ScrapingController::class, 'storeLocations']);

Route::get('/store-locations/{slug}', [ProductController::class, 'getStoreLocations']);
