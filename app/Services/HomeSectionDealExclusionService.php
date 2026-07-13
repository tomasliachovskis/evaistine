<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Support\HomeSectionKeywordBlacklist;
use Illuminate\Support\Collection;

class HomeSectionDealExclusionService
{
    /** @var KeywordPageService */
    private $keywordPageService;

    /** @var Collection<int, KeywordPage>|null */
    private $blacklistedPages;

    /** @var list<string>|null */
    private $blacklistNeedles;

    public function __construct(KeywordPageService $keywordPageService)
    {
        $this->keywordPageService = $keywordPageService;
    }

    public function isExcluded(Discount $discount): bool
    {
        $productName = mb_strtolower($discount->product?->name ?? '');

        foreach ($this->blacklistNeedles() as $needle) {
            if ($needle !== '' && mb_strpos($productName, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Discount>  $discounts
     * @return list<Discount>
     */
    public function filterDiscountArray(array $discounts): array
    {
        return array_values(array_filter(
            $discounts,
            fn (Discount $discount) => !$this->isExcluded($discount)
        ));
    }

    public function filterDiscounts(Collection $discounts): Collection
    {
        return $discounts->filter(fn (Discount $discount) => !$this->isExcluded($discount))->values();
    }

    /**
     * @return Collection<int, KeywordPage>
     */
    private function blacklistedPages(): Collection
    {
        if ($this->blacklistedPages === null) {
            $this->blacklistedPages = KeywordPage::query()
                ->whereIn('slug', HomeSectionKeywordBlacklist::SLUGS)
                ->get();
        }

        return $this->blacklistedPages;
    }

    /**
     * @return list<string>
     */
    private function blacklistNeedles(): array
    {
        if ($this->blacklistNeedles !== null) {
            return $this->blacklistNeedles;
        }

        $needles = [];

        foreach ($this->blacklistedPages() as $page) {
            $needles[] = mb_strtolower($page->slug);

            foreach (array_slice((array) ($page->search_terms ?? []), 0, 3) as $term) {
                $term = mb_strtolower(trim((string) $term));
                if ($term !== '' && mb_strlen($term) >= 3) {
                    $needles[] = $term;
                }
            }
        }

        $this->blacklistNeedles = array_values(array_unique($needles));

        return $this->blacklistNeedles;
    }
}
