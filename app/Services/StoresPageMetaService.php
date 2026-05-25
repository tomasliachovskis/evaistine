<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StoresPageMetaService
{
    public function __construct(
        private PageFreshnessService $freshnessService
    ) {
    }

    public function buildBestOfferForStore(Store $store): ?array
    {
        $discount = Discount::query()
            ->with(['product.category'])
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->first();

        if (!$discount || !$discount->product) {
            return null;
        }

        $categorySlug = $discount->product->category?->slug;
        $productHref = $categorySlug
            ? "/akcijos/{$store->slug}/{$categorySlug}"
            : "/akcijos/{$store->slug}";

        return [
            'product_name' => $discount->product->name,
            'product_slug' => $discount->product->slug,
            'category_slug' => $categorySlug,
            'product_href' => $productHref,
            'discount_percent' => (int) round($discount->discount_percent),
            'valid_to' => $discount->end_at
                ? Carbon::parse($discount->end_at)->format('Y-m-d')
                : $this->freshnessService->getCurrentWeekRange()['valid_to'],
        ];
    }

    public function getMaxDiscountPercentForStore(Store $store): ?int
    {
        $max = Discount::where('store_id', $store->id)->max('discount_percent');

        return $max !== null ? (int) round($max) : null;
    }

    public function formatStore(Collection $stores): array
    {
        return $stores->map(function (Store $store) {
            $bestOffer = $this->buildBestOfferForStore($store);
            $maxDiscount = $this->getMaxDiscountPercentForStore($store);

            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'discounts_count' => $store->discounts_count ?? 0,
                'max_discount_percent' => $maxDiscount,
                'best_offer' => $bestOffer,
            ];
        })->values()->all();
    }

    public function buildPageMeta(Collection $stores): array
    {
        $freshness = $this->freshnessService->build();
        $activeStores = $stores->filter(fn (Store $s) => ($s->discounts_count ?? 0) > 0)
            ->sortByDesc('discounts_count')
            ->values();

        $topNames = $this->formatStoreList($activeStores->take(4)->pluck('name')->all());
        $storeCount = $stores->count();
        $validTo = $freshness['valid_to'];

        $rows = $activeStores->map(function (Store $store) use ($validTo) {
            $bestOffer = $this->buildBestOfferForStore($store);

            return [
                'store_name' => $store->name,
                'store_slug' => $store->slug,
                'store_href' => "/akcijos/{$store->slug}",
                'product_name' => $bestOffer['product_name'] ?? 'Peržiūrėti visas akcijas',
                'product_href' => $bestOffer['product_href'] ?? "/akcijos/{$store->slug}",
                'discount_percent' => $bestOffer['discount_percent'] ?? $this->getMaxDiscountPercentForStore($store) ?? 0,
                'valid_to' => $bestOffer['valid_to'] ?? $validTo,
            ];
        })->values()->all();

        return [
            'updated_at' => $freshness['updated_at'],
            'freshness' => $freshness,
            'seo' => [
                'h1' => 'Parduotuvių akcijos Lietuvoje',
                'intro' => "Akcijos ir nuolaidos iš {$topNames} bei kitų Lietuvos tinklų. Pasirinkite parduotuvę žemiau.",
                'meta_title' => 'Parduotuvių akcijos Lietuvoje – Maxima, Lidl, Iki, Rimi | SuperAkcijos.lt',
                'meta_description' => "Akcijos {$storeCount} tinkluose: {$topNames}. Palyginkite savaitės nuolaidas vienoje vietoje.",
            ],
            'best_offers' => [
                'title' => 'Geriausi pasiūlymai pagal parduotuvę',
                'rows' => $rows,
            ],
            'faq' => $this->getFaq(),
        ];
    }

    private function formatStoreList(array $names): string
    {
        if (count($names) === 0) {
            return 'Maxima, Lidl, Iki, Rimi';
        }
        if (count($names) === 1) {
            return $names[0];
        }
        if (count($names) === 2) {
            return "{$names[0]} ir {$names[1]}";
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' ir ' . $last;
    }

    private function getFaq(): array
    {
        return [
            [
                'question' => 'Kur rasti Maxima akcijas?',
                'answer' => 'Maxima akcijas rasite paspaudę Maxima kortelę arba nuorodą „Maxima akcijos“. Visos aktyvios nuolaidos atnaujinamos pagal naujausius leidinius.',
            ],
            [
                'question' => 'Ar SuperAkcijos.lt rodo visas Lietuvos parduotuves?',
                'answer' => 'Taip – stebime pagrindinius prekybos tinklus: Maxima, Lidl, Iki, Rimi, Norfa, Aibė ir kitus. Sąrašas nuolat plečiamas.',
            ],
            [
                'question' => 'Kaip dažnai atnaujinamos parduotuvių akcijos?',
                'answer' => 'Akcijos atnaujinamos kasdien. Savaitės leidiniai paprastai galioja nuo pirmadienio – kainos keičiasi kiekvieną savaitę.',
            ],
            [
                'question' => 'Ar galiu palyginti kainas tarp parduotuvių?',
                'answer' => 'Taip. Pasirinkite kategoriją, pvz. vaisiai ir daržovės, ir palyginkite akcijas Maxima, Lidl, Iki ir kituose tinkluose vienoje vietoje.',
            ],
            [
                'question' => 'Kuo skiriasi parduotuvės ir kategorijos puslapiai?',
                'answer' => 'Parduotuvės puslapyje matote vieno tinklo akcijas. Kategorijos puslapyje – tos pačios prekės akcijas visuose tinkluose.',
            ],
        ];
    }
}
