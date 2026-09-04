<?php

namespace App\Services;

use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomeDealPoolService
{
    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const POPULAR_CATEGORY_IDS = [1, 52, 121, 352, 380];

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

    /** @var DealFamilyKeyResolver */
    private $familyKeyResolver;

    public function __construct(DealFamilyKeyResolver $familyKeyResolver)
    {
        $this->familyKeyResolver = $familyKeyResolver;
    }

    /**
     * Top-N deals for a single category (optionally scoped to one store),
     * ranked by the same deal_score used for the home page pools. Used to
     * power one carousel per category on /akcijos and /akcijos/{store}
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
            ->orderByDesc('deal_score')
            ->orderByDesc('discounts.discount_percent')
            ->limit(min($limit * 4, 40))
            ->get();

        return collect($this->applyFamilyCap($candidates, $limit));
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
            self::EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS,
            $maxPerCategory,
            $limit
        );
    }

    private function scoredCandidatesQuery(?int $excludeId): Builder
    {
        $popularIds = implode(',', self::POPULAR_CATEGORY_IDS);
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

                    // Same-family skip mirrors applyFamilyCap() below — a
                    // category-diverse pool can still stack several near-
                    // identical variants (e.g. chip flavors) inside one
                    // category slot without this.
                    $familyKey = $this->familyKeyResolver->resolve($discount);
                    if (in_array($familyKey, $pickedFamilyKeys, true)) {
                        continue;
                    }

                    if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                        continue;
                    }

                    $picked[] = $discount;
                    $pickedIds[] = $discount->id;
                    $pickedFamilyKeys[] = $familyKey;
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

        // Two-tier backfill, same reasoning as applyFamilyCap(): the
        // round-robin loop above can legitimately fall short of $limit
        // because of the category/family caps rather than genuinely thin
        // inventory (e.g. only 2 categories have any candidates left) — fill
        // remaining slots still respecting the family cap first, and only
        // ignore it as a last resort so two Oral-B toothbrushes don't both
        // land in "Geriausi pasiūlymai" just because the diversity loop
        // stopped early.
        if (count($picked) < $limit) {
            foreach ($available as $discount) {
                if (count($picked) >= $limit) {
                    break;
                }

                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }

                $familyKey = $this->familyKeyResolver->resolve($discount);
                if (in_array($familyKey, $pickedFamilyKeys, true)) {
                    continue;
                }

                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[] = $discount->id;
                $pickedFamilyKeys[] = $familyKey;
                if ($this->isPriceless($discount)) {
                    $pricelessCount++;
                }
            }
        }

        // Last resort ignores the family cap (a repeated product beats an
        // empty slot) but deliberately still enforces the priceless cap —
        // unlike a same-family duplicate, flooding a thin section with
        // "Sutaupyk iki X%" pills instead of stopping short is the opposite
        // of what this cap is for; a shorter, honestly-priced section is
        // better than padding it out with priceless ones.
        if (count($picked) < $limit) {
            foreach ($available as $discount) {
                if (count($picked) >= $limit) {
                    break;
                }

                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }

                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[] = $discount->id;
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
     * family key — falls back to filling remaining slots ignoring the cap if
     * the candidate pool is too thin for the family rule alone to reach the
     * limit (thin inventory beats an artificially short list).
     *
     * @return list<Discount>
     */
    private function applyFamilyCap(Collection $candidates, int $limit, int $maxPerFamily = 1): array
    {
        $picked = [];
        $familyCounts = [];
        $pricelessCount = 0;

        foreach ($candidates as $discount) {
            if (count($picked) >= $limit) {
                break;
            }

            $familyKey = $this->familyKeyResolver->resolve($discount);
            if (($familyCounts[$familyKey] ?? 0) >= $maxPerFamily) {
                continue;
            }

            if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                continue;
            }

            $picked[] = $discount;
            $familyCounts[$familyKey] = ($familyCounts[$familyKey] ?? 0) + 1;
            if ($this->isPriceless($discount)) {
                $pricelessCount++;
            }
        }

        // Same two-tier backfill reasoning as pickDiversePool(): relax the
        // family cap first if still short, but keep the priceless cap as
        // long as possible so a thin category doesn't fill up entirely with
        // "Sutaupyk iki X%" pills instead of real priced cards.
        if (count($picked) < $limit) {
            $pickedIds = array_map(fn (Discount $d) => $d->id, $picked);
            foreach ($candidates as $discount) {
                if (count($picked) >= $limit) {
                    break;
                }
                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }
                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }

                $picked[] = $discount;
                $pickedIds[] = $discount->id;
                if ($this->isPriceless($discount)) {
                    $pricelessCount++;
                }
            }
        }

        // Last resort ignores the family cap but still enforces the
        // priceless cap — see the matching comment in pickDiversePool().
        if (count($picked) < $limit) {
            $pickedIds = array_map(fn (Discount $d) => $d->id, $picked);
            foreach ($candidates as $discount) {
                if (count($picked) >= $limit) {
                    break;
                }
                if (in_array($discount->id, $pickedIds, true)) {
                    continue;
                }
                if ($this->isPriceless($discount) && $this->pricelessCapReached($pricelessCount, count($picked))) {
                    continue;
                }
                $picked[] = $discount;
                $pickedIds[] = $discount->id;
                if ($this->isPriceless($discount)) {
                    $pricelessCount++;
                }
            }
        }

        return $picked;
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
