<?php

namespace App\Services\KeywordImport;

class KeywordCsvGroupRouter
{
    private const MERGE_INTO = [
        'kavos' => 'kava',
        'kavai' => 'kava',
        'pupeles' => 'kava',
    ];

    private const COFFEE_KEYWORD_NEEDLES = [
        'kav', 'nespresso', 'dolce', 'lavazza', 'gusto', 'aroma gold', 'jacob', 'paulig', 'dallmayr',
    ];

    private const LAUNDRY_KEYWORD_NEEDLES = [
        'skalb', 'indaplov', 'ariel', 'fairy', 'pelyno', 'persil', 'lenor',
    ];

    /**
     * @param  array<string, array{term_group: string, slug: string, keywords: list<array>, total_volume: int}>  $groups
     * @return array<string, array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}>
     */
    public function route(array $groups): array
    {
        $result = [];

        foreach ($groups as $group) {
            $slug = $group['slug'];

            if ($slug === 'kapsules') {
                $this->splitKapsulesGroup($group, $result);
                continue;
            }

            $targetSlug = $this->mergeMap()[$slug] ?? $slug;
            $this->mergeGroup($result, $targetSlug, $group);
        }

        foreach ($result as &$bucket) {
            $bucket['total_volume'] = array_sum(array_column($bucket['keywords'], 'volume'));
            usort($bucket['keywords'], fn ($a, $b) => $b['volume'] <=> $a['volume']);
        }
        unset($bucket);

        uasort($result, fn ($a, $b) => $b['total_volume'] <=> $a['total_volume']);

        return $result;
    }

    /**
     * @param  array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>  $buckets
     * @return array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>
     */
    public function routeCandidates(array $buckets): array
    {
        $intermediate = [];

        foreach ($buckets as $bucket) {
            $slug = $bucket['slug'];

            if ($slug === 'kapsules') {
                foreach ($bucket['keywords'] as $row) {
                    $target = $this->resolveKapsulesTarget($row);
                    if ($target === null) {
                        continue;
                    }
                    $this->mergeCandidateBucket($intermediate, $target, $bucket, [$row]);
                }
                continue;
            }

            $targetSlug = $this->mergeMap()[$slug] ?? $slug;
            $this->mergeCandidateBucket($intermediate, $targetSlug, $bucket, $bucket['keywords']);
        }

        foreach ($intermediate as &$bucket) {
            $bucket['total_volume'] = array_sum(array_column($bucket['keywords'], 'volume'));
            usort($bucket['keywords'], fn ($a, $b) => $b['volume'] <=> $a['volume']);
        }
        unset($bucket);

        uasort($intermediate, fn ($a, $b) => $b['total_volume'] <=> $a['total_volume']);

        return $this->mergeSmallBrands($intermediate);
    }

    /**
     * @param  array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>  $buckets
     * @return array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>
     */
    private function mergeSmallBrands(array $buckets): array
    {
        $brandMin = config('keyword-candidates.brand_min_volume', 100);
        $merges = [];

        foreach ($buckets as $slug => $bucket) {
            if (!str_starts_with($bucket['group_key'], 'brand:')) {
                continue;
            }
            if ($bucket['total_volume'] >= $brandMin) {
                continue;
            }

            $targetSlug = $this->inferCategorySlugFromBrandBucket($bucket);
            if ($targetSlug === null || $targetSlug === $slug || !isset($buckets[$targetSlug])) {
                $buckets[$slug]['group_key'] = 'term:' . $slug;
                continue;
            }

            $merges[] = ['from' => $slug, 'to' => $targetSlug];
        }

        foreach ($merges as $merge) {
            $from = $merge['from'];
            $to = $merge['to'];
            if (!isset($buckets[$from], $buckets[$to])) {
                continue;
            }

            foreach ($buckets[$from]['term_groups'] as $tg) {
                if (!in_array($tg, $buckets[$to]['term_groups'], true)) {
                    $buckets[$to]['term_groups'][] = $tg;
                }
            }

            foreach ($buckets[$from]['keywords'] as $row) {
                $buckets[$to]['keywords'][] = $row;
            }
            unset($buckets[$from]);
        }

        foreach ($buckets as &$bucket) {
            $bucket['total_volume'] = array_sum(array_column($bucket['keywords'], 'volume'));
            usort($bucket['keywords'], fn ($a, $b) => $b['volume'] <=> $a['volume']);
        }
        unset($bucket);

        return $buckets;
    }

