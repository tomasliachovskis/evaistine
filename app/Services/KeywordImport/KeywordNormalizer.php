<?php

namespace App\Services\KeywordImport;

use Illuminate\Support\Str;

class KeywordNormalizer
{
    /**
     * @return array{
     *   original: string,
     *   normalized: string,
     *   slug: string,
     *   tokens: list<string>,
     *   brand: string|null,
     *   has_store: bool,
     *   intent_cheapest: bool,
     *   intent_top: bool,
     * }
     */
    public function analyze(string $keyword): array
    {
        $original = trim($keyword);
        $lower = mb_strtolower($original);

        $stores = config('keyword-candidates.store_names', []);
        $hasStore = false;

        foreach ($stores as $store) {
            if (preg_match('/\b' . preg_quote($store, '/') . '\b/u', $lower)) {
                $hasStore = true;
                $lower = preg_replace('/\b' . preg_quote($store, '/') . '\b/u', ' ', $lower);
            }
        }

        $intentCheapest = (bool) preg_match('/\bkur\s+pigiausia\b/u', $lower);
        $intentTop = (bool) preg_match('/\b(top|pigiausi(?:as)?|pigiausias)\b/u', $lower);

        $noise = config('keyword-candidates.noise_words', []);
        $tokens = preg_split('/\s+/u', trim($lower)) ?: [];

        $cleanTokens = [];
        $slugTokens = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || in_array($token, $noise, true)) {
                continue;
            }
            if (in_array($token, $stores, true)) {
                $hasStore = true;
                continue;
            }
            $slugTokens[] = $token;
            $cleanTokens[] = $this->lightStem($token);
        }

        $cleanTokens = array_values(array_unique(array_filter($cleanTokens)));
        $slugTokens = array_values(array_unique(array_filter($slugTokens)));
        $normalized = implode(' ', $cleanTokens);
        $brand = $this->detectBrand($cleanTokens);
        $slug = Str::slug(implode(' ', $slugTokens) ?: $original, '-', 'lt');

        return [
            'original' => $original,
            'normalized' => $normalized,
            'slug' => $slug,
            'tokens' => $cleanTokens,
            'brand' => $brand,
            'has_store' => $hasStore,
            'intent_cheapest' => $intentCheapest,
            'intent_top' => $intentTop,
        ];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function detectBrand(array $tokens): ?string
    {
        $brands = config('keyword-candidates.known_brands', []);

        foreach ($tokens as $token) {
            if (in_array($token, $brands, true)) {
                return $token;
            }
        }

        return null;
    }

    private function lightStem(string $token): string
    {
        $replacements = [
            '/ės$/' => 'ė',
            '/os$/' => 'a',
            '/as$/' => 'a',
            '/ui$/' => 'a',
            '/ams$/' => 'a',
            '/ų$/' => 'a',
            '/iai$/' => 'a',
            '/ius$/' => 'a',
        ];

        foreach ($replacements as $pattern => $replacement) {
            if (preg_match($pattern, $token)) {
                return preg_replace($pattern, $replacement, $token);
            }
        }

        return $token;
    }
}
