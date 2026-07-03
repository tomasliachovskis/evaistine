<?php

namespace App\Services\KeywordImport;

class KeywordCandidateFilter
{
    public function __construct(
        private KeywordNormalizer $normalizer,
    ) {
    }

    /**
     * @param  array{keyword: string, volume: int, term_group: string, category: string, intents: string, difficulty: int, cpc: string, parent_keyword: string}  $row
     * @return array{accept: bool, reason: string, analysis: array}
     */
    public function evaluate(array $row): array
    {
        $keyword = trim($row['keyword']);
        if ($keyword === '') {
            return $this->reject($row, 'empty keyword', []);
        }

        if ($row['volume'] <= 0) {
            return $this->reject($row, 'zero volume', []);
        }

        $analysis = $this->normalizer->analyze($keyword);

        if ($analysis['normalized'] === '' || $analysis['normalized'] === 'akcija') {
            return $this->reject($row, 'too generic', $analysis);
        }

        if ($analysis['has_store']) {
            return $this->reject($row, 'contains store name', $analysis);
        }

        if ($this->isStoreOnlyKeyword($analysis)) {
            return $this->reject($row, 'store-only intent', $analysis);
        }

        $termGroupSlug = $this->slugify($row['term_group'] ?? '');
        if ($termGroupSlug !== '' && $this->isStoreSlug($termGroupSlug)) {
            return $this->reject($row, 'store term group', $analysis);
        }

        if ($this->isBlockedCategory($row['category'], $keyword)) {
            return $this->reject($row, 'non-product category', $analysis);
        }

        if ($this->isRetailerIntent($row)) {
            return $this->reject($row, 'retailer/store intent', $analysis);
        }

        return [
            'accept' => true,
            'reason' => '',
            'analysis' => $analysis,
        ];
    }

    /**
     * @return array{accept: false, reason: string, analysis: array}
     */
    private function reject(array $row, string $reason, array $analysis): array
    {
        return [
            'accept' => false,
            'reason' => $reason,
            'analysis' => $analysis,
            'row' => $row,
        ];
    }

    private function isStoreOnlyKeyword(array $analysis): bool
    {
        $stores = config('keyword-candidates.store_names', []);
        $tokens = $analysis['tokens'];

        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (!in_array($token, $stores, true)) {
                return false;
            }
        }

        return true;
    }

    private function isStoreSlug(string $slug): bool
    {
        $stores = config('keyword-candidates.store_names', []);

        return in_array($slug, $stores, true);
    }

    private function slugify(string $value): string
    {
        return \Illuminate\Support\Str::slug(mb_strtolower(trim($value)), '-', 'lt');
    }

    private function isBlockedCategory(string $category, string $keyword): bool
    {
        if ($category === '') {
            return false;
        }

        foreach (config('keyword-candidates.allowed_categories', []) as $pattern) {
            if (str_contains($category, $pattern)) {
                return false;
            }
        }

        if ($this->matchesProductKeyword($keyword)) {
            return false;
        }

        $blocked = [
            'Medications', 'Supplements', 'Dental care', 'Autos', 'Consumer electronics',
            'Mobile phones', 'Massage therapy', 'Short-term stays', 'Treatment centers',
            'Immunizations', 'Eyewear', 'Footwear', 'Roofing', 'Windows', 'Bonds',
            'Financial markets', 'Home', 'Bedroom', 'Grills', 'Dryers', 'Small kitchen appliances',
            'Outlet stores', 'Equipment', 'Beauty services', 'Retailers', 'Photo printing',
            'Computers', 'Laptops', 'Monitors', 'Televisions', 'Tools', 'Hardware',
            'Travel', 'Hotels', 'Spas', 'Gyms', 'Fitness', 'Pharmacy', 'Cosmetics',
            'Fragrances', 'Nail care', 'Appliances', 'Refrigerators', 'Large appliances',
        ];

        foreach ($blocked as $pattern) {
            if (str_contains($category, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesProductKeyword(string $keyword): bool
    {
        $lower = mb_strtolower($keyword);

        foreach (config('keyword-candidates.product_keyword_needles', []) as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{keyword: string, category: string, intents: string}  $row
     */
    private function isRetailerIntent(array $row): bool
    {
        $category = mb_strtolower($row['category'] ?? '');
        if (str_contains($category, 'retailer')) {
            return true;
        }

        $intents = mb_strtolower($row['intents'] ?? '');
        if (str_contains($intents, 'local') && str_contains($category, 'discount')) {
            $keyword = mb_strtolower(trim($row['keyword']));
            $stores = config('keyword-candidates.store_names', []);
            foreach ($stores as $store) {
                if (preg_match('/\b' . preg_quote($store, '/') . '\b/u', $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}
