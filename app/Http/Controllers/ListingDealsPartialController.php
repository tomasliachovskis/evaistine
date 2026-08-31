<?php

namespace App\Http\Controllers;

use App\Support\ListingDealsFetcher;
use App\Support\StoreDisplayMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingDealsPartialController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|in:discounts,keyword',
            'primary_slug' => 'nullable|string|max:255',
            'secondary_slug' => 'nullable|string|max:255',
            'page' => 'required|integer|min:2',
            'order' => 'nullable|string|max:64',
            'store' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:2000',
            'card' => 'nullable|in:0,1',
            'plus' => 'nullable|in:0,1',
        ]);

        $pagination = ListingDealsFetcher::fetch(
            mode: $validated['mode'],
            primarySlug: $validated['primary_slug'] ?? null,
            secondarySlug: $validated['secondary_slug'] ?? null,
            page: (int) $validated['page'],
            order: $validated['order'] ?? 'popular',
            storeFilter: $validated['store'] ?? '',
            categoryFilter: $validated['category'] ?? '',
            cardOnly: ($validated['card'] ?? '0') === '1',
            plusOnly: ($validated['plus'] ?? '0') === '1',
            request: $request,
        );

        $deals = $pagination['data'] ?? [];
        $primarySlug = $validated['primary_slug'] ?? null;
        $contextStoreSlug = $primarySlug && StoreDisplayMeta::isStoreSlug($primarySlug)
            ? $primarySlug
            : null;

        $html = view('components.partials.listing-deals-chunk', [
            'deals' => $deals,
            'contextStoreSlug' => $contextStoreSlug,
        ])->render();

        return response()->json([
            'html' => $html,
            'page' => (int) ($pagination['current_page'] ?? $validated['page']),
            'last_page' => (int) ($pagination['last_page'] ?? $validated['page']),
            'deals' => $deals,
        ]);
    }
}
