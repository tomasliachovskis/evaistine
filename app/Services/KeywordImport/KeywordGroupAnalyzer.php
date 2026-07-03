<?php

namespace App\Services\KeywordImport;

class KeywordGroupAnalyzer
{
    private const KAVA_PRIMARY_KEYWORDS = [
        'kava akcija',
        'kavos pupelės akcija',
        'kavos kapsules akcija',
    ];

    /**
     * @param  array{term_group: string, slug: string, keywords: list<array{keyword: string, volume: int, category: string, intents: string}>, total_volume: int, source_groups?: list<string>}  $group
     * @return array{primary_keywords: list<string>, secondary_keywords: list<array>, h1: string, candidate_brands: list<string>}
     */
    public function analyze(array $group): array
    {
        $keywords = $group['keywords'];
        usort($keywords, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        if ($group['slug'] === 'kava') {
            return $this->analyzeKava($keywords);
        }

        $primaryKeywords = [];
        foreach ($keywords as $row) {
            $kw = trim($row['keyword']);
            if ($kw === '') {
                continue;
            }
            if (!in_array($kw, $primaryKeywords, true)) {
                $primaryKeywords[] = $kw;
            }
            if (count($primaryKeywords) >= 3) {
                break;
            }
        }

        $primarySet = array_flip($primaryKeywords);
        $secondary = array_values(array_filter(
            $keywords,
            fn ($row) => !isset($primarySet[trim($row['keyword'])])
        ));

        $h1 = $this->capitalizeKeyword($primaryKeywords[0] ?? $group['term_group']);

        return [
            'primary_keywords' => $primaryKeywords,
            'secondary_keywords' => $secondary,
            'h1' => $h1,
            'candidate_brands' => $this->extractCandidateBrands($group['slug'], $secondary),
        ];
    }

    /**
     * @param  list<array{keyword: string, volume: int, category: string, intents: string}>  $keywords
     * @return array{primary_keywords: list<string>, secondary_keywords: list<array>, h1: string, candidate_brands: list<string>}
     */
    private function analyzeKava(array $keywords): array
    {
        $primaryKeywords = self::KAVA_PRIMARY_KEYWORDS;
        $primarySet = array_flip(array_map('mb_strtolower', $primaryKeywords));

        $secondary = array_values(array_filter(
            $keywords,
            fn ($row) => !isset($primarySet[mb_strtolower(trim($row['keyword']))]),
        ));

        $mainKw = $primaryKeywords[0];

        return [
            'primary_keywords' => $primaryKeywords,
            'secondary_keywords' => $secondary,
            'h1' => $this->capitalizeKeyword($mainKw),
            'candidate_brands' => $this->extractCandidateBrands('kava', $secondary),
        ];
    }

    /**
     * @param  list<array{keyword: string, volume: int, category: string, intents: string}>  $secondary
     * @return list<string>
     */
    private function extractCandidateBrands(string $slug, array $secondary): array
    {
        $brands = [];
        $termLower = mb_strtolower($slug);
        $stopWords = ['akcija', 'akcijos', 'nuolaida', 'nuolaidos', $termLower];

        foreach ($secondary as $row) {
            $kw = mb_strtolower(trim($row['keyword']));
            $kw = preg_replace('/\s+akcija(\s|$)/u', ' ', $kw);
            $kw = preg_replace('/\b(iki|maxima|lidl|rimi|norfa)\b/u', '', $kw);
            $kw = trim((string) $kw);

            foreach (preg_split('/\s+/u', $kw) ?: [] as $part) {
                if (mb_strlen($part) < 3 || in_array($part, $stopWords, true)) {
                    continue;
                }
                if (!in_array($part, $brands, true)) {
                    $brands[] = $part;
                }
            }
        }

        return array_slice($brands, 0, 15);
    }

    private function capitalizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($keyword, 0, 1)) . mb_substr($keyword, 1);
    }
}
