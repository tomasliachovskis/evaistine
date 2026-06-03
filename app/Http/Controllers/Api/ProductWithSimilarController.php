<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class ProductWithSimilarController extends Controller
{
    public function __invoke(string $slug)
    {
        $cacheKey = ProductController::productWithSimilarCacheKey($slug);
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse !== null) {
            return response($cachedResponse, 200, ['Content-Type' => 'application/json'])
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', $cacheKey);
        }

        return app(ProductController::class)->getProductWithSimilar($slug);
    }
}
