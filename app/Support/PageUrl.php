<?php

namespace App\Support;

// Public URL shapes, in one place (eVaistine.lt, 2026-10-07):
//   product           /p/{slug}             never contains the category, so a
//                                            product keeps its URL when it moves
//   listing           /{slug}[/{slug2}]      category, pharmacy, keyword page,
//                                            pharmacy x category
//   search            /paieska/{query}
// New code builds paths with these; first segments of other routes are
// reserved in config('routing.reserved_slugs').
class PageUrl
{
    public static function product(string $slug): string
    {
        return '/p/' . $slug;
    }

    public static function listing(string $slug, ?string $secondSlug = null): string
    {
        return '/' . $slug . ($secondSlug !== null && $secondSlug !== '' ? '/' . $secondSlug : '');
    }

    public static function search(string $query): string
    {
        return '/paieska/' . rawurlencode($query);
    }
}
