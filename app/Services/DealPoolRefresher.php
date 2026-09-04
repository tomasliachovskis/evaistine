<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\Store;
use App\Support\FoodCategorySlugs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes ranked "best deals" into the curated_deals table instead of leaving
 * them behind a Cache::remember() that gets invalidated wholesale by every
 * discounts:process batch. See the "Replace store-scoped best-deals caching"
 * plan for the full rationale — the short version: the write side
 * (discounts:process --only-store) is already precisely store-scoped, so the
 * read side should be too, instead of recomputing every store's carousels on
 * every 5-minute tick regardless of which store actually changed.
 */
class DealPoolRefresher
{
    private const CATEGORY_LIMIT = 8;

    private const TOP_OFFERS_LIMIT = 10;

    private const HOME_BEST_LIMIT = 10;

    private const HOME_BEST_MAX_PER_CATEGORY = 2;

    private const HOME_NON_FOOD_LIMIT = 25;

    private const HOME_NON_FOOD_MAX_PER_CATEGORY = 4;

    private const HOME_FOOD_LIMIT = 40;

    private const HOME_FOOD_MAX_PER_CATEGORY = 6;

    /**
     * Same hand-picked display order ProductController::buildBestByCategorySections()
     * used to hardcode — kept here now that this is where categories are
     * iterated for the persisted carousels.
     */
    private const CATEGORY_CAROUSEL_ORDER = [
        'bakaleja',
        'gerimai-kava-arbata',
        'pieno-produktai-ir-kiausiniai',
        'mesa-ir-zuvis',
        'duonos-gaminiai',
        'saldumynai-ir-uzkandziai',
        'saldytas-maistas-ir-ledai',
        'vaisiai-ir-darzoves',
        'kosmetika-ir-higiena',
        'buitine-chemija-valymo-priemones',
        'namu-ukio-ir-laisvalaikio-prekes',
        'gyvunu-prekes',
        'vaiku-ir-kudikiu-prekes',
        'augalai-geles',
    ];

    public function __construct(private HomeDealPoolService $pool)
    {
    }

    /**
     * The real per-tick entrypoint, called from ProcessDiscounts::handle()
     * after it finishes writing discounts for the given store(s). Refreshes
     * everything those stores can affect: their own carousels/top-offers, the
     * cross-store global-category buckets, and — now that refreshHomePools()
     * reads from those same global_category rows instead of running its own
     * ~19s keyword-page fan-out — the home page pools too, all cheap enough
     * to run on every batch instead of needing a separate schedule.
     *
     * @param  list<int>  $storeIds
     */
    public function refreshAfterBatch(array $storeIds): void
    {
        foreach (array_unique($storeIds) as $storeId) {
            $this->refreshForStore($storeId);
        }

        $this->refreshGlobalCategoryBuckets();
        $this->refreshHomePools();
    }

    public function refreshForStore(int $storeId): void
    {
        DB::transaction(function () use ($storeId) {
            CuratedDeal::where('store_id', $storeId)->delete();

            $rows = [];

            foreach ($this->carouselCategories() as $category) {
                $discounts = $this->pool->bestForCategory($category->id, self::CATEGORY_LIMIT, $storeId);
                $rows = array_merge($rows, $this->buildRows($discounts, [
                    'store_id' => $storeId,
                    'scope' => 'store_category',
                    'category_id' => $category->id,
                ]));
            }

            $topOffers = $this->pool->bestForStore($storeId, self::TOP_OFFERS_LIMIT);
            $rows = array_merge($rows, $this->buildRows(collect($topOffers), [
                'store_id' => $storeId,
                'scope' => 'store_top_offers',
                'category_id' => null,
            ]));

            $this->insertRows($rows);
        });
    }

    public function refreshGlobalCategoryBuckets(): void
    {
        DB::transaction(function () {
            CuratedDeal::whereNull('store_id')->where('scope', 'global_category')->delete();

            $rows = [];

            foreach ($this->carouselCategories() as $category) {
                $discounts = $this->pool->bestForCategory($category->id, self::CATEGORY_LIMIT, null);
                $rows = array_merge($rows, $this->buildRows($discounts, [
                    'store_id' => null,
                    'scope' => 'global_category',
                    'category_id' => $category->id,
                ]));
            }

            $this->insertRows($rows);
        });
    }

