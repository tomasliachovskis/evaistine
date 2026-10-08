<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Support\HomeSectionKeywordBlacklist;
use Illuminate\Support\Collection;

/**
 * Groups discounts into "this is really the same underlying product family"
 * buckets, so a curated deal list doesn't end up with several near-identical
 * variants (e.g. 5 flavors of the same chips bag at the same price) crowding
 * out real variety. Checked in priority order:
 *
 * 1. keyword-page match — topic-level (e.g. "vitaminas-d").
 * 2. category slug — always available, coarsest fallback.
 */
class DealFamilyKeyResolver
{
    private const MIN_KEYWORD_OFFERS = 3;

    private ?Collection $keywordPages = null;

    public function __construct(private KeywordPageService $keywordPageService)
    {
    }

    public function resolve(Discount $discount): string
    {
        $keywordSlug = $this->resolveKeywordSlug($discount);
        if ($keywordSlug !== null) {
            return "keyword:{$keywordSlug}";
        }

        $categorySlug = $discount->product?->category?->slug ?? 'unknown';

        return "category:{$categorySlug}";
    }

    private function resolveKeywordSlug(Discount $discount): ?string
    {
        foreach ($this->loadKeywordPages() as $page) {
            if ($this->keywordPageService->productMatchesKeywordPage($discount, $page)) {
                return $page->slug;
            }
        }

        return null;
    }

    private function loadKeywordPages(): Collection
    {
        if ($this->keywordPages !== null) {
            return $this->keywordPages;
        }

        return $this->keywordPages = KeywordPage::query()
            ->published()
            ->where('matching_offers_count', '>=', self::MIN_KEYWORD_OFFERS)
            ->whereNotIn('slug', HomeSectionKeywordBlacklist::SLUGS)
            ->orderByDesc('matching_offers_count')
            ->get();
    }
}
