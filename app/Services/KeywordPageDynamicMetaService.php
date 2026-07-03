<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use Illuminate\Support\Collection;

class KeywordPageDynamicMetaService
{
    /**
     * @param  Collection<int, Discount>  $discounts
     * @return array{seo_title: string, seo_description: string, meta_title: string, meta_description: string}
     */
    public function build(KeywordPage $page, Collection $discounts, int $matchingTotal): array
    {
        $primary = $page->primary_keywords ?? [];

        if ($page->slug === 'kava') {
            $mainKw = $this->capitalizeKeyword($primary[0] ?? 'kava akcija');
            $h1 = $mainKw;
            $titleKw = $mainKw;
            $descKw = $mainKw;
        } else {
            $h1 = $page->h1 ?: $this->capitalizeKeyword($primary[0] ?? $page->title);
            $titleKw = $this->capitalizeKeyword($primary[1] ?? $primary[0] ?? ($page->title . ' akcija'));
            $descKw = $this->capitalizeKeyword($primary[2] ?? $primary[0] ?? ($page->title . ' akcija'));
        }

        $priced = $discounts->filter(fn (Discount $d) => (float) $d->discounted_price > 0);
        $minPrice = $priced->min('discounted_price');
        $maxPrice = $priced->max('discounted_price');

        $minLabel = $this->formatPrice($minPrice);
        $maxLabel = $this->formatPrice($maxPrice);
        $storeHashtags = $this->buildStoreHashtags($discounts);

        $metaTitle = $this->buildMetaTitle($titleKw, $minLabel, $matchingTotal);
        $metaDescription = $this->buildMetaDescription($descKw, $minLabel, $maxLabel, $matchingTotal, $storeHashtags);

        return [
            'seo_title' => $h1,
            'seo_description' => strip_tags($page->intro_html ?? ''),
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
        ];
    }

    private function buildMetaTitle(string $keyword, ?string $minPrice, int $count): string
    {
        if ($minPrice !== null) {
            return "{$keyword} – kaina nuo {$minPrice} ({$count})";
        }

        return "{$keyword} ({$count})";
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
            $parts[] = " ✔ nuo {$minPrice} iki {$maxPrice}";
        } elseif ($minPrice !== null) {
            $parts[] = " ✔ kaina nuo {$minPrice}";
        }

        $parts[] = ". {$count} pasiūlymai";

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
            ->map(fn (Discount $d) => $d->store->name ?? null)
            ->filter()
            ->unique()
            ->take(5)
            ->map(fn ($name) => '#' . mb_strtoupper(preg_replace('/\s+/', '', $name)))
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
