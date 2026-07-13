<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Support\FoodCategorySlugs;
use App\Support\HomeSectionKeywordBlacklist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HomeKeywordDealPoolBuilder
{
    private const MAX_DEALS_PER_KEYWORD = 2;

    private const FOOD_POOL_LIMIT = 50;

    private const NON_FOOD_POOL_LIMIT = 25;

    private const MIN_KEYWORD_OFFERS = 3;

    private const MAX_FOOD_KEYWORD_PAGES = 25;

    private const MAX_NON_FOOD_KEYWORD_PAGES = 13;

    /** @var KeywordPageService */
    private $keywordPageService;

    public function __construct(KeywordPageService $keywordPageService)
    {
        $this->keywordPageService = $keywordPageService;
    }

    public function buildFoodPool(): array
    {
        return Cache::tags(['discounts', 'home', 'home_kw_pool'])
            ->remember('home_kw_food_pool_v4', 7200, function () {
                return $this->buildPoolForSegment('food');
            });
    }

    public function buildNonFoodPool(): array
    {
        return Cache::tags(['discounts', 'home', 'home_kw_pool'])
            ->remember('home_kw_non_food_pool_v4', 7200, function () {
                return $this->buildPoolForSegment('non_food');
            });
    }

    public function resolveKeywordSlugForDiscount(Discount $discount, string $segment): ?string
    {
        foreach ($this->loadAllKeywordPagesForSegment($segment) as $page) {
            if ($this->keywordPageService->productMatchesKeywordPage($discount, $page)) {
                return $page->slug;
            }
        }

        return null;
    }

    private function buildPoolForSegment(string $segment): array
    {
        $pages = $this->loadKeywordPagesForSegment($segment);

        if ($pages->isEmpty()) {
            return [];
        }

        $pageQueues = [];

        foreach ($pages as $page) {
            $deals = $this->keywordPageService
                ->fetchTopDiscountsForPage($page, self::MAX_DEALS_PER_KEYWORD)
                ->values()
                ->all();

            if ($deals !== []) {
                $pageQueues[$page->slug] = $deals;
            }
        }

        if ($pageQueues === []) {
            return [];
        }

        return $this->roundRobinPick($pageQueues, $this->poolLimitForSegment($segment));
    }

    private function poolLimitForSegment(string $segment): int
    {
        return $segment === 'food' ? self::FOOD_POOL_LIMIT : self::NON_FOOD_POOL_LIMIT;
    }

    private function maxKeywordPagesForSegment(string $segment): int
    {
        return $segment === 'food' ? self::MAX_FOOD_KEYWORD_PAGES : self::MAX_NON_FOOD_KEYWORD_PAGES;
    }

    /**
     * @return Collection<int, KeywordPage>
     */
    private function loadAllKeywordPagesForSegment(string $segment): Collection
    {
        return KeywordPage::query()
            ->published()
            ->where('matching_offers_count', '>=', self::MIN_KEYWORD_OFFERS)
            ->whereNotIn('slug', HomeSectionKeywordBlacklist::SLUGS)
            ->orderByDesc('matching_offers_count')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (KeywordPage $page) => $this->classifyKeywordPage($page) === $segment)
            ->values();
    }

    /**
     * @return Collection<int, KeywordPage>
     */
    private function loadKeywordPagesForSegment(string $segment): Collection
    {
        return KeywordPage::query()
            ->published()
            ->where('matching_offers_count', '>=', self::MIN_KEYWORD_OFFERS)
            ->whereNotIn('slug', HomeSectionKeywordBlacklist::SLUGS)
            ->orderByDesc('matching_offers_count')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (KeywordPage $page) => $this->classifyKeywordPage($page) === $segment)
            ->take($this->maxKeywordPagesForSegment($segment))
            ->values();
    }

    private function classifyKeywordPage(KeywordPage $page): ?string
    {
        $slugs = array_values(array_filter((array) ($page->category_slugs ?? [])));

        if ($slugs === []) {
            return null;
        }

        $nonFoodSlugs = array_values(array_diff(
            FoodCategorySlugs::NON_FOOD,
            FoodCategorySlugs::EXCLUDED_FROM_BEST_AND_NON_FOOD,
        ));

        $hasFood = array_intersect($slugs, FoodCategorySlugs::FOOD) !== [];
        $hasNonFood = array_intersect($slugs, $nonFoodSlugs) !== [];

        if ($hasFood && !$hasNonFood) {
            return 'food';
        }

        if ($hasNonFood && !$hasFood) {
            return 'non_food';
        }

        return null;
    }

    /**
     * @param  array<string, list<Discount>>  $pageQueues
     * @return list<Discount>
     */
    private function roundRobinPick(array $pageQueues, int $poolLimit): array
    {
        $picked = [];
        $pickedDiscountIds = [];
        $pickedProductIds = [];
        $pageIndexes = array_fill_keys(array_keys($pageQueues), 0);

        while (count($picked) < $poolLimit) {
            $addedThisRound = false;

            foreach ($pageQueues as $slug => $queue) {
                if (count($picked) >= $poolLimit) {
                    break;
                }

                $index = $pageIndexes[$slug];

                while ($index < count($queue)) {
                    /** @var Discount $discount */
                    $discount = $queue[$index];
                    $index++;
                    $pageIndexes[$slug] = $index;

                    if (in_array($discount->id, $pickedDiscountIds, true)) {
                        continue;
                    }

                    if (in_array($discount->product_id, $pickedProductIds, true)) {
                        continue;
                    }

                    $picked[] = $discount;
                    $discount->setAttribute('home_keyword_slug', $slug);
                    $pickedDiscountIds[] = $discount->id;
                    $pickedProductIds[] = $discount->product_id;
                    $addedThisRound = true;
                    break;
                }
            }

            if (!$addedThisRound) {
                break;
            }
        }

        return $picked;
    }
}
