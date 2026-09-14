<?php

namespace App\Services;

use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\GenericProduct;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Builds /pigiausios-prekes and the homepage's comparison teaser: for a
 * fixed pool of everyday grocery items, the top few currently-active matches
 * per store. Reads from curated_deals (scope 'price_index', one row per
 * item+store+rank) instead of live-querying Discount on every request —
 * that used to run ~34 items' worth of queries per page load (measured at
 * up to 37s cold), the slowest part of either page. refreshPersistedIndex()
 * (called from DealPoolRefresher, same warmup pipeline as every other
 * curated_deals scope) is the only place that still does the live
 * per-generic-product computation; reads here are just an indexed lookup.
 */
class PriceIndexService
{
    /**
     * Candidate everyday grocery items, as slugs into the existing curated
     * `generic_products` table (see SeedGenericProducts/MatchGenericProducts)
     * rather than ad hoc name-substring matching. Tried substring matching
     * first (e.g. product name LIKE '%aliejus%') and it was unusable live —
     * 'aliejus' matched baby oil and hair oil, 'bulv' matched a LEGO set and
     * potato crisps, 'obuoli' matched apple juice. generic_products already
     * solves exactly this curation problem (499 products, 33k+ real Product
     * rows matched to them) — reuse it instead of re-solving it here.
     *
     * Expanded 2026-09-08 from 16 to 34 items, modeled on pricer.lt's own
     * monthly "pigiausias krepšelis" (cheapest-basket) price comparison —
     * the closest thing to a standard reference basket for the LT market;
     * taupulis.lt has no such fixed list, it's a pure live-discount
     * aggregator. Every added slug was verified to exist in generic_products
     * before adding — see conversation for the pricer.lt article cross-
     * referenced against this table. Includes alcohol (alus, degtine) since
     * pricer.lt's own basket does; drop those two if that's not wanted here.
     */
    private const CANDIDATE_ITEM_SLUGS = [
        'pienas', 'duona', 'kiausiniai', 'bulves', 'obuoliai', 'bananai',
        'morkos', 'svogunai', 'suris', 'jogurtas', 'sviestas', 'makaronai',
        'ryziai', 'aliejus', 'cukrus', 'miltai',
        // pricer.lt "pigiausias krepšelis" additions:
        'kefyras', 'varskei', 'grietine', 'desra', 'kiauliena', 'kumpis',
        'vistienai', 'silke', 'zuvis', 'sokoladas', 'dribsniai', 'kava',
        'arbata', 'batonas', 'sultys', 'pomidorai', 'alus', 'degtine',
        // Non-food additions — needed so the homepage's "Ne maisto prekės"
        // block (NewHomeController::HOMEPAGE_NONFOOD_SLUGS) has real matches
        // to show; every other slug above is food/drink only.
        'skalbimo-milteliai', 'indaploviu-tabletes', 'dantu-pasta', 'sampunas',
        'indu-ploviklis', 'muilas',
    ];

    /**
     * Every store with any active discount is now included (was a fixed
     * 5-store allowlist) — per explicit product decision, real coverage at
     * every store beats a narrow "5 big chains only" comparison. This list
     * is display ORDER only (named chains first, in this exact order —
     * brand recognizability, same idea as App\Support\StoreListPriority,
     * just this feature's own slightly different list), not a filter:
     * every other store with a match still shows, just after these, sorted
     * by discount count.
     */
    private const STORE_PRIORITY_SLUGS = [
        'maxima', 'norfa', 'lidl', 'iki', 'rimi',
        'aibe', 'silas', 'vynoteka', 'promo-cash-carry',
    ];

