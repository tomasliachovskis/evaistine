<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mano vaistinės": saves the signed-in user's chosen stores, so the
 * choice follows them to other devices. Guests keep it in localStorage only
 * (see components/my-stores-sheet.blade.php).
 */
class MyStoresController extends Controller
{
    private const MAX_STORES = 12;

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'stores' => 'present|array|max:'.self::MAX_STORES,
            'stores.*' => 'string|max:100',
        ]);

        // Only real store slugs, in the order given, no duplicates.
        $known = Store::whereIn('slug', $request->input('stores'))->pluck('slug')->all();
        $slugs = array_values(array_unique(array_filter($request->input('stores'), fn ($slug) => in_array($slug, $known, true))));

        $request->user()->update(['preferred_store_slugs' => $slugs === [] ? null : $slugs]);

        return response()->json(['stores' => $slugs]);
    }
}
