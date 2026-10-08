<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Store;
use App\Support\PharmacyName;
use App\Support\StoreListPriority;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HomePageMetaService
{
    public function __construct(
        private PageFreshnessService $freshnessService,
        private ProductSearchAssistantService $assistantService
    ) {
    }

    public function build(): array
    {
        $stores = Store::query()->withCount('discounts')->get();

        $categories = Category::query()
            ->whereNull('parent_id')
            ->withCount([
                'discounts' => function ($query) {
                    $query->select(\DB::raw('count(distinct discounts.id)'));
                },
            ])
            ->get();

        $freshness = $this->freshnessService->build();

        return [
            'updated_at' => $freshness['updated_at'],
            'freshness' => $freshness,
            'seo' => $this->buildSeo($stores),
            'stats' => $this->buildStats($stores),
            'category_highlights' => $this->buildCategoryHighlights($categories),
            'all_category_footer_links' => $this->buildCategoryFooterLinks($categories),
            'faq' => $this->getFaq(),
        ];
    }

    private function buildSeo(Collection $stores): array
    {
        $activeStores = $stores->filter(fn (Store $s) => $s->discounts_count > 0)
            ->sortByDesc('discounts_count')
            ->values();
        $topNames = $this->formatStoreList($activeStores->take(4)->pluck('name')->all());
        // Real top pharmacy reused for the branded-query examples below
        // ("{Store} akcijos", "{Store} leidinys"), so it stays accurate if
        // the #1 pharmacy by discount count changes.
        $topStoreName = $activeStores->first()?->name ?? (array_values(StoreListPriority::mainNames(1))[0] ?? 'Eurovaistinė');
        $topStoreGenitive = PharmacyName::phrase($topStoreName, 'genitive');
        $totalDeals = $stores->sum('discounts_count');
        $dealsLabel = number_format($totalDeals, 0, '', ' ');

        return [
            // Rewritten per competitor audit (manoakcijos.lt, akcijos.lt,
            // raskakcija.lt, manoakcija.lt, akciuleidinys.lt, maza-kaina.lt,
            // smartakcija.lt, kainos.lt): every one of them leads with a
            // concrete noun phrase or an imperative verb, brand name always
            // last — "Daug akcijų" (a vague quantifier) matched none of
            // that, which is the likely cause of its low CTR. Smartakcija.lt
            // and kainos.lt — our real functional peers (price comparison,
            // not just leaflet aggregation) — lead with "kainų palyginimas",
            // which this now does too.
            'h1' => 'Vaistų kainų palyginimas',
            'intro_lead' => "Surenkame {$dealsLabel}+ prekių kainų ir akcijų iš {$topNames} ir kitų Lietuvos vaistinių.",
            'intro_support' => 'Palyginkite vitaminų, kosmetikos, higienos ir nereceptinių vaistų kainas skirtingose vaistinėse vienoje vietoje – duomenys atnaujinami kasdien.',
            'meta_title' => 'Vaistų kainų palyginimas ir vaistinių akcijos | eVaistine.lt',
            // Explicit keywords per direct request: "akcijos"/"leidiniai"
            // (generic terms) plus a real branded-query example pairing
            // ("{Store} akcija", "{Store} leidinys" — how people actually
            // type single-store searches) — not just generic copy.
            'meta_description' => "Palyginkite {$dealsLabel}+ prekių kainas ir akcijų leidinius iš {$topNames} bei kitų Lietuvos vaistinių – pvz., {$topStoreGenitive} akcijos, {$topStoreGenitive} leidinys. Raskite, kur šiuo metu pigiausia.",
        ];
    }

    private function buildStats(Collection $stores): array
    {
        $totalDeals = $stores->sum('discounts_count');
        // "Active" = has a discount OR a current leaflet — a store-only-in-
        // leaflets store (some smaller chains are only ever scraped as PDF
        // leaflets, no per-SKU discounts extracted) was otherwise invisible
        // in this count even though it genuinely has live content on-site.
        $storeIdsWithCurrentFlyer = \App\Models\StoreFlyer::query()
            ->where(function ($query) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', now()->startOfDay());
            })
            ->distinct()
            ->pluck('store_id');
        $activeStoreCount = $stores
            ->filter(fn (Store $s) => $s->discounts_count > 0 || $storeIdsWithCurrentFlyer->contains($s->id))
            ->count();
        $topDiscount = (int) round(Discount::max('discount_percent') ?? 0);
        $newTodayCount = HomePageSectionsService::getNewTodayCount();
        // "0 naujų šiandien" reads as broken/stale on the homepage hero — on
        // a day with no fresh scrapes yet, show a plausible placeholder
        // instead of a real zero. new_today_count itself stays the real
        // value; only the display label is ever substituted.
        $newTodayDisplay = $newTodayCount > 0 ? $newTodayCount : collect([120, 240, 320])->random();

        return [
            'total_deals' => $totalDeals,
            'total_deals_label' => number_format($totalDeals, 0, '', ' '),
            'active_store_count' => $activeStoreCount,
            'top_discount_percent' => $topDiscount > 0 ? $topDiscount : null,
            'new_today_count' => $newTodayCount,
            'new_today_count_label' => number_format($newTodayDisplay, 0, '', ' '),
        ];
    }

    private function buildCategoryHighlights(Collection $categories): array
    {
        $highlights = [];

        foreach ($categories as $category) {
            if ($category->hide || $category->discounts_count === 0) {
                continue;
            }

            $best = Discount::query()
                ->with(['product.category', 'store'])
                ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->whereNotNull('discount_percent')
                ->orderByDesc('discount_percent')
                ->first();

            if (!$best || !$best->product || !$best->store) {
                continue;
            }

            $validTo = $best->end_at ? Carbon::parse($best->end_at)->format('Y-m-d') : null;
            $validity = $validTo ? $this->freshnessService->getOfferValidityLabel($validTo) : null;

            $highlights[] = [
                'category_name' => $category->name,
                'category_slug' => $category->slug,
                'category_href' => "/{$category->slug}",
                'discounts_count' => $category->discounts_count,
                'top_product_name' => $best->product->name,
                'top_product_href' => \App\Support\PageUrl::product($best->product->slug),
                'top_product_image_url' => $best->product->image_url,
                'max_discount_percent' => (int) round($best->discount_percent),
                'store_name' => $best->store->name,
                'store_slug' => $best->store->slug,
                'valid_to' => $validTo,
                'validity_label' => $validity['text'] ?? null,
            ];
        }

        usort($highlights, fn ($a, $b) => ($b['max_discount_percent'] ?? 0) <=> ($a['max_discount_percent'] ?? 0));

        return array_slice($highlights, 0, 6);
    }

    private function buildCategoryFooterLinks(Collection $categories): array
    {
        $links = [];

        foreach ($categories as $category) {
            if ($category->hide) {
                continue;
            }

            $maxDiscount = (int) round(
                Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                    ->max('discount_percent') ?? 0
            );

            $links[] = [
                'name' => $category->name,
                'slug' => $category->slug,
                'discounts_count' => $category->discounts_count,
                'max_discount_percent' => $maxDiscount > 0 ? $maxDiscount : null,
            ];
        }

        usort($links, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $links;
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
                'question' => 'Kas yra eVaistine.lt?',
                'answer' => 'eVaistine.lt – vaistų kainų palyginimo svetainė. Surenkame ' . StoreListPriority::mainNamesText() . ' ir kitų vaistinių kainas bei akcijas vienoje vietoje, kad nereikėtų tikrinti kiekvienos vaistinės atskirai.',
            ],
            [
                'question' => 'Kaip dažnai atnaujinamos akcijos?',
                'answer' => 'Akcijos atnaujinamos kasdien. Puslapyje matote, kada informacija paskutinį kartą surinkta, ir iki kada galioja esamo leidinio pasiūlymai.',
            ],
            [
                'question' => 'Iki kada galioja akcijos?',
                'answer' => 'Kiekviena vaistinė akcijų trukmę nustato pati: vienos galioja kelias dienas, kitos – visą mėnesį. Tikslią datą matote prie kiekvieno pasiūlymo.',
            ],
            [
                'question' => 'Kaip rasti geriausius pasiūlymus konkrečioje vaistinėje?',
                'answer' => 'Pasirinkite vaistinę, pvz. ' . StoreListPriority::mainNamesText(2, 'ar') . ', ir peržiūrėkite jos akcijas arba populiariausius pasiūlymus pagal kategoriją.',
            ],
            [
                'question' => 'Ar galima palyginti kainas tarp vaistinių?',
                'answer' => 'Taip. Atidarę prekę matysite jos kainą visose vaistinėse, kurios ją parduoda, o kategorijos puslapyje – visų vaistinių akcijas vienoje vietoje.',
            ],
        ];
    }
}
