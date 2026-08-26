<?php

namespace App\Support;

// Reads config/stores.php — the frontend-only per-store metadata (brand color,
// hero-logo inversion) ported from discount/src/lib/stores.ts. The Store model
// itself has no equivalent columns.
class StoreDisplayMeta
{
    public static function brandColor(string $slug): string
    {
        return config("stores.brand_colors.{$slug}") ?? config('stores.brand_color_fallback');
    }

    public static function shouldInvertHeroLogo(string $slug): bool
    {
        return in_array($slug, config('stores.hero_invert_logos', []), true);
    }

    public static function isStoreSlug(string $slug): bool
    {
        return in_array($slug, array_column(config('stores.stores', []), 'slug'), true);
    }
}
