<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\KeywordPage;
use App\Models\Store;
use App\Support\CacheVersion;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class KeywordPageService
{
    private const PER_PAGE = 24;

    private const MAX_LISTING_FETCH = 1000;

    private const MEILISEARCH_FETCH_BUFFER = 50;

    private const STORE_COMPARISON_FETCH_LIMIT = 250;

    private const MIN_CHIP_OFFERS = 3;

    private const HOME_TOP_DEALS_FETCH = 30;

    private const HOME_POOL_MAX_SEARCH_TERMS = 2;

    private const MIN_HOME_POOL_PRICE = 5.0;

    private const EXCLUDED_HOME_POOL_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
    ];

    public function __construct(
        private MeilisearchService $meilisearchService,
        private DiscountResponseFormatter $formatter,
        private PageFreshnessService $freshnessService,
        private StoreFlyerTitleBuilder $flyerTitleBuilder,
        private KeywordPageDynamicMetaService $dynamicMetaService,
        private KeywordPageCategoryResolver $categoryResolver,
        private HomeDealScorer $homeDealScorer,
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
        return KeywordPage::query()
            ->published()
            ->where('is_chip', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['slug', 'title', 'h1', 'emoji', 'matching_offers_count'])
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

    public function fetchTopDiscountsForPage(KeywordPage $page, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $cacheKey = "home_kw_top_deals_{$page->slug}_v3_" . CacheVersion::suffix(['discounts']);

        $discounts = Cache::remember($cacheKey, 7200, function () use ($page) {
            return $this->fetchTopDiscountsForHomePoolFromDatabase($page);
        });

        return $this->homeDealScorer
            ->sortByScore(
                $discounts->filter(fn (Discount $discount) => $this->passesHomePoolFilters($discount, $page))
            )
            ->take($limit)
            ->values();
    }

    private function fetchTopDiscountsForHomePoolFromDatabase(KeywordPage $page): Collection
    {
        $terms = $this->resolveHomePoolSearchTerms($page);

        if ($terms === []) {
            return collect();
        }

        // product.discounts.store and product.discountHistories.store are
        // eager-loaded here for the same reason as HomeDealPoolService::baseQuery()
        // — without them, DiscountResponseFormatter falls back to one query per
        // product to compute offer_count/min_price/history.
        $query = Discount::query()
            ->with(['product.category', 'product.discounts.store', 'product.discountHistories.store', 'store'])
            ->whereNotNull('discounts.discount_percent')
            ->where('discounts.discount_percent', '>', 0)
            ->whereNotNull('discounts.discounted_price')
            ->where('discounts.discounted_price', '>=', self::MIN_HOME_POOL_PRICE)
            ->whereHas('product', function ($productQuery) use ($terms, $page) {
                $this->applyHomePoolCategoryFilter($productQuery, $page);

                $productQuery->where(function ($termQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $termQuery->orWhere('name', 'like', '%' . $term . '%');
                    }
                });
            });

        return $query
            ->orderByDesc('discounts.discount_percent')
            ->limit(self::HOME_TOP_DEALS_FETCH)
            ->get()
            ->filter(fn (Discount $discount) => $this->passesKeywordFilters($discount, $page))
            ->values();
    }

    private function applyHomePoolCategoryFilter($productQuery, KeywordPage $page): void
    {
        $listingSlugs = $this->categoryResolver->resolvePrimaryListingCategorySlugs(
            (array) ($page->category_slugs ?? []),
        );

        if ($listingSlugs === []) {
            $productQuery->whereHas('category', function ($categoryQuery) {
                $categoryQuery->whereNotIn('slug', self::EXCLUDED_HOME_POOL_CATEGORY_SLUGS);
            });

            return;
        }

        $productQuery->whereHas('category', function ($categoryQuery) use ($listingSlugs) {
            $categoryQuery
                ->whereNotIn('slug', self::EXCLUDED_HOME_POOL_CATEGORY_SLUGS)
                ->where(function ($slugQuery) use ($listingSlugs) {
                    $slugQuery
                        ->whereIn('slug', $listingSlugs)
                        ->orWhereIn('parent_id', function ($subQuery) use ($listingSlugs) {
                            $subQuery
                                ->select('id')
                                ->from('categories')
                                ->whereIn('slug', $listingSlugs);
                        });
                });
        });
    }

    /**
     * @return list<string>
     */
    private function resolveHomePoolSearchTerms(KeywordPage $page): array
    {
        $terms = [];
        $slug = trim($page->slug);

        if ($slug !== '') {
            $terms[] = $slug;
        }

        foreach ((array) ($page->search_terms ?? []) as $term) {
            $term = trim((string) $term);
            if ($term === '' || in_array($term, $terms, true)) {
                continue;
            }

            $terms[] = $term;

            if (count($terms) >= self::HOME_POOL_MAX_SEARCH_TERMS) {
                break;
            }
        }

        return $terms;
    }

    public function productMatchesKeywordPage(Discount $discount, KeywordPage $page): bool
    {
        return $this->passesKeywordFilters($discount, $page);
    }

    private function passesHomePoolFilters(Discount $discount, KeywordPage $page): bool
    {
        if (!$this->passesKeywordFilters($discount, $page)) {
            return false;
        }

        if ((float) ($discount->discount_percent ?? 0) <= 0) {
            return false;
        }

        if ((float) ($discount->discounted_price ?? 0) < self::MIN_HOME_POOL_PRICE) {
            return false;
        }

        $categorySlug = $discount->product?->category?->slug;
        if ($categorySlug !== null && in_array($categorySlug, self::EXCLUDED_HOME_POOL_CATEGORY_SLUGS, true)) {
            return false;
        }

        return true;
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
        $stats = $this->buildQuickStats($displayedDiscounts, $matchingTotal);
        $storeComparison = $this->buildStoreComparisonForPage($page, $matchingTotal);
        $relatedPages = $this->buildRelatedPages($page);
        $leaflets = $this->buildLeafletsForDiscounts($displayedDiscounts);

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
                'description' => strip_tags($page->intro_html ?? ''),
                'seo_about' => $page->intro_html,
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'updated_at' => $freshness['updated_at'],
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
                    'store_comparison' => $storeComparison,
                    'top_discounted_products' => [],
                    'updated_at' => Carbon::now()->format('Y-m-d'),
                ],
                'leaflets' => $leaflets,
                'faq' => $page->faq ?? [],
            ],
        ];
    }

    private function buildQuickStats(Collection $discounts, int $matchingTotal): array
    {
        if ($discounts->isEmpty()) {
            return [];
        }

        $lowest = $discounts
            ->filter(fn (Discount $d) => $d->discounted_price > 0)
            ->sortBy('discounted_price')
            ->first();

        $avgDiscount = (int) round($discounts->avg('discount_percent') ?? 0);
        $maxDeal = $discounts->sortByDesc('discount_percent')->first();

        $stats = [
            [
                'label' => 'Žemiausia kaina šią savaitę',
                'value' => $lowest
                    ? number_format($lowest->discounted_price, 2, ',', ' ') . ' €'
                    : '—',
                'sublabel' => $lowest
                    ? ($lowest->product->name . ' – ' . $lowest->store->name)
                    : null,
            ],
            [
                'label' => 'Vidutinė nuolaida šią savaitę',
                'value' => $avgDiscount > 0 ? '~' . $avgDiscount . '%' : '—',
                'sublabel' => 'Remiantis visais šios savaitės pasiūlymais',
            ],
        ];

        if ($maxDeal) {
            $stats[] = [
                'label' => 'Didžiausia nuolaida šią savaitę',
                'value' => '-' . (int) round($maxDeal->discount_percent) . '%',
                'sublabel' => $maxDeal->product->name . ' – ' . $maxDeal->store->name,
            ];
        }

        $stats[] = [
            'label' => 'Aktyvūs pasiūlymai',
            'value' => (string) $matchingTotal,
        ];

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

    private function buildStoreComparisonForPage(KeywordPage $page, int $matchingTotal): array
    {
        $discounts = $this->collectMatchingDiscountsForComparison($page, $matchingTotal);

        if ($discounts->isEmpty()) {
            return [];
        }

        $grouped = $discounts->groupBy('store_id');

        return $grouped
            ->map(function (Collection $storeDiscounts) {
                $store = $storeDiscounts->first()->store;
                $priced = $storeDiscounts->filter(fn (Discount $discount) => (float) $discount->discounted_price > 0);
                $minPrice = $priced->isNotEmpty()
                    ? (float) $priced->min('discounted_price')
                    : null;

                return [
                    'store' => $store->name,
                    'store_slug' => $store->slug,
                    'href' => "/akcijos/{$store->slug}",
                    'offers_count' => $storeDiscounts->count(),
                    'max_discount_percent' => (int) round($storeDiscounts->max('discount_percent') ?? 0),
                    'avg_discount_percent' => (int) round($storeDiscounts->avg('discount_percent') ?? 0),
                    'min_price' => $minPrice,
                ];
            })
            ->sortBy(fn (array $row) => [
                $row['min_price'] === null ? 1 : 0,
                $row['min_price'] ?? 999999,
            ])
            ->values()
            ->take(8)
            ->all();
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
        if (empty($slugs)) {
            return [];
        }

        return KeywordPage::query()
            ->published()
            ->whereIn('slug', $slugs)
            ->get()
            ->map(fn (KeywordPage $related) => [
                'label' => $related->title,
                'href' => "/akcijos/{$related->slug}",
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
