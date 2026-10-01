<?php

namespace App\Support;

// Ported from discount/src/lib/schema-utils.ts generateProductsItemListSchema /
// generateStoresItemListSchema — both were the same shape, just different item sources.
class ItemListSchema
{
    // $totalCount: the real total match count (e.g. a keyword/category
    // page's matchingTotal), not just count($items) — $items is usually
    // only the current page's ~20-24 results, so without this the schema
    // understated a page's true size (confirmed live: a keyword page with
    // 100 real matches reported "numberOfItems": 20). Defaults to
    // count($items) for callers with nothing paginated to under-report
    // (e.g. StoreController's fixed store list).
    //
    // Each $item may optionally carry 'price' (float/string) — when
    // present, adds an inline Offer so the list item itself carries price
    // data, not just name/url/image. Omitted (not "price": null) when a
    // particular item has no price, since Offer requires a real price.
    // Optional 'price_valid_until' (Y-m-d) adds the Offer's priceValidUntil.
    public static function build(string $name, array $items, ?int $totalCount = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => $totalCount ?? count($items),
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => url($item['href']),
                'image' => $item['image'] ?? null,
                'item' => isset($item['price']) && (float) $item['price'] > 0 ? array_filter([
                    '@type' => 'Product',
                    'name' => $item['name'],
                    'url' => url($item['href']),
                    'image' => $item['image'] ?? null,
                    'offers' => array_filter([
                        '@type' => 'Offer',
                        'price' => (string) $item['price'],
                        'priceCurrency' => 'EUR',
                        'priceValidUntil' => $item['price_valid_until'] ?? null,
                        'availability' => 'https://schema.org/InStock',
                        'url' => url($item['href']),
                    ]),
                ]) : null,
            ]))->all(),
        ];
    }
}
