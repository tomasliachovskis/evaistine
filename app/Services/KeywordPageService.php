<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CuratedDeal;
use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\KeywordPage;
use App\Models\KeywordPageProduct;
use App\Models\Store;
use App\Support\CacheVersion;
use App\Support\FoodCategorySlugs;
use App\Support\LithuanianDate;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KeywordPageService
{
    private const PER_PAGE = 20;

    private const MAX_LISTING_FETCH = 1000;

    private const MEILISEARCH_FETCH_BUFFER = 50;

    private const STORE_COMPARISON_FETCH_LIMIT = 250;

    private const MIN_CHIP_OFFERS = 3;

    // Same 5 chains buildStoreKeywordVariants()/KeywordPageGptService already
    // treat as "the main stores" — guarantees each of these a row in
    // buildStorePriceTable() even when it isn't among the top-8-by-offer-
    // count brands (found live: Norfa's cheapest match for "kava" has no
    // brand at all, so Norfa never appeared in the old per-brand table).
    private const PRIORITY_STORE_NAMES = ['Maxima', 'Norfa', 'Lidl', 'Iki', 'Rimi'];

    public function __construct(
        private MeilisearchService $meilisearchService,
        private DiscountResponseFormatter $formatter,
        private PageFreshnessService $freshnessService,
        private StoreFlyerTitleBuilder $flyerTitleBuilder,
        private KeywordPageDynamicMetaService $dynamicMetaService,
        private KeywordPageCategoryResolver $categoryResolver,
    ) {
    }

    public function listPublishedSlugs(): array
    {
        return KeywordPage::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->pluck('slug')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function listAllSlugs(): array
    {
        return KeywordPage::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->pluck('slug')
            ->all();
    }

    public function listPublishedPages(): array
    {
        // Footer "Produktai" list and the search modal's "Populiariausi"
        // suggestions both come from this one list — is_chip alone let
        // through editorially-flagged pages with few or zero real matched
        // products (e.g. "Karpis", "Antis", "Tofu kraikas"), which read as
        // broken/random in a "popular" list. Real product count is now the
        // actual ranking, not the manual sort_order field. Food items list
        // first, then non-food — same grouping topCandidatesByCategoryGroup()
        // uses for the homepage teaser, per explicit product decision (was a
        // single list mixing both, e.g. "Dantų pasta" next to "Kačių kraikas").
        $pages = KeywordPage::query()
            ->published()
            ->where('is_chip', true)
            ->where('matching_offers_count', '>', 0)
            ->orderByDesc('matching_offers_count')
            ->orderBy('title')
            ->get(['slug', 'title', 'h1', 'emoji', 'matching_offers_count', 'category_slugs']);

        $food = [];
        $nonFood = [];
        $other = [];

        foreach ($pages as $page) {
            $primary = $this->categoryResolver->resolvePrimaryListingCategorySlugs(
                (array) ($page->category_slugs ?? []),
            );
            $categorySlug = $primary[0] ?? null;

            if (in_array($categorySlug, FoodCategorySlugs::FOOD, true)) {
                $food[] = $page;
            } elseif (in_array($categorySlug, FoodCategorySlugs::NON_FOOD, true)) {
                $nonFood[] = $page;
            } else {
                $other[] = $page;
            }
        }

        return collect([...$food, ...$nonFood, ...$other])
            ->map(fn (KeywordPage $page) => $this->mapPublishedPageSummary($page))
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, title: string, h1: string, emoji: string, href: string}>
     */
    public function listPublishedPagesForCategory(string $categorySlug): array
    {
        $listingSlugs = $this->categoryResolver->resolveListingCategorySlugs([$categorySlug]);
        if ($listingSlugs === []) {
            return [];
        }

        return KeywordPage::query()
            ->published()
            ->where('is_chip', true)
            ->orderByDesc('matching_offers_count')
            ->orderBy('title')
            ->get(['slug', 'title', 'h1', 'emoji', 'matching_offers_count', 'category_slugs'])
            ->filter(function (KeywordPage $page) use ($listingSlugs) {
                $primary = $this->categoryResolver->resolvePrimaryListingCategorySlugs(
                    (array) ($page->category_slugs ?? []),
                );

                return $primary !== [] && in_array($primary[0], $listingSlugs, true);
            })
            ->map(fn (KeywordPage $page) => $this->mapPublishedPageSummary($page))
            ->values()
            ->all();
    }

    public function refreshOfferCounts(KeywordPage $page): KeywordPage
    {
        $page->forceFill([
            'matching_offers_count' => $this->countMatchingOffers($page),
            'displayed_offers_count' => $this->countDisplayedOffersForPage($page),
            'offers_counted_at' => now(),
        ])->save();

        return $page->refresh();
    }

    public function refreshAllOfferCounts(): int
    {
        $count = 0;

        KeywordPage::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->each(function (KeywordPage $page) use (&$count) {
                $this->refreshOfferCounts($page);
                $count++;
            });

        return $count;
    }

    /**
     * @return array{slug: string, title: string, h1: string, emoji: string, href: string, matching_offers_count: int}
     */
    private function mapPublishedPageSummary(KeywordPage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'h1' => $page->h1,
            'emoji' => $page->emoji ?: '🏷️',
            'href' => "/akcijos/{$page->slug}",
            'matching_offers_count' => (int) ($page->matching_offers_count ?? 0),
        ];
    }

    public function countMatchingOffersForPage(KeywordPage $page): int
    {
        return $this->countMatchingOffers($page);
    }


    public function countDisplayedOffersForPage(KeywordPage $page): int
    {
        return $this->collectMatchingDiscountsCollection($page)->count();
    }

    public function buildListingResponse(KeywordPage $page, array $filters): array
    {
        $matchingTotal = $this->countMatchingOffers($page);

        if ($matchingTotal < $page->min_active_offers) {
            abort(404);
        }

        $allDiscounts = $this->collectMatchingDiscountsCollection($page);

        if ($allDiscounts->isEmpty()) {
            abort(404);
        }

        $filtered = $this->applyCollectionFilters($allDiscounts, $filters);
        $sorted = $this->sortDiscounts($filtered, (string) ($filters['order'] ?? 'popular'));
        $filteredTotal = $sorted->count();

        if ($filteredTotal === 0) {
            abort(404);
        }

        $pageNumber = max(1, (int) ($filters['page'] ?? 1));
        $pageItems = $sorted
            ->slice(($pageNumber - 1) * self::PER_PAGE, self::PER_PAGE)
            ->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $filteredTotal,
            self::PER_PAGE,
            $pageNumber,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $listingMeta = $this->buildListingMeta($page, $allDiscounts, $matchingTotal);

        return [
            'data' => $this->formatter->format($paginator),
            'expired' => [],
            'breadcrumbs' => $this->buildBreadcrumbs($page),
            'seo' => $this->buildSeo($page, $allDiscounts, $matchingTotal),
            'listing_meta' => $listingMeta,
        ];
    }

    public function isKeywordSlug(string $slug): bool
    {
        return KeywordPage::query()
            ->where('slug', $slug)
            ->exists();
    }

    public function productMatchesKeywordPage(Discount $discount, KeywordPage $page): bool
    {
        return $this->passesKeywordFilters($discount, $page);
    }

    /**
     * Real, indexable cross-links from a single PRODUCT page to whichever
     * published keyword pages that product actually belongs to (e.g. one
     * Jacobs ground-coffee product -> "Kava" AND "Malta kava"). Completes
     * the internal-link picture: category pages already link to keyword
     * pages, keyword pages now link to each other (buildRelatedPages()
     * above) — product pages linked to neither until this.
     *
     * Reads the keyword_page_products table (built by the
     * `keywords:map-products` batch command — see
     * app/Console/Commands/MapProductsToKeywordPages.php), not a live scan:
     * looping every published keyword page's filters on every product-page
     * request doesn't scale, so that matching runs once, product-first,
     * over all ~53k products, and this just reads the precomputed result.
     * Cache wraps the read itself (cheap either way, indexed on product_id)
     * so a hot product page doesn't repeat even that query — versioned by
     * the 'keywords' CacheVersion group, already bumped both by the mapping
     * command and by every keyword-page admin save.
     *
     * @return list<array{label: string, href: string, emoji: ?string}>
     */
    public function relatedPagesForProduct(?int $productId): array
    {
        if (!$productId) {
            return [];
        }

        $cacheKey = 'kw_related_product_' . $productId . '_' . CacheVersion::suffix(['keywords']);

        return Cache::remember($cacheKey, 3600, function () use ($productId) {
            return KeywordPageProduct::where('product_id', $productId)
                ->orderByDesc('score')
                ->with(['keywordPage' => fn ($q) => $q->published()])
                ->take(4)
                ->get()
                ->filter(fn (KeywordPageProduct $row) => $row->keywordPage !== null)
                ->map(fn (KeywordPageProduct $row) => [
                    'label' => $this->capitalizeFirst($row->keywordPage->grammar_dative ?: $row->keywordPage->title),
                    'href' => "/akcijos/{$row->keywordPage->slug}",
                    'emoji' => $row->keywordPage->emoji,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Reads the persisted 'keyword_teaser' curated_deals rows written by
     * refreshHomeTeasers() below — NOT a live computation anymore. An
     * earlier version of this method called buildStoreComparisonForPage()
     * directly per page, which meant a cold /pigiausios-prekes request ran
     * a live Meilisearch comparison for every one of ~40 candidate keyword
     * pages in sequence (measured: multiple minutes) — the same mistake
     * this codebase's own refreshHomePools() docblock already describes
     * making once before with HomeKeywordDealPoolBuilder, fixed the same
     * way here: compute on the write path (refreshHomeTeasers(), run from
     * DealPoolRefresher alongside every other curated_deals scope), read
     * cheaply on the request path.
     *
     * @return array{slug: string, href: string, label: string, emoji: string, matching_offers_count: int, leading_deals: array}|null
     */
    public function buildHomeTeaser(KeywordPage $page, int $limit = 5): ?array
    {
        $cacheKey = 'kw_home_teaser_' . $page->slug . '_' . $limit . '_' . CacheVersion::suffix(['keywords', 'discounts']);

        return Cache::remember($cacheKey, 1800, function () use ($page, $limit) {
            $rows = CuratedDeal::query()
                ->where('scope', 'keyword_teaser')
                ->where('item_key', $page->slug)
                ->with(['discount.product.category', 'discount.product.discounts.store', 'discount.product.discountHistories.store', 'discount.store'])
                ->orderBy('position')
                ->take($limit)
                ->get();

            $leadingDeals = $rows->pluck('discount')->filter()->values();

            // Last-resort top-up when this keyword genuinely doesn't have
            // $limit active/ever-scraped discounts among its mapped products
            // (refreshHomeTeasers()'s own widen tier already exhausted the
            // discounts table) — per explicit product decision, still show
            // $limit real products with a real (if stale) price rather than
            // stopping short. Not persisted into curated_deals — that table
            // only ever references real Discount rows (its discount_id FK
            // points at the discounts table) — so this runs live here on a
            // cache miss instead, same cost profile as everything else in
            // this method.
            if ($leadingDeals->count() < $limit) {
                $usedProductIds = $leadingDeals->pluck('product_id')->all();
                $usedStoreIds = $leadingDeals->pluck('store_id')->filter()->all();
                $productIds = KeywordPageProduct::where('keyword_page_id', $page->id)->pluck('product_id');

                // Same per-store diversification buildIndexBackedTeaserDeals()
                // uses above — without it, ordering plain by discounted_price
                // can fill every remaining slot from whichever single store
                // happens to run the deepest storewide discount (confirmed
                // live: a "Vanduo" teaser landed all 5 cards on Rimi even
                // though Maxima/Norfa/Lidl/Iki also had matching products).
                $priorityRank = array_flip(self::PRIORITY_STORE_NAMES);

                $candidates = DiscountHistory::query()
                    ->whereIn('product_id', $productIds)
                    ->whereNotIn('product_id', $usedProductIds)
                    ->where('discounted_price', '>', 0)
                    ->with(['product.category', 'store'])
                    ->orderBy('discounted_price')
                    ->limit(200)
                    ->get();

                $byStore = $candidates->groupBy('store_id')
                    ->map(fn (Collection $storeItems) => $storeItems->sortBy('discounted_price')->values());

                $storeOrder = $byStore->keys()
                    ->sort(function ($a, $b) use ($byStore, $priorityRank) {
                        $rankA = $priorityRank[$byStore[$a]->first()->store->name] ?? count($priorityRank);
                        $rankB = $priorityRank[$byStore[$b]->first()->store->name] ?? count($priorityRank);

                        return $rankA <=> $rankB;
                    })
                    ->values();

                $needed = $limit - $leadingDeals->count();
                $historical = collect();
                $roundIndex = 0;

                while ($historical->count() < $needed) {
                    $addedThisRound = false;

                    foreach ($storeOrder as $storeId) {
                        if ($historical->count() >= $needed) {
                            break;
                        }

                        // Prefer a store not already represented among the
                        // real leading deals until every store has had a
                        // turn, then allow repeats to still hit $limit.
                        if ($roundIndex === 0 && in_array($storeId, $usedStoreIds, true) && count($storeOrder) > count($usedStoreIds)) {
                            continue;
                        }

                        $items = $byStore[$storeId];
                        if ($roundIndex >= $items->count()) {
                            continue;
                        }

                        $historical->push($items[$roundIndex]);
                        $addedThisRound = true;
                    }

                    if (! $addedThisRound) {
                        break;
                    }

                    $roundIndex++;
                }

                $leadingDeals = $leadingDeals->concat($historical)->values();
            }

            if ($leadingDeals->isEmpty()) {
                return null;
            }

            return [
                'slug' => $page->slug,
                'href' => "/akcijos/{$page->slug}",
                // title, not h1 — h1 is written as "{keyword} akcija" (e.g.
                // "Varškei akcija") for the page's own SEO heading, and
                // stripping "akcija" from it can leave an odd standalone
                // case-inflected fragment ("Varškei" — dative — instead of
                // the plain "Varškė"). title is already the plain noun form.
                'label' => $page->title,
                'emoji' => $page->emoji,
                'matching_offers_count' => (int) ($page->matching_offers_count ?? 0),
                'leading_deals' => $this->formatter->formatList($leadingDeals),
            ];
        });
    }

    /**
     * Write side for buildHomeTeaser() above — cheapest-per-store comparison
     * per candidate keyword page, computed from the already-precomputed
     * keyword_page_products index (product_id -> keyword page, built by
     * keywords:map-products) instead of countMatchingOffers()/
     * buildStoreComparisonForPage()'s Meilisearch-or-live-DB-scan path —
     * per explicit product decision: we already have the product index for
     * exactly this purpose, no reason to also hit search here. One indexed
     * `WHERE product_id IN (...)` per page (typically 0.5-1s even against
     * the remote dev DB), not a search query. Persisted as curated_deals
     * rows (scope='keyword_teaser', item_key=the keyword page's slug,
     * reusing the same item_key column the price_index scope already used
     * for its own bucket key). Meant to be called from
     * DealPoolRefresher::refreshAfterBatch(), same cadence as every other
     * curated_deals scope.
     */
    public function refreshHomeTeasers(int $limitPerGroup = 20, int $dealsPerPage = 5): void
    {
        $candidates = $this->topCandidatesByCategoryGroup($limitPerGroup);
        $pages = collect($candidates['food'])->concat($candidates['non_food'])->unique('id');

        $rows = [];
        $now = now();

        foreach ($pages as $page) {
            $deals = $this->buildIndexBackedTeaserDeals($page, $dealsPerPage);

            foreach ($deals as $position => $deal) {
                $rows[] = [
                    'store_id' => null,
                    'scope' => 'keyword_teaser',
                    'category_id' => null,
                    'item_key' => $page->slug,
                    'position' => $position,
                    'discount_id' => $deal->id,
                    'deal_score' => (float) $deal->discounted_price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows) {
            CuratedDeal::where('scope', 'keyword_teaser')->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('curated_deals')->insert($chunk);
            }
        });
    }

    /**
     * @return Collection<int, Discount>
     */
    private function buildIndexBackedTeaserDeals(KeywordPage $page, int $limit): Collection
    {
        $productIds = KeywordPageProduct::where('keyword_page_id', $page->id)->pluck('product_id');
        if ($productIds->isEmpty()) {
            return collect();
        }

        $discounts = Discount::query()
            ->whereIn('product_id', $productIds)
            // Same still-valid check ProductController's own similar-products
            // query uses — end_at is a DATE stored at midnight ("valid
            // through this day"), so comparing against startOfDay() instead
            // of plain now() doesn't wrongly expire a discount at the start
            // of its last valid day. discounts:archive-expired only runs
            // per-store as part of that store's own processing job (not a
            // standalone sweep), so an already-expired row can otherwise
            // still be sitting in this table.
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay());
            })
            ->with(['product.category', 'store'])
            ->get();

        // Same PRIORITY_STORE_NAMES-first ordering buildStorePriceTable()
        // already uses for the keyword page's own "Kainos pagal parduotuvę"
        // table — without it, a teaser capped at 5 stores can silently miss
        // a main chain (Maxima/Norfa/Lidl/Iki/Rimi) whenever a niche store's
        // match happens to have a bigger discount. Within a store, the
        // primary pick is the biggest discount_percent — per explicit
        // product decision, a teaser's whole point is "best deals", so the
        // headline number here is the discount size, not the lowest absolute
        // price (a €0.30 item at -10% would otherwise beat a €20 item at
        // -60% just for being cheaper in absolute terms).
        $priorityRank = array_flip(self::PRIORITY_STORE_NAMES);

        $priced = $discounts->filter(fn (Discount $d) => (float) $d->discounted_price > 0);

        $perStoreCheapest = $priced->groupBy('store_id')
            ->map(fn (Collection $storeDiscounts) => $storeDiscounts->sortByDesc('discount_percent')->first())
            ->values()
            ->sort(function (Discount $a, Discount $b) use ($priorityRank) {
                $rankA = $priorityRank[$a->store->name] ?? count($priorityRank);
                $rankB = $priorityRank[$b->store->name] ?? count($priorityRank);

                return $rankA <=> $rankB ?: $b->discount_percent <=> $a->discount_percent;
            })
            ->values();

        if ($perStoreCheapest->count() >= $limit) {
            return $perStoreCheapest->take($limit);
        }

        // Still short of $limit distinct stores — round-robin a second,
        // third, ... cheapest item per store (same priority order as above)
        // before ever giving one store more slots than another. Without
        // this, a store running a storewide campaign across many SKUs in
        // this category (e.g. "-30% all cat food") could out-supply every
        // other store and take every remaining slot on cheapest-price alone,
        // even though other real stores have their own offer here too —
        // confirmed live on "Sausas kačių maistas" (all 4 slots landing on
        // one store despite others having active discounts).
        $byStore = $priced->groupBy('store_id')
            ->map(fn (Collection $storeDiscounts) => $storeDiscounts->sortByDesc('discount_percent')->values());
        $storeOrder = $byStore->keys()
            ->sort(function ($a, $b) use ($byStore, $priorityRank) {
                $rankA = $priorityRank[$byStore[$a]->first()->store->name] ?? count($priorityRank);
                $rankB = $priorityRank[$byStore[$b]->first()->store->name] ?? count($priorityRank);

                return $rankA <=> $rankB;
            })
            ->values();

        $usedIds = $perStoreCheapest->pluck('id')->all();
        $combined = $perStoreCheapest;
        $roundIndex = 1; // index 0 (cheapest per store) is already in $perStoreCheapest

        while ($combined->count() < $limit) {
            $addedThisRound = false;

            foreach ($storeOrder as $storeId) {
                if ($combined->count() >= $limit) {
                    break;
                }

                $items = $byStore[$storeId];
                if ($roundIndex >= $items->count()) {
                    continue;
                }

                $discount = $items[$roundIndex];
                $combined = $combined->push($discount);
                $usedIds[] = $discount->id;
                $addedThisRound = true;
            }

            if (! $addedThisRound) {
                break;
            }

            $roundIndex++;
        }

        $combined = $combined->values();

        if ($combined->count() >= $limit) {
            return $combined;
        }

        // Still short even after exhausting every store's own active
        // discounts round-robin — fill the rest by biggest discount_percent
        // regardless of store, same "don't stop early just because it
        // repeats a store" decision as before, just as the last resort
        // instead of the first.
        $backfill = $priced
            ->reject(fn (Discount $d) => in_array($d->id, $usedIds, true))
            ->sortByDesc('discount_percent')
            ->take($limit - $combined->count());

        $combined = $combined->concat($backfill)->values();

        if ($combined->count() >= $limit) {
            return $combined;
        }

        // Still short after using every currently-active discount on these
        // mapped products — per explicit product decision, widen to ANY
        // discount ever scraped for them (drops the end_at validity check
        // entirely), biggest discount_percent first, so a thin keyword still
        // shows $limit real products with a real price instead of stopping
        // short. A keyword this thin on active offers is rare; this only
        // ever engages as the last resort after the two tiers above.
        $usedIds = $combined->pluck('id')->all();
        $widened = Discount::query()
            ->whereIn('product_id', $productIds)
            ->whereNotIn('id', $usedIds)
            ->where('discounted_price', '>', 0)
            ->with(['product.category', 'store'])
            ->orderByDesc('discount_percent')
            ->take($limit - $combined->count())
            ->get();

        return $combined->concat($widened)->values();
    }

    /**
     * Candidate keyword pages for the homepage teaser / "pigiausios prekės"
     * page — restricted to is_chip=true (the existing "worth surfacing as a
     * popular page" flag, same one keyword-chips-row/home already key off)
     * instead of every published page, ranked by matching_offers_count,
     * split food/non-food using the same FoodCategorySlugs groups
     * ListingPageMetaService already uses for a store's featured-category
     * pick — no new categorization scheme.
     *
     * @return array{food: list<KeywordPage>, non_food: list<KeywordPage>}
     */
    public function topCandidatesByCategoryGroup(int $limitPerGroup = 20): array
    {
        $cacheKey = 'kw_home_candidates_' . $limitPerGroup . '_' . CacheVersion::suffix(['keywords']);

        return Cache::remember($cacheKey, 1800, function () use ($limitPerGroup) {
            $pages = KeywordPage::query()
                ->published()
                ->where('is_chip', true)
                ->where('matching_offers_count', '>', 0)
                ->orderByDesc('matching_offers_count')
                ->get();

            $food = [];
            $nonFood = [];

            foreach ($pages as $page) {
                if (count($food) >= $limitPerGroup && count($nonFood) >= $limitPerGroup) {
                    break;
                }

                $primary = $this->categoryResolver->resolvePrimaryListingCategorySlugs(
                    (array) ($page->category_slugs ?? []),
                );
                $categorySlug = $primary[0] ?? null;

                if (in_array($categorySlug, FoodCategorySlugs::FOOD, true) && count($food) < $limitPerGroup) {
                    $food[] = $page;
                } elseif (in_array($categorySlug, FoodCategorySlugs::NON_FOOD, true) && count($nonFood) < $limitPerGroup) {
                    $nonFood[] = $page;
                }
            }

            return ['food' => $food, 'non_food' => $nonFood];
        });
    }

    private function collectMatchingDiscountsCollection(KeywordPage $page): Collection
    {
        $limit = min(
            max($this->countMatchingOffers($page) + self::MEILISEARCH_FETCH_BUFFER, self::PER_PAGE),
            self::MAX_LISTING_FETCH,
        );

        $discounts = $this->fetchDisplayedDiscountsFromMeilisearch($page, $limit);

        if ($discounts->isEmpty()) {
            $discounts = $this->fallbackCollectDisplayedDiscounts($page, $limit);
        }

        return $discounts->values();
    }

    private function sortDiscounts(Collection $discounts, string $order): Collection
    {
        return match ($order) {
            'price_min' => $discounts->sortBy(fn (Discount $d) => [
                (float) $d->discounted_price > 0 ? 0 : 1,
                (float) $d->discounted_price > 0 ? (float) $d->discounted_price : 999999,
            ])->values(),
            'price_max' => $discounts->sortByDesc(fn (Discount $d) => (float) $d->discounted_price)->values(),
            'price_discount_proc_max' => $discounts->sortByDesc(fn (Discount $d) => (float) $d->discount_percent)->values(),
            'price_discount_max' => $discounts->sortByDesc(
                fn (Discount $d) => (float) $d->original_price - (float) $d->discounted_price
            )->values(),
            default => $discounts->values(),
        };
    }

    private function countMatchingOffers(KeywordPage $page): int
    {
        $query = $this->buildSearchQuery($page);
        if ($query === '') {
            return $this->fallbackCountMatchingOffers($page);
        }

        try {
            $results = $this->meilisearchService->search(
                $query,
                $this->buildMeilisearchFilters($this->resolveCategoryIds($page)),
                [],
                1,
                1,
            );

            return (int) ($results['total'] ?? 0);
        } catch (\Exception $e) {
            \Log::warning('Keyword page Meilisearch count failed', [
                'slug' => $page->slug,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackCountMatchingOffers($page);
        }
    }

    private function buildSearchQuery(KeywordPage $page): string
    {
        $terms = array_values(array_filter(array_map(
            fn ($term) => trim((string) $term),
            $page->search_terms ?? [],
        )));

        return implode(' ', $terms);
    }

    private function resolvePrimarySearchTerm(KeywordPage $page): string
    {
        foreach ((array) ($page->search_terms ?? []) as $term) {
            $term = trim((string) $term);
            if ($term !== '') {
                return $term;
            }
        }

        return $page->slug;
    }

    private function fetchDisplayedDiscountsFromMeilisearch(KeywordPage $page, ?int $maxResults = null): Collection
    {
        $limit = $maxResults ?? self::MAX_LISTING_FETCH;

        $query = $this->buildSearchQuery($page);
        if ($query === '') {
            return collect();
        }

        $filters = $this->buildMeilisearchFilters($this->resolveCategoryIds($page));

        try {
            $results = $this->meilisearchService->search(
                $query,
                $filters,
                [],
                1,
                min($limit + self::MEILISEARCH_FETCH_BUFFER, self::MAX_LISTING_FETCH),
            );
        } catch (\Exception $e) {
            \Log::warning('Keyword page displayed deals Meilisearch search failed', [
                'slug' => $page->slug,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        $discountIds = collect($results['hits'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (empty($discountIds)) {
            return collect();
        }

        $discountsById = Discount::query()
            ->with(['product.category', 'store'])
            ->whereIn('id', $discountIds)
            ->get()
            ->keyBy('id');

        $ordered = collect();
        foreach ($discountIds as $discountId) {
            $discount = $discountsById->get($discountId);
            if (!$discount || !$this->passesKeywordFilters($discount, $page)) {
                continue;
            }
            $ordered->push($discount);
            if ($ordered->count() >= $limit) {
                break;
            }
        }

        return $ordered;
    }

    private function fallbackCollectDisplayedDiscounts(KeywordPage $page, ?int $maxResults = null): Collection
    {
        $categoryIds = $this->resolveCategoryIds($page);
        $discountIds = $this->fallbackSearchDiscountIds($page, $categoryIds);

        if (empty($discountIds)) {
            return collect();
        }

        return Discount::query()
            ->with(['product.category', 'store'])
            ->whereIn('id', $discountIds)
            ->get()
            ->filter(fn (Discount $discount) => $this->passesKeywordFilters($discount, $page))
            ->take($maxResults ?? self::MAX_LISTING_FETCH)
            ->values();
    }

    private function fallbackCountMatchingOffers(KeywordPage $page): int
    {
        $categoryIds = $this->resolveCategoryIds($page);
        $discountIds = $this->fallbackSearchDiscountIds($page, $categoryIds);

        if (empty($discountIds)) {
            return 0;
        }

        return Discount::query()
            ->with(['product.category'])
            ->whereIn('id', $discountIds)
            ->get()
            ->filter(fn (Discount $discount) => $this->passesKeywordFilters($discount, $page))
            ->count();
    }

    private function applyCollectionFilters(Collection $discounts, array $filters): Collection
    {
        return $discounts->filter(function (Discount $discount) use ($filters) {
            if (!empty($filters['card']) && !$discount->card) {
                return false;
            }

            if (!empty($filters['plus']) && $discount->condition !== '1+1') {
                return false;
            }

            if (!empty($filters['store'])) {
                $storeSlugs = array_values(array_filter(array_map('trim', explode(',', (string) $filters['store']))));
                if (!in_array($discount->store?->slug, $storeSlugs, true)) {
                    return false;
                }
            }

            if (!empty($filters['category'])) {
                $categorySlugs = array_values(array_filter(array_map('trim', explode(',', (string) $filters['category']))));
                $categorySlug = $discount->product?->category?->slug;
                if (!$categorySlug || !in_array($categorySlug, $categorySlugs, true)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function buildMeilisearchFilters(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        if (count($categoryIds) === 1) {
            return ['category_id' => $categoryIds[0]];
        }

        return ['category_ids' => $categoryIds];
    }

    private function fallbackSearchDiscountIds(KeywordPage $page, array $categoryIds): array
    {
        $query = Discount::query()->with(['product']);

        if (!empty($categoryIds)) {
            $query->whereHas('product', fn ($q) => $q->whereIn('category_id', $categoryIds));
        }

        $terms = $page->search_terms ?? [];
        if (empty($terms)) {
            return [];
        }

        $query->whereHas('product', function ($q) use ($terms) {
            $q->where(function ($sub) use ($terms) {
                foreach ($terms as $term) {
                    $sub->orWhere('name', 'like', '%' . $term . '%');
                }
            });
        });

        return $query->pluck('discounts.id')->all();
    }

    private function resolveCategoryIds(KeywordPage $page): array
    {
        $slugs = $page->category_slugs ?? [];
        if (empty($slugs)) {
            return [];
        }

        return $this->categoryResolver->resolvePrimaryCategoryTreeIds($slugs);
    }

    private function passesKeywordFilters(Discount $discount, KeywordPage $page): bool
    {
        $productName = mb_strtolower($discount->product?->name ?? '');
        $brand = mb_strtolower($discount->product?->brand ?? '');

        foreach ($page->exclude_terms ?? [] as $exclude) {
            $exclude = mb_strtolower(trim((string) $exclude));
            if ($exclude !== '' && (mb_strpos($productName, $exclude) !== false || mb_strpos($brand, $exclude) !== false)) {
                return false;
            }
        }

        if (!empty($page->category_slugs)) {
            if (!$this->categoryResolver->productMatchesAllowedCategories(
                $discount->product?->category,
                (array) $page->category_slugs,
            )) {
                return false;
            }
        }

        return true;
    }

    private function buildListingMeta(KeywordPage $page, Collection $displayedDiscounts, int $matchingTotal): array
    {
        $freshness = $this->freshnessService->build();
        $validity = $this->freshnessService->getCurrentWeekRange();
        $storeComparison = $this->buildStoreComparisonForPage($page, $matchingTotal);
        $cheapestPrice = $storeComparison['summary_rows'][0]['min_price'] ?? null;
        // Real distinct store count (answer.store_count), not
        // count(summary_rows) — summary_rows/leading_deals are capped at 8
        // for display, which used to under-report "Parduotuvių" on any
        // keyword covering more than 8 stores.
        $stats = $this->buildQuickStats($matchingTotal, $storeComparison['answer']['store_count'] ?? count($storeComparison['summary_rows']), $cheapestPrice);
        $relatedPages = $this->buildRelatedPages($page);
        $leaflets = $this->buildLeafletsForDiscounts($displayedDiscounts);
        $description = strip_tags($page->intro_html ?? '');

        // Newest created_at among the discounts actually fetched for this
        // page — an approximation (the very newest matching discount site-
        // wide could in theory sit outside this fetch), but real data from
        // what's already loaded, not a new query bolted onto the
        // Meilisearch/fallback dual-path search this service already does.
        $newestDiscount = $displayedDiscounts->max('created_at');

        return [
            'type' => 'keyword',
            'keyword_slug' => $page->slug,
            'keyword_title' => $page->title,
            'keyword_emoji' => $page->emoji ?: '🏷️',
            'keyword_brands' => array_values(array_filter((array) ($page->brands ?? []))),
            'keyword_search_terms' => array_values(array_filter(array_map(
                fn ($term) => trim((string) $term),
                (array) ($page->search_terms ?? []),
            ))),
            'keyword_primary_search_term' => $this->resolvePrimarySearchTerm($page),
            'keyword_examples' => $this->buildKeywordExamples($page),
            'keyword_store_keywords' => $this->buildStoreKeywordVariants($page),
            'keyword_categories' => $this->buildKeywordCategories($page),
            'keyword_grammar' => [
                'plural' => $page->grammar_plural ?: mb_strtolower($page->title),
                'genitive' => $page->grammar_genitive ?: mb_strtolower($page->title),
                'dative' => $page->grammar_dative ?: mb_strtolower($page->title),
            ],
            'intro' => [
                'description' => $description,
                // A short, real one-liner (not the admin intro's first
                // sentence, which tends to be generic filler like "Kava –
                // kasdienis daugeliui reikalingas ritualas.") — the per-store
                // "how many, from what price" detail renders as its own chip
                // row below the hero (store_price_chips), not crammed into
                // this paragraph — a dense semicolon-separated sentence for
                // 8 stores read as a wall of text. Full admin text still
                // renders in full further down as "Apie šias akcijas".
                'short_description' => $this->firstSentence($description),
                'store_price_chips' => $storeComparison['summary_rows'],
                'brand_price_summary' => $storeComparison['brand_summary'],
                'store_price_table' => $storeComparison['store_price_table'],
                'store_keyword_sentence' => $this->buildStoreKeywordSentence($page, $storeComparison['store_price_table']),
                // The direct "kur šiandien pigiausia X" answer — single
                // cheapest offer across every store, shown above the brand
                // list rather than making a reader piece it together from
                // chips/rows themselves.
                'cheapest_answer' => $storeComparison['answer'],
                'total_matching_offers' => $matchingTotal,
                'seo_about' => $page->intro_html,
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'updated_at' => $freshness['updated_at'],
                'freshness_label' => $newestDiscount ? LithuanianDate::relative(Carbon::parse($newestDiscount)) : null,
                'quick_stats' => $stats,
            ],
            'tips' => $page->tips ?? [],
            'related_pages' => $relatedPages,
            'popular_carousel_title' => 'TOP pasiūlymai pagal nuolaidą',
            'sections' => [
                'top_deals' => [],
                'category_stats' => [
                    'title' => $page->title . ' akcijų statistika',
                    'summary' => $this->buildStatsSummary($page, $displayedDiscounts, $matchingTotal),
                    'highlights' => array_slice($stats, 0, 4),
                    'store_comparison' => $storeComparison['leading_deals'],
                    'top_discounted_products' => [],
                    'updated_at' => Carbon::now()->format('Y-m-d'),
                ],
                'leaflets' => $leaflets,
                'faq' => $page->faq ?? [],
            ],
        ];
    }

    /**
     * "Susijusios akcijos" cross-link anchor text uses grammar_dative (e.g.
     * "varškei", "kavai") — stored lowercase since it's normally used
     * mid-sentence, so a standalone chip label needs its first letter
     * capitalized. mb_* to not mangle multi-byte Lithuanian diacritics.
     */
    private function capitalizeFirst(string $label): string
    {
        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    private function firstSentence(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return preg_split('/(?<=[.!?])\s+/u', $text, 2)[0];
    }

    /**
     * The standard "Aktyvios akcijos" / "Parduotuvių" pair — same stat set
     * as the category page's hero (see ListingPageMetaService::buildForCategory()),
     * kept simple since the store_price_chips row already carries the
     * per-store detail.
     */
    private function buildQuickStats(int $matchingTotal, int $storeCount, ?float $cheapestPrice): array
    {
        if ($matchingTotal <= 0) {
            return [];
        }

        $stats = [
            ['label' => 'Aktyvūs pasiūlymai', 'value' => (string) $matchingTotal],
            ['label' => 'Parduotuvių', 'value' => (string) $storeCount],
        ];

        if ($cheapestPrice !== null && $cheapestPrice > 0) {
            // Label comes BEFORE the value here ("Kaina nuo 0,33 €"), unlike
            // the other two stats above (value then label, "155 aktyvūs
            // pasiūlymai") — 'label_first' tells the view to flip the order
            // for this one instead of misreading as "0,33 € kaina nuo".
            $stats[] = ['label' => 'Kaina nuo', 'value' => number_format($cheapestPrice, 2, ',', ' ') . ' €', 'label_first' => true];
        }

        return $stats;
    }

    private function mapTopDealsFromDiscounts(Collection $discounts): array
    {
        return $discounts
            ->map(function (Discount $discount) {
                $categorySlug = $discount->product->category?->slug;
                $href = $categorySlug
                    ? "/akcijos/{$categorySlug}/{$discount->product->slug}"
                    : '/akcijos';

                return [
                    'name' => $discount->product->name,
                    'href' => $href,
                    'product_id' => $discount->product->id,
                    'discount_percent' => (int) round($discount->discount_percent ?? 0),
                    'discounted_price' => (float) $discount->discounted_price,
                    'original_price' => (float) $discount->original_price,
                    'unit_price' => null,
                    'image_url' => $discount->product->image_url,
                    'category_slug' => $categorySlug,
                    'valid_to' => $discount->end_at ? $discount->end_at->format('Y-m-d') : '',
                    'store_name' => $discount->store->name,
                    'store_slug' => $discount->store->slug,
                    'card' => (bool) $discount->card,
                    'condition' => $discount->condition,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The cheapest matching discount per store, one real <x-deal-card>-shaped
     * item each — no separate "compare" section/card anymore (removed per
     * explicit product decision); these are meant to lead the main results
     * grid instead, same card as every other product in it.
     *
     * @return array{leading_deals: array, summary_rows: array, brand_summary: array, store_price_table: array}
     */
    private function buildStoreComparisonForPage(KeywordPage $page, int $matchingTotal, int $limit = 8): array
    {
        $discounts = $this->collectMatchingDiscountsForComparison($page, $matchingTotal);

        if ($discounts->isEmpty()) {
            return ['leading_deals' => [], 'summary_rows' => [], 'brand_summary' => [], 'store_price_table' => [], 'answer' => null];
        }

        $cheapestPerStoreAll = $discounts->groupBy('store_id')
            ->map(function (Collection $storeDiscounts) {
                $priced = $storeDiscounts->filter(fn (Discount $discount) => (float) $discount->discounted_price > 0);
                $cheapest = $priced->isNotEmpty() ? $priced->sortBy('discounted_price')->first() : null;

                return $cheapest ? ['discount' => $cheapest, 'offers_count' => $storeDiscounts->count()] : null;
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['discount']->discounted_price)
            ->values();

        $cheapestPerStore = $cheapestPerStoreAll->take($limit);
        $best = $cheapestPerStoreAll->first();

        return [
            'leading_deals' => $cheapestPerStore
                ->map(fn (array $row) => $this->formatter->formatListDiscount($row['discount']))
                ->all(),
            // Per-store "how many, from what price" — used by the hero
            // summary sentence (buildKeywordSummary()), not the grid.
            'summary_rows' => $cheapestPerStore
                ->map(fn (array $row) => [
                    'name' => $row['discount']->store->name,
                    'offers_count' => $row['offers_count'],
                    'min_price' => (float) $row['discount']->discounted_price,
                ])
                ->all(),
            'brand_summary' => $this->buildBrandSummary($discounts),
            'store_price_table' => $this->buildStorePriceTable($discounts),
            // The single cheapest offer across every store — the direct
            // "kur šiandien pigiausia X" answer, shown above everything else.
            // Also carries this week's biggest single discount (same
            // $discounts already fetched here, just aggregated differently —
            // no extra query) so the answer sentence can name a second real,
            // week-fresh fact instead of stopping at the cheapest price.
            'answer' => $best ? (function () use ($best, $cheapestPerStoreAll, $discounts) {
                $formatted = $this->formatter->formatListDiscount($best['discount']);
                $priced = $discounts->filter(fn (Discount $d) => (float) $d->discount_percent > 0);
                $maxDiscountRow = $priced->isNotEmpty() ? $priced->sortByDesc('discount_percent')->first() : null;

                return [
                    'product_name' => $best['discount']->product->name,
                    'product_image_url' => $formatted['product']['image_url'],
                    'product_href' => '/akcijos/' . $formatted['product']['full_slug'],
                    'price' => (float) $best['discount']->discounted_price,
                    'store_name' => $best['discount']->store->name,
                    'store_slug' => $best['discount']->store->slug,
                    'store_offers_count' => $best['offers_count'],
                    'store_count' => $cheapestPerStoreAll->count(),
                    'max_discount_percent' => $maxDiscountRow ? (int) round($maxDiscountRow->discount_percent) : null,
                    'max_discount_product' => $maxDiscountRow?->product->name,
                    'max_discount_store' => $maxDiscountRow?->store->name,
                ];
            })() : null,
        ];
    }

    /**
     * Per-brand "cheapest right now" — same $discounts already fetched for
     * the store comparison above, just grouped a different way. Per explicit
     * product decision: comparing two different stores' "from" price is
     * comparing two different products (different pack size/variant), which
     * reads as a real price comparison but isn't one — grouping by the SAME
     * brand across stores is the one comparison that's actually fair.
     * Grouped case-insensitively (product.brand has real duplicates in the
     * data — "JACOBS" and "Jacobs" as distinct stored strings for the same
     * brand) and sorted by offer count, not price, so the list leads with
     * brands people actually buy, not whichever has one lone cheap outlier.
     *
     * @return list<array{brand: string, offers_count: int, min_price: float, store_name: string, store_slug: string, product_name: string, product_image_url: ?string, product_href: string, discount_percent: ?float, valid_to: ?string}>
     */
    private function buildBrandSummary(Collection $discounts): array
    {
        return $discounts
            ->filter(fn (Discount $discount) => (float) $discount->discounted_price > 0
                && trim($discount->product->brand ?? '') !== '')
            ->groupBy(fn (Discount $discount) => mb_strtoupper(trim($discount->product->brand)))
            ->map(function (Collection $brandDiscounts) {
                $cheapest = $brandDiscounts->sortBy('discounted_price')->first();
                // Reuses the same product/image/discount formatting every
                // deal card on the site already uses, instead of resolving
                // the product image URL a second, bespoke way here.
                $formatted = $this->formatter->formatListDiscount($cheapest);

                return [
                    // Title-cased for display, not whichever raw casing the
                    // cheapest row happened to be scraped with — the same
                    // brand shows up as both "JACOBS" and "Jacobs" in the
                    // data, and showing that inconsistency verbatim next to
                    // 7 other brands looks like a bug, not real content.
                    'brand' => mb_convert_case(mb_strtolower(trim($cheapest->product->brand)), MB_CASE_TITLE, 'UTF-8'),
                    'offers_count' => $brandDiscounts->count(),
                    'min_price' => (float) $cheapest->discounted_price,
                    'store_name' => $cheapest->store->name,
                    'store_slug' => $cheapest->store->slug,
                    'product_name' => $formatted['product']['name'],
                    'product_image_url' => $formatted['product']['image_url'],
                    'product_href' => '/akcijos/' . $formatted['product']['full_slug'],
                    'discount_percent' => $formatted['discount_percent'],
                    'valid_to' => $formatted['to_date'],
                ];
            })
            ->sortByDesc('offers_count')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Same $discounts as buildBrandSummary(), grouped by STORE instead of
     * brand and ordered by store priority instead of offer count — the
     * per-brand table's guarantee is "top 8 brands", not "every main store",
     * so a main store whose cheapest match happens to have no brand or a
     * low-volume brand can silently vanish from it. This table guarantees a
     * row for every PRIORITY_STORE_NAMES entry that has any match at all,
     * still shows the real brand/product per row, and lists any remaining
     * (non-priority) stores afterwards, cheapest first.
     *
     * @return list<array{store_name: string, store_slug: string, offers_count: int, min_price: float, brand: ?string, product_name: string, product_image_url: ?string, product_href: string}>
     */
    private function buildStorePriceTable(Collection $discounts): array
    {
        $priorityRank = array_flip(self::PRIORITY_STORE_NAMES);

        $rows = $discounts
            ->filter(fn (Discount $discount) => (float) $discount->discounted_price > 0)
            ->groupBy(fn (Discount $discount) => $discount->store->name)
            ->map(function (Collection $storeDiscounts, string $storeName) {
                $cheapest = $storeDiscounts->sortBy('discounted_price')->first();
                $formatted = $this->formatter->formatListDiscount($cheapest);
                $brand = trim($cheapest->product->brand ?? '');

                return [
                    'store_name' => $storeName,
                    'store_slug' => $cheapest->store->slug,
                    'offers_count' => $storeDiscounts->count(),
                    'min_price' => (float) $cheapest->discounted_price,
                    'brand' => $brand !== '' ? mb_convert_case(mb_strtolower($brand), MB_CASE_TITLE, 'UTF-8') : null,
                    'product_name' => $formatted['product']['name'],
                    'product_image_url' => $formatted['product']['image_url'],
                    'product_href' => '/akcijos/' . $formatted['product']['full_slug'],
                ];
            })
            ->values();

        return $rows
            ->sort(function (array $a, array $b) use ($priorityRank) {
                $rankA = $priorityRank[$a['store_name']] ?? count($priorityRank);
                $rankB = $priorityRank[$b['store_name']] ?? count($priorityRank);

                return $rankA <=> $rankB ?: $a['min_price'] <=> $b['min_price'];
            })
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * One real flowing sentence naming each priority store next to the
     * keyword + "akcija" + a real price — the literal "{store} + keyword +
     * akcija" phrase pairing nothing else on the page produces in visible
     * text, meant to give this page a shot at store+keyword long-tail
     * queries (e.g. "norfa kava akcija") the way the brand table/chips alone
     * don't (a search-suggestion chip list isn't a real sentence Google can
     * quote back).
     */
    private function buildStoreKeywordSentence(KeywordPage $page, array $storePriceTable): string
    {
        $keywordLower = mb_strtolower(trim($page->title));
        $parts = [];

        foreach ($storePriceTable as $row) {
            if (!in_array($row['store_name'], self::PRIORITY_STORE_NAMES, true)) {
                continue;
            }

            $label = $row['brand'] ? $row['brand'] . ' ' . $keywordLower : $keywordLower;
            $parts[] = sprintf(
                '%s — %s kaina nuo %s €',
                $row['store_name'],
                $label,
                number_format($row['min_price'], 2, ',', ' ')
            );
        }

        if ($parts === []) {
            return '';
        }

        $genitive = $page->grammar_genitive ?: $keywordLower;

        return ucfirst($genitive) . ' akcijos šiuo metu galioja pagrindinėse parduotuvėse: '
            . implode('; ', $parts) . '. Palyginkite ir rinkitės pigiausią variantą.';
    }

    private function collectMatchingDiscountsForComparison(KeywordPage $page, int $matchingTotal): Collection
    {
        $fetchLimit = min($matchingTotal, self::STORE_COMPARISON_FETCH_LIMIT);
        if ($fetchLimit <= 0) {
            return collect();
        }

        $query = $this->buildSearchQuery($page);
        if ($query === '') {
            return $this->fallbackCollectAllMatchingDiscounts($page, $fetchLimit);
        }

        try {
            $results = $this->meilisearchService->search(
                $query,
                $this->buildMeilisearchFilters($this->resolveCategoryIds($page)),
                [],
                1,
                $fetchLimit,
            );
        } catch (\Exception $e) {
            \Log::warning('Keyword page store comparison Meilisearch search failed', [
                'slug' => $page->slug,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackCollectAllMatchingDiscounts($page, $fetchLimit);
        }

        $discountIds = collect($results['hits'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        if (empty($discountIds)) {
            return collect();
        }

        return Discount::query()
            ->with(['product.category', 'store'])
            ->whereIn('id', $discountIds)
            ->get()
            ->filter(fn (Discount $discount) => $this->passesKeywordFilters($discount, $page))
            ->values();
    }

    private function fallbackCollectAllMatchingDiscounts(KeywordPage $page, int $limit): Collection
    {
        $categoryIds = $this->resolveCategoryIds($page);
        $discountIds = $this->fallbackSearchDiscountIds($page, $categoryIds);

        if (empty($discountIds)) {
            return collect();
        }

        return Discount::query()
            ->with(['product.category', 'store'])
            ->whereIn('id', $discountIds)
            ->get()
            ->filter(fn (Discount $discount) => $this->passesKeywordFilters($discount, $page))
            ->take($limit)
            ->values();
    }

    private function buildRelatedPages(KeywordPage $page): array
    {
        $slugs = $page->related_slugs ?? [];

        if (!empty($slugs)) {
            return KeywordPage::query()
                ->published()
                ->whereIn('slug', $slugs)
                ->get()
                ->map(fn (KeywordPage $related) => [
                    // grammar_dative (e.g. "varškei", "kavai") reads better as
                    // a standalone chip label than the h1-minus-"akcija"
                    // approach used before ("Bulvės akcija" -> "Bulvės",
                    // wrong case for some declensions) — every published page
                    // has this field populated.
                    'label' => $this->capitalizeFirst($related->grammar_dative ?: $related->title),
                    'href' => "/akcijos/{$related->slug}",
                    'emoji' => $related->emoji,
                ])
                ->values()
                ->all();
        }

        // Fallback: related_slugs is empty on almost every keyword page (21
        // of 240 published as of 2026-09-13) — auto-derive siblings from
        // shared category_slugs instead of leaving this section empty.
        // Every coffee/tea page (kava, malta-kava, tirpi-kava, kavos-kapsules,
        // arbata...) already shares "gerimai-kava-arbata", so this alone
        // closes most of the cross-linking gap without curating 240 rows by
        // hand. Real <a> links, not the nofollow search-term chips this
        // replaced — every target here is its own indexable page.
        $categorySlugs = (array) ($page->category_slugs ?? []);
        if (empty($categorySlugs)) {
            return [];
        }

        return KeywordPage::query()
            ->published()
            ->where('id', '!=', $page->id)
            ->where(function ($query) use ($categorySlugs) {
                foreach ($categorySlugs as $slug) {
                    $query->orWhereJsonContains('category_slugs', $slug);
                }
            })
            ->orderByDesc('matching_offers_count')
            ->limit(8)
            ->get(['slug', 'title', 'grammar_dative', 'emoji'])
            ->map(fn (KeywordPage $related) => [
                'label' => $this->capitalizeFirst($related->grammar_dative ?: $related->title),
                'href' => "/akcijos/{$related->slug}",
                'emoji' => $related->emoji,
            ])
            ->values()
            ->all();
    }

    private function buildLeafletsForDiscounts(Collection $discounts): array
    {
        $storeIds = $discounts->pluck('store_id')->unique()->filter()->values()->all();
        if (empty($storeIds)) {
            return [];
        }

        $leaflets = [];

        foreach (Store::whereIn('id', $storeIds)->get() as $store) {
            $storeLeaflets = $store->flyers()
                ->active()
                ->ready()
                ->withCount('pages')
                ->ordered()
                ->limit(1)
                ->get();

            foreach ($storeLeaflets as $flyer) {
                $leaflet = $this->flyerTitleBuilder->toListingArray($flyer, $store);
                if (($leaflet['image_url'] || $leaflet['pdf_url']) && ($leaflet['pages_count'] > 0 || $leaflet['image_url'])) {
                    $leaflets[] = $leaflet;
                }
            }
        }

        return array_slice($leaflets, 0, 6);
    }

    private function buildWeeklyHighlight(array $topDeals, string $title): string
    {
        if (count($topDeals) < 2) {
            return '';
        }

        $first = $topDeals[0];
        $second = $topDeals[1];

        return sprintf(
            'Šią savaitę ryškiausios %s nuolaidos: %s su –%d%% už %s € ir %s (%s).',
            mb_strtolower($title),
            $first['name'],
            $first['discount_percent'],
            number_format($first['discounted_price'], 2, ',', ' '),
            $second['name'],
            $second['store_name'] ?? ''
        );
    }

    private function buildStatsSummary(KeywordPage $page, Collection $discounts, int $matchingTotal): string
    {
        $storeCount = $discounts->pluck('store_id')->unique()->count();

        return "Šiuo metu stebime {$matchingTotal} aktyvių " . mb_strtolower($page->title) . " akcijų {$storeCount} prekybos tinkluose.";
    }

    /**
     * @return list<string>
     */
    private function buildStoreKeywordVariants(KeywordPage $page): array
    {
        $stores = ['Maxima', 'Norfa', 'Rimi', 'Lidl', 'Iki'];
        $base = trim((string) (($page->primary_keywords ?? [])[0] ?? ''));

        if ($base === '') {
            $base = mb_strtolower(trim($page->h1 ?: $page->title)) . ' akcija';
        }

        $preferredStores = array_slice($stores, 0, 2);

        return array_map(fn ($store) => $base . ' ' . $store, $preferredStores);
    }

    /**
     * @return list<string>
     */
    private function buildKeywordExamples(KeywordPage $page): array
    {
        $examples = [];

        foreach ((array) ($page->primary_keywords ?? []) as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword !== '' && !in_array($keyword, $examples, true)) {
                $examples[] = $keyword;
            }
        }

        foreach ((array) ($page->secondary_keywords ?? []) as $row) {
            $keyword = trim((string) ($row['keyword'] ?? ''));
            if ($keyword !== '' && !in_array($keyword, $examples, true)) {
                $examples[] = $keyword;
            }
            if (count($examples) >= 5) {
                break;
            }
        }

        return array_slice($examples, 0, 5);
    }

    /**
     * @return list<array{name: string, href: string}>
     */
    private function buildKeywordCategories(KeywordPage $page): array
    {
        $slugs = $this->categoryResolver->resolveListingCategorySlugs(
            (array) ($page->category_slugs ?? []),
        );
        if ($slugs === []) {
            return [];
        }

        return collect($slugs)
            ->map(function (string $slug) {
                $category = Category::query()
                    ->where('slug', $slug)
                    ->first(['slug', 'name']);

                if (!$category) {
                    return null;
                }

                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'href' => '/akcijos/' . $category->slug,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveStoreNameFromDeal(array $deal): string
    {
        return $deal['store_name'] ?? '';
    }

    private function buildBreadcrumbs(KeywordPage $page): array
    {
        $breadcrumbs = [
            [
                'name' => 'Akcijos',
                'slug' => '/',
                'type' => 'home',
            ],
        ];

        $category = $this->resolveBreadcrumbCategory($page);

        if ($category) {
            $breadcrumbs[] = [
                'name' => $category->name,
                'slug' => 'akcijos/' . $category->slug,
                'type' => 'category',
            ];
        } else {
            $breadcrumbs[] = [
                'name' => 'Akcijos pagal produktą',
                'slug' => 'akcijos',
                'type' => 'all_discounts',
            ];
        }

        $breadcrumbs[] = [
            'name' => $this->dynamicMetaService->heading($page),
            'slug' => 'akcijos/' . $page->slug,
            'type' => 'keyword',
        ];

        return $breadcrumbs;
    }

    private function resolveBreadcrumbCategory(KeywordPage $page): ?Category
    {
        $slugs = $this->categoryResolver->resolveListingCategorySlugs(
            (array) ($page->category_slugs ?? []),
        );

        if ($slugs === []) {
            return null;
        }

        return Category::query()
            ->where('slug', $slugs[0])
            ->first(['slug', 'name']);
    }

    private function buildSeo(KeywordPage $page, Collection $discounts, int $matchingTotal): array
    {
        return $this->dynamicMetaService->build($page, $discounts, $matchingTotal);
    }
}
