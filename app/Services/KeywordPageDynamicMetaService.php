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
        // Built around the commercial queries autocomplete shows for these
        // pages ("vitaminas d kaina", "magnis akcija"): nominative title in
        // the meta title, genitive in the H1 and description.
        $title = $this->capitalizeKeyword(trim((string) $page->title));
        $genitive = $this->genitive($page);

        $priced = $discounts->filter(fn (Discount $d) => (float) $d->discounted_price > 0);
        $minLabel = $this->formatPrice($priced->min('discounted_price'));
        $maxLabel = $this->formatPrice($priced->max('discounted_price'));
        $storeList = $this->buildStoreList($discounts);

        // No live offers (the page still renders, with links to related
        // keyword pages): a "| 0 pasiūlymų" title would read as broken.
        if ($matchingTotal === 0) {
            $metaTitle = "{$title} kainos ir akcijos vaistinėse";
            $metaDescription = "{$title}: šiuo metu aktyvių pasiūlymų nėra. Palyginkite panašių prekių kainas vaistinėse.";
        } else {
            $metaTitle = $this->buildMetaTitle($title, $minLabel, $matchingTotal);
            $metaDescription = $this->buildMetaDescription($genitive, $minLabel, $maxLabel, $matchingTotal, $storeList);
        }

        return [
            'seo_title' => $this->heading($page),
            'seo_description' => strip_tags($page->intro_html ?? ''),
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
        ];
    }

    // Genitive ("vitamino D", "Biodermos"), the title when no form is
    // authored. GPT returns the forms lowercase, so the title's own casing is
    // put back: codes and brand words keep their capitals mid-sentence.
    public function genitive(KeywordPage $page): string
    {
        $title = trim((string) $page->title);
        $genitive = trim((string) $page->grammar_genitive);
        if ($genitive === '') {
            return $title;
        }

        $titleWords = preg_split('/\s+/u', $title);
        $isBrandPage = ! empty($page->brands);

        $words = array_map(function (string $word) use ($titleWords, $isBrandPage) {
            foreach ($titleWords as $index => $titleWord) {
                $hasCapitals = $titleWord !== mb_strtolower($titleWord);
                if (! $hasCapitals || ($index === 0 && ! $isBrandPage && mb_strtolower($word) !== mb_strtolower($titleWord))) {
                    continue;
                }
                if (mb_strtolower($word) === mb_strtolower($titleWord)) {
                    return $titleWord;
                }
                // Inflected brand word ("biodermos" from "Bioderma").
                if (($index > 0 || $isBrandPage) && mb_strlen($titleWord) >= 4
                    && mb_strtolower(mb_substr($word, 0, mb_strlen($titleWord) - 1)) === mb_strtolower(mb_substr($titleWord, 0, -1))) {
                    return mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
                }
            }

            return $word;
        }, preg_split('/\s+/u', $genitive));

        return implode(' ', $words);
    }

    public function heading(KeywordPage $page): string
    {
        if (trim((string) $page->title) === '') {
            return '';
        }

        // Genitive ("Vitamino D kainos…"): the nominative isn't grammatical here.
        return $this->capitalizeKeyword($this->genitive($page)) . ' kainos ir akcijos vaistinėse';
    }

    private function buildMetaTitle(string $title, ?string $minPrice, int $count): string
    {
        $offers = "{$count} " . LithuanianPlural::offerWord($count) . ' vaistinėse';

        return $minPrice !== null
            ? "{$title} kaina nuo {$minPrice} | {$offers}"
            : "{$title} kainos | {$offers}";
    }

    private function buildMetaDescription(
        string $genitive,
        ?string $minPrice,
        ?string $maxPrice,
        int $count,
        string $storeList,
    ): string {
        $text = "Palyginkite {$genitive} kainas vaistinėse";

        if ($minPrice !== null && $maxPrice !== null && $minPrice !== $maxPrice) {
            $text .= ": nuo {$minPrice} iki {$maxPrice}";
        } elseif ($minPrice !== null) {
            $text .= ": nuo {$minPrice}";
        }

        $text .= ". {$count} " . LithuanianPlural::offerWord($count);

        if ($storeList !== '') {
            $text .= " – {$storeList}";
        }

        return str_ends_with($text, '.') ? $text : $text . '.';
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

        // Plain "Eurovaistinė, Camelia ir kt.", not hashtags.
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
