<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductDuplicateFilterService
{
    private int $minChunkSize = 20;
    private int $maxChunkSize = 30;
    private float $similarityThreshold = 85.0;
    private array $ignoreWords = ['g', 'ml', 'kg', 'vnt', 'x', 'g.', 'ml.', 'kg.', 'vnt.', '%', 'rieb.', 'rieb'];

    public function findPotentialDuplicates(?int $categoryId = null): Collection
    {
        $query = Product::query();

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->with('category')->get();

        $potentialDuplicates = collect();

        foreach ($products as $product) {
            $similarProducts = $this->findSimilarProducts($product, $products);

            if ($similarProducts->count() >= 2) {
                $group = collect([$product])->merge($similarProducts);
                $potentialDuplicates->push($group);
            }
        }

        return $this->deduplicateGroups($potentialDuplicates);
    }

    private function findSimilarProducts(Product $product, Collection $allProducts): Collection
    {
        return $allProducts->filter(function ($otherProduct) use ($product) {
            if ($otherProduct->id === $product->id) {
                return false;
            }

            if ($otherProduct->category_id !== $product->category_id) {
                return false;
            }

            return $this->isSimilarName(
                $product->name,
                $otherProduct->name,
                $product->brand,
                $otherProduct->brand
            );
        });
    }

    private function isSimilarName(string $name1, string $name2, ?string $brand1 = null, ?string $brand2 = null): bool
    {
        if (!$this->brandsMatch($brand1, $brand2)) {
            return false;
        }

        $normalizedName1 = $this->normalizeName($name1);
        $normalizedName2 = $this->normalizeName($name2);

        $keyWords1 = $this->extractKeyWords($normalizedName1);
        $keyWords2 = $this->extractKeyWords($normalizedName2);

        if ($this->hasMatchingKeyWords($keyWords1, $keyWords2)) {
            return true;
        }

        if ($this->hasSameFirstWords($normalizedName1, $normalizedName2, 2)) {
            return true;
        }

        $normalized1 = $this->removeNumbersAndSizes($normalizedName1);
        $normalized2 = $this->removeNumbersAndSizes($normalizedName2);

        $similarity = $this->calculateSimilarity($normalized1, $normalized2);
        return $similarity >= $this->similarityThreshold;
    }

    private function brandsMatch(?string $brand1, ?string $brand2): bool
    {
        if (empty($brand1) && empty($brand2)) {
            return true;
        }

        if (empty($brand1) || empty($brand2)) {
            return false;
        }

        $normalizedBrand1 = $this->normalizeBrand($brand1);
        $normalizedBrand2 = $this->normalizeBrand($brand2);

        return $normalizedBrand1 === $normalizedBrand2;
    }

    private function normalizeBrand(?string $brand): string
    {
        if (empty($brand)) {
            return '';
        }

        $brand = trim($brand);
        $brand = mb_strtolower($brand);
        $brand = preg_replace('/[^a-z0-9\s]/', '', $brand);
        $brand = preg_replace('/\s+/', ' ', $brand);

        return trim($brand);
    }

    private function extractKeyWords(string $name): array
    {
        $words = preg_split('/\s+/', trim($name));
        $keyWords = [];

        foreach ($words as $word) {
            $word = mb_strtolower(trim($word, '.,;:!?'));
            
            if (empty($word)) {
                continue;
            }

            if (in_array($word, $this->ignoreWords)) {
                continue;
            }

            if (preg_match('/^\d+[.,]?\d*[gmlkgvnt%x]*$/', $word)) {
                continue;
            }

            if (mb_strlen($word) < 2) {
                continue;
            }

            $keyWords[] = $word;
        }

        return $keyWords;
    }

    private function hasMatchingKeyWords(array $keyWords1, array $keyWords2): bool
    {
        if (empty($keyWords1) || empty($keyWords2)) {
            return false;
        }

        $minWords = min(count($keyWords1), count($keyWords2));
        $matchingCount = 0;
        $minMatchRequired = max(2, (int)($minWords * 0.6));

        foreach ($keyWords1 as $word1) {
            foreach ($keyWords2 as $word2) {
                if ($word1 === $word2) {
                    $matchingCount++;
                    break;
                }
            }
        }

        return $matchingCount >= $minMatchRequired;
    }

    private function hasSameFirstWords(string $name1, string $name2, int $wordCount = 2): bool
    {
        $words1 = preg_split('/\s+/', trim($name1));
        $words2 = preg_split('/\s+/', trim($name2));

        if (count($words1) < $wordCount || count($words2) < $wordCount) {
            return false;
        }

        for ($i = 0; $i < $wordCount; $i++) {
            $word1 = mb_strtolower(trim($words1[$i], '.,;:!?'));
            $word2 = mb_strtolower(trim($words2[$i], '.,;:!?'));

            if (in_array($word1, $this->ignoreWords) || in_array($word2, $this->ignoreWords)) {
                continue;
            }

            if ($word1 !== $word2) {
                return false;
            }
        }

        return true;
    }

    private function removeNumbersAndSizes(string $name): string
    {
        $name = preg_replace('/\d+[.,]?\d*\s*[gmlkgvnt%x]+/i', '', $name);
        $name = preg_replace('/\d+/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    private function calculateSimilarity(string $name1, string $name2): float
    {
        similar_text(mb_strtolower($name1), mb_strtolower($name2), $percent);
        return $percent;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    private function deduplicateGroups(Collection $groups): Collection
    {
        $seen = collect();
        $deduplicated = collect();

        foreach ($groups as $group) {
            $groupIds = $group->pluck('id')->sort()->implode(',');
            
            if (!$seen->contains($groupIds)) {
                $seen->push($groupIds);
                $deduplicated->push($group);
            }
        }

        return $deduplicated;
    }

    public function groupForGptAnalysis(Collection $potentialDuplicates): Collection
    {
        $allProducts = collect();

        foreach ($potentialDuplicates as $group) {
            $allProducts = $allProducts->merge($group);
        }

        $uniqueProducts = $allProducts->unique('id');

        return $this->getProductChunks($uniqueProducts);
    }

    public function getProductChunks(Collection $products): Collection
    {
        $chunks = collect();
        $currentChunk = collect();

        foreach ($products as $product) {
            $currentChunk->push($product);

            if ($currentChunk->count() >= $this->maxChunkSize) {
                $chunks->push($currentChunk);
                $currentChunk = collect();
            }
        }

        if ($currentChunk->count() >= $this->minChunkSize) {
            $chunks->push($currentChunk);
        } elseif ($currentChunk->isNotEmpty() && $chunks->isNotEmpty()) {
            $lastChunk = $chunks->last();
            $merged = $lastChunk->merge($currentChunk);
            
            if ($merged->count() <= $this->maxChunkSize) {
                $chunks->pop();
                $chunks->push($merged);
            } else {
                $chunks->push($currentChunk);
            }
        } elseif ($currentChunk->isNotEmpty()) {
            $chunks->push($currentChunk);
        }

        return $chunks;
    }

    public function setChunkSize(int $min, int $max): self
    {
        $this->minChunkSize = $min;
        $this->maxChunkSize = $max;
        return $this;
    }

    public function setSimilarityThreshold(float $threshold): self
    {
        $this->similarityThreshold = $threshold;
        return $this;
    }
}


