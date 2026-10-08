<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Store;
use App\Support\PharmacyName;
use App\Support\StoreListPriority;
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
            ? "/{$store->slug}/{$categorySlug}"
            : "/{$store->slug}";

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
        // Batched once for the whole collection (not per-store inside the
        // map below) — this method also feeds /vaistines, where $stores
        // can be every store site-wide, not just the homepage's 5.
        // Only leaflets a visitor can open (a pending one has no pages yet,
        // and its store's /leidinys page still redirects).
        $leafletCounts = \App\Models\StoreFlyer::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->active()
            ->ready()
            ->currentlyValid()
            ->selectRaw('store_id, count(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        return $stores->map(function (Store $store) use ($leafletCounts) {
            $bestOffer = $this->buildBestOfferForStore($store);
            $maxDiscount = $this->getMaxDiscountPercentForStore($store);

            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'discounts_count' => $store->discounts_count ?? 0,
                'leaflets_count' => (int) ($leafletCounts[$store->id] ?? 0),
                'shows_discounts_page' => $store->showsDiscountsPage(),
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
                'store_href' => "/{$store->slug}",
                'product_name' => $bestOffer['product_name'] ?? 'Peržiūrėti visas akcijas',
                'product_href' => $bestOffer['product_href'] ?? "/{$store->slug}",
                'discount_percent' => $bestOffer['discount_percent'] ?? $this->getMaxDiscountPercentForStore($store) ?? 0,
                'valid_to' => $bestOffer['valid_to'] ?? $validTo,
            ];
        })->values()->all();

        return [
            'updated_at' => $freshness['updated_at'],
            'freshness' => $freshness,
            'seo' => [
                'h1' => 'Vaistinių akcijos Lietuvoje',
                'intro' => "Akcijų leidiniai ir nuolaidos iš {$topNames} bei kitų Lietuvos vaistinių. Pasirinkite vaistinę žemiau.",
                'meta_title' => 'Vaistinių akcijos ir leidiniai – ' . StoreListPriority::mainNamesText(3),
                'meta_description' => "Visų {$storeCount} vaistinių akcijos ir leidiniai: {$topNames}. Palyginkite kainas ir nuolaidas vienoje vietoje.",
            ],
            'best_offers' => [
                'title' => 'Geriausi pasiūlymai pagal vaistinę',
                'rows' => $rows,
            ],
            'faq' => $this->getFaq(),
        ];
    }

    private function formatStoreList(array $names): string
    {
        if (count($names) === 0) {
            return StoreListPriority::mainNamesText(4);
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
                'question' => 'Kur rasti ' . ($exampleGenitive = PharmacyName::phrase($exampleName = array_values(StoreListPriority::mainNames(1))[0] ?? 'Eurovaistinė', 'genitive')) . ' akcijas?',
                'answer' => mb_ucfirst($exampleGenitive) . ' akcijas rasite paspaudę ' . PharmacyName::phrase($exampleName, 'genitive') . ' kortelę šiame sąraše. Visos aktyvios nuolaidos atnaujinamos kasdien.',
            ],
            [
                'question' => 'Ar eVaistine.lt rodo visas Lietuvos vaistines?',
                'answer' => 'Stebime didžiuosius vaistinių tinklus (' . StoreListPriority::mainNamesText() . ') ir internetines vaistines, turinčias leidimą prekiauti nuotoliniu būdu. Sąrašas nuolat plečiamas.',
            ],
            [
                'question' => 'Kaip dažnai atnaujinamos vaistinių akcijos?',
                'answer' => 'Kainas ir akcijas tikriname kasdien. Vaistinių leidiniai dažniausiai galioja kelias savaites ar mėnesį, o atskiros akcijos gali keistis dažniau.',
            ],
            [
                'question' => 'Ar galiu palyginti kainas tarp vaistinių?',
                'answer' => 'Taip. Pasirinkite kategoriją, pvz. vitaminai ir maisto papildai, ir palyginkite visų vaistinių akcijas vienoje vietoje. Atidarę prekę matysite jos kainą kiekvienoje vaistinėje.',
            ],
            [
                'question' => 'Kuo skiriasi vaistinės ir kategorijos puslapiai?',
                'answer' => 'Vaistinės puslapyje matote vienos vaistinės akcijas. Kategorijos puslapyje – tos kategorijos akcijas visose vaistinėse.',
            ],
        ];
    }
}
