<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\ProductController;
use App\Models\ProductFavorite;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FavoritesController extends Controller
{
    // Plain endpoint behind the deal-card grid's <x-favorite-button> (Alpine,
    // no Livewire) — see FavoritedProducts. Not behind the 'auth' middleware
    // on purpose: that would redirect a guest to /login, but the frontend
    // needs a JSON 401 here so it can open the login modal in place instead.
    public function toggle(int $product)
    {
        if (! auth()->check()) {
            return response()->json(['authenticated' => false], 401);
        }

        $existing = ProductFavorite::where('user_id', auth()->id())
            ->where('product_id', $product)
            ->first();

        if ($existing) {
            $existing->delete();
            $favorited = false;
        } else {
            ProductFavorite::create(['user_id' => auth()->id(), 'product_id' => $product]);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }

    // Reuses Api\ProductController::getFavoriteProducts() in-process (same
    // pattern as HomeController/AkcijosController) — $request->user() still
    // resolves correctly here since this route runs behind the "auth" (web
    // session) middleware, same guard chain the API method expects.
    public function index(Request $request, ProductController $api)
    {
        $payload = json_decode($api->getFavoriteProducts($request)->getContent(), true);

        // calculateStoreTotals() (private, inside Api\ProductController) doesn't
        // return a slug, only store_id/store_name — needed here for the store logo.
        $storeSlugs = Store::whereIn('id', array_column($payload['store_totals'], 'store_id'))->pluck('slug', 'id');
        $storeTotals = array_map(fn ($total) => $total + ['store_slug' => $storeSlugs[$total['store_id']] ?? null], $payload['store_totals']);

        // Same editorial priority order as the home hero chips / store
        // directory — StoreListPriority::sort() expects 'slug'/'discounts_count'
        // keys and drops anything with a zero count, so map this array's own
        // field names onto those rather than duplicating the ranking logic.
        $storeTotals = \App\Support\StoreListPriority::sort(array_map(
            fn ($total) => $total + ['slug' => $total['store_slug'], 'discounts_count' => $total['product_count']],
            $storeTotals
        ));

        return view('favorites.index', [
            'title' => 'Stebimos prekės | eVaistine.lt',
            'robots' => 'noindex, nofollow',
            'products' => $payload['products'],
            'storeTotals' => $storeTotals,
            'savingsSummary' => $this->resolveSavingsSummary($payload['products']),
        ]);
    }

    // Ported from discount/src/components/favorites/favorites-content.tsx's
    // resolveSavingsSummary()/countDaysUntilDate() — total possible savings
    // across all favorited discounts, and how many expire within 24h.
    private function resolveSavingsSummary(array $products): array
    {
        $totalSavings = 0;
        $expiringSoonCount = 0;
        $today = Carbon::today();

        foreach ($products as $product) {
            $discounted = (float) ($product['discounted_price'] ?? 0);
            $original = (float) ($product['original_price'] ?? 0);

            if ($discounted <= 0) {
                continue;
            }

            if ($original > $discounted) {
                $totalSavings += $original - $discounted;
            }

            if (! empty($product['to_date'])) {
                // Explicit timestamp math (not diffInDays()) — Phase 4 found this
                // Carbon version's diffIn*() sign convention isn't the "always
                // positive" default you'd expect, so don't rely on it here.
                $end = Carbon::parse($product['to_date'])->endOfDay();
                $daysLeft = (int) ceil(($end->timestamp - $today->timestamp) / 86400);
                if ($daysLeft >= 0 && $daysLeft <= 1) {
                    $expiringSoonCount++;
                }
            }
        }

        return ['total_savings' => $totalSavings, 'expiring_soon_count' => $expiringSoonCount];
    }
}
