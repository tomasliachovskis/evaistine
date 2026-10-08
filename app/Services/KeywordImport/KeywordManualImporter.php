<?php

namespace App\Services\KeywordImport;

use App\Models\Category;
use App\Models\KeywordPage;
use App\Services\KeywordPageCategoryResolver;
use App\Services\KeywordPageProductMapper;
use App\Services\KeywordPageService;
use App\Models\Discount;
use App\Support\CacheVersion;
use Illuminate\Support\Str;

class KeywordManualImporter
{
    // A new keyword page is published only with enough offers to compare.
    // Only the publish decision: once live, a page shows its products from
    // the first offer (min_active_offers = 1) and never 404s.
    private const MIN_OFFERS_TO_PUBLISH = 10;

    // ...and only when at least this many pharmacies sell it.
    private const MIN_PHARMACIES = 3;

    public function __construct(
        private KeywordGroupAnalyzer $analyzer,
        private KeywordPageGptService $gpt,
        private KeywordPageService $keywordPageService,
        private KeywordPageCategoryResolver $categoryResolver,
        private KeywordPageProductMapper $mapper,
    ) {
    }

    /**
     * @param  list<array{title: string, slug?: string, category_slugs?: list<string>, keywords?: list<string>}>  $entries
     * @return array{imported: list<array>, skipped: list<array>, failed: list<array>, stats: array}
     */
    public function run(
        array $entries,
        bool $apply = false,
        bool $useGpt = true,
        bool $force = false,
        ?string $onlySlug = null,
        ?callable $onProgress = null,
    ): array {
        $categories = $this->listingCategories();
        $existingSlugs = KeywordPage::query()->pluck('slug')->all();
        $existingSlugSet = array_flip($existingSlugs);

        $imported = [];
        $skipped = [];
        $failed = [];
        $sortOrder = $this->nextSortOrder();

        foreach ($entries as $entry) {
            $title = trim((string) ($entry['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $slug = trim((string) ($entry['slug'] ?? '')) ?: Str::slug($title, language: 'lt');
            if ($slug === '') {
                $skipped[] = ['title' => $title, 'slug' => '', 'reason' => 'empty slug'];
                continue;
            }

            if ($onlySlug !== null && $slug !== $onlySlug) {
                continue;
            }

            if ($onProgress) {
                $onProgress(['title' => $title, 'slug' => $slug]);
            }

            if (!$force && isset($existingSlugSet[$slug])) {
                $skipped[] = ['title' => $title, 'slug' => $slug, 'reason' => 'already exists'];
                continue;
            }

            $categoryHint = $entry['category_slugs'] ?? [];
            $group = $this->buildSyntheticGroup($title, $slug, (array) ($entry['keywords'] ?? []));
            $analysis = $this->analyzer->analyze($group);

            if (empty($analysis['primary_keywords'])) {
                $skipped[] = ['title' => $title, 'slug' => $slug, 'reason' => 'no primary keywords'];
                continue;
            }

            if (!$useGpt) {
                $skipped[] = [
                    'title' => $title,
                    'slug' => $slug,
                    'reason' => 'dry-run',
                    'primary' => implode(' | ', $analysis['primary_keywords']),
                ];
                continue;
            }

            $result = $this->gpt->generateContent($group, $analysis, $categories);

            if ($result === null) {
                $failed[] = ['title' => $title, 'slug' => $slug, 'reason' => 'GPT API error'];
                continue;
            }

            if (!empty($result['skip'])) {
                $skipped[] = [
                    'title' => $title,
                    'slug' => $slug,
                    'reason' => $result['skip_reason'] ?? 'GPT skip',
                ];
                continue;
            }

            $pageData = array_merge($result['page'], [
                'slug' => $slug,
                'h1' => $analysis['h1'],
                'primary_keywords' => $analysis['primary_keywords'],
                'secondary_keywords' => $analysis['secondary_keywords'],
                'meta_title' => null,
                'meta_description' => null,
                'sort_order' => $sortOrder,
                // Shown in the footer, category pages and home blocks.
                'is_chip' => true,
                'min_active_offers' => 1,
            ]);
            $pageData['category_slugs'] = $this->resolveCategorySlugs(
                $slug,
                $pageData['category_slugs'] ?? [],
                $categoryHint,
            );
            $sortOrder += 10;

            // Same matching the saved page gets (keyword_page_products), run on
            // the unsaved page so a preview shows real counts too.
            $productIds = array_keys($this->mapper->matchProducts(new KeywordPage($pageData)));
            $activeOffers = Discount::query()
                ->whereIn('product_id', $productIds)
                ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay()));
            $matchCount = (clone $activeOffers)->count();
            $storeCount = (clone $activeOffers)->distinct()->count('store_id');
            $displayedCount = $matchCount;
            $enoughOffers = $matchCount >= self::MIN_OFFERS_TO_PUBLISH && $storeCount >= self::MIN_PHARMACIES;
            $recommendation = $enoughOffers ? 'PUBLISH' : 'KEEP UNPUBLISHED';
            // Keyword pages live at /{slug}, next to pharmacies, categories
            // and other routes; a taken slug would never be reachable.
            if ($slugConflict = \App\Rules\FreeTopLevelSlug::conflict($pageData['slug'])) {
                $recommendation = "SKIP (slug taken: {$slugConflict})";
            }

            if ($apply && ! $slugConflict) {
                $persist = $pageData;
                // An import may publish a page but never take a live one
                // offline: a published page stays published (it shows the
                // empty state when offers run out), only a person in
                // Filament unpublishes.
                $persist['is_published'] = $enoughOffers
                    || KeywordPage::where('slug', $persist['slug'])->where('is_published', true)->exists();
                $persist['matching_offers_count'] = $matchCount;
                $persist['displayed_offers_count'] = $displayedCount;
                $persist['offers_counted_at'] = now();
                $saved = KeywordPage::updateOrCreate(['slug' => $persist['slug']], $persist);
                $this->mapper->mapPage($saved);
            }

            $imported[] = [
                'title' => $title,
                'slug' => $slug,
                'h1' => $pageData['h1'],
                'primary' => implode(' | ', $analysis['primary_keywords']),
                'matches' => $matchCount,
                'pharmacies' => $storeCount,
                'recommendation' => $recommendation,
            ];
        }

        if ($apply) {
            CacheVersion::bump('keywords');
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'stats' => [
                'total' => count($entries),
                'imported' => count($imported),
                'skipped' => count($skipped),
                'failed' => count($failed),
                'published' => count(array_filter($imported, fn ($r) => $r['recommendation'] === 'PUBLISH')),
            ],
        ];
    }

    /**
     * Primary keywords are the commercial forms ("{title} kaina", "{title}
     * akcija"); the file's real search variants (Google autocomplete) become
     * secondary keywords, which the GPT step turns into search terms and copy.
     *
     * @param  list<string>  $variants
     * @return array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}
     */
    private function buildSyntheticGroup(string $title, string $slug, array $variants = []): array
    {
        $titleLower = mb_strtolower($title);
        $keywords = [
            ['keyword' => $titleLower . ' kaina', 'volume' => 100, 'category' => 'Product', 'intents' => 'Commercial,Transactional'],
            ['keyword' => $titleLower . ' akcija', 'volume' => 90, 'category' => 'Product', 'intents' => 'Commercial,Transactional'],
            ['keyword' => $titleLower . ' vaistinėje', 'volume' => 80, 'category' => 'Product', 'intents' => 'Commercial'],
        ];

        $seen = array_flip(array_column($keywords, 'keyword'));
        $volume = 50;
        foreach ($variants as $variant) {
            $variant = mb_strtolower(trim((string) $variant));
            if ($variant === '' || isset($seen[$variant])) {
                continue;
            }
            $seen[$variant] = true;
            $keywords[] = ['keyword' => $variant, 'volume' => max(1, $volume--), 'category' => 'Product', 'intents' => 'Commercial'];
        }

        return [
            'term_group' => $slug,
            'slug' => $slug,
            'keywords' => $keywords,
            'total_volume' => array_sum(array_column($keywords, 'volume')),
            'source_groups' => [$slug],
        ];
    }

    /**
     * @param  list<string>  $fromGpt
     * @param  list<string>  $hint
     * @return list<string>
     */
    private function resolveCategorySlugs(string $slug, array $fromGpt, array $hint): array
    {
        $resolvedHint = $this->categoryResolver->resolveListingCategorySlugs($hint);
        if ($resolvedHint !== []) {
            return $resolvedHint;
        }

        $resolved = $this->categoryResolver->resolveListingCategorySlugs($fromGpt);
        if ($resolved !== []) {
            return $resolved;
        }

        return [];
    }

    private function nextSortOrder(): int
    {
        $max = KeywordPage::query()->max('sort_order');

        return $max ? ((int) $max) + 10 : 10;
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function listingCategories(): array
    {
        return Category::query()
            ->where('hide', 0)
            ->whereIn('slug', array_values(config('categories.roots', [])))
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])
            ->values()
            ->all();
    }
}
