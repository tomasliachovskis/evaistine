<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HomePageSectionsService
{
    /** @var HomeDealPoolService */
    private $poolService;

    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    /** @var DiscountResponseFormatter */
    private $formatter;

    public function __construct(
        DiscountResponseFormatter $formatter,
        HomeDealPoolService $poolService
    ) {
        $this->formatter = $formatter;
        $this->poolService = $poolService;
    }

    public function build(): array
    {
        $pools = $this->poolService->buildPools();

        return [
            'best_pool' => $this->formatDeals(collect($pools['best'])),
            'food_pool' => $this->formatDeals(collect($pools['food'])),
            'non_food_pool' => $this->formatDeals(collect($pools['non_food'])),
            'store_ranking' => $this->getStoreRanking(),
        ];
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

        $ranked = [];

        foreach ($stores as $store) {
            $maxDiscount = (int) round(
                Discount::query()
                    ->where('store_id', $store->id)
                    ->whereNotNull('discount_percent')
                    ->max('discount_percent') ?? 0
            );

            if ($maxDiscount <= 0) {
                continue;
            }

            $topProducts = $this->getTopProductsForStore($store);

            $ranked[] = [
                'slug' => $store->slug,
                'name' => $store->name,
                'href' => "/leidinys/{$store->slug}",
                'max_discount_percent' => $maxDiscount,
                'hot_deals_count' => $store->discounts_count,
                'top_products' => $topProducts,
            ];
        }

        usort($ranked, fn ($a, $b) => $b['max_discount_percent'] <=> $a['max_discount_percent']);

        $ranked = array_slice($ranked, 0, 8);

        foreach ($ranked as $index => &$row) {
            $row['crown_rank'] = $index < 3 ? $index + 1 : null;
        }
        unset($row);

        return $ranked;
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
                    ? "/akcijos/{$categorySlug}/{$d->product->slug}"
                    : "/akcijos/{$store->slug}";

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
                $categoryQuery->whereNotIn('slug', self::EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS);
            });
    }

    private function formatDeals(Collection $discounts): array
    {
        return $this->formatter->format($discounts)->values()->all();
    }
}
