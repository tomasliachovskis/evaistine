<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductSearchAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductAssistantController extends Controller
{
    protected ProductSearchAssistantService $assistantService;

    public function __construct(ProductSearchAssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    public function cartComparison(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1|max:50',
            'products.*' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $products = $request->input('products');
        $cacheKey = 'cart_comparison_' . md5(serialize($products));

        return Cache::remember($cacheKey, 1800, function () use ($products) {
                try {
                    $results = $this->assistantService->calculateCartPrices($products);

                    return response()->json([
                        'success' => true,
                        'data' => $results
                    ]);
                } catch (\Exception $e) {
                    Log::error('Cart comparison failed', [
                        'products' => $products,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to calculate cart prices',
                        'message' => $e->getMessage()
                    ], 500);
                }
            });
    }
}

