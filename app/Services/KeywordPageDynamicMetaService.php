<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Support\LithuanianPlural;
use Illuminate\Support\Collection;

class KeywordPageDynamicMetaService
{

    /**
     * @param  Collection<int, Discount>  $discounts
     * @return array{seo_title: string, seo_description: string, meta_title: string, meta_description: string}
     */
    public function build(KeywordPage $page, Collection $discounts, int $matchingTotal): array
    {
        $keyword = $this->titleKeyword($page);
        // Title's first word uses the dative form ("Ledams akcija" instead
        // of "Ledai akcija") — explicit product decision, title only; the
        // description keeps the nominative $keyword above unchanged.
        $titleKeywordDative = $this->titleKeywordDative($page);

        $priced = $discounts->filter(fn (Discount $d) => (float) $d->discounted_price > 0);
        $minPrice = $priced->min('discounted_price');
        $maxPrice = $priced->max('discounted_price');

        $minLabel = $this->formatPrice($minPrice);
        $maxLabel = $this->formatPrice($maxPrice);
        $storeList = $this->buildStoreList($discounts);

        // No live offers (the page still renders, with links to related
        // keyword pages) — a "| 0 pasiūlymų" title would read as broken in
        // search results, so drop the count/price instead.
        if ($matchingTotal === 0) {
            $metaTitle = "{$titleKeywordDative} – kainos ir akcijos";
            $metaDescription = "{$keyword}: šiuo metu aktyvių akcijų nėra. Naujos akcijos atsiranda kas savaitę – palyginkite panašių prekių pasiūlymus.";
        } else {
            $metaTitle = $this->buildMetaTitle($titleKeywordDative, $minLabel, $matchingTotal);
            $metaDescription = $this->buildMetaDescription($keyword, $minLabel, $maxLabel, $matchingTotal, $storeList);
        }

        return [
            'seo_title' => $this->heading($page),
            'seo_description' => strip_tags($page->intro_html ?? ''),
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
        ];
    }

    private function titleKeyword(KeywordPage $page): string
    {
        return $this->capitalizeKeyword(trim((string) $page->title) . ' akcija');
    }

    // "Akcija ledams" instead of "Ledai akcija" — falls back to the plain
    // nominative title (same as titleKeyword()) when a page has no
    // grammar_dative authored yet, same null-safety pattern already used
    // for this column elsewhere (e.g. KeywordPageService.php:291,1356).
    private function titleKeywordDative(KeywordPage $page): string
    {
        $dative = trim((string) $page->grammar_dative);
        $word = $dative !== '' ? $dative : trim((string) $page->title);

        return 'Akcija ' . mb_strtolower($word);
    }

    public function heading(KeywordPage $page): string
    {
        $title = trim((string) $page->title);
        if ($title === '') {
            return '';
        }

        // Genitive ("Grietinės akcijos…", "Lavazzos akcijos…") — the
        // nominative ("Grietinė akcijos…") isn't grammatical Lithuanian.
        // Every published page has grammar_genitive (240/240, 2026-09-26);
        // the title stays as a fallback for a half-authored draft.
        $genitive = trim((string) $page->grammar_genitive);

        return $this->capitalizeKeyword($genitive !== '' ? $genitive : $title) . ' akcijos ir nuolaidos šią savaitę';
    }

    private function buildMetaTitle(string $keyword, ?string $minPrice, int $count): string
    {
        $offerWord = LithuanianPlural::offerWord($count);

        if ($minPrice !== null) {
            return "{$keyword} – kaina nuo {$minPrice} | {$count} {$offerWord}";
        }

        return "{$keyword} | {$count} {$offerWord}";
    }

    private function buildMetaDescription(
        string $keyword,
        ?string $minPrice,
        ?string $maxPrice,
        int $count,
        string $storeList,
    ): string {
        $parts = ["Ieškai pigiau? {$keyword}"];

        if ($minPrice !== null && $maxPrice !== null && $minPrice !== $maxPrice) {
            $parts[] = " nuo {$minPrice} iki {$maxPrice}";
        } elseif ($minPrice !== null) {
            $parts[] = " kaina nuo {$minPrice}";
        }

        $parts[] = ". {$count} " . LithuanianPlural::offerWord($count);

        if ($storeList !== '') {
            $parts[] = " – {$storeList}";
        }

        return implode('', $parts);
    }

    /**
     * @param  Collection<int, Discount>  $discounts
     */
    private function buildStoreList(Collection $discounts): string
    {
        $names = $discounts
            ->filter(fn (Discount $d) => $d->store !== null)
            ->unique(fn (Discount $d) => $d->store->id)
            ->sortBy(function (Discount $d) {
                $rank = array_search($d->store->slug, \App\Support\StoreListPriority::mainSlugs(), true);

                return $rank === false ? 99 : $rank;
            })
            ->map(fn (Discount $d) => $d->store->name)
            ->values();

        // Plain "Maxima, Norfa, Lidl ir kt." — the old "#MAXIMA #NORFA
        // #PROMOCASH&CARRY" hashtags read as spam in a search snippet.
        $shown = $names->take(4)->all();
        if ($shown === []) {
            return '';
        }

        return implode(', ', $shown) . ($names->count() > 4 ? ' ir kt.' : '');
    }

    private function formatPrice(mixed $price): ?string
    {
        if ($price === null || (float) $price <= 0) {
            return null;
        }

        $value = (float) $price;
        $formatted = floor($value) == $value
            ? number_format($value, 0, '.', '')
            : number_format($value, 2, '.', '');

        return $formatted . ' €';
    }

    private function capitalizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($keyword, 0, 1)) . mb_substr($keyword, 1);
    }
}
