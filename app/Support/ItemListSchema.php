<?php

namespace App\Support;

// Ported from discount/src/lib/schema-utils.ts generateProductsItemListSchema /
// generateStoresItemListSchema — both were the same shape, just different item sources.
class ItemListSchema
{
    public static function build(string $name, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => count($items),
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => url($item['href']),
                'image' => $item['image'] ?? null,
            ]))->all(),
        ];
    }
}
