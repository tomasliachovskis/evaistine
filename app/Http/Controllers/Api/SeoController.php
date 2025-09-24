<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Category;
use App\Models\Store;

class SeoController extends Controller
{
    public function getBreadcrumbs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $breadcrumbs = [
            [
                'name' => 'Akcijos',
                'slug' => '/',
                'type' => 'home'
            ]
        ];

        $segments = explode('/', trim($request->url, '/'));

        if ($segments[0] !== 'akcijos') {
            return response()->json(['error' => 'Invalid URL format'], 404);
        }

        if (count($segments) === 3) {
            $store = \App\Models\Store::where('slug', $segments[1])->first();
            if ($store) {
                $breadcrumbs[] = [
                    'name' => $store->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'store'
                ];

                $product = \App\Models\Product::where('slug', $segments[2])->first();
                if ($product) {
                    $breadcrumbs[] = [
                        'name' => $product->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'product'
                    ];

                    return response()->json($breadcrumbs);
                }

                $category = \App\Models\Category::where('slug', $segments[2])->first();
                if ($category) {
                    $breadcrumbs[] = [
                        'name' => $category->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'category'
                    ];

                    return response()->json($breadcrumbs);
                }
            }

            $category = \App\Models\Category::where('slug', $segments[1])->first();
            if ($category) {
                $breadcrumbs[] = [
                    'name' => $category->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'category'
                ];

                $product = \App\Models\Product::where('slug', $segments[2])->first();
                if ($product) {
                    $breadcrumbs[] = [
                        'name' => $product->name,
                        'slug' => 'akcijos/' . $segments[1] . '/' . $segments[2],
                        'type' => 'product'
                    ];
                }

                return response()->json($breadcrumbs);
            }
        }

        if (count($segments) === 2) {
            if ($segments[1] === '') {
                return response()->json($breadcrumbs);
            }

            $category = \App\Models\Category::where('slug', $segments[1])->first();
            if ($category) {
                $breadcrumbs[] = [
                    'name' => $category->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'category'
                ];

                return response()->json($breadcrumbs);
            }

            $store = \App\Models\Store::where('slug', $segments[1])->first();
            if ($store) {
                $breadcrumbs[] = [
                    'name' => $store->name,
                    'slug' => 'akcijos/' . $segments[1],
                    'type' => 'store'
                ];

                return response()->json($breadcrumbs);
            }
        }

        return response()->json(['error' => 'Entity not found'], 404);
    }

    public function getTitlesBySlug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segments = explode('/', trim($request->url, '/'));

        if ($segments[0] !== 'akcijos') {
            return response()->json(['error' => 'Invalid URL format'], 404);
        }

        if (count($segments) === 2) {
            $store = Store::where('slug', $segments[1])->first();
            if ($store) {
                return response()->json([
                    'seo_title' => $store->name,
                    'seo_description' => $store->description,
                    'meta_title' => $store->name,
                    'meta_description' => $store->description,
                ]);
            }

            $category = Category::where('slug', $segments[1])->first();
            if ($category) {
                return response()->json([
                    'seo_title' => $category->name,
                    'seo_description' => $category->description,
                    'meta_title' => $category->name,
                    'meta_description' => $category->description,
                ]);
            }
        }

        if (count($segments) === 3) {
            $store = Store::where('slug', $segments[1])->first();
            if ($store) {
                $category = Category::where('slug', $segments[2])->first();
                if ($category) {
                    return response()->json([
                        'seo_title' => $store->name  . ' akcija ' . mb_strtolower($category->name),
                        'seo_description' => $category->description,
                        'meta_title' => $store->name  . ' akcija ' . mb_strtolower($category->name),
                        'meta_description' => $category->description,
                    ]);
                }
            }
        }

        return response()->json(['error' => 'Entity not found'], 404);
    }
}
