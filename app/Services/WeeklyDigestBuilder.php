<?php

namespace App\Services;

use App\Models\CuratedDeal;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\LithuanianDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Content of the Thursday email for a set of stores: their leaflets that
 * started in the last week (or start in the next few days) and their best
 * current offers, from the store_top_offers pool DealPoolRefresher already
 * keeps fresh. Built per distinct store set, so subscribers with the same
 * stores share one build.
 */
class WeeklyDigestBuilder
{
    private const LEAFLET_LIMIT = 6;

    private const OFFER_LIMIT = 8;

    private const OFFERS_PER_STORE = 3;

    public function __construct(private StoreFlyerTitleBuilder $titles)
    {
    }

    /**
     * @param  list<string>  $storeSlugs
     * @return array{stores: Collection, leaflets: list<array>, offers: Collection}
     */
    public function build(array $storeSlugs): array
    {
        $stores = Store::whereIn('slug', $storeSlugs)->get();

        $leaflets = StoreFlyer::query()
            ->with('store')
            ->active()
            ->ready()
            ->currentlyValid()
            ->whereIn('store_id', $stores->pluck('id'))
            ->whereBetween('valid_from', [now()->subDays(7)->startOfDay(), now()->addDays(3)->endOfDay()])
            ->orderByDesc('valid_from')
            ->limit(self::LEAFLET_LIMIT)
            ->get()
            ->map(fn (StoreFlyer $flyer) => $this->leafletRow($flyer))
            ->all();

        // The stores' "best offers of the week" pools, best first, at most
        // OFFERS_PER_STORE from one store so every chosen store shows up.
        $perStore = [];
        $offers = CuratedDeal::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->where('scope', 'store_top_offers')
            ->with(['discount.product.category', 'discount.store'])
            ->orderByDesc('deal_score')
            ->get()
            ->pluck('discount')
            ->filter(fn ($discount) => $discount && $discount->product
                && ($discount->end_at === null || $discount->end_at->gte(now()->startOfDay())))
            ->filter(function ($discount) use (&$perStore) {
                $perStore[$discount->store_id] = ($perStore[$discount->store_id] ?? 0) + 1;

                return $perStore[$discount->store_id] <= self::OFFERS_PER_STORE;
            })
            ->take(self::OFFER_LIMIT)
            ->values();

        return ['stores' => $stores, 'leaflets' => $leaflets, 'offers' => $offers];
    }

    /**
     * One leaflet as the emails show it.
     *
     * @return array{title: string, store_name: string, image: ?string, url: string, dates: ?string}
     */
    public function leafletRow(StoreFlyer $flyer): array
    {
        return [
            'title' => $this->titles->build($flyer, $flyer->store),
            'store_name' => $flyer->store->name,
            'image' => $this->absolute($flyer->thumbnail_url ?: $flyer->image_url),
            'url' => url($flyer->view_url ?: '/leidinys/'.$flyer->store->slug),
            'dates' => $flyer->valid_from && $flyer->valid_to
                ? LithuanianDate::range(Carbon::parse($flyer->valid_from), Carbon::parse($flyer->valid_to))
                : null,
        ];
    }

    public function absolute(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }
}
