<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\GenericProduct;
use App\Models\PriceIndexEntry;
use App\Models\PriceIndexSnapshot;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Savaitės krepšelio indeksas" — a weekly, dynamically-chosen basket of a
 * few everyday grocery items, priced at each main store's currently cheapest
 * active discount.
 *
 * Why dynamic, not a fixed basket: this system only ever has a price for a
 * product via an active Discount row (no standalone catalog price) — see
 * DescriptionGenerationService's design notes. Checked live (2026-09): Iki
 * had zero active discounts at all, and Maxima/Rimi's active discounts were
 * >95% household chemicals/cosmetics with almost nothing in food categories
 * that week. A fixed 5-item basket would show missing data for most stores
 * most weeks. Instead, each week we score a candidate pool of ~15 common
 * grocery items by how many of the tracked stores currently have a match,
 * and take the best-covered ones — the basket composition can vary week to
 * week, but it's always real and complete for whichever stores it reports.
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
     */
    private const CANDIDATE_ITEM_SLUGS = [
        'pienas', 'duona', 'kiausiniai', 'bulves', 'obuoliai', 'bananai',
        'morkos', 'svogunai', 'suris', 'jogurtas', 'sviestas', 'makaronai',
        'ryziai', 'aliejus', 'cukrus', 'miltai',
    ];

    public const TRACKED_STORE_SLUGS = ['maxima', 'lidl', 'rimi', 'norfa', 'iki'];

    /**
     * Extracts a trailing quantity+unit from a product name (e.g. "Pienas
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
     * For one generic product, the cheapest currently-active match per store,
     * by normalized unit price. Different matched products can use different
     * units (e.g. cheese sold by weight vs. by count) — to keep the
     * comparison apples-to-apples, only the majority unit basis found this
     * run is used; matches on any other basis are dropped rather than mixed
     * in.
     *
     * @return array{basis: ?string, matches: array<int, array{store_id:int, product_id:int, price:float}>}
     */
    private function cheapestPerStoreForGenericProduct(int $genericProductId, array $storeIds): array
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
            ->get(['store_id', 'product_id', 'discounted_price']);

        $parsed = [];
        foreach ($rows as $row) {
            $unit = $this->parseUnitPrice($row->product->name ?? '', (float) $row->discounted_price);
            if ($unit === null) {
                continue;
            }
            $parsed[] = [
                'store_id' => $row->store_id,
                'product_id' => $row->product_id,
                'basis' => $unit['basis'],
                'unit_price' => $unit['unit_price'],
                'raw_price' => (float) $row->discounted_price,
            ];
        }

        if (empty($parsed)) {
            return ['basis' => null, 'matches' => []];
        }

        $basisCounts = array_count_values(array_column($parsed, 'basis'));
        arsort($basisCounts);
        $dominantBasis = array_key_first($basisCounts);

        $cheapestPerStore = [];
        foreach ($parsed as $row) {
            if ($row['basis'] !== $dominantBasis) {
                continue;
            }
            if (!isset($cheapestPerStore[$row['store_id']]) || $row['unit_price'] < $cheapestPerStore[$row['store_id']]['price']) {
                $cheapestPerStore[$row['store_id']] = [
                    'store_id' => $row['store_id'],
                    'product_id' => $row['product_id'],
                    'price' => round($row['unit_price'], 2),
                    'raw_price' => round($row['raw_price'], 2),
                ];
            }
        }

        return ['basis' => $dominantBasis, 'matches' => $cheapestPerStore];
    }

    /**
     * Picks the $count candidate items with the best store coverage right
     * now and returns each item's per-store cheapest match (normalized unit
     * price — see parseUnitPrice).
     *
     * @return array<string, array{name: string, basis: string, matches: array<int, array{store_id:int, product_id:int, price:float}>}>
     */
    public function selectWeeklyBasket(int $count = 5): array
    {
        $storeIds = Store::whereIn('slug', self::TRACKED_STORE_SLUGS)->pluck('id')->all();

        $genericProducts = GenericProduct::whereIn('slug', self::CANDIDATE_ITEM_SLUGS)->get(['id', 'slug', 'name']);

        $scored = [];
        foreach ($genericProducts as $gp) {
            $result = $this->cheapestPerStoreForGenericProduct($gp->id, $storeIds);
            $scored[$gp->slug] = [
                'name' => $gp->name,
                'basis' => $result['basis'],
                'matches' => $result['matches'],
                'coverage' => count($result['matches']),
            ];
        }

        uasort($scored, fn ($a, $b) => $b['coverage'] <=> $a['coverage']);

        $chosen = array_slice($scored, 0, $count, true);

        // Drop items with zero coverage entirely rather than padding the
        // basket with something nobody currently sells at a discount.
        return array_filter($chosen, fn ($item) => $item['coverage'] > 0);
    }

    /**
     * Persists this week's basket as a snapshot (idempotent — replaces any
     * existing snapshot for the same ISO week so re-running is safe).
     */
    public function snapshotThisWeek(int $itemCount = 5): PriceIndexSnapshot
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $basket = $this->selectWeeklyBasket($itemCount);

        return DB::transaction(function () use ($weekStart, $basket) {
            PriceIndexSnapshot::where('week_start', $weekStart)->delete();

            $snapshot = PriceIndexSnapshot::create(['week_start' => $weekStart]);

            foreach ($basket as $key => $item) {
                foreach ($item['matches'] as $match) {
                    PriceIndexEntry::create([
                        'price_index_snapshot_id' => $snapshot->id,
                        'item_key' => $key,
                        'item_name' => $item['name'],
                        'store_id' => $match['store_id'],
                        'product_id' => $match['product_id'],
                        'price' => $match['price'],
                        'raw_price' => $match['raw_price'],
                        'unit_basis' => $item['basis'],
                    ]);
                }
            }

            return $snapshot->load('entries');
        });
    }

    /**
     * Sitewide "today" stats — computed live, not snapshotted, since these
     * are cheap queries and change throughout the day.
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
     * Builds everything the /kainu-indeksas page needs from the latest
     * snapshot: the per-item breakdown (normalized €/kg-€/l-€/10vnt prices,
     * fair for comparing one item across stores) and every tracked store's
     * real basket total (raw, actually-payable prices summed — see 'stores'
     * below).
     */
    public function getPageData(): array
    {
        $latest = PriceIndexSnapshot::with(['entries.store', 'entries.product.category'])->latest('week_start')->first();

        if ($latest === null) {
            return [
                'snapshot' => null,
                'items' => [],
                'stores' => [],
                'tracked_stores' => [],
                'stats' => $this->getCurrentStats(),
            ];
        }

        $itemKeys = $latest->entries->pluck('item_key')->unique()->values();
        $entriesByItem = $latest->entries->groupBy('item_key');

        $trackedStores = Store::whereIn('slug', self::TRACKED_STORE_SLUGS)->get(['id', 'name', 'slug']);

        $items = $itemKeys->map(function ($key) use ($entriesByItem, $trackedStores) {
            $entries = $entriesByItem[$key]->sortBy('price')->values();
            $cheapest = $entries->first();
            $entriesByStoreId = $entries->keyBy('store_id');

            $product = $cheapest->product;

            return [
                'key' => $key,
                'name' => $entries->first()->item_name,
                'unit_basis' => $entries->first()->unit_basis,
                'cheapest_store' => $cheapest->store->name,
                'cheapest_store_slug' => $cheapest->store->slug,
                'cheapest_price' => (float) $cheapest->price,
                // Lets a reader verify this exact number against the real,
                // live discount it came from — a citable index needs this,
                // not just a store logo.
                'cheapest_product_name' => $product?->name,
                'cheapest_product_url' => ($product && $product->category)
                    ? "/akcijos/{$product->category->slug}/{$product->slug}"
                    : null,
                // Every tracked store's own price for this item, in one row —
                // the actual comparison a reader wants, not just the winner.
                'prices_by_store' => $trackedStores->mapWithKeys(function ($store) use ($entriesByStoreId) {
                    $entry = $entriesByStoreId->get($store->id);

                    return [$store->slug => $entry ? (float) $entry->price : null];
                })->all(),
            ];
        })->values()->all();

        // Every tracked store's real basket total — sum of the actual
        // payable price (raw_price) for whichever chosen items that store
        // currently has, NOT the normalized per-kg/per-l comparison price
        // (summing €/kg + €/l values together produced a meaningless number
        // — a real "basket total" must be real, summable euros). A store
        // missing some items still gets a row, clearly marked with how many
        // of the basket's items it actually covers, rather than being
        // silently dropped — a store simply absent from the list reads as
        // an oversight or cherry-picking.
        $entriesByStore = $latest->entries->groupBy('store_id');
        $totalItems = $itemKeys->count();
        $stores = Store::whereIn('slug', self::TRACKED_STORE_SLUGS)
            ->get(['id', 'name', 'slug'])
            ->map(function ($store) use ($entriesByStore, $totalItems) {
                $storeEntries = $entriesByStore->get($store->id, collect());
                $matchedCount = $storeEntries->pluck('item_key')->unique()->count();

                return [
                    'store' => $store->name,
                    'store_slug' => $store->slug,
                    'total' => $matchedCount > 0 ? (float) $storeEntries->sum('raw_price') : null,
                    'matched_items' => $matchedCount,
                    'total_items' => $totalItems,
                    'full_coverage' => $matchedCount === $totalItems,
                ];
            })
            // Stores with data first (cheapest total leading), then stores
            // with zero matches at the bottom — never silently hidden.
            ->sortBy(fn ($s) => $s['total'] ?? PHP_FLOAT_MAX)
            ->values()
            ->all();

        return [
            'snapshot' => ['week_start' => $latest->week_start->toDateString()],
            'items' => $items,
            'stores' => $stores,
            'tracked_stores' => $trackedStores->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug])->values()->all(),
            'stats' => $this->getCurrentStats(),
        ];
    }
}
