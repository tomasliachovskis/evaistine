<?php

namespace App\Services;

use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomePageSectionsService
{
    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    /** @var DiscountResponseFormatter */
    private $formatter;

    public function __construct(
        DiscountResponseFormatter $formatter
    ) {
        $this->formatter = $formatter;
    }

    /**
     * best_pool/food_pool/non_food_pool now read App\Services\DealPoolRefresher's
     * persisted curated_deals rows (scope home_best/home_food/home_non_food)
     * instead of calling HomeDealPoolService::buildPools() live — that call
     * fans out to a DB query per keyword page across ~38 keyword pages and is
     * documented as running ~45s uncached, so it gets its own, much less
     * frequent refresh schedule (see DealPoolRefresher::refreshHomePools())
     * rather than being recomputed on every request or every discount batch.
     */
    // How many of home_best's persisted candidates (DealPoolRefresher stores
    // 40, see HOME_BEST_LIMIT there) actually show per request — randomly
    // resampled every time so the strip visibly rotates without needing its
    // own refresh schedule, unlike food_pool/non_food_pool below whose
    // stored count still equals what's shown.
    private const HOME_BEST_DISPLAY_LIMIT = 10;

    public function build(): array
    {
        return [
            'best_pool' => $this->formatDeals($this->poolFromScope('home_best', self::HOME_BEST_DISPLAY_LIMIT)),
            // The grocery food/non-food pools are gone; kept empty for the
            // unrouted old home view (home.blade.php) that still reads them.
            'food_pool' => [],
            'non_food_pool' => [],
            'store_ranking' => $this->getStoreRanking(),
        ];
    }

    private function poolFromScope(string $scope, ?int $randomSampleLimit = null): Collection
    {
        return CuratedDeal::query()
            ->whereNull('store_id')
            ->where('scope', $scope)
            ->with(['discount.product.category', 'discount.product.discounts.store', 'discount.product.discountHistories.store', 'discount.store'])
            ->when($randomSampleLimit, fn ($query) => $query->inRandomOrder()->limit($randomSampleLimit))
            ->unless($randomSampleLimit, fn ($query) => $query->orderBy('position'))
            ->get()
            ->pluck('discount')
            ->filter()
            ->values();
    }

    public static function getNewTodayCount(): int
    {
        return Discount::query()
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    public function resolvePriceChangeAmount(Discount $discount): ?float
    {
        $previous = DiscountHistory::query()
            ->where('product_id', $discount->product_id)
            ->where('store_id', $discount->store_id)
            ->orderByDesc('id')
            ->value('discounted_price');

        if ($previous === null) {
            return null;
        }

        $change = round((float) $previous - (float) $discount->discounted_price, 2);

        return $change > 0 ? $change : null;
    }

    private function getStoreRanking(): array
    {
        $stores = Store::query()
            ->withCount('discounts')
            ->having('discounts_count', '>', 0)
            ->get();

        if ($stores->isEmpty()) {
            return [];
        }

        // One batched query for every store's max discount instead of one
        // query per store — the per-store top_products drill-down below is
        // then only run for the 8 stores that actually make the final cut,
        // rather than for every store with any discount at all.
        $maxDiscounts = Discount::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->whereNotNull('discount_percent')
            ->groupBy('store_id')
            ->selectRaw('store_id, MAX(discount_percent) as max_discount')
            ->pluck('max_discount', 'store_id');

        $ranked = $stores
            ->map(function (Store $store) use ($maxDiscounts) {
                return [
                    'store' => $store,
                    'max_discount_percent' => (int) round($maxDiscounts[$store->id] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['max_discount_percent'] > 0)
            ->sortByDesc('max_discount_percent')
            ->take(8)
            ->values();

        return $ranked
            ->map(function (array $row, int $index) {
                $store = $row['store'];

                return [
                    'slug' => $store->slug,
                    'name' => $store->name,
                    'href' => "/leidinys/{$store->slug}",
                    'max_discount_percent' => $row['max_discount_percent'],
                    'hot_deals_count' => $store->discounts_count,
                    'top_products' => $this->getTopProductsForStore($store),
                    'crown_rank' => $index < 3 ? $index + 1 : null,
                ];
            })
            ->all();
    }

    private function getTopProductsForStore(Store $store): array
    {
        $query = Discount::query()
            ->with(['product.category'])
            ->where('store_id', $store->id)
            ->whereNotNull('discount_percent');

        $this->applyTopProductFilters($query);

        return $query
            ->orderByDesc('discounts.discount_percent')
            ->limit(3)
            ->get()
            ->map(function (Discount $d) use ($store) {
                $category = $d->product->category;
                $categorySlug = $category ? $category->slug : null;
                $href = $categorySlug
                    ? "/p/{$d->product->slug}"
                    : "/{$store->slug}";

                return [
                    'name' => $d->product->name,
                    'href' => $href,
                    'image_url' => $d->product->image_url,
                    'category_slug' => $categorySlug,
                ];
            })
            ->values()
            ->all();
    }

    private function applyTopProductFilters(Builder $query): Builder
    {
        return $query
            ->whereNotNull('discounts.discounted_price')
            ->where('discounts.discounted_price', '>=', self::MIN_TOP_PRODUCT_PRICE)
            ->whereHas('product.category', function ($categoryQuery) {
                $categoryQuery->whereNotIn('slug', config('categories.excluded_top_product_slugs', []));
            });
    }

    private function formatDeals(Collection $discounts): array
    {
        return $this->formatter->format($discounts)->values()->all();
    }
}