    /**
     * Every store with any active discount, right now, ordered per
     * STORE_PRIORITY_SLUGS above (named chains first in that exact order,
     * then everyone else by discount count descending) — reuses the same
     * priority + fallback-by-count algorithm as
     * App\Support\StoreListPriority::sort(), just against this feature's own
     * store list (couldn't reuse that class directly: it filters/sorts a
     * plain array already shaped for store-grid display, not an Eloquent
     * Store collection).
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function orderedStores()
    {
        return Store::withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->get(['id', 'name', 'slug'])
            ->sort(function (Store $a, Store $b) {
                $rankA = array_search($a->slug, self::STORE_PRIORITY_SLUGS, true);
                $rankB = array_search($b->slug, self::STORE_PRIORITY_SLUGS, true);

                if ($rankA !== false || $rankB !== false) {
                    return ($rankA === false ? PHP_INT_MAX : $rankA) <=> ($rankB === false ? PHP_INT_MAX : $rankB);
                }

                return $b->discounts_count <=> $a->discounts_count;
            })
            ->values();
    }

    /** How many of a store's own matches to show per item, cheapest first. */
    private const TOP_MATCHES_PER_STORE = 5;

    /**
     * Last-resort fallback (see resolveUnitPrice() above, which is what
     * callers actually use) for the rare Discount row where even
     * ProcessDiscounts' pack-size-derived unit_price came up null — extracts
     * a trailing quantity+unit from the product name instead (e.g. "Pienas
     * UHT, 3,2 %, 1 l" -> 1 l; "Duona JORĖ, 580 g" -> 580 g) and normalizes
     * to a price on a standard basis, so a 220g loaf and a 1kg loaf are
     * actually comparable — otherwise "cheapest" would just mean "smallest
     * pack", not lowest per-unit price.
     *
     * @return array{basis: string, unit_price: float}|null null when no
     *   quantity could be parsed — callers must skip these, never guess.
     */
    private function parseUnitPrice(string $productName, float $price): ?array
    {
        if (!preg_match_all('/(\d+(?:[.,]\d+)?)\s*(kg|g|l|ml|vnt)\b/ui', $productName, $matches, PREG_SET_ORDER)) {
            return null;
        }

        // Some names carry more than one quantity (e.g. a combo pack listing
        // two weights) — the LAST one is conventionally the total net
        // quantity in these scraped names.
        $last = end($matches);
        $qty = (float) str_replace(',', '.', $last[1]);
        $unit = mb_strtolower($last[2]);

        if ($qty <= 0) {
            return null;
        }

        return match ($unit) {
            'g' => ['basis' => 'kg', 'unit_price' => $price / ($qty / 1000)],
            'kg' => ['basis' => 'kg', 'unit_price' => $price / $qty],
            'ml' => ['basis' => 'l', 'unit_price' => $price / ($qty / 1000)],
            'l' => ['basis' => 'l', 'unit_price' => $price / $qty],
            'vnt' => ['basis' => '10vnt', 'unit_price' => ($price / $qty) * 10],
            default => null,
        };
    }

    /**
     * Prefers the store's own real (or pack-size-estimated — see
     * ProcessDiscounts::resolveUnitPrice()) unit_price/unit_price_basis
     * already stored on the Discount row over regex-parsing the product
     * name — that name-parsing stopgap (parseUnitPrice() below) is now only
     * a last resort for the rare row where even the pack-size fallback
     * couldn't find a parseable quantity. 'vnt' is rescaled to this
     * service's own '10vnt' basis (10x the per-unit price) to match
     * parseUnitPrice()'s existing convention for count-based items.
     *
     * @return array{basis: string, unit_price: float}|null
     */
    private function resolveUnitPrice(Discount $row): ?array
    {
        if ($row->unit_price !== null && $row->unit_price > 0 && $row->unit_price_basis !== null) {
            return match ($row->unit_price_basis) {
                'kg' => ['basis' => 'kg', 'unit_price' => (float) $row->unit_price],
                'l' => ['basis' => 'l', 'unit_price' => (float) $row->unit_price],
                'vnt' => ['basis' => '10vnt', 'unit_price' => (float) $row->unit_price * 10],
                default => null,
            };
        }

        return $this->parseUnitPrice($row->product->name ?? '', (float) $row->discounted_price);
    }

