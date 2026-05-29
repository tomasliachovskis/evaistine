<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomePageSectionsService
{
    private const EXCLUDED_TOP_PRODUCT_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    private const MIN_TOP_PRODUCT_PRICE = 5.0;

    private const POPULAR_CATEGORY_IDS = [1, 52, 121, 352, 380];

    private const SECTION_LIMIT = 8;

    /** @var DiscountResponseFormatter */
    private $formatter;

    public function __construct(
        DiscountResponseFormatter $formatter
    ) {
        $this->formatter = $formatter;
    }

    public function build(): array
    {
        $featured = $this->getFeaturedDeal();
        $featuredId = $featured ? $featured->id : null;

        return [
            'featured_deal' => $featured ? $this->formatDeal($featured) : null,
            'new_today' => $this->formatDeals($this->getNewToday($featuredId)),
            'price_drops' => $this->formatDealsWithPriceChange($this->getPriceDrops($featuredId)),
            'trending' => $this->formatDeals($this->getTrending($featuredId)),
            'expiring_soon' => $this->formatDeals($this->getExpiringSoon($featuredId)),
            'category_savings' => $this->getCategorySavings(),
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

    private function baseQuery(): Builder
    {
        return Discount::query()
            ->select('discounts.*')
            ->with(['product.category', 'store'])
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

    private function getFeaturedDeal(): ?Discount
    {
        $query = $this->baseQuery();
        $this->applyTopProductFilters($query);

        return $query
            ->orderByDesc('discounts.discount_percent')
            ->first();
    }

    private function getNewToday(?int $excludeId): Collection
    {
        $query = $this->baseQuery();
        $this->applyTopProductFilters($query);
        $this->excludeIds($query, $excludeId);

        return $query
            ->whereDate('discounts.created_at', Carbon::today())
            ->orderByDesc('discounts.discount_percent')
            ->limit(self::SECTION_LIMIT)
            ->get();
    }

    private function getTrending(?int $excludeId): Collection
    {
        $query = $this->baseQuery();
        $this->applyTopProductFilters($query);
        $this->excludeIds($query, $excludeId);

        $ids = implode(',', self::POPULAR_CATEGORY_IDS);

        return $query
            ->orderByRaw(
                "CASE WHEN (SELECT category_id FROM products WHERE products.id = discounts.product_id) IN ({$ids}) THEN 0 ELSE 1 END"
            )
            ->orderByDesc('discounts.discount_percent')
            ->limit(self::SECTION_LIMIT)
            ->get();
    }

    private function getExpiringSoon(?int $excludeId): Collection
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->addDays(3);

        $query = $this->baseQuery();
        $this->applyTopProductFilters($query);
        $this->excludeIds($query, $excludeId);

        return $query
            ->whereNotNull('discounts.end_at')
            ->where('discounts.end_at', '>=', $now)
            ->where('discounts.end_at', '<=', $cutoff)
            ->orderBy('discounts.end_at')
            ->limit(self::SECTION_LIMIT)
            ->get();
    }

    private function getPriceDrops(?int $excludeId): Collection
    {
        $latestHistory = DB::table('discount_histories as dh')
            ->select('dh.product_id', 'dh.store_id', DB::raw('MAX(dh.id) as max_id'))
            ->groupBy('dh.product_id', 'dh.store_id');

        $query = $this->baseQuery();
        $this->applyTopProductFilters($query);
        $this->excludeIds($query, $excludeId);

        return $query
            ->joinSub($latestHistory, 'latest_hist', function ($join) {
                $join->on('discounts.product_id', '=', 'latest_hist.product_id')
                    ->on('discounts.store_id', '=', 'latest_hist.store_id');
            })
            ->join('discount_histories as prev', 'prev.id', '=', 'latest_hist.max_id')
            ->whereColumn('discounts.discounted_price', '<', 'prev.discounted_price')
            ->orderByRaw('(prev.discounted_price - discounts.discounted_price) DESC')
            ->select('discounts.*')
            ->limit(self::SECTION_LIMIT)
            ->get();
    }

    private function getCategorySavings(): array
    {
        $today = Carbon::today()->toDateString();

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('hide', false)
            ->withCount([
                'discounts' => function ($query) {
                    $query->select(DB::raw('count(distinct discounts.id)'));
                },
            ])
            ->whereHas('discounts')
            ->get();

        $rows = [];

        foreach ($categories as $category) {
            $best = Discount::query()
                ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->whereNotNull('discount_percent')
                ->max('discount_percent');

            if (!$best || $best <= 0) {
                continue;
            }

            $newTodayCount = Discount::query()
                ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->whereDate('created_at', $today)
                ->count();

            $offerCount = $newTodayCount > 0 ? $newTodayCount : $category->discounts_count;

            $rows[] = [
                'name' => $category->name,
                'slug' => $category->slug,
                'href' => "/akcijos/{$category->slug}",
                'max_discount_percent' => (int) round($best),
                'offers_count' => $offerCount,
                'new_today_count' => $newTodayCount,
                'offers_count_label' => $newTodayCount > 0 ? 'šiandien' : 'akcijų',
            ];
        }

        usort($rows, fn ($a, $b) => $b['max_discount_percent'] <=> $a['max_discount_percent']);

        return array_slice($rows, 0, 8);
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

    private function formatDeals(Collection $discounts): array
    {
        return $this->formatter->format($discounts)->values()->all();
    }

    private function formatDeal(Discount $discount): array
    {
        return $this->formatter->format(collect([$discount]))->first();
    }

    private function formatDealsWithPriceChange(Collection $discounts): array
    {
        return $discounts->map(function (Discount $discount) {
            $formatted = $this->formatDeal($discount);
            $change = $this->resolvePriceChangeAmount($discount);
            if ($change !== null) {
                $formatted['price_change_amount'] = $change;
            }

            return $formatted;
        })->values()->all();
    }
}
