<?php

namespace App\Services\KeywordImport;

use App\Models\Category;
use App\Models\KeywordPage;
use App\Services\KeywordPageCategoryResolver;
use App\Services\KeywordPageService;
use App\Support\FoodCategorySlugs;
use Illuminate\Support\Facades\Cache;

class KeywordCsvImporter
{
    public function __construct(
        private KeywordCsvParser $parser,
        private KeywordCsvGrouper $grouper,
        private KeywordGroupAnalyzer $analyzer,
        private KeywordPageGptService $gpt,
        private KeywordPageService $keywordPageService,
        private KeywordPageCategoryResolver $categoryResolver,
    ) {
    }

    /**
     * @return array{imported: list<array>, skipped: list<array>, failed: list<array>, stats: array}
     */
    public function runFromCandidates(
        string $csvPath,
        string $candidatesPath,
        bool $apply = false,
        bool $useGpt = true,
        ?string $onlySlug = null,
        ?array $priorities = null,
        ?callable $onProgress = null,
    ): array {
        $data = json_decode(file_get_contents($candidatesPath), true);
        $candidates = $data['candidates'] ?? [];

        $candidates = array_filter($candidates, function ($c) use ($priorities) {
            if (!str_starts_with($c['page_url'], '/akcijos/')) {
                return false;
            }
            if (!in_array($c['page_type'], ['category', 'brand', 'product'], true)) {
                return false;
            }
            if ($priorities !== null && !in_array($c['priority'], $priorities, true)) {
                return false;
            }

            return true;
        });

        if ($onlySlug !== null) {
            $candidates = array_filter($candidates, fn ($c) => $c['slug'] === $onlySlug);
        }

        $rows = $this->parser->parse($csvPath);
        $rowsByKeyword = [];
        foreach ($rows as $row) {
            $rowsByKeyword[$row['keyword']] = $row;
        }

        $categories = $this->listingCategories();

        $imported = [];
        $skipped = [];
        $failed = [];
        $sortOrder = 10;

        foreach ($candidates as $candidate) {
            $group = $this->buildGroupFromCandidate($candidate, $rowsByKeyword);

            if ($onProgress) {
                $onProgress($group);
            }

            if (empty($group['keywords'])) {
                $skipped[] = [
                    'term_group' => $candidate['slug'],
                    'slug' => $candidate['slug'],
                    'volume' => $candidate['total_volume'],
                    'reason' => 'no matching CSV keywords',
                ];
                continue;
            }

            $analysis = $this->analyzer->analyze($group);

            if (!$useGpt) {
                continue;
            }

            $result = $this->gpt->generateContent($group, $analysis, $categories);

            if ($result === null) {
                $failed[] = ['slug' => $candidate['slug'], 'reason' => 'GPT API error'];
                continue;
            }

            if (!empty($result['skip'])) {
                $skipped[] = [
                    'slug' => $candidate['slug'],
                    'volume' => $candidate['total_volume'],
                    'reason' => $result['skip_reason'] ?? 'GPT skip',
                ];
                continue;
            }

            $pageData = array_merge($result['page'], [
                'slug' => $candidate['slug'],
                'h1' => $analysis['h1'],
                'primary_keywords' => $analysis['primary_keywords'],
                'secondary_keywords' => $analysis['secondary_keywords'],
                'meta_title' => null,
                'meta_description' => null,
                'sort_order' => $sortOrder,
            ]);
            $pageData['category_slugs'] = $this->resolveCategorySlugs(
                $pageData['slug'],
                $pageData['category_slugs'] ?? [],
            );
            $sortOrder += 10;

            $page = new KeywordPage($pageData);
            $matchCount = $this->keywordPageService->countMatchingOffersForPage($page);
            $displayedCount = $this->keywordPageService->countDisplayedOffersForPage($page);
            $recommendation = $matchCount >= $pageData['min_active_offers'] ? 'PUBLISH' : 'KEEP UNPUBLISHED';

            if ($apply) {
                $persist = $pageData;
                $persist['is_published'] = $matchCount >= $persist['min_active_offers'];
                $persist['matching_offers_count'] = $matchCount;
                $persist['displayed_offers_count'] = $displayedCount;
                $persist['offers_counted_at'] = now();
                KeywordPage::updateOrCreate(['slug' => $persist['slug']], $persist);
            }

            $imported[] = [
                'slug' => $pageData['slug'],
                'title' => $pageData['title'],
                'h1' => $pageData['h1'],
                'primary' => implode(' | ', $analysis['primary_keywords']),
                'volume' => $group['total_volume'],
                'page_type' => $candidate['page_type'],
                'priority' => $candidate['priority'],
                'matches' => $matchCount,
                'recommendation' => $recommendation,
            ];
        }

        if ($apply) {
            Cache::forget('keyword_pages_list_v8');
            foreach ($imported as $row) {
                Cache::forget('keyword_page_v5_' . $row['slug']);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'stats' => [
                'candidates' => count($candidates),
                'imported' => count($imported),
                'skipped' => count($skipped),
                'failed' => count($failed),
                'published' => count(array_filter($imported, fn ($r) => $r['recommendation'] === 'PUBLISH')),
            ],
        ];
    }

    /**
     * @param  array<string, array>  $rowsByKeyword
     * @return array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}
     */
    private function buildGroupFromCandidate(array $candidate, array $rowsByKeyword): array
    {
        $keywords = [];

        foreach ($candidate['included_keywords'] as $kw) {
            if (!isset($rowsByKeyword[$kw])) {
                continue;
            }
            $row = $rowsByKeyword[$kw];
            $keywords[] = [
                'keyword' => $row['keyword'],
                'volume' => $row['volume'],
                'category' => $row['category'],
                'intents' => $row['intents'],
            ];
        }

        return [
            'term_group' => $candidate['slug'],
            'slug' => $candidate['slug'],
            'keywords' => $keywords,
            'total_volume' => $candidate['total_volume'],
            'source_groups' => $candidate['term_groups'] ?? [$candidate['slug']],
        ];
    }

    /**
     * @return array{imported: list<array>, skipped: list<array>, failed: list<array>, stats: array}
     */
    public function run(
        string $path,
        bool $apply = false,
        bool $useGpt = true,
        int $minVolume = 0,
        ?string $onlyGroup = null,
        ?callable $onProgress = null,
    ): array {
        $rows = $this->parser->parse($path);
        $groups = $this->grouper->group($rows);

        if ($onlyGroup !== null) {
            $needle = mb_strtolower(trim($onlyGroup));
            $groups = array_filter($groups, function ($g) use ($needle) {
                return $g['slug'] === $needle
                    || mb_strtolower($g['term_group']) === $needle
                    || in_array($needle, array_map('mb_strtolower', $g['source_groups'] ?? []), true);
            });
        }

        if ($minVolume > 0) {
            $groups = array_filter($groups, fn ($g) => $g['total_volume'] >= $minVolume);
        }

        $categories = $this->listingCategories();

        $imported = [];
        $skipped = [];
        $failed = [];
        $sortOrder = 10;

        foreach ($groups as $group) {
            if ($onProgress) {
                $onProgress($group);
            }

            $analysis = $this->analyzer->analyze($group);

            if (empty($analysis['primary_keywords'])) {
                $skipped[] = [
                    'term_group' => $group['term_group'],
                    'slug' => $group['slug'],
                    'volume' => $group['total_volume'],
                    'reason' => 'no keywords',
                ];
                continue;
            }

            if (!$useGpt) {
                $skipped[] = [
                    'term_group' => $group['term_group'],
                    'slug' => $group['slug'],
                    'volume' => $group['total_volume'],
                    'keywords' => count($group['keywords']),
                    'primary' => implode(' | ', $analysis['primary_keywords']),
                    'reason' => 'dry-run (no GPT)',
                ];
                continue;
            }

            $result = $this->gpt->generateContent($group, $analysis, $categories);

            if ($result === null) {
                $failed[] = [
                    'term_group' => $group['term_group'],
                    'slug' => $group['slug'],
                    'volume' => $group['total_volume'],
                    'reason' => 'GPT API error',
                ];
                continue;
            }

            if (!empty($result['skip'])) {
                $skipped[] = [
                    'term_group' => $group['term_group'],
                    'slug' => $group['slug'],
                    'volume' => $group['total_volume'],
                    'keywords' => count($group['keywords']),
                    'reason' => $result['skip_reason'] ?? 'GPT skip',
                ];
                continue;
            }

            $pageData = array_merge($result['page'], [
                'h1' => $analysis['h1'],
                'primary_keywords' => $analysis['primary_keywords'],
                'secondary_keywords' => $analysis['secondary_keywords'],
                'meta_title' => null,
                'meta_description' => null,
                'sort_order' => $sortOrder,
            ]);
            $pageData['category_slugs'] = $this->resolveCategorySlugs(
                $pageData['slug'],
                $pageData['category_slugs'] ?? [],
            );
            $sortOrder += 10;

            $page = new KeywordPage($pageData);
            $matchCount = $this->keywordPageService->countMatchingOffersForPage($page);
            $displayedCount = $this->keywordPageService->countDisplayedOffersForPage($page);
            $recommendation = $matchCount >= $pageData['min_active_offers'] ? 'PUBLISH' : 'KEEP UNPUBLISHED';

            if ($apply) {
                $persist = $pageData;
                $persist['is_published'] = $matchCount >= $persist['min_active_offers'];
                $persist['matching_offers_count'] = $matchCount;
                $persist['displayed_offers_count'] = $displayedCount;
                $persist['offers_counted_at'] = now();
                KeywordPage::updateOrCreate(['slug' => $persist['slug']], $persist);
            }

            $imported[] = [
                'term_group' => $group['term_group'],
                'slug' => $pageData['slug'],
                'title' => $pageData['title'],
                'h1' => $pageData['h1'],
                'primary' => implode(' | ', $analysis['primary_keywords']),
                'volume' => $group['total_volume'],
                'keywords' => count($group['keywords']),
                'sources' => implode(', ', $group['source_groups'] ?? []),
                'matches' => $matchCount,
                'recommendation' => $recommendation,
            ];
        }

        if ($apply) {
            Cache::forget('keyword_pages_list_v8');
            foreach ($imported as $row) {
                Cache::forget('keyword_page_v5_' . $row['slug']);
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'stats' => [
                'csv_rows' => count($rows),
                'groups_total' => count($groups),
                'imported' => count($imported),
                'skipped' => count($skipped),
                'failed' => count($failed),
                'published' => count(array_filter($imported, fn ($r) => $r['recommendation'] === 'PUBLISH')),
            ],
        ];
    }

    /**
     * @param  list<string>  $fromGpt
     * @return list<string>
     */
    private function resolveCategorySlugs(string $slug, array $fromGpt): array
    {
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
