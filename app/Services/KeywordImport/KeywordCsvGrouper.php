<?php

namespace App\Services\KeywordImport;

use Illuminate\Support\Str;

class KeywordCsvGrouper
{
    public function __construct(
        private KeywordCsvGroupRouter $router,
    ) {
    }

    /**
     * @param  list<array{term_group: string, keyword: string, volume: int, category: string, intents: string}>  $rows
     * @return array<string, array{term_group: string, slug: string, keywords: list<array{keyword: string, volume: int, category: string, intents: string}>, total_volume: int, source_groups: list<string>}>
     */
    public function group(array $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $termGroup = trim($row['term_group']);
            if ($termGroup === '') {
                continue;
            }

            $slug = Str::slug(mb_strtolower($termGroup), '-', 'lt');
            if ($slug === '') {
                continue;
            }

            if (!isset($buckets[$slug])) {
                $buckets[$slug] = [
                    'term_group' => $termGroup,
                    'slug' => $slug,
                    'keywords' => [],
                    'total_volume' => 0,
                ];
            }

            $buckets[$slug]['keywords'][] = [
                'keyword' => $row['keyword'],
                'volume' => $row['volume'],
                'category' => $row['category'],
                'intents' => $row['intents'],
            ];
            $buckets[$slug]['total_volume'] += $row['volume'];
        }

        return $this->router->route($buckets);
    }
}
