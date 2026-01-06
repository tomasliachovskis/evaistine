<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscountTemp;
use App\Models\Discount;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScrapingController extends Controller
{
    public function storeDiscountTemp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            '*.name' => 'nullable|string',
            '*.store' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $discountTemps = [];
        foreach ($request->all() as $product) {
            if (!empty($product['start_at']) &&
                (\DateTime::createFromFormat('Y-m-d', $product['start_at'])) !== false &&
                new \DateTime($product['start_at']) > (new \DateTime())->modify('+10 months')
            ) {
                $product['start_at'] = (new \DateTime($product['start_at']))
                    ->modify('-1 year')
                    ->format('Y-m-d');
            }
            $discountTemps[] = DiscountTemp::create($product);
        }

        return response()->json($discountTemps, 201);
    }

    public function getActiveDiscountsByStore($storeName)
    {
        $validator = Validator::make(['store' => $storeName], [
            'store' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $store = Store::where('name', $storeName)->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $discounts = Discount::where('store_id', $store->id)
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', date('Y-m-d H:i:s'));
            })
            ->with(['product'])
            ->orderBy('discount_percent', 'desc')
            ->get();

        return response()->json($discounts);
    }

    public function checkValidDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store' => 'required|string',
            'url' => 'required|string',
            'discounted_price' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $store = Store::where('name', $request->store)->first();

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $existingDiscount = Discount::where('store_id', $store->id)
            ->where('product_url', $request->url)
            ->where('discounted_price', $request->discounted_price)
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhereDate('end_at', '>=', now()->toDateString());
            })
            ->first();

        $isValid = $existingDiscount !== null;

        if ($isValid && $existingDiscount->product) {
            $existingDiscount->touch();
        }

        return response()->json([
            'is_valid' => $isValid,
        ]);
    }
}
