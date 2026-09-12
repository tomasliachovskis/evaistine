<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\GenericProduct;
use App\Models\Product;
use App\Models\Store;

/**
 * Builds /pigiausios-prekes: for a fixed pool of everyday grocery items, the
 * top few currently-active matches per store, computed live from `Discount`
 * rows on every request — no weekly snapshot, no persisted "index". This
 * used to snapshot a single cheapest-per-store pick into `price_index_*`
 * tables (see git history), but that only ever supported one product per
 * store per item; showing several real options per store (see
 * TOP_MATCHES_PER_STORE) needed more than one row per (item, store), which
 * the old unique-per-store schema couldn't hold. Dropped the tables rather
 * than migrate them — live queries here are cheap enough (34 candidate
 * items × 5 stores) that persistence was never buying anything but a stale
 * page between snapshots.
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
    ];

    public const TRACKED_STORE_SLUGS = ['maxima', 'lidl', 'rimi', 'norfa', 'iki'];

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
                $q->where('end_at', '>=', now())->orWhereNull('end_at');
            })
            // Some scraped rows have a null/blank discounted_price (a source
            // data gap, seen live on a Norfa "Duonai JORĖ" row) — a €0.00
            // basket entry is worse than no entry, exclude them.
            ->where('discounted_price', '>', 0)
            ->with('product:id,name')
            ->get(['store_id', 'product_id', 'discounted_price', 'unit_price', 'unit_price_basis']);

        $parsed = [];
        foreach ($rows as $row) {
            $unit = $this->resolveUnitPrice($row);
            if ($unit === null) {
                continue;
            }
            $parsed[] = [
                'store_id' => $row->store_id,
                'product_id' => $row->product_id,
                'basis' => $unit['basis'],
                'price' => $unit['unit_price'],
                'raw_price' => (float) $row->discounted_price,
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
                'store_id' => $row['store_id'],
                'product_id' => $row['product_id'],
                'price' => round($row['price'], 2),
                'raw_price' => round($row['raw_price'], 2),
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
     * Sitewide "today" stats — active discount count/average, and the
     * week's single biggest discount percentage.
     *
     * @return array{active_discounts: int, avg_discount_percent: float, record: ?array{store: string, product: string, original_price: float, discounted_price: float, discount_percent: float}}
     */
    public function getCurrentStats(): array
    {
        $active = Discount::where(function ($q) {
            $q->where('end_at', '>=', now())->orWhereNull('end_at');
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
     * Builds everything the /pigiausios-prekes page needs, live: for each
     * candidate item that has at least one match right now, every tracked
     * store's own top matches (normalized €/kg-€/l-€/10vnt price decides
     * ranking; the real, payable raw_price is what's shown).
     */
    public function getPageData(): array
    {
        $trackedStores = Store::whereIn('slug', self::TRACKED_STORE_SLUGS)->get(['id', 'name', 'slug']);
        $storeIds = $trackedStores->pluck('id')->all();

        $genericProducts = GenericProduct::whereIn('slug', self::CANDIDATE_ITEM_SLUGS)
            ->with('category')
            ->get(['id', 'slug', 'name', 'category_id']);

        $rawItems = [];
        foreach ($genericProducts as $gp) {
            $result = $this->topMatchesPerStoreForGenericProduct($gp->id, $storeIds);
            $coverageCount = count($result['by_store']);

            // Drop items with zero coverage entirely rather than padding the
            // list with something nobody currently sells at a discount.
            if ($coverageCount === 0) {
                continue;
            }

            $rawItems[$gp->slug] = [
                'name' => $gp->name,
                'category' => trim($gp->category?->name ?? '') ?: 'Kita',
                'basis' => $result['basis'],
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

            return [
                'store_id' => $match['store_id'],
                'price' => (float) $match['price'],
                'raw_price' => (float) $match['raw_price'],
                // Lets a reader verify this exact number against the real,
                // live discount it came from, not just a bare price.
                'product_name' => $product?->name,
                'product_image_url' => $product?->image_url,
                'product_url' => ($product && $product->category)
                    ? "/akcijos/{$product->category->slug}/{$product->slug}"
                    : null,
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
