<?php

namespace App\Services;

use App\Models\Discount;
use App\Support\FoodCategorySlugs;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomeDealPoolService
{
    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    private const POPULAR_CATEGORY_IDS = [1, 52, 121, 352, 380];

    private const FOOD_POOL_LIMIT = 50;

    private const NON_FOOD_POOL_LIMIT = 25;

    private const CANDIDATE_LIMIT = 600;

    private const BEST_MAX_PER_CATEGORY = 10;

    private const FOOD_MAX_PER_CATEGORY = 1;

    private const NON_FOOD_MAX_PER_CATEGORY = 1;

    private const FOOD_POOL_MIN_SIZE = 40;

    private const NON_FOOD_POOL_MIN_SIZE = 20;

    private const MAX_DEALS_PER_KEYWORD = 2;

    /** @var HomeKeywordDealPoolBuilder */
    private $keywordPoolBuilder;

    /** @var HomeSectionDealExclusionService */
    private $homeSectionDealExclusion;

    public function __construct(
        HomeKeywordDealPoolBuilder $keywordPoolBuilder,
        HomeSectionDealExclusionService $homeSectionDealExclusion
    ) {
        $this->keywordPoolBuilder = $keywordPoolBuilder;
        $this->homeSectionDealExclusion = $homeSectionDealExclusion;
    }

    public function buildPools(?int $excludeId = null): array
    {
        $allCandidates = $this->fetchScoredCandidates($excludeId);
        $sectionCandidates = $this->homeSectionDealExclusion->filterDiscounts($allCandidates);

        $food = $this->homeSectionDealExclusion->filterDiscountArray(
            $this->keywordPoolBuilder->buildFoodPool()
        );
        if (count($food) < self::FOOD_POOL_MIN_SIZE) {
            $food = $this->supplementPool(
                $food,
                $sectionCandidates,
                FoodCategorySlugs::FOOD,
                [],
                self::FOOD_MAX_PER_CATEGORY,
                self::FOOD_POOL_LIMIT,
            );
        }
        $food = $this->enforceKeywordPoolLimits($food, 'food');

        $nonFood = $this->homeSectionDealExclusion->filterDiscountArray(
            $this->keywordPoolBuilder->buildNonFoodPool()
        );
        if (count($nonFood) < self::NON_FOOD_POOL_MIN_SIZE) {
            $nonFood = $this->supplementPool(
                $nonFood,
                $sectionCandidates,
                FoodCategorySlugs::NON_FOOD,
                FoodCategorySlugs::EXCLUDED_FROM_BEST_AND_NON_FOOD,
                self::NON_FOOD_MAX_PER_CATEGORY,
                self::NON_FOOD_POOL_LIMIT,
            );
        }
        $nonFood = $this->enforceKeywordPoolLimits($nonFood, 'non_food');

        return [
            'best' => $this->pickDiversePool(
                $allCandidates,
                FoodCategorySlugs::NON_FOOD,
                array_merge(
                    FoodCategorySlugs::EXCLUDED_FROM_BEST_AND_NON_FOOD,
                    FoodCategorySlugs::FOOD
                ),
                self::BEST_MAX_PER_CATEGORY
            ),
            'food' => $food,
            'non_food' => $nonFood,
        ];
    }

    /**
     * @param  list<Discount>  $existing
     * @return list<Discount>
     */
    private function supplementPool(
        array $existing,
        Collection $candidates,
        ?array $allowedSlugs,
        array $excludedSlugs,
        int $maxPerCategory,
        int $poolLimit
    ): array {
        $remaining = $poolLimit - count($existing);

        if ($remaining <= 0) {
            return $existing;
        }

        $existingDiscountIds = array_flip(array_map(fn (Discount $discount) => $discount->id, $existing));
        $existingProductIds = array_flip(array_map(fn (Discount $discount) => $discount->product_id, $existing));

        $filteredCandidates = $candidates->filter(function (Discount $discount) use ($existingDiscountIds, $existingProductIds) {
            return !isset($existingDiscountIds[$discount->id])
                && !isset($existingProductIds[$discount->product_id]);
        });

        $additional = $this->pickDiversePool(
            $filteredCandidates,
            $allowedSlugs,
            $excludedSlugs,
            $maxPerCategory,
            $remaining,
        );

        return array_merge($existing, $this->homeSectionDealExclusion->filterDiscountArray($additional));
    }

    /**
     * @param  list<Discount>  $pool
     * @return list<Discount>
     */
    private function enforceKeywordPoolLimits(array $pool, string $segment): array
    {
        $counts = [];
        $result = [];

        foreach ($pool as $discount) {
            $keywordSlug = $discount->getAttribute('home_keyword_slug')
                ?: $this->keywordPoolBuilder->resolveKeywordSlugForDiscount($discount, $segment);

            if ($keywordSlug !== null) {
                $discount->setAttribute('home_keyword_slug', $keywordSlug);
            }

            $groupKey = $keywordSlug
                ?? ('category:' . ($discount->product->category->slug ?? 'unknown'));

            if (($counts[$groupKey] ?? 0) >= self::MAX_DEALS_PER_KEYWORD) {
                continue;
            }

            $counts[$groupKey] = ($counts[$groupKey] ?? 0) + 1;
            $result[] = $discount;
        }

        return $result;
    }

    private function fetchScoredCandidates(?int $excludeId): Collection
    {
        return $this->scoredCandidatesQuery($excludeId)
            ->orderByDesc('deal_score')
            ->orderByDesc('discounts.discount_percent')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();
    }

    /**
     * Top-N deals for a single category (optionally scoped to one store),
     * ranked by the same deal_score used for the home page pools. Used to
     * power one carousel per category on /akcijos and /akcijos/{store}
     * instead of a single cross-category pool.
     */
    public function bestForCategory(int $categoryId, int $limit, ?int $storeId = null): Collection
    {
        // Deliberately skips applyTopProductFilters()'s home-page-only heuristics
        // (min €5 price, excluded "namu-ukio" category) — every category needs
        // its own best deals here, including cheap ones (produce, plants) and
        // the one category the home page spotlight excludes.
        return $this->scoredCandidatesQuery(null, false)
            ->whereNotNull('discounts.discounted_price')
            ->when($storeId, function ($query) use ($storeId) {
                $query->where('discounts.store_id', $storeId);
            })
            ->whereHas('product', function ($productQuery) use ($categoryId) {
                $productQuery->where('category_id', $categoryId);
            })
            ->orderByDesc('deal_score')
            ->orderByDesc('discounts.discount_percent')
            ->limit($limit)
            ->get();
    }

    private function scoredCandidatesQuery(?int $excludeId, bool $applyTopProductFilters = true): Builder
    {
        $popularIds = implode(',', self::POPULAR_CATEGORY_IDS);
        $today = Carbon::today()->toDateString();
        $urgencyCutoff = Carbon::now()->copy()->addDays(3)->toDateTimeString();

        $query = $this->baseQuery();
        if ($applyTopProductFilters) {
            $this->applyTopProductFilters($query);
        }
        $this->excludeIds($query, $excludeId);

        // "prev" = the discount_history row immediately before this discount's
        // current price, used for the deal_score "price just dropped" bonus.
        // A GROUP BY dh.product_id, dh.store_id derived table here used to
        // aggregate the *entire* discount_histories table (160k+ rows and
        // growing) on every call — bestForCategory() calls this once per
        // category (~13x) per store page load, so a cache-miss re-scanned
        // the whole table over a dozen times (~8s total, confirmed via
        // EXPLAIN showing "Using temporary" over 80k+ rows for a single
        // busy store). A correlated per-row subquery instead lets MySQL use
        // discount_histories' (product_id, store_id, id) index directly per
        // discount row: ~20x faster once category/store filters shrink the
        // outer row count (13ms vs 264ms measured), and no slower even
        // fully unscoped (342ms vs 378ms for all 11.8k active discounts).
        return $query
            ->leftJoin('discount_histories as prev', 'prev.id', '=', DB::raw(
                '(SELECT dh.id FROM discount_histories dh
                    WHERE dh.product_id = discounts.product_id AND dh.store_id = discounts.store_id
                    ORDER BY dh.id DESC LIMIT 1)'
            ))
            ->select('discounts.*')
            ->selectRaw(
                "(
                    LEAST(COALESCE(discounts.discount_percent, 0), 70) * 2
                    + COALESCE(discounts.original_price - discounts.discounted_price, 0) * 3
                    + CASE
                        WHEN prev.discounted_price IS NOT NULL
                            AND prev.discounted_price > discounts.discounted_price
                        THEN (prev.discounted_price - discounts.discounted_price) * 5
                        ELSE 0
                    END
                    + CASE
                        WHEN (SELECT category_id FROM products WHERE products.id = discounts.product_id) IN ({$popularIds})
                        THEN 15
                        ELSE 0
                    END
                    + CASE WHEN DATE(discounts.created_at) = ? THEN 5 ELSE 0 END
                    + CASE
                        WHEN discounts.end_at IS NOT NULL
                            AND discounts.end_at >= NOW()
                            AND discounts.end_at <= ?
                        THEN 8
                        ELSE 0
                    END
                ) AS deal_score",
                [$today, $urgencyCutoff]
            );
    }

    private function pickDiversePool(
        Collection $candidates,
        ?array $allowedSlugs,
        array $excludedSlugs,
        int $maxPerCategory,
        int $limit = self::FOOD_POOL_LIMIT
    ): array {
        $available = $candidates->filter(function (Discount $discount) use ($allowedSlugs, $excludedSlugs) {
            $slug = $discount->product->category->slug ?? null;

            if ($slug !== null && in_array($slug, $excludedSlugs, true)) {
                return false;
            }

            if ($allowedSlugs !== null) {
                if ($slug === null || !in_array($slug, $allowedSlugs, true)) {
                    return false;
                }
            }

            return true;
        });

        $byCategory = $available
            ->groupBy(fn (Discount $discount) => $discount->product->category->slug ?? '__unknown__')
            ->map(function (Collection $group) {
                return $group
                    ->sortByDesc(fn (Discount $discount) => $this->resolveDealScore($discount))
                    ->values();
            });

        if ($byCategory->isEmpty()) {
            return [];
        }

        $categoryKeys = $byCategory->keys()->all();
        $categoryIndexes = array_fill_keys($categoryKeys, 0);
        $categoryCounts = array_fill_keys($categoryKeys, 0);
        $picked = [];
        $pickedIds = [];

        while (count($picked) < $limit) {
            $addedThisRound = false;
            $roundKeys = $categoryKeys;
            shuffle($roundKeys);

            foreach ($roundKeys as $slug) {
                if (count($picked) >= $limit) {
                    break;
                }

                if ($categoryCounts[$slug] >= $maxPerCategory) {
                    continue;
                }

                $items = $byCategory[$slug];
                $index = $categoryIndexes[$slug];

                while ($index < $items->count()) {
                    $discount = $items[$index];
                    $index++;
                    $categoryIndexes[$slug] = $index;

                    if (in_array($discount->id, $pickedIds, true)) {
                        continue;
                    }

                    $picked[] = $discount;
                    $pickedIds[] = $discount->id;
                    $categoryCounts[$slug]++;
                    $addedThisRound = true;
                    break;
                }
            }

            if (!$addedThisRound) {
                break;
            }
        }

        if (count($picked) < $limit) {
            foreach ($available as $discount) {
                if (count($picked) >= $limit) {
                    break;
                }

                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[] = $discount->id;
            }
        }

        return $picked;
    }

    private function resolveDealScore(Discount $discount): float
    {
        if (isset($discount->deal_score)) {
            return (float) $discount->deal_score;
        }

        return (float) ($discount->discount_percent ?? 0);
    }

    private function baseQuery(): Builder
    {
        // product.discounts.store and product.discountHistories.store are
        // eager-loaded here (not just product.category) because
        // DiscountResponseFormatter::getProductDiscounts()/getProductDiscountHistories()
        // re-query per item to compute offer_count/min_price/history when the
        // relation isn't already loaded — without this, formatting N candidates
        // fires N extra queries (this was the root cause of the home page's and
        // every store's /akcijos carousel's 600-700 query cold-cache pass).
        return Discount::query()
            ->select('discounts.*')
            ->with(['product.category', 'product.discounts.store', 'product.discountHistories.store', 'store'])
            ->whereNotNull('discounts.discount_percent')
            ->where('discounts.discount_percent', '>', 0);
    }

    private function applyTopProductFilters(Builder $query): Builder
    {
        return $query
            ->whereNotNull('discounts.discounted_price')
            ->where('discounts.discounted_price', '>=', self::MIN_TOP_PRODUCT_PRICE)
            ->whereHas('product.category', function ($categoryQuery) {
                $categoryQuery->whereNotIn('slug', self::EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS);
            });
    }

    private function excludeIds(Builder $query, ?int $excludeId): Builder
    {
        if ($excludeId !== null) {
            $query->where('discounts.id', '!=', $excludeId);
        }

        return $query;
    }
}
