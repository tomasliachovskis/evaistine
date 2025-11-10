<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductDuplicateFilterService
{
    private int $minChunkSize = 20;
    private int $maxChunkSize = 30;
    private float $similarityThreshold = 70.0;

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

            return $this->isSimilarName($product->name, $otherProduct->name);
        });
    }

    private function isSimilarName(string $name1, string $name2): bool
    {
        $normalizedName1 = $this->normalizeName($name1);
        $normalizedName2 = $this->normalizeName($name2);

        return $this->hasSameFirstWord($normalizedName1, $normalizedName2);
    }

    private function hasSameFirstWord(string $name1, string $name2): bool
    {
        $words1 = preg_split('/\s+/', trim($name1));
        $words2 = preg_split('/\s+/', trim($name2));

        if (empty($words1[0]) || empty($words2[0])) {
            return false;
        }

        $firstWord1 = mb_strtolower($words1[0]);
        $firstWord2 = mb_strtolower($words2[0]);

        return $firstWord1 === $firstWord2;
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

