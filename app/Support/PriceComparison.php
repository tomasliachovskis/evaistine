<?php

namespace App\Support;

// How one store's price for a product compares with the other stores
// currently selling it, from a formatted deal's 'offers' list. Used on
// leaflet pages, where the store is fixed by the page and the other
// stores' logos are hidden: deal cards ("Pigiausia iš 3 parduotuvių",
// "Rimi pigiau – 1,29 €") and the flyer hotspot cards.
class PriceComparison
{
    /**
     * @return array{cheapest: bool, label: string, others: list<array{store: string, slug: string, price: float}>}|null
     */
    public static function forDeal(array $deal, string $storeSlug): ?array
    {
        $price = (float) ($deal['discounted_price'] ?? 0);

        if ($price <= 0) {
            return null;
        }

        $others = collect($deal['offers'] ?? [])
            ->filter(fn ($o) => ($o['store']['slug'] ?? null) !== $storeSlug && (float) ($o['discounted_price'] ?? 0) > 0)
            ->sortBy('discounted_price')
            ->unique(fn ($o) => $o['store']['slug'])
            ->values();

        if ($others->isEmpty()) {
            return null;
        }

        $cheapestOther = $others->first();
        $otherList = $others->map(fn ($o) => [
            'store' => $o['store']['name'],
            'slug' => $o['store']['slug'],
            'price' => (float) $o['discounted_price'],
        ])->all();

        if ((float) $cheapestOther['discounted_price'] < $price - 0.005) {
            return [
                'cheapest' => false,
                'label' => $cheapestOther['store']['name'].' pigiau – '.self::euro($cheapestOther['discounted_price']),
                'others' => $otherList,
            ];
        }

        $storeCount = $others->count() + 1;
        $storeWord = $storeCount % 10 === 1 && $storeCount % 100 !== 11 ? 'parduotuvės' : 'parduotuvių';

        return [
            'cheapest' => true,
            'label' => "Pigiausia iš {$storeCount} {$storeWord}",
            'others' => $otherList,
        ];
    }

    private static function euro(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' €';
    }
}
