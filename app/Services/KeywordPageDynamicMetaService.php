<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Support\LithuanianPlural;
use Illuminate\Support\Collection;

class KeywordPageDynamicMetaService
{
    // The 5 nationally recognizable chains — same list as
    // ProductController::MAIN_STORE_SLUGS / KeywordPageService::
    // PRIORITY_STORE_NAMES, duplicated locally rather than shared since
    // this class doesn't otherwise depend on either (matches this
    // codebase's own existing convention of each class keeping its own
    // small copy of this list).
    private const PRIORITY_STORE_SLUGS = ['maxima', 'norfa', 'lidl', 'iki', 'rimi'];

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
        $storeHashtags = $this->buildStoreHashtags($discounts);

        $metaTitle = $this->buildMetaTitle($titleKeywordDative, $minLabel, $matchingTotal);
        $metaDescription = $this->buildMetaDescription($keyword, $minLabel, $maxLabel, $matchingTotal, $storeHashtags);

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

        return $this->capitalizeKeyword($title) . ' akcijos ir nuolaidos šią savaitę';
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
        string $storeHashtags,
    ): string {
        $parts = ["Ieškai pigiau? {$keyword}"];

        if ($minPrice !== null && $maxPrice !== null && $minPrice !== $maxPrice) {
            $parts[] = " nuo {$minPrice} iki {$maxPrice}";
        } elseif ($minPrice !== null) {
            $parts[] = " kaina nuo {$minPrice}";
        }

        $parts[] = ". {$count} " . LithuanianPlural::offerWord($count);

        if ($storeHashtags !== '') {
            $parts[] = ": {$storeHashtags}";
        }

        return implode('', $parts);
    }

    /**
     * @param  Collection<int, Discount>  $discounts
     */
    private function buildStoreHashtags(Collection $discounts): string
    {
        $names = $discounts
            ->filter(fn (Discount $d) => $d->store !== null)
            ->unique(fn (Discount $d) => $d->store->id)
            ->sortBy(function (Discount $d) {
                $rank = array_search($d->store->slug, self::PRIORITY_STORE_SLUGS, true);

                return $rank === false ? 99 : $rank;
            })
            ->take(5)
            ->map(fn (Discount $d) => '#' . mb_strtoupper(preg_replace('/\s+/', '', $d->store->name)))
            ->values()
            ->all();

        return implode(' ', $names);
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
