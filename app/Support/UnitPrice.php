<?php

namespace App\Support;

// Normalized unit price (discounts.unit_price/unit_price_basis: Rimi/Lidl
// scrape it directly, others get it parsed from the product name), shared by
// the deal card, the product page offer card and the Product JSON-LD.
class UnitPrice
{
    // UN/CEFACT codes schema.org's referenceQuantity expects. 'vnt' prices
    // are per piece, which says nothing the offer price doesn't, so skipped.
    private const UNIT_CODES = [
        'kg' => 'KGM',
        'l' => 'LTR',
    ];

    public static function label(mixed $unitPrice, ?string $basis): ?string
    {
        if ($unitPrice === null || $unitPrice === '' || ! $basis) {
            return null;
        }

        $suffix = match ($basis) {
            'kg' => '€/kg',
            'l' => '€/l',
            '10vnt' => '€/10 vnt.',
            default => '€/'.$basis,
        };

        return number_format((float) $unitPrice, 2, ',', ' ').' '.$suffix;
    }

    /**
     * UnitPriceSpecification for an offer, or null when the unit price is
     * missing, estimated (derived by us, not stated by the store) or not
     * per kg/l.
     */
    public static function schema(array $offer): ?array
    {
        $price = (float) ($offer['unit_price'] ?? 0);
        $unitCode = self::UNIT_CODES[$offer['unit_price_basis'] ?? ''] ?? null;

        if ($price <= 0 || $unitCode === null || ! empty($offer['unit_price_estimated'])) {
            return null;
        }

        return [
            '@type' => 'UnitPriceSpecification',
            'price' => (string) round($price, 2),
            'priceCurrency' => 'EUR',
            'referenceQuantity' => [
                '@type' => 'QuantitativeValue',
                'value' => '1',
                'unitCode' => $unitCode,
            ],
        ];
    }
}
