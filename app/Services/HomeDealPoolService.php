<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Support\ProductLineKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomeDealPoolService
{


    private const CANDIDATE_LIMIT = 600;

    /**
     * Some stores (seen on Iki) publish storewide "-40% visiems X prekės
     * ženklo produktams" conditional promos with a real discount_percent but
     * no concrete original_price/discounted_price for any single SKU —
     * excluding these entirely starved thin store+category combos down to
     * 1-2 displayable items even though the store genuinely has more deals
     * running. deal-card.blade.php already has a "Sutaupyk iki X%" pill for
     * exactly this case (no price to show), so these are let in when the
     * percent is at least this — but capped (MAX_PRICELESS_PER_SECTION
     * below) so a section can't fill up entirely with price-less pills.
     */
    private const MIN_PRICELESS_DISCOUNT_PERCENT = 20;

    private const MAX_PRICELESS_PER_SECTION = 2;

    /**
     * A section with only 1-3 cards looks broken next to every other section
     * on the page — the priceless cap above is a soft preference for once a
     * section is already presentable, not a hard rule that should shrink a
     * whole row down to 3 items. Below this floor, pricelessCapReached()
     * ignores the cap so a thin category still fills out to a normal-looking
     * row; above it, the cap re-applies so slots beyond the floor still
     * prefer real, priced cards over more percent-only pills.
     */
    private const MIN_SECTION_SIZE = 5;

    /**
     * How many scored candidates bestForCategory() considers. A pharmacy
     * category holds thousands of discounts, and deal_score favours big
     * absolute savings, so the top 30-40 are often one expensive product line
     * (5 CRESCINA HFSC kits, then 3 NIOXIN serums, owner's report
     * 2026-10-08). A wider pool gives the line-key rule enough other lines to
     * pick from. Only ids are kept (DealPoolRefresher), so these load just
     * product + category, not the offer/history relations.
     */
    private const CATEGORY_CANDIDATE_LIMIT = 150;

    /** @var array<int, string> */
    private array $familyKeyCache = [];

    /** @var array<int, list<string>> */
    private array $lineKeyCache = [];

    /** @var DealFamilyKeyResolver */
    private $familyKeyResolver;

    public function __construct(DealFamilyKeyResolver $familyKeyResolver)
    {
        $this->familyKeyResolver = $familyKeyResolver;
    }

    /**
     * Top-N deals for a single category (optionally scoped to one store),
     * ranked by the same deal_score used for the home page pools. Used to
     * power one carousel per category on /akcijos and /{store}
     * instead of a single cross-category pool.
     */
    public function bestForCategory(int $categoryId, int $limit, ?int $storeId = null): Collection
    {
        // No minimum price filter here — every category needs its own best
        // deals, including cheap ones (produce, plants).
        //
        // Over-fetches a larger candidate pool (4x the display limit, capped)
        // than it needs, then applies a max-1-per-family-key cap below —
        // without this, a single campaign discounting a whole flavor lineup
        // at the same % off would otherwise dominate the top-N by score alone
        // (e.g. 5 different flavors of the same chips bag in one carousel).
        $candidates = $this->scoredCandidatesQuery(null)
            ->where(function ($query) {
                $query->whereNotNull('discounts.discounted_price')
                    ->orWhere('discounts.discount_percent', '>=', self::MIN_PRICELESS_DISCOUNT_PERCENT);
            })
            ->when($storeId, function ($query) use ($storeId) {
                $query->where('discounts.store_id', $storeId);
            })
            ->where('products.category_id', $categoryId)
            ->setEagerLoads([])
            ->with(['product.category', 'store'])
            ->orderByDesc('deal_score')
            ->orderByDesc('discounts.discount_percent')
            ->limit(max($limit * 4, self::CATEGORY_CANDIDATE_LIMIT))
            ->get();

        // A global (unscoped, $storeId === null) category carousel mixes
        // every store's discounts into one deal_score ranking — without a
        // per-store cap, one store's storewide campaign in a category (e.g.
        // "-30% all cat food") can legitimately out-score every other store's
        // single discount there and take every slot, even though other
        // stores have real offers in the same category. Scoped
        // (single-store) calls don't need this — every candidate is already
        // that one store.
        $maxPerStore = $storeId === null ? max(2, (int) ceil($limit / 2)) : PHP_INT_MAX;

        // A new product line beats a repeated one, even when the repeat has
        // a discount and the new line doesn't: the scored pick and the fill-up
        // both run with the line rule first, and only then is the line rule
        // dropped (scored duplicates first, then fill-up duplicates).
        $picked = $this->applyFamilyCap($candidates, $limit, 1, $maxPerStore, relaxLine: false);
        $picked = [...$picked, ...$this->fillCategory($categoryId, $storeId, $picked, $limit - count($picked), $maxPerStore, relaxLine: false)];
        $picked = $this->applyFamilyCap($candidates, $limit, 1, $maxPerStore, relaxLine: true, seed: $picked);

        return collect([...$picked, ...$this->fillCategory($categoryId, $storeId, $picked, $limit - count($picked), $maxPerStore, relaxLine: true)]);
    }

    /**
     * Tops a thin category carousel up to $missing more cards (owner's rule,
     * 2026-10-02): full-catalog stores (Ermitažas, Vynoteka, Thomas Philipps)
     * often have only 1-2 discounts in a category, so the carousel showed one
     * card next to "Žiūrėti visas (6)". After the scored pick it takes the
     * remaining discounts by biggest discount, then products without a
     * discount by lowest price. No family cap here (a second flavour beats
     * an empty slot), but one card per product and the same per-store cap
     * on global carousels. Product lines (lineKeys()) already in the carousel
     * are skipped, and allowed again only with $relaxLine.
     *
     * @param  list<Discount>  $picked
     * @return list<Discount>
     */
    private function fillCategory(int $categoryId, ?int $storeId, array $picked, int $missing, int $maxPerStore, bool $relaxLine): array
    {
        if ($missing <= 0) {
            return [];
        }

        $pickedIds = array_map(fn (Discount $d) => $d->id, $picked);
        $productIds = array_map(fn (Discount $d) => $d->product_id, $picked);
        $storeCounts = array_count_values(array_map(fn (Discount $d) => $d->store_id, $picked));
        $usedLines = [];
        foreach ($picked as $discount) {
            $this->markLine($discount, $usedLines);
        }

        $candidates = Discount::query()
            ->select('discounts.*')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->with(['product.category', 'store'])
            ->where('products.category_id', $categoryId)
            ->when($storeId, fn ($query) => $query->where('discounts.store_id', $storeId))
            ->whereNotIn('discounts.id', $pickedIds ?: [0])
            ->where('discounts.discounted_price', '>', 0)
            ->orderByRaw('COALESCE(discounts.discount_percent, 0) > 0 DESC')
            ->orderByDesc('discounts.discount_percent')
            ->orderBy('discounts.discounted_price')
            ->limit(max($missing * 4, self::CATEGORY_CANDIDATE_LIMIT))
            ->get();

        $filled = [];
        foreach ($relaxLine ? [true, false] : [true] as $useLine) {
            foreach ($candidates as $discount) {
                if (count($filled) >= $missing) {
                    break 2;
                }
                if (in_array($discount->product_id, $productIds, true)) {
                    continue;
                }
                if (($storeCounts[$discount->store_id] ?? 0) >= $maxPerStore) {
                    continue;
                }
                if ($useLine && $this->lineTaken($discount, $usedLines)) {
                    continue;
                }
                $filled[] = $discount;
                $productIds[] = $discount->product_id;
                $storeCounts[$discount->store_id] = ($storeCounts[$discount->store_id] ?? 0) + 1;
                $this->markLine($discount, $usedLines);
            }
        }

        return $filled;
    }

    /**
     * Cross-category "best offers" pool for a single store's /akcijos and
     * /leidinys hub pages — same deal_score ranking as bestForCategory(), but
     * diversified across categories (max $maxPerCategory per category)
     * instead of one category at a time. Excludes the
     * "namu-ukio-ir-laisvalaikio-prekes" catch-all category: a UX audit found
     * it surfacing unrelated high-ticket items (e.g. an electric toothbrush)
     * alongside grocery deals when mixed into a single cross-category list.
     *
     * @return list<Discount>
     */
    public function bestForStore(int $storeId, int $limit = 10, int $maxPerCategory = 2): array
    {
        $candidates = $this->scoredCandidatesQuery(null)
            ->where(function ($query) {
                $query->whereNotNull('discounts.discounted_price')
                    ->orWhere('discounts.discount_percent', '>=', self::MIN_PRICELESS_DISCOUNT_PERCENT);
            })
            ->where('discounts.store_id', $storeId)
            ->orderByDesc('deal_score')
            ->orderByDesc('discounts.discount_percent')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return $this->pickDiversePool(
            $candidates,
            null,
            config('categories.excluded_top_product_slugs', []),
            $maxPerCategory,
            $limit
        );
    }

    private function scoredCandidatesQuery(?int $excludeId): Builder
    {
        $popularIds = implode(',', Category::popularIds() ?: [0]);
        $today = Carbon::today()->toDateString();
        $urgencyCutoff = Carbon::now()->copy()->addDays(3)->toDateTimeString();

        $query = $this->baseQuery();
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
                        WHEN products.category_id IN ({$popularIds})
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
        $pickedFamilyKeys = [];
        $usedLines = [];
        $pricelessCount = 0;

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

                    // Same-family and same-line skips mirror applyFamilyCap()
                    // below — a category-diverse pool can still stack several
                    // near-identical variants (e.g. chip flavors, CRESCINA
                    // kits) inside one category slot without this.
                    $familyKey = $this->familyKey($discount);
                    if (in_array($familyKey, $pickedFamilyKeys, true)) {
                        continue;
                    }

                    if ($this->lineTaken($discount, $usedLines)) {
                        continue;
                    }

                    if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                        continue;
                    }

                    $picked[] = $discount;
                    $pickedIds[] = $discount->id;
                    $pickedFamilyKeys[] = $familyKey;
                    $this->markLine($discount, $usedLines);
                    if ($this->isPriceless($discount)) {
                        $pricelessCount++;
                    }
                    $categoryCounts[$slug]++;
                    $addedThisRound = true;
                    break;
                }
            }

            if (!$addedThisRound) {
                break;
            }
        }

        // Tiered backfill, same reasoning as applyFamilyCap(): the
        // round-robin loop above can legitimately fall short of $limit
        // because of the category/family/line caps rather than genuinely thin
        // inventory (e.g. only 2 categories have any candidates left). Each
        // tier drops one more rule: first the family key, then the product
        // line, so two CRESCINA kits or two Oral-B toothbrushes only both
        // land in "Geriausi pasiūlymai" when nothing else is left. The
        // priceless cap is never dropped: flooding a thin section with
        // "Sutaupyk iki X%" pills is the opposite of what that cap is for.
        foreach ([[true, true], [false, true], [false, false]] as [$useFamily, $useLine]) {
            foreach ($available as $discount) {
                if (count($picked) >= $limit) {
                    break 2;
                }

                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }

                $familyKey = $this->familyKey($discount);
                if ($useFamily && in_array($familyKey, $pickedFamilyKeys, true)) {
                    continue;
                }

                if ($useLine && $this->lineTaken($discount, $usedLines)) {
                    continue;
                }

                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[] = $discount->id;
                $pickedFamilyKeys[] = $familyKey;
                $this->markLine($discount, $usedLines);
                if ($this->isPriceless($discount)) {
                    $pricelessCount++;
                }
            }
        }

        return $picked;
    }

    /**
     * Greedily picks up to $limit discounts from an already deal_score-sorted
     * candidate list, allowing at most $maxPerFamily per DealFamilyKeyResolver
     * family key and one card per product line (lineKeys()), with an optional
     * per-store cap. When the pool is too thin for every rule, it fills the
     * remaining slots in tiers (thin inventory beats an artificially short
     * list), dropping one rule per tier:
     *
     * 1. family + line + store
     * 2. line + store: for pharmacies the family key is usually just the
     *    category slug, so it would allow only one card per carousel
     * 3. line only: a thin category may genuinely have just 1-2 stores
     * 4. none but the priceless cap
     *
     * The product line goes last because repeated lines are what the owner
     * noticed (2026-10-08: five CRESCINA HFSC kits in "Plaukų priežiūra").
     * $relaxLine = false stops before tier 4, so bestForCategory() can try
     * other lines from the fill-up first; $seed carries cards already picked.
     *
     * @return list<Discount>
     */
    private function applyFamilyCap(
        Collection $candidates,
        int $limit,
        int $maxPerFamily = 1,
        int $maxPerStore = PHP_INT_MAX,
        bool $relaxLine = true,
        array $seed = []
    ): array {
        $picked = [];
        $pickedIds = [];
        $familyCounts = [];
        $storeCounts = [];
        $usedLines = [];
        $pricelessCount = 0;

        foreach ($seed as $discount) {
            $picked[] = $discount;
            $pickedIds[$discount->id] = true;
            $familyKey = $this->familyKey($discount);
            $familyCounts[$familyKey] = ($familyCounts[$familyKey] ?? 0) + 1;
            $storeCounts[$discount->store_id] = ($storeCounts[$discount->store_id] ?? 0) + 1;
            $this->markLine($discount, $usedLines);
            if ($this->isPriceless($discount)) {
                $pricelessCount++;
            }
        }

        $tiers = [[true, true, true], [false, true, true], [false, true, false]];
        if ($relaxLine) {
            $tiers[] = [false, false, false];
        }

        foreach ($tiers as [$useFamily, $useLine, $useStore]) {
            foreach ($candidates as $discount) {
                if (count($picked) >= $limit) {
                    break 2;
                }
                if (isset($pickedIds[$discount->id])) {
                    continue;
                }

                $familyKey = $this->familyKey($discount);
                if ($useFamily && ($familyCounts[$familyKey] ?? 0) >= $maxPerFamily) {
                    continue;
                }
                if ($useLine && $this->lineTaken($discount, $usedLines)) {
                    continue;
                }
                if ($useStore && ($storeCounts[$discount->store_id] ?? 0) >= $maxPerStore) {
                    continue;
                }
                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[$discount->id] = true;
                $familyCounts[$familyKey] = ($familyCounts[$familyKey] ?? 0) + 1;
                $storeCounts[$discount->store_id] = ($storeCounts[$discount->store_id] ?? 0) + 1;
                $this->markLine($discount, $usedLines);
                if ($this->isPriceless($discount)) {
                    $pricelessCount++;
                }
            }
        }

        return $picked;
    }

    private function familyKey(Discount $discount): string
    {
        return $this->familyKeyCache[$discount->id] ??= $this->familyKeyResolver->resolve($discount);
    }

    /**
     * @return list<string>
     */
    private function lineKeys(Discount $discount): array
    {
        return $this->lineKeyCache[$discount->id] ??= ProductLineKey::for($discount->product?->brand, $discount->product?->name);
    }

    /**
     * @param  array<string, true>  $usedLines
     */
    private function lineTaken(Discount $discount, array $usedLines): bool
    {
        return ProductLineKey::taken($this->lineKeys($discount), $usedLines);
    }

    /**
     * @param  array<string, true>  $usedLines
     */
    private function markLine(Discount $discount, array &$usedLines): void
    {
        foreach ($this->lineKeys($discount) as $key) {
            $usedLines[$key] = true;
        }
    }


    private function isPriceless(Discount $discount): bool
    {
        return (float) ($discount->discounted_price ?? 0) <= 0;
    }

    private function pricelessCapReached(int $pricelessCount, int $pickedCount): bool
    {
        if ($pickedCount < self::MIN_SECTION_SIZE) {
            return false;
        }

        return $pricelessCount >= self::MAX_PRICELESS_PER_SECTION;
    }

    /**
     * deal_score's static part (discount %, € saved, popular category) for
     * an already-loaded Discount, without the SQL query's history/date
     * bonuses. Keep in step with scoredCandidatesQuery().
     */
    public static function staticDealScore(Discount $discount): float
    {
        $percent = min((float) ($discount->discount_percent ?? 0), 70);
        $saved = max((float) $discount->original_price - (float) $discount->discounted_price, 0);
        $popular = in_array($discount->product?->category_id, Category::popularIds(), true) ? 15 : 0;

        return $percent * 2 + $saved * 3 + $popular;
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
        // Joining products here (every discount has one, so INNER is safe)
        // lets scoredCandidatesQuery() read products.category_id directly
        // for the popularity-bonus check instead of a separate correlated
        // subquery per row — ~2.3x faster for that part alone (measured:
        // 79ms vs 34ms scanning one store's ~4.4k discounts). select()
        // stays discounts.* only, so Discount model hydration is unaffected.
        return Discount::query()
            ->select('discounts.*')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->with(['product.category', 'product.discounts.store', 'product.discountHistories.store', 'store'])
            ->whereNotNull('discounts.discount_percent')
            ->where('discounts.discount_percent', '>', 0);
    }

    private function excludeIds(Builder $query, ?int $excludeId): Builder
    {
        if ($excludeId !== null) {
            $query->where('discounts.id', '!=', $excludeId);
        }

        return $query;
    }
}