    /**
     * @param  array{group_key: string, slug: string, keywords: list<array>}  $bucket
     */
    private function inferCategorySlugFromBrandBucket(array $bucket): ?string
    {
        $brand = substr($bucket['group_key'], 6);
        $brands = config('keyword-candidates.known_brands', []);
        $tokenCounts = [];

        foreach ($bucket['keywords'] as $row) {
            foreach ($row['analysis']['tokens'] as $token) {
                if ($token === $brand || in_array($token, $brands, true)) {
                    continue;
                }
                $tokenCounts[$token] = ($tokenCounts[$token] ?? 0) + $row['volume'];
            }
        }

        arsort($tokenCounts);

        $top = array_key_first($tokenCounts);

        return $top ? \Illuminate\Support\Str::slug($top, '-', 'lt') : null;
    }

    /**
     * @param  array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>  $result
     * @param  list<array>  $keywords
     */
    private function mergeCandidateBucket(array &$result, string $targetSlug, array $sourceBucket, array $keywords): void
    {
        if (!isset($result[$targetSlug])) {
            $result[$targetSlug] = [
                'group_key' => str_starts_with($sourceBucket['group_key'], 'brand:')
                    ? 'brand:' . $targetSlug
                    : 'term:' . $targetSlug,
                'slug' => $targetSlug,
                'keywords' => [],
                'total_volume' => 0,
                'term_groups' => [],
            ];
        }

        foreach ($sourceBucket['term_groups'] as $tg) {
            if (!in_array($tg, $result[$targetSlug]['term_groups'], true)) {
                $result[$targetSlug]['term_groups'][] = $tg;
            }
        }

        foreach ($keywords as $row) {
            $result[$targetSlug]['keywords'][] = $row;
        }
    }

    /**
     * @param  array<string, array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}>  $result
     * @param  array{term_group: string, slug: string, keywords: list<array>, total_volume: int}  $group
     */
    private function splitKapsulesGroup(array $group, array &$result): void
    {
        foreach ($group['keywords'] as $row) {
            $target = $this->resolveKapsulesTarget($row);

            if ($target === null) {
                continue;
            }

            $this->mergeGroup($result, $target, [
                'term_group' => $group['term_group'],
                'slug' => $target,
                'keywords' => [$row],
                'total_volume' => $row['volume'],
            ]);
        }
    }

    /**
     * @param  array{keyword: string, volume: int, category: string, intents: string}  $row
     */
    private function resolveKapsulesTarget(array $row): ?string
    {
        $kw = mb_strtolower($row['keyword']);
        $category = mb_strtolower($row['category'] ?? '');

        $isCoffee = $this->matchesNeedles($kw, self::COFFEE_KEYWORD_NEEDLES)
            || str_contains($category, 'tea');

        $isLaundry = $this->matchesNeedles($kw, self::LAUNDRY_KEYWORD_NEEDLES)
            || str_contains($category, 'laundry');

        if ($isCoffee && !$isLaundry) {
            return 'kava';
        }

        if ($isLaundry && !$isCoffee) {
            return 'skalbimo';
        }

        if ($isCoffee) {
            return 'kava';
        }

        if ($isLaundry) {
            return 'skalbimo';
        }

        return null;
    }

    /**
     * @param  array<string, array{term_group: string, slug: string, keywords: list<array>, total_volume: int, source_groups: list<string>}>  $result
     * @param  array{term_group: string, slug: string, keywords: list<array>, total_volume: int}  $group
     */
    private function mergeGroup(array &$result, string $targetSlug, array $group): void
    {
        if (!isset($result[$targetSlug])) {
            $result[$targetSlug] = [
                'term_group' => $this->displayTermGroup($targetSlug),
                'slug' => $targetSlug,
                'keywords' => [],
                'total_volume' => 0,
                'source_groups' => [],
            ];
        }

        if (!in_array($group['term_group'], $result[$targetSlug]['source_groups'], true)) {
            $result[$targetSlug]['source_groups'][] = $group['term_group'];
        }

        foreach ($group['keywords'] as $row) {
            $result[$targetSlug]['keywords'][] = $row;
        }
    }

    private function matchesNeedles(string $keyword, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($keyword, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function displayTermGroup(string $slug): string
    {
        return match ($slug) {
            'kava' => 'kava',
            'skalbimo' => 'skalbimo',
            default => str_replace('-', ' ', $slug),
        };
    }

    /**
     * @return array<string, string>
     */
    private function mergeMap(): array
    {
        return array_merge(self::MERGE_INTO, config('keyword-candidates.merge_into', []));
    }
}
