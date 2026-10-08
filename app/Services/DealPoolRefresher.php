<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\Store;
use App\Support\ProductLineKey;
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

    // Persists a wider candidate pool than the 10 actually shown —
    // HomePageSectionsService::poolFromScope() randomly samples 10 out of
    // these each request, so the "best deals" strip visibly rotates without
    // its own refresh schedule. Drawn from every root category, at most 4
    // per category so one category can't fill it.
    private const HOME_BEST_LIMIT = 40;

    private const HOME_BEST_MAX_PER_CATEGORY = 4;

    public function __construct(
        private HomeDealPoolService $pool,
        private KeywordPageService $keywordPageService,
    ) {
    }

    /**
     * The real per-tick entrypoint, called from ProcessDiscounts::handle()
     * after it finishes writing discounts for the given store(s). Refreshes
     * everything those stores can affect: their own carousels/top-offers, the
     * cross-store global-category buckets, and — now that refreshHomePools()
     * reads from those same global_category rows instead of running its own
     * ~19s keyword-page fan-out — the home page pools too, all cheap enough
     * to run on every batch instead of needing a separate schedule.
     * refreshHomeTeasers() is the one genuinely expensive step left here
     * (Meilisearch per candidate keyword page) — that cost belongs on this
     * write path, not on every /pigiausios-prekes or homepage visit.
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
        $this->keywordPageService->refreshHomeTeasers();
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
     * reading rows that already exist. The grocery food/non-food split
     * (home_food/home_non_food) is gone; pharmacies get one home_best pool
     * across every root category.
     * That's why this can now run on every batch instead of its own
     * separate 30-minute schedule — see refreshAfterBatch().
     */
    public function refreshHomePools(): void
    {
        $rows = CuratedDeal::query()
            ->whereNull('store_id')
            ->where('scope', 'global_category')
            // Scored picks only: the carousel fill-up rows
            // (HomeDealPoolService::fillCategory(), no deal_score) are there
            // so a thin carousel isn't empty, not "best deals" for the home.
            ->whereNotNull('deal_score')
            ->orderBy('category_id')
            ->orderBy('position')
            ->get(['category_id', 'position', 'discount_id', 'deal_score']);

        $categoryIds = $rows->pluck('category_id')->unique()->values()->all();
        $best = $this->pickFromCategoryRows($rows, $categoryIds, self::HOME_BEST_LIMIT, self::HOME_BEST_MAX_PER_CATEGORY);

        DB::transaction(function () use ($best) {
            // home_food/home_non_food are deleted too: rows left from the
            // grocery split.
            CuratedDeal::whereNull('store_id')
                ->whereIn('scope', ['home_food', 'home_non_food', 'home_best'])
                ->delete();

            $rows = $this->buildRowsFromPicks($best, 'home_best');

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
        $rows = $rows->whereIn('category_id', $categoryIds);
        $byCategory = $rows
            ->groupBy('category_id')
            ->map(fn (Collection $group) => $group->sortBy('position')->values());

        // Each category's rows are already one per product line, but the
        // same line can top two categories (CRESCINA in hair care and in
        // skin care), so the pool checks lines across categories too
        // (ProductLineKey), and repeats one only in the second pass.
        $lineKeys = Discount::query()
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->whereIn('discounts.id', $rows->pluck('discount_id')->all() ?: [0])
            ->get(['discounts.id', 'products.brand', 'products.name'])
            ->mapWithKeys(fn ($row) => [$row->id => ProductLineKey::for($row->brand, $row->name)])
            ->all();

        $categoryKeys = $byCategory->keys()->all();
        $counts = array_fill_keys($categoryKeys, 0);
        $pickedDiscountIds = [];
        $usedLines = [];
        $picked = [];

        foreach ([true, false] as $useLine) {
            $indexes = array_fill_keys($categoryKeys, 0);
            $this->pickRoundRobin($byCategory, $categoryKeys, $indexes, $counts, $pickedDiscountIds, $usedLines, $picked, $lineKeys, $useLine, $limit, $maxPerCategory);
        }

        return $picked;
    }

    /**
     * One round-robin pass over the categories for pickFromCategoryRows().
     *
     * @param  array<int, list<string>>  $lineKeys
     * @param  array<string, true>  $usedLines
     * @param  list<array{discount_id:int, deal_score:?float}>  $picked
     */
    private function pickRoundRobin(
        Collection $byCategory,
        array $categoryKeys,
        array $indexes,
        array &$counts,
        array &$pickedDiscountIds,
        array &$usedLines,
        array &$picked,
        array $lineKeys,
        bool $useLine,
        int $limit,
        int $maxPerCategory
    ): void {
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

                    $keys = $lineKeys[$row->discount_id] ?? [];
                    if ($useLine && ProductLineKey::taken($keys, $usedLines)) {
                        continue;
                    }

                    $picked[] = ['discount_id' => $row->discount_id, 'deal_score' => $row->deal_score];
                    $pickedDiscountIds[] = $row->discount_id;
                    foreach ($keys as $key) {
                        $usedLines[$key] = true;
                    }
                    $counts[$categoryId]++;
                    $addedThisRound = true;
                    break;
                }
            }

            if (!$addedThisRound) {
                break;
            }
        }
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
        $this->keywordPageService->refreshHomeTeasers();
    }

    /**
     * @return Collection<int, Category>
     */
    private function carouselCategories(): Collection
    {
        // Carousel order = config('categories.roots') display order.
        $order = array_values(config('categories.roots', []));

        return Category::whereNull('parent_id')
            ->where('hide', false)
            ->withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->sortBy(function (Category $category) use ($order) {
                $position = array_search($category->slug, $order, true);

                return $position === false ? count($order) : $position;
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
