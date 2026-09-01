<?php

namespace App\Support;

// Ported from discount/src/lib/schema-utils.ts generateProductSchema. Google
// requires `price` on every Offer — never publish a Product/Offer missing a
// real image or price, incomplete markup is worse than none (it's what trips
// Merchant listings / Product snippet checks).
class ProductSchema
{
    public static function build(array $deal, array $seo, string $currentUrl, ?float $lastKnownPrice = null): ?array
    {
        $product = $deal['product'];

        if (empty($product['image_url'])) {
            return null;
        }

        $offers = collect($deal['offers'] ?? [$deal])
            ->filter(fn ($offer) => (float) ($offer['discounted_price'] ?? 0) > 0)
            ->values();

        if ($offers->isNotEmpty()) {
            $productOffers = $offers->map(function ($offer) use ($currentUrl) {
                $isGenuineDiscount = (float) ($offer['discount_percent'] ?? 0) > 0;
                // SEO audit finding: this offer's own to_date can already be
                // in the past (e.g. it's carried over from $deal['offers']
                // while the product page itself has fallen back to showing
                // a "last known price") — availability must follow that
                // date, not assume every entry here is a live deal.
                $isActive = ProductPageMeta::offerIsActive($offer['to_date'] ?? null);

                return array_filter([
                    '@type' => 'Offer',
                    'price' => (string) $offer['discounted_price'],
                    'priceCurrency' => 'EUR',
                    'availability' => $isActive ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $currentUrl,
                    'seller' => ['@type' => 'Organization', 'name' => $offer['store']['name'] ?? null],
                    'validFrom' => $isGenuineDiscount && $isActive ? ($offer['from_date'] ?? null) : null,
                    'priceValidUntil' => $isGenuineDiscount ? ($offer['to_date'] ?? null) : null,
                ]);
            })->all();
        } elseif ($lastKnownPrice > 0) {
            $productOffers = [
                '@type' => 'Offer',
                'price' => (string) $lastKnownPrice,
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/OutOfStock',
                'url' => $currentUrl,
            ];
        } else {
            return null;
        }

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $seo['meta_title'] ?? $seo['seo_title'] ?? $product['name'],
            'description' => $seo['meta_description'] ?? $seo['seo_description'] ?? ($product['name'] . ' akcija ir nuolaidos'),
            'image' => $product['image_url'],
            'category' => $product['category']['name'] ?? 'Akcijos',
            'url' => $currentUrl,
            'offers' => $productOffers,
        ]);

        if (!empty($product['brand'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product['brand']];
        }

        return $schema;
    }
}
