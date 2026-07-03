<?php

namespace App\Services\KeywordImport;

class KeywordCandidateSelector
{
    public function __construct(
        private KeywordCsvParser $parser,
        private KeywordCandidateFilter $filter,
        private KeywordCandidateGrouper $grouper,
    ) {
    }

    /**
     * @return array{candidates: list<array>, rejected: list<array>, stats: array}
     */
    public function select(string $path): array
    {
        $rows = $this->parser->parse($path);
        $accepted = [];
        $rejected = [];

        foreach ($rows as $row) {
            $result = $this->filter->evaluate($row);

            if (!$result['accept']) {
                $rejected[] = [
                    'keyword' => $row['keyword'],
                    'volume' => $row['volume'],
                    'term_group' => $row['term_group'],
                    'reason' => $result['reason'],
                ];
                continue;
            }

            $accepted[] = [
                'row' => $row,
                'analysis' => $result['analysis'],
            ];
        }

        $groups = $this->grouper->group($accepted);
        $candidates = [];
        $seenUrls = [];

        foreach ($groups as $group) {
            $pages = $this->buildPageCandidatesFromGroup($group);

            foreach ($pages as $page) {
                if ($page['total_volume'] < config('keyword-candidates.min_group_volume', 50)
                    && !$page['force']) {
                    foreach ($page['keywords'] as $kw) {
                        $rejected[] = [
                            'keyword' => $kw['keyword'],
                            'volume' => $kw['volume'],
                            'term_group' => implode(', ', $group['term_groups']),
                            'reason' => 'group volume below minimum (' . $page['total_volume'] . ')',
                        ];
                    }
                    continue;
                }

                if (isset($seenUrls[$page['page_url']])) {
                    continue;
                }
                $seenUrls[$page['page_url']] = true;
                $candidates[] = $page;
            }
        }

        usort($candidates, fn ($a, $b) => $b['total_volume'] <=> $a['total_volume']);

        return [
            'candidates' => $candidates,
            'rejected' => $rejected,
            'stats' => [
                'csv_rows' => count($rows),
                'accepted' => count($accepted),
                'rejected' => count($rejected),
                'candidates' => count($candidates),
                'high' => count(array_filter($candidates, fn ($c) => $c['priority'] === 'high')),
                'medium' => count(array_filter($candidates, fn ($c) => $c['priority'] === 'medium')),
                'low' => count(array_filter($candidates, fn ($c) => $c['priority'] === 'low')),
            ],
        ];
    }

    /**
     * @param  array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}  $group
     * @return list<array>
     */
    private function buildPageCandidatesFromGroup(array $group): array
    {
        $default = [];
        $cheapest = [];
        $top = [];

        foreach ($group['keywords'] as $kw) {
            $analysis = $kw['analysis'];
            if ($analysis['intent_cheapest']) {
                $cheapest[] = $kw;
            } elseif ($analysis['intent_top']) {
                $top[] = $kw;
            } else {
                $default[] = $kw;
            }
        }

        $pages = [];

        if ($default !== []) {
            $pages[] = $this->makeCandidate($group, $default, $this->resolveDefaultPageType($group));
        }

        if ($cheapest !== []) {
            $pages[] = $this->makeCandidate($group, $cheapest, 'cheapest', '/kur-pigiausia/' . $group['slug']);
        }

        if ($top !== []) {
            $pages[] = $this->makeCandidate($group, $top, 'top', '/top/pigiausi-' . $group['slug']);
        }

        return $pages;
    }

    /**
     * @param  array{group_key: string, slug: string, term_groups: list<string>}  $group
     * @param  list<array>  $keywords
     */
    private function makeCandidate(array $group, array $keywords, string $pageType, ?string $url = null): array
    {
        $keywords = $this->dedupeKeywords($keywords);
        usort($keywords, fn ($a, $b) => $b['volume'] <=> $a['volume']);
        $totalVolume = array_sum(array_column($keywords, 'volume'));
        $mainKeyword = $keywords[0]['keyword'];
        $pageUrl = $url ?? '/akcijos/' . $group['slug'];

        $priority = $this->resolvePriority($totalVolume, $pageType);
        $reason = $this->buildReason($group, $pageType, $totalVolume);

        $brandMin = config('keyword-candidates.brand_min_volume', 100);
        $force = str_starts_with($group['group_key'], 'brand:') && $totalVolume >= $brandMin;

        return [
            'page_url' => $pageUrl,
            'page_type' => $pageType,
            'main_keyword' => $mainKeyword,
            'slug' => $group['slug'],
            'total_volume' => $totalVolume,
            'keyword_count' => count($keywords),
            'included_keywords' => array_map(fn ($k) => $k['keyword'], $keywords),
            'priority' => $priority,
            'reason' => $reason,
            'term_groups' => $group['term_groups'],
            'force' => $force || $totalVolume >= config('keyword-candidates.priority.high', 300),
            'keywords' => $keywords,
        ];
    }

    /**
     * @param  array{group_key: string, slug: string}  $group
     */
    private function resolveDefaultPageType(array $group): string
    {
        if (str_starts_with($group['group_key'], 'brand:')) {
            return 'brand';
        }

        if (str_contains($group['slug'], '-') && mb_strlen($group['slug']) > 12) {
            return 'product';
        }

        return 'category';
    }

    private function resolvePriority(int $volume, string $pageType): string
    {
        $high = config('keyword-candidates.priority.high', 300);
        $medium = config('keyword-candidates.priority.medium', 100);

        if ($volume >= $high || ($pageType === 'brand' && $volume >= config('keyword-candidates.brand_min_volume', 100))) {
            return 'high';
        }

        if ($volume >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  list<array>  $keywords
     * @return list<array>
     */
    private function dedupeKeywords(array $keywords): array
    {
        $seen = [];
        $unique = [];

        foreach ($keywords as $kw) {
            if (isset($seen[$kw['keyword']])) {
                continue;
            }
            $seen[$kw['keyword']] = true;
            $unique[] = $kw;
        }

        return $unique;
    }

    /**
     * @param  array{term_groups: list<string>, slug: string}  $group
     */
    private function buildReason(array $group, string $pageType, int $volume): string
    {
        $sources = implode(', ', $group['term_groups']) ?: $group['slug'];

        return match ($pageType) {
            'brand' => "Brand term group ({$sources}), volume {$volume}",
            'cheapest' => "„Kur pigiausia“ intent, subject {$group['slug']}, volume {$volume}",
            'top' => "Top/pigiausi intent, subject {$group['slug']}, volume {$volume}",
            'product' => "Specific product group ({$sources}), volume {$volume}",
            default => "Category/product group ({$sources}), volume {$volume}",
        };
    }
}
