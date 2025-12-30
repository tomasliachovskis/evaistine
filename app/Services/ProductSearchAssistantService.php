<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Store;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProductSearchAssistantService
{
    private MeilisearchService $meilisearchService;

    public function __construct(MeilisearchService $meilisearchService)
    {
        $this->meilisearchService = $meilisearchService;
    }

    public function calculateCartPrices(array $productList): array
    {
        $normalizedProducts = $this->normalizeProductList($productList);

        $productResults = [];
        $storeOffers = [];

        foreach ($normalizedProducts as $normalized) {
            $matches = $this->findProductMatches($normalized);

            $bestOffersByStore = $this->getBestOffersByStore($matches);

            $productResults[] = [
                'original_query' => $normalized['original'],
                'normalized' => $normalized['normalized'],
                'category' => $normalized['category'] ?? null,
                'matches_found' => $matches->count(),
                'best_offers_by_store' => $bestOffersByStore,
            ];

            foreach ($bestOffersByStore as $storeSlug => $offer) {
                if (!isset($storeOffers[$storeSlug])) {
                    $storeOffers[$storeSlug] = [];
                }
                $storeOffers[$storeSlug][] = [
                    'product_query' => $normalized['original'],
                    'product_name' => $offer['product_name'],
                    'price' => $offer['price'],
                    'original_price' => $offer['original_price'],
                    'discount_percent' => $offer['discount_percent'],
                    'savings' => $offer['savings'],
                    'discount_id' => $offer['discount_id'],
                ];
            }
        }

        $cartComparison = $this->calculateStoreTotals($storeOffers);
        $recommendation = $this->generateRecommendation($cartComparison);

        return [
            'products' => $productResults,
            'cart_comparison' => $cartComparison,
            'recommendation' => $recommendation,
        ];
    }

    private function normalizeProductList(array $productList): array
    {
        return $this->simpleNormalization($productList);
    }

    private function simpleNormalization(array $productList): array
    {
        return array_map(function($product) {
            $normalized = mb_strtolower(trim($product));

            return [
                'original' => $product,
                'normalized' => $normalized,
                'category' => null,
                'keywords' => [$normalized],
                'variants' => []
            ];
        }, $productList);
    }

    private function expandKeywords(array $normalized): array
    {
        $baseKeyword = mb_strtolower($normalized['normalized']);
        $keywords = $normalized['keywords'] ?? [$baseKeyword];

        $declensionVariants = $this->generateDeclensionVariants($baseKeyword);

        return array_unique(array_merge($keywords, $declensionVariants));
    }

    private function generateDeclensionVariants(string $word): array
    {
        $variants = [];
        $word = mb_strtolower(trim($word));

        if (mb_strlen($word) < 3) {
            return [];
        }

        $variants[] = $word;

        if (mb_substr($word, -2) === 'ai') {
            $singular = mb_substr($word, 0, -2) . 'as';
            $variants[] = $singular;
            $variants[] = mb_substr($word, 0, -2) . 'is';
            $variants[] = mb_substr($word, 0, -2) . 'ė';
        } elseif (mb_substr($word, -2) === 'os') {
            $singular = mb_substr($word, 0, -2) . 'a';
            $variants[] = $singular;
            $variants[] = mb_substr($word, 0, -2) . 'ė';
        } elseif (mb_substr($word, -2) === 'ės') {
            $singular = mb_substr($word, 0, -2) . 'ė';
            $variants[] = $singular;
            $variants[] = mb_substr($word, 0, -2) . 'a';
        } elseif (mb_substr($word, -1) === 'a') {
            $plural = mb_substr($word, 0, -1) . 'os';
            $variants[] = $plural;
            $variants[] = mb_substr($word, 0, -1) . 'ai';
            $variants[] = mb_substr($word, 0, -1) . 'ės';
        } elseif (mb_substr($word, -1) === 'ė') {
            $plural = mb_substr($word, 0, -1) . 'ės';
            $variants[] = $plural;
            $variants[] = mb_substr($word, 0, -1) . 'os';
        } elseif (mb_substr($word, -1) === 's') {
            $withoutS = mb_substr($word, 0, -1);
            if (mb_strlen($withoutS) > 2) {
                $variants[] = $withoutS . 'ai';
                $variants[] = $withoutS . 'os';
                $variants[] = $withoutS . 'ės';
            }
        } elseif (mb_substr($word, -2) === 'as' || mb_substr($word, -2) === 'is') {
            $plural = mb_substr($word, 0, -2) . 'ai';
            $variants[] = $plural;
        }

        return array_filter(array_unique($variants), function($v) {
            return mb_strlen($v) >= 3;
        });
    }

    private function findProductMatches(array $normalized): Collection
    {
        $allMatches = collect();

        $expandedKeywords = $this->expandKeywords($normalized);
        $expandedKeywords = array_slice($expandedKeywords, 0, 10);
        $expandedResults = $this->searchWithKeywords($expandedKeywords, $normalized);
        $allMatches = $allMatches->merge($expandedResults);

        return $allMatches
            ->unique('discount_id')
            ->sortByDesc('meilisearch_score')
            ->values();
    }

    private function searchWithKeywords(array $keywords, array $normalized): Collection
    {
        $matches = collect();
        $maxKeywords = 5;

        foreach (array_slice($keywords, 0, $maxKeywords) as $keyword) {
            if ($matches->count() >= 10) {
                break;
            }

            try {
                $filters = [];

                if (!empty($normalized['category'])) {
                    $category = Category::where('name', $normalized['category'])->first();
                    if ($category) {
                        $filters['category_id'] = $category->id;
                    } else {
                        $filters['category_name'] = $normalized['category'];
                    }
                }

                $searchResults = $this->meilisearchService->search(
                    $keyword,
                    $filters,
                    ['discounted_price:asc'],
                    1,
                    10
                );

                foreach ($searchResults['hits'] ?? [] as $hit) {
                    $meilisearchScore = $hit['_rankingScore'] ?? 0.0;

                    if ($meilisearchScore < 0.30) {
                        continue;
                    }

                    $productName = mb_strtolower($hit['product_name'] ?? '');
                    $normalizedName = mb_strtolower($normalized['normalized']);

                    if ($this->isLikelyUnrelated($productName, $normalizedName)) {
                        continue;
                    }

                    $matches->push([
                        'discount_id' => $hit['id'],
                        'product_id' => $hit['product_id'] ?? null,
                        'product_name' => $hit['product_name'] ?? '',
                        'store_id' => $hit['store_id'] ?? null,
                        'store_name' => $hit['store_name'] ?? '',
                        'store_slug' => $hit['store_slug'] ?? '',
                        'discounted_price' => $hit['discounted_price'] ?? 0,
                        'original_price' => $hit['original_price'] ?? 0,
                        'discount_percent' => $hit['discount_percent'] ?? 0,
                        'savings_amount' => $hit['savings_amount'] ?? 0,
                        'product_url' => $hit['product_url'] ?? null,
                        'matched_keyword' => $keyword,
                        'relevance_score' => $meilisearchScore * 100,
                        'meilisearch_score' => $meilisearchScore
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Meilisearch search failed for keyword', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $matches;
    }


    private function isLikelyUnrelated(string $productName, string $normalizedName): bool
    {
        $unrelatedPatterns = [
            'skonio',
            'kvapo',
            'formos',
            'pavidalo',
            'panašus į',
            'kaip',
        ];

        $productNameLower = mb_strtolower($productName);
        $normalizedNameLower = mb_strtolower($normalizedName);

        foreach ($unrelatedPatterns as $pattern) {
            if (mb_strpos($productNameLower, $pattern) !== false) {
                $normalizedNamePos = mb_strpos($productNameLower, $normalizedNameLower);
                $patternPos = mb_strpos($productNameLower, $pattern);

                if ($normalizedNamePos !== false && $patternPos !== false && $patternPos < $normalizedNamePos) {
                    return true;
                }
            }
        }

        $beverageWords = [
            'limonadas', 'limonadai',
            'gėrimas', 'gėrimai',
            'sultys', 'sulčių',
            'kokteilis', 'kokteiliai',
            'sprudelis', 'sprudeliai',
            'pienas', 'pieno',
            'sviestas', 'sviesto',
        ];

        $normalizedNamePos = mb_strpos($productNameLower, $normalizedNameLower);
        if ($normalizedNamePos !== false) {
            $afterBaseWord = mb_substr($productNameLower, $normalizedNamePos + mb_strlen($normalizedNameLower));
            foreach ($beverageWords as $beverage) {
                if (mb_strpos($afterBaseWord, $beverage) !== false) {
                    return true;
                }
            }
            if (mb_strpos($afterBaseWord, 'sk.') !== false || mb_strpos($afterBaseWord, 'skonio') !== false) {
                return true;
            }
        }

        $normalizedNameWords = preg_split('/\s+/', trim($normalizedNameLower));
        $productNameWords = preg_split('/\s+/', trim($productNameLower));

        if (count($normalizedNameWords) === 1) {
            $baseWord = $normalizedNameWords[0];

            if (mb_strlen($baseWord) < 4) {
                return false;
            }

            $modifierWords = [
                'džiovinti', 'džiovintos', 'džiovintų',
                'konservuoti', 'konservuotos', 'konservuotų',
                'rūkyti', 'rūkytos', 'rūkytų',
                'marinuoti', 'marinuotos', 'marinuotų',
                'šaldyti', 'šaldytos', 'šaldytų',
                'saldinti', 'saldintos', 'saldintų',
                'sūdyti', 'sūdytos', 'sūdytų',
                'kepti', 'keptos', 'keptų',
                'virti', 'virtos', 'virtų',
                'gaminiai', 'gaminys',
                'skonio', 'kvapo',
                'pieno', 'sviesto',
                'ekstra', 'premium', 'bio', 'org',
            ];

            $baseWordFound = false;
            $modifierBeforeBase = false;
            $wordCount = 0;

            foreach ($productNameWords as $index => $word) {
                $word = trim($word, '.,;:!?');

                if (mb_strlen($word) < 2) {
                    continue;
                }

                $wordCount++;

                if ($word === $baseWord || mb_strpos($word, $baseWord) !== false || mb_strpos($baseWord, $word) !== false) {
                    $baseWordFound = true;

                    if ($index > 0) {
                        $previousWord = trim($productNameWords[$index - 1] ?? '', '.,;:!?');
                        foreach ($modifierWords as $modifier) {
                            if (mb_strpos($previousWord, $modifier) !== false || mb_strpos($modifier, $previousWord) !== false) {
                                $modifierBeforeBase = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($baseWordFound && $modifierBeforeBase && $wordCount > 2) {
                return true;
            }

            if ($baseWordFound && count($productNameWords) > 3) {
                $baseWordIndex = -1;
                foreach ($productNameWords as $index => $word) {
                    if (mb_strpos($word, $baseWord) !== false || mb_strpos($baseWord, $word) !== false) {
                        $baseWordIndex = $index;
                        break;
                    }
                }

                if ($baseWordIndex > 0 && $baseWordIndex < count($productNameWords) - 1) {
                    return true;
                }
            }
        }

        if (count($normalizedNameWords) === 1 && count($productNameWords) > 3) {
            $normalizedNameWord = $normalizedNameWords[0];
            $foundAt = -1;
            foreach ($productNameWords as $index => $word) {
                if (mb_strpos($word, $normalizedNameWord) !== false) {
                    $foundAt = $index;
                    break;
                }
            }

            if ($foundAt > 0 && $foundAt < count($productNameWords) - 1) {
                return true;
            }
        }

        return false;
    }

    private function getBestOffersByStore(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return [];
        }

        $byStore = $matches->groupBy('store_slug');
        $bestOffers = [];

        foreach ($byStore as $storeSlug => $storeMatches) {
            $bestMatch = $storeMatches
                ->sortBy(function($match) {
                    return -($match['meilisearch_score'] ?? 0) * 1000 + $match['discounted_price'];
                })
                ->first();

            if ($bestMatch) {
                $bestOffers[$storeSlug] = [
                    'store_name' => $bestMatch['store_name'],
                    'product_name' => $bestMatch['product_name'],
                    'price' => round($bestMatch['discounted_price'], 2),
                    'original_price' => round($bestMatch['original_price'], 2),
                    'discount_percent' => round($bestMatch['discount_percent'], 1),
                    'savings' => round($bestMatch['savings_amount'], 2),
                    'discount_id' => $bestMatch['discount_id'],
                    'product_url' => $bestMatch['product_url'],
                ];
            }
        }

        return $bestOffers;
    }

    private function calculateStoreTotals(array $storeOffers): array
    {
        $cartComparison = [];
        $stores = Store::whereIn('slug', array_keys($storeOffers))->get()->keyBy('slug');

        foreach ($storeOffers as $storeSlug => $offers) {
            $store = $stores[$storeSlug] ?? null;
            if (!$store) {
                continue;
            }

            $total = 0;
            $totalOriginal = 0;
            $products = [];

            foreach ($offers as $offer) {
                $total += $offer['price'];
                $totalOriginal += $offer['original_price'];
                $products[] = $offer;
            }

            $cartComparison[$storeSlug] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'store_slug' => $store->slug,
                'total' => round($total, 2),
                'total_original' => round($totalOriginal, 2),
                'total_savings' => round($totalOriginal - $total, 2),
                'products' => $products,
                'product_count' => count($products),
            ];
        }

        uasort($cartComparison, function($a, $b) {
            return $a['total'] <=> $b['total'];
        });

        return $cartComparison;
    }

    private function generateRecommendation(array $cartComparison): ?string
    {
        if (empty($cartComparison)) {
            return null;
        }

        $cheapest = reset($cartComparison);
        $recommendations = [];

        if ($cheapest) {
            $recommendations[] = "Geriausia pirkti {$cheapest['store_name']} - €{$cheapest['total']}";

            if ($cheapest['total_savings'] > 0) {
                $recommendations[] = "sutaupyta €{$cheapest['total_savings']}";
            }
        }

        if (count($cartComparison) > 1) {
            $second = next($cartComparison);
            if ($second && ($second['total'] - $cheapest['total']) < 1.0) {
                $recommendations[] = "Arba {$second['store_name']} - €{$second['total']} (skirtumas tik €" . round($second['total'] - $cheapest['total'], 2) . ")";
            }
        }

        return implode(' (', $recommendations) . ')';
    }
}