    /**
     * For one generic product, up to TOP_MATCHES_PER_STORE currently-active
     * matches per store, cheapest first by normalized unit price. Different
     * matched products can use different units (e.g. cheese sold by weight
     * vs. by count) — to keep the comparison apples-to-apples, only the
     * majority unit basis found this run is used; matches on any other
     * basis are dropped rather than mixed in.
     *
     * @return array{basis: ?string, by_store: array<int, array<int, array{store_id:int, product_id:int, price:float, raw_price:float}>>}
     */
    private function topMatchesPerStoreForGenericProduct(int $genericProductId, array $storeIds): array
    {
        $rows = Discount::whereIn('store_id', $storeIds)
            ->whereHas('product', fn ($q) => $q->where('generic_product_id', $genericProductId))
            ->where(function ($q) {
                // end_at is a DATE the discount is valid THROUGH, stored at
                // midnight (00:00:00) — comparing against plain now() (with
                // today's time-of-day) made a discount expire at the START
                // of its last valid day instead of the end of it, wrongly
                // excluding every discount whose end_at is today, for the
                // rest of today. now()->startOfDay() matches the convention
                // MeilisearchService/DescriptionGenerationService already
                // use for this same check.
                $q->where('end_at', '>=', now()->startOfDay())->orWhereNull('end_at');
            })
            // Some scraped rows have a null/blank discounted_price (a source
            // data gap, seen live on a Norfa "Duonai JORĖ" row) — a €0.00
            // basket entry is worse than no entry, exclude them.
            ->where('discounted_price', '>', 0)
            ->with('product:id,name')
            ->get(['id', 'store_id', 'product_id', 'discounted_price', 'original_price', 'discount_percent', 'unit_price', 'unit_price_basis']);

        $parsed = [];
        foreach ($rows as $row) {
            $unit = $this->resolveUnitPrice($row);
            if ($unit === null) {
                continue;
            }
            $parsed[] = [
                'discount_id' => $row->id,
                'store_id' => $row->store_id,
                'product_id' => $row->product_id,
                'basis' => $unit['basis'],
                'price' => $unit['unit_price'],
                'raw_price' => (float) $row->discounted_price,
                'original_price' => (float) $row->original_price,
                'discount_percent' => $row->discount_percent !== null ? (float) $row->discount_percent : null,
            ];
        }

        if (empty($parsed)) {
            return ['basis' => null, 'by_store' => []];
        }

        $basisCounts = array_count_values(array_column($parsed, 'basis'));
        arsort($basisCounts);
        $dominantBasis = array_key_first($basisCounts);

        $byStore = [];
        foreach ($parsed as $row) {
            if ($row['basis'] !== $dominantBasis) {
                continue;
            }
            $byStore[$row['store_id']][] = [
                'discount_id' => $row['discount_id'],
                'store_id' => $row['store_id'],
                'product_id' => $row['product_id'],
                'price' => round($row['price'], 2),
                'raw_price' => round($row['raw_price'], 2),
                'original_price' => round($row['original_price'], 2),
                'discount_percent' => $row['discount_percent'],
            ];
        }

        foreach ($byStore as &$matches) {
            usort($matches, fn ($a, $b) => $a['price'] <=> $b['price']);
            $matches = array_slice($matches, 0, self::TOP_MATCHES_PER_STORE);
        }
        unset($matches);

        return ['basis' => $dominantBasis, 'by_store' => $byStore];
    }