    /**
     * The home page's food/non-food/best sections — built directly from the
     * already-persisted global_category rows (refreshGlobalCategoryBuckets(),
     * cheap, runs every batch) instead of a separate live computation.
     *
     * This used to call HomeDealPoolService::buildPools(), which fanned out
     * to HomeKeywordDealPoolBuilder's per-keyword-page product-name LIKE
     * search across ~38 keyword pages — measured at ~19s cold (219 queries).
     * Reusing global_category avoids that entirely: no extra query beyond
     * reading rows that already exist, classified into food/non-food by
     * root category slug (FoodCategorySlugs) instead of by keyword page.
     * That's why this can now run on every batch instead of its own
     * separate 30-minute schedule — see refreshAfterBatch().
     */
    public function refreshHomePools(): void
    {
        $rows = CuratedDeal::query()
            ->whereNull('store_id')
            ->where('scope', 'global_category')
            ->orderBy('category_id')
            ->orderBy('position')
            ->get(['category_id', 'position', 'discount_id', 'deal_score']);

        $foodCategoryIds = Category::whereIn('slug', FoodCategorySlugs::FOOD)->pluck('id')->all();
        $nonFoodCategoryIds = Category::whereIn('slug', FoodCategorySlugs::NON_FOOD)
            ->whereNotIn('slug', FoodCategorySlugs::EXCLUDED_FROM_BEST_AND_NON_FOOD)
            ->pluck('id')->all();

        $best = $this->pickFromCategoryRows($rows, $nonFoodCategoryIds, self::HOME_BEST_LIMIT, self::HOME_BEST_MAX_PER_CATEGORY);
        $nonFood = $this->pickFromCategoryRows($rows, $nonFoodCategoryIds, self::HOME_NON_FOOD_LIMIT, self::HOME_NON_FOOD_MAX_PER_CATEGORY);
        $food = $this->pickFromCategoryRows($rows, $foodCategoryIds, self::HOME_FOOD_LIMIT, self::HOME_FOOD_MAX_PER_CATEGORY);

        DB::transaction(function () use ($best, $nonFood, $food) {
            CuratedDeal::whereNull('store_id')
                ->whereIn('scope', ['home_food', 'home_non_food', 'home_best'])
                ->delete();

            $rows = array_merge(
                $this->buildRowsFromPicks($best, 'home_best'),
                $this->buildRowsFromPicks($food, 'home_food'),
                $this->buildRowsFromPicks($nonFood, 'home_non_food'),
            );

            $this->insertRows($rows);
        });
    }

    /**
     * Round-robins across the given categories' already-ranked global_category
     * rows (ascending position = descending deal_score within a category, per
     * bestForCategory()) so the mix isn't dominated by whichever category
     * happens to have the highest-scoring single item — same principle as
     * HomeDealPoolService::pickDiversePool(), simplified since these rows are
     * already family-capped and priceless-mixed by refreshGlobalCategoryBuckets().
     *
     * @param  Collection<int, CuratedDeal>  $rows
     * @param  list<int>  $categoryIds
     * @return list<array{discount_id:int, deal_score:?float}>
     */
    private function pickFromCategoryRows(Collection $rows, array $categoryIds, int $limit, int $maxPerCategory): array
    {
        $byCategory = $rows->whereIn('category_id', $categoryIds)
            ->groupBy('category_id')
            ->map(fn (Collection $group) => $group->sortBy('position')->values());

        $categoryKeys = $byCategory->keys()->all();
        $indexes = array_fill_keys($categoryKeys, 0);
        $counts = array_fill_keys($categoryKeys, 0);
        $pickedDiscountIds = [];
        $picked = [];

        while (count($picked) < $limit) {
            $addedThisRound = false;

            foreach ($categoryKeys as $categoryId) {
                if (count($picked) >= $limit) {
                    break;
                }
                if ($counts[$categoryId] >= $maxPerCategory) {
                    continue;
                }

                $items = $byCategory[$categoryId];
                $index = $indexes[$categoryId];

                while ($index < $items->count()) {
                    $row = $items[$index];
                    $index++;
                    $indexes[$categoryId] = $index;

                    if (in_array($row->discount_id, $pickedDiscountIds, true)) {
                        continue;
                    }

                    $picked[] = ['discount_id' => $row->discount_id, 'deal_score' => $row->deal_score];
                    $pickedDiscountIds[] = $row->discount_id;
                    $counts[$categoryId]++;
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

    /**
     * @param  list<array{discount_id:int, deal_score:?float}>  $picks
     * @return list<array<string, mixed>>
     */
    private function buildRowsFromPicks(array $picks, string $scope): array
    {
        $now = now();

        return array_values(array_map(fn (array $pick, int $position) => [
            'store_id' => null,
            'scope' => $scope,
            'category_id' => null,
            'position' => $position,
            'discount_id' => $pick['discount_id'],
            'deal_score' => $pick['deal_score'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $picks, array_keys($picks)));
    }

    /**
     * Full rebuild — every store, the global category buckets, and the home
     * pools. Used for the one-time backfill when this ships, and as a nightly
     * safety net: deal_score partly depends on NOW() (the "expiring within 3
     * days" / "added today" bonuses), so a discount's ideal rank can drift
     * over time with no new scrape data to otherwise trigger a refresh.
     */
    public function refreshAll(): void
    {
        foreach (Store::pluck('id') as $storeId) {
            $this->refreshForStore($storeId);
        }

        $this->refreshGlobalCategoryBuckets();
        $this->refreshHomePools();
    }

    /**
     * @return Collection<int, Category>
     */
    private function carouselCategories(): Collection
    {
        return Category::whereNull('parent_id')
            ->where('hide', false)
            ->withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->sortBy(function (Category $category) {
                $position = array_search($category->slug, self::CATEGORY_CAROUSEL_ORDER, true);

                return $position === false ? count(self::CATEGORY_CAROUSEL_ORDER) : $position;
            })
            ->values();
    }

    /**
     * @param  Collection<int, Discount>  $discounts
     * @param  array{store_id: ?int, scope: string, category_id: ?int}  $bucket
     * @return list<array<string, mixed>>
     */
    private function buildRows(Collection $discounts, array $bucket): array
    {
        $now = now();

        return $discounts->values()->map(fn (Discount $discount, int $position) => [
            'store_id' => $bucket['store_id'],
            'scope' => $bucket['scope'],
            'category_id' => $bucket['category_id'],
            'position' => $position,
            'discount_id' => $discount->id,
            'deal_score' => $discount->getAttribute('deal_score'),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('curated_deals')->insert($chunk);
        }
    }
}
