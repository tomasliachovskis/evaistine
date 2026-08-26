<?php

namespace App\Support;

use App\Models\ProductFavorite;

// Per-request memoized favorited product IDs for the current user — same
// memoization FavoriteButton (Livewire) used to do per-component, now shared
// by the plain Blade <x-favorite-button> so a page with dozens of deal cards
// still only queries this once.
class FavoritedProducts
{
    private static ?array $ids = null;

    public static function ids(): array
    {
        if (self::$ids === null) {
            self::$ids = auth()->check()
                ? ProductFavorite::where('user_id', auth()->id())->pluck('product_id')->all()
                : [];
        }

        return self::$ids;
    }

    public static function has(int $productId): bool
    {
        return in_array($productId, self::ids(), true);
    }
}
