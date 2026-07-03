<?php

namespace App\Services\KeywordImport;

use Illuminate\Support\Str;

class KeywordCandidateGrouper
{
    public function __construct(
        private KeywordCsvGroupRouter $router,
    ) {
    }

    /**
     * @param  list<array{row: array, analysis: array}>  $accepted
     * @return array<string, array{group_key: string, slug: string, keywords: list<array>, total_volume: int, term_groups: list<string>}>
     */
    public function group(array $accepted): array
    {
        $buckets = [];

        foreach ($accepted as $item) {
            $row = $item['row'];
            $analysis = $item['analysis'];
            $groupKey = $this->resolveGroupKey($row, $analysis);

            if ($groupKey === null) {
                continue;
            }

            if (!isset($buckets[$groupKey])) {
                $buckets[$groupKey] = [
                    'group_key' => $groupKey,
                    'slug' => $this->slugFromGroupKey($groupKey),
                    'keywords' => [],
                    'total_volume' => 0,
                    'term_groups' => [],
                ];
            }

            $termGroup = trim($row['term_group'] ?? '');
            if ($termGroup !== '' && !in_array($termGroup, $buckets[$groupKey]['term_groups'], true)) {
                $buckets[$groupKey]['term_groups'][] = $termGroup;
            }

            $buckets[$groupKey]['keywords'][] = [
                'keyword' => $row['keyword'],
                'volume' => $row['volume'],
                'category' => $row['category'],
                'intents' => $row['intents'],
                'analysis' => $analysis,
            ];
            $buckets[$groupKey]['total_volume'] += $row['volume'];
        }

        return $this->router->routeCandidates($buckets);
    }

    /**
     * @param  array{term_group: string}  $row
     * @param  array{normalized: string, brand: string|null, slug: string}  $analysis
     */
    private function resolveGroupKey(array $row, array $analysis): ?string
    {
        $termGroup = trim($row['term_group'] ?? '');
        $skipGroups = config('keyword-candidates.skip_term_groups', []);

        if ($termGroup !== '') {
            $tgSlug = Str::slug(mb_strtolower($termGroup), '-', 'lt');
            if (in_array($tgSlug, $skipGroups, true)) {
                return null;
            }

            if ($this->isStoreSlug($tgSlug)) {
                return null;
            }

            $knownBrands = config('keyword-candidates.known_brands', []);
            if (in_array($tgSlug, $knownBrands, true)) {
                return 'brand:' . $tgSlug;
            }

            if ($analysis['brand'] !== null && $this->shouldUseBrandGroup($analysis['brand'], $termGroup)) {
                return 'brand:' . $analysis['brand'];
            }

            return 'term:' . $tgSlug;
        }

        if ($analysis['brand'] !== null) {
            $brandSlug = $analysis['brand'];
            if ($this->isBlockedSlug($brandSlug, $skipGroups)) {
                return null;
            }

            return 'brand:' . $brandSlug;
        }

        if ($analysis['normalized'] !== '') {
            $coreSlug = $analysis['slug'];
            if ($this->isBlockedSlug($coreSlug, $skipGroups)) {
                return null;
            }

            return 'core:' . $coreSlug;
        }

        return null;
    }

    private function isBlockedSlug(string $slug, array $skipGroups): bool
    {
        if (in_array($slug, $skipGroups, true)) {
            return true;
        }

        return $this->isStoreSlug($slug);
    }

    private function shouldUseBrandGroup(string $brand, string $termGroup): bool
    {
        $tgLower = mb_strtolower($termGroup);

        return $tgLower === $brand || str_contains($tgLower, $brand);
    }

    private function isStoreSlug(string $slug): bool
    {
        return in_array($slug, config('keyword-candidates.store_names', []), true);
    }

    private function slugFromGroupKey(string $groupKey): string
    {
        if (str_starts_with($groupKey, 'brand:')) {
            return substr($groupKey, 6);
        }
        if (str_starts_with($groupKey, 'term:')) {
            return substr($groupKey, 5);
        }
        if (str_starts_with($groupKey, 'core:')) {
            return substr($groupKey, 5);
        }

        return Str::slug($groupKey, '-', 'lt');
    }
}
