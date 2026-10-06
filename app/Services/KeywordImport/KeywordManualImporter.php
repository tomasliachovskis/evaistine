<?php

namespace App\Services\KeywordImport;

use App\Models\Category;
use App\Models\KeywordPage;
use App\Services\KeywordPageCategoryResolver;
use App\Services\KeywordPageService;
use App\Support\CacheVersion;
use App\Support\FoodCategorySlugs;
use Illuminate\Support\Str;

class KeywordManualImporter
{
    public function __construct(
        private KeywordGroupAnalyzer $analyzer,
        private KeywordPageGptService $gpt,
        private KeywordPageService $keywordPageService,
        private KeywordPageCategoryResolver $categoryResolver,
    ) {
    }

    /**
     * @param  list<array{title: string, category_slugs?: list<string>}>  $entries
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

            $slug = Str::slug($title, language: 'lt');
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
            $group = $this->buildSyntheticGroup($title, $slug);
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
            ]);
            $pageData['category_slugs'] = $this->resolveCategorySlugs(
                $slug,
                $pageData['category_slugs'] ?? [],
                $categoryHint,
            );
            $sortOrder += 10;

            $page = new KeywordPage($pageData);
            $matchCount = $this->keywordPageService->countMatchingOffersForPage($page);
            $displayedCount = $this->keywordPageService->countDisplayedOffersForPage($page);
            $recommendation = $matchCount >= $pageData['min_active_offers'] ? 'PUBLISH' : 'KEEP UNPUBLISHED';
            // Keyword pages live at /{slug}, next to pharmacies, categories
            // and other routes; a taken slug would never be reachable.
            if ($slugConflict = \App\Rules\FreeTopLevelSlug::conflict($pageData['slug'])) {
                $recommendation = "SKIP (slug taken: {$slugConflict})";
            }

            if ($apply && ! $slugConflict) {
                $persist = $pageData;
                $persist['is_published'] = $matchCount >= $persist['min_active_offers'];
                $persist['matching_offers_count'] = $matchCount;
                $persist['displayed_offers_count'] = $displayedCount;
                $persist['offers_counted_at'] = now();
                KeywordPage::updateOrCreate(['slug' => $persist['slug']], $persist);
            }

            $imported[] = [
                'title' => $title,
                'slug' => $slug,
                'h1' => $pageData['h1'],
                'primary' => implode(' | ', $analysis['primary_keywords']),
                'matches' => $matchCount,
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
     * @return array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}
     */
    private function buildSyntheticGroup(string $title, string $slug): array
    {
        $titleLower = mb_strtolower($title);
        $keywords = [
            [
                'keyword' => $titleLower . ' akcija',
                'volume' => 100,
                'category' => 'Product',
                'intents' => 'Commercial,Transactional',
            ],
            [
                'keyword' => 'pigiausi ' . $titleLower,
                'volume' => 50,
                'category' => 'Product',
                'intents' => 'Commercial',
            ],
            [
                'keyword' => $titleLower . ' nuolaida',
                'volume' => 30,
                'category' => 'Product',
                'intents' => 'Commercial,Transactional',
            ],
        ];

        return [
            'term_group' => $slug,
            'slug' => $slug,
            'keywords' => $keywords,
            'total_volume' => 180,
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

        return match ($slug) {
            'kava' => ['gerimai-kava-arbata'],
            'skalbimo' => ['buitine-chemija-valymo-priemones'],
            default => [],
        };
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
            ->whereIn('slug', FoodCategorySlugs::ALL)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])
            ->values()
            ->all();
    }
}
