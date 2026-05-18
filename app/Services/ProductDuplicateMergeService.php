<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\ProductMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductDuplicateMergeService
{
    public function filterPairs(Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return $pairs;
        }

        $productIds = $pairs->flatMap(fn ($pair) => [(int) $pair->id1, (int) $pair->id2])->unique()->values();
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return $pairs->filter(function ($pair) use ($products) {
            $first = $products->get((int) $pair->id1);
            $second = $products->get((int) $pair->id2);

            if (!$first || !$second) {
                return false;
            }

            return !$this->pairHasSpecialCharInDifferingWord($first->name, $second->name);
        })->values();
    }

    public function pairHasSpecialCharInDifferingWord(string $name1, string $name2): bool
    {
        $mismatchWords = $this->getMismatchWords(
            $this->extractBaseWords($name1),
            $this->extractBaseWords($name2)
        );

        foreach ($mismatchWords as $word) {
            if ($this->wordHasSpecialCharacters($word)) {
                return true;
            }
        }

        return false;
    }

    public function buildClusters(Collection $pairs): array
    {
        $parent = [];

        $find = function (int $id) use (&$parent, &$find): int {
            if (!isset($parent[$id])) {
                $parent[$id] = $id;
            }
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        $union = function (int $a, int $b) use ($find, &$parent): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        };

        foreach ($pairs as $pair) {
            $union((int) $pair->id1, (int) $pair->id2);
        }

        $clusters = [];

        foreach ($pairs as $pair) {
            foreach ([(int) $pair->id1, (int) $pair->id2] as $id) {
                $root = $find($id);
                $clusters[$root][$id] = $id;
            }
        }

        return array_values(array_map(
            fn (array $ids) => array_values($ids),
            $clusters
        ));
    }

    public function pickBaseProduct(array $productIds): Product
    {
        return Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy([
                fn (Product $product) => $product->image_from_flyer ? 1 : 0,
                fn (Product $product) => $this->nameHasSpecialCharacters($product->name) ? 1 : 0,
                fn (Product $product) => $product->created_at?->getTimestamp() ?? 0,
            ])
            ->firstOrFail();
    }

    public function nameHasSpecialCharacters(string $name): bool
    {
        return (bool) preg_match('/[^\p{L}\p{N}\s,.-]/u', $name);
    }

    public function wordHasSpecialCharacters(string $word): bool
    {
        return (bool) preg_match('/[^\p{L}\p{N}.-]/u', $word);
    }

    private function extractBaseWords(string $name): array
    {
        $commaPos = strpos($name, ',');
        $base = $commaPos !== false ? trim(substr($name, 0, $commaPos)) : trim($name);
        $words = preg_split('/\s+/u', mb_strtolower($base), -1, PREG_SPLIT_NO_EMPTY);

        return $words ?: [];
    }

    private function getMismatchWords(array $words1, array $words2): array
    {
        $remaining = $words2;
        $mismatch = [];

        foreach ($words1 as $word1) {
            $matchedIndex = null;
            $matchedWord = null;

            foreach ($remaining as $index => $word2) {
                if ($this->wordsFuzzyMatch($word1, $word2)) {
                    $matchedIndex = $index;
                    $matchedWord = $word2;
                    break;
                }
            }

            if ($matchedIndex === null) {
                $mismatch[] = $word1;
            } else {
                unset($remaining[$matchedIndex]);
                if ($word1 !== $matchedWord) {
                    $mismatch[] = $word1;
                    $mismatch[] = $matchedWord;
                }
            }
        }

        foreach ($remaining as $word2) {
            $mismatch[] = $word2;
        }

        return array_values(array_unique($mismatch));
    }

    private function wordsFuzzyMatch(string $word1, string $word2): bool
    {
        $len1 = mb_strlen($word1);
        $len2 = mb_strlen($word2);

        if ($len1 <= 3 || $len2 <= 3) {
            return $word1 === $word2;
        }

        if (abs($len1 - $len2) > 3) {
            return false;
        }

        $prefixLength = min($len1, $len2) - 1;

        if ($prefixLength < 1) {
            return $word1 === $word2;
        }

        return mb_substr($word1, 0, $prefixLength) === mb_substr($word2, 0, $prefixLength);
    }

    public function mergeAllClusters(array $clusters, bool $dryRun): array
    {
        $merged = [];

        $run = function () use ($clusters, $dryRun, &$merged) {
            foreach ($clusters as $productIds) {
                if (count($productIds) < 2) {
                    continue;
                }

                $base = $this->pickBaseProduct($productIds);
                $duplicateIds = array_values(array_filter($productIds, fn (int $id) => $id !== $base->id));

                foreach ($duplicateIds as $duplicateId) {
                    $duplicate = Product::findOrFail($duplicateId);

                    if ($this->pairHasSpecialCharInDifferingWord($base->name, $duplicate->name)) {
                        continue;
                    }

                    $merged = array_merge(
                        $merged,
                        $this->mergeDuplicateIntoBase($base, $duplicate, $dryRun)
                    );
                }
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $merged;
    }

    public function mergeDuplicateIntoBase(Product $base, Product $duplicate, bool $dryRun): array
    {
        $this->resolveDiscountConflicts($base->id, $duplicate->id, $dryRun);

        if (!$dryRun) {
            Discount::withoutEvents(function () use ($base, $duplicate) {
                Discount::query()
                    ->where('product_id', $duplicate->id)
                    ->update(['product_id' => $base->id]);
            });

            DB::table('discount_histories')
                ->where('product_id', $duplicate->id)
                ->update(['product_id' => $base->id]);

            $this->reassignFavorites($base->id, $duplicate->id);

            ProductMapping::firstOrCreate(
                ['name' => $duplicate->name],
                ['product_id' => $base->id]
            );

            $duplicate->delete();
        }

        return [[
            'duplicate_id' => $duplicate->id,
            'duplicate_name' => $duplicate->name,
            'base_id' => $base->id,
            'base_name' => $base->name,
        ]];
    }

    public function resolveDiscountConflicts(int $baseId, int $duplicateId, bool $dryRun): int
    {
        $duplicateDiscounts = Discount::query()
            ->where('product_id', $duplicateId)
            ->get();

        $removed = 0;

        foreach ($duplicateDiscounts as $duplicateDiscount) {
            $conflictExists = Discount::query()
                ->where('product_id', $baseId)
                ->where('store_id', $duplicateDiscount->store_id)
                ->where('start_at', $duplicateDiscount->start_at)
                ->where('end_at', $duplicateDiscount->end_at)
                ->exists();

            if ($conflictExists) {
                if (!$dryRun) {
                    Discount::withoutEvents(function () use ($duplicateDiscount) {
                        $duplicateDiscount->delete();
                    });
                }
                $removed++;
            }
        }

        return $removed;
    }

    private function reassignFavorites(int $baseId, int $duplicateId): void
    {
        $baseUserIds = ProductFavorite::query()
            ->where('product_id', $baseId)
            ->pluck('user_id');

        ProductFavorite::query()
            ->where('product_id', $duplicateId)
            ->whereIn('user_id', $baseUserIds)
            ->delete();

        ProductFavorite::query()
            ->where('product_id', $duplicateId)
            ->update(['product_id' => $baseId]);
    }
}
