<?php

namespace App\Support;

// Ported from discount/src/lib/schema-utils.ts generateBreadcrumbSchema — builds
// a schema.org BreadcrumbList. $items is an ordered list of ['name' => ..., 'href' => ...]
// (the last item's href should be the current page's own canonical path).
class BreadcrumbSchema
{
    public static function build(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => url($item['href']),
            ])->all(),
        ];
    }
}
