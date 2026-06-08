<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ProductWithSimilarController extends Controller
{
    public function __invoke(string $slug)
    {
        $cached = ProductController::resolveCachedProductWithSimilar($slug);

        if ($cached !== null) {
            return ProductController::cachedProductWithSimilarResponse($cached);
        }

        return app(ProductController::class)->getProductWithSimilar($slug);
    }
}
