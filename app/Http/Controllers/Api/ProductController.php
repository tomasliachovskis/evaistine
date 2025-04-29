<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\DiscountTemp;

class ProductController extends Controller
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
            $discountTemps[] = DiscountTemp::create($product);
        }

        return response()->json($discountTemps, 201);
    }
}