    /**
     * The write side — called from DealPoolRefresher on the same warmup
     * schedule as every other curated_deals scope (after each
     * discounts:process batch, plus the nightly refreshAll() safety net).
     * Recomputes every candidate item's top-per-store matches live (this is
     * the one place that still does — it's fine here, it's not on the
     * request path) and persists them, so getPageData() only ever does an
     * indexed read.
     */
    public function refreshPersistedIndex(): void
    {
        $storeIds = $this->orderedStores()->pluck('id')->all();
        $genericProducts = GenericProduct::whereIn('slug', self::CANDIDATE_ITEM_SLUGS)->get(['id', 'slug']);

        $rows = [];
        $now = now();

        foreach ($genericProducts as $gp) {
            $result = $this->topMatchesPerStoreForGenericProduct($gp->id, $storeIds);

            if (empty($result['by_store'])) {
                continue;
            }

            foreach ($result['by_store'] as $storeId => $matches) {
                foreach ($matches as $position => $match) {
                    $rows[] = [
                        'store_id' => $storeId,
                        'scope' => 'price_index',
                        'category_id' => null,
                        'item_key' => $gp->slug,
                        'unit_basis' => $result['basis'],
                        'position' => $position,
                        'discount_id' => $match['discount_id'],
                        'deal_score' => $match['price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::transaction(function () use ($rows) {
            CuratedDeal::where('scope', 'price_index')->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('curated_deals')->insert($chunk);
            }
        });
    }

    /**
     * The read side — an indexed lookup against curated_deals instead of
     * topMatchesPerStoreForGenericProduct()'s live per-item query. Same
     * return shape that method had, so getPageData() below didn't need to
     * change at all.
     *
     * @return array<string, array{basis: ?string, by_store: array<int, array<int, array{discount_id:int, store_id:int, product_id:int, price:float, raw_price:float, original_price:float, discount_percent:?float}>>}>
     */
    private function readPersistedIndex(array $genericProductSlugs): array
    {
        $rows = CuratedDeal::where('scope', 'price_index')
            ->whereIn('item_key', $genericProductSlugs)
            ->with('discount:id,store_id,product_id,discounted_price,original_price,discount_percent')
            ->orderBy('item_key')
            ->orderBy('store_id')
            ->orderBy('position')
            ->get();

        $byItem = [];
        foreach ($rows as $row) {
            $discount = $row->discount;
            if ($discount === null) {
                continue;
            }

            $byItem[$row->item_key]['basis'] ??= $row->unit_basis;
            $byItem[$row->item_key]['by_store'][$row->store_id][] = [
                'discount_id' => $discount->id,
                'store_id' => $row->store_id,
                'product_id' => $discount->product_id,
                'price' => (float) $row->deal_score,
                'raw_price' => round((float) $discount->discounted_price, 2),
                'original_price' => round((float) $discount->original_price, 2),
                'discount_percent' => $discount->discount_percent !== null ? (float) $discount->discount_percent : null,
            ];
        }

        return $byItem;
    }

    /**
     * Sitewide "today" stats — active discount count/average, and the
     * week's single biggest discount percentage.
     *
     * @return array{active_discounts: int, avg_discount_percent: float, record: ?array{store: string, product: string, original_price: float, discounted_price: float, discount_percent: float}}
     */
    public function getCurrentStats(): array
    {
        $active = Discount::where(function ($q) {
            // See the identical comment in topMatchesPerStoreForGenericProduct()
            // — startOfDay() so a discount stays active through its whole
            // last valid day, not just until midnight.
            $q->where('end_at', '>=', now()->startOfDay())->orWhereNull('end_at');
        });

        $recordDiscount = (clone $active)
            ->where('discount_percent', '<=', 90) // excludes clearly bad/miskeyed data, same guard used elsewhere
            ->with(['product', 'store'])
            ->orderByDesc('discount_percent')
            ->first();

        return [
            'active_discounts' => (clone $active)->count(),
            'avg_discount_percent' => round((clone $active)->avg('discount_percent'), 1),
            'record' => $recordDiscount ? [
                'store' => $recordDiscount->store->name,
                'product' => $recordDiscount->product->name,
                'original_price' => (float) $recordDiscount->original_price,
                'discounted_price' => (float) $recordDiscount->discounted_price,
                'discount_percent' => (float) $recordDiscount->discount_percent,
            ] : null,
        ];
    }

    /**
     * Builds everything the /pigiausios-prekes page (and the homepage's
     * comparison teaser) needs — read from the persisted price_index rows
     * (see readPersistedIndex()/refreshPersistedIndex() above), not
     * computed live.
     */
    public function getPageData(): array
    {
        $trackedStores = $this->orderedStores();

        $genericProducts = GenericProduct::whereIn('slug', self::CANDIDATE_ITEM_SLUGS)
            ->with('category')
            ->get(['id', 'slug', 'name', 'category_id']);

        $persisted = $this->readPersistedIndex($genericProducts->pluck('slug')->all());

        $rawItems = [];
        foreach ($genericProducts as $gp) {
            $result = $persisted[$gp->slug] ?? null;
            $coverageCount = $result ? count($result['by_store']) : 0;

            // Drop items with zero coverage entirely rather than padding the
            // list with something nobody currently sells at a discount.
            if ($coverageCount === 0) {
                continue;
            }

            $rawItems[$gp->slug] = [
                'name' => $gp->name,
                'category' => trim($gp->category?->name ?? '') ?: 'Kita',
                'basis' => $result['basis'] ?? null,
                'by_store' => $result['by_store'],
                'coverage' => $coverageCount,
            ];
        }

        if (empty($rawItems)) {
            return [
                'items' => [],
                'best_deal' => null,
                'tracked_stores' => $trackedStores->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug])->values()->all(),
                'stats' => $this->getCurrentStats(),
            ];
        }

        // Best-covered items first — not that it changes what's shown (every
        // item with any coverage is shown), just the order they appear in
        // within their category.
        uasort($rawItems, fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

        $allProductIds = collect($rawItems)
            ->flatMap(fn ($item) => collect($item['by_store'])->flatten(1)->pluck('product_id'))
            ->unique()
            ->filter()
            ->values();

        $productsById = Product::whereIn('id', $allProductIds)->with('category')->get()->keyBy('id');

        $formatMatch = function (array $match) use ($productsById) {
            $product = $productsById->get($match['product_id']);
            $fullSlug = ($product && $product->category) ? "{$product->category->slug}/{$product->slug}" : null;

            return [
                'store_id' => $match['store_id'],
                'price' => (float) $match['price'],
                'raw_price' => (float) $match['raw_price'],
                'original_price' => (float) ($match['original_price'] ?? 0),
                'discount_percent' => $match['discount_percent'] ?? null,
                // Lets a reader verify this exact number against the real,
                // live discount it came from, not just a bare price.
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'product_image_url' => $product?->image_url,
                'product_full_slug' => $fullSlug,
                'product_url' => $fullSlug ? "/akcijos/{$fullSlug}" : null,
            ];
        };

        $items = [];
        foreach ($rawItems as $key => $raw) {
            $byStoreFormatted = [];
            foreach ($trackedStores as $store) {
                $matches = $raw['by_store'][$store->id] ?? [];
                $byStoreFormatted[$store->slug] = array_map($formatMatch, $matches);
            }

            $allMatches = collect($raw['by_store'])->flatten(1)->sortBy('price')->values();
            $cheapest = $allMatches->first();
            $cheapestStore = $trackedStores->firstWhere('id', $cheapest['store_id']);

            $items[$key] = [
                'key' => $key,
                'name' => $raw['name'],
                'category' => $raw['category'],
                'unit_basis' => $raw['basis'],
                // Decides which single card gets the "Pigiausia" tag — the
                // normalized €/kg-€/l-€/10vnt price, comparable across pack
                // sizes (comparing raw prices across different pack sizes
                // would be meaningless).
                'cheapest_price' => (float) $cheapest['price'],
                'cheapest_store' => $cheapestStore?->name,
                'cheapest_raw_price' => (float) $cheapest['raw_price'],
                'by_store' => $byStoreFormatted,
                'coverage_count' => $raw['coverage'],
                'coverage_total' => $trackedStores->count(),
                // Real, payable-price spread across every match this item
                // has (not the normalized per-kg price) — this is the
                // number a shopper actually saves, used to pick the
                // headline "best deal today" below.
                'raw_spread' => $allMatches->count() > 1
                    ? round($allMatches->max('raw_price') - $allMatches->min('raw_price'), 2)
                    : 0.0,
            ];
        }

        $items = array_values($items);

        // The single biggest real-euro gap between cheapest and priciest
        // match for any one item right now — the headline "check this one"
        // moment, not just another row in the list.
        $bestDealItem = collect($items)->sortByDesc('raw_spread')->first();
        $bestDeal = ($bestDealItem && $bestDealItem['raw_spread'] > 0) ? [
            'name' => $bestDealItem['name'],
            'unit_basis' => $bestDealItem['unit_basis'],
            'store' => $bestDealItem['cheapest_store'],
            'price' => $bestDealItem['cheapest_raw_price'],
            'spread' => $bestDealItem['raw_spread'],
        ] : null;

        return [
            'items' => $items,
            'best_deal' => $bestDeal,
            'tracked_stores' => $trackedStores->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug])->values()->all(),
            'stats' => $this->getCurrentStats(),
        ];
    }
}
