<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use App\Models\Discount;
use App\Models\Product;
use App\Models\StoreCategoryDescription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DescriptionGenerationService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private KeywordPageService $keywordPageService,
    ) {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured in DescriptionGenerationService');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function generateStoreDescription(Store $store): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('DescriptionGenerationService is not configured');
            return null;
        }

        $storeData = $this->getStoreData($store);

        try {
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getStoreSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($storeData, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                // Deliberately NOT splicing in generateStoreTopProductsTable() here:
                // that table bakes in today's exact prices/dates, which would make
                // this evergreen description stale again within days.
                return trim($content);
            }

            Log::error('OpenAI API request failed for store description', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for store description', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function generateCategoryDescription(Category $category): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('DescriptionGenerationService is not configured');
            return null;
        }

        $categoryData = $this->getCategoryData($category);

        try {
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getCategorySystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($categoryData, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                // Deliberately NOT splicing in generateCategoryTopProductsTable() here:
                // that table bakes in today's exact prices/dates, which would make
                // this evergreen description stale again within days.
                return trim($content);
            }

            Log::error('OpenAI API request failed for category description', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for category description', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * @return list<array{question: string, answer: string}>|null
     */
    public function generateCategoryFaq(Category $category): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('DescriptionGenerationService is not configured');
            return null;
        }

        $categoryData = $this->getCategoryData($category);

        try {
            $response = Http::timeout(120)
                ->retry(2, 2000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getCategoryFaqSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($categoryData, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed for category FAQ', [
                    'category_id' => $category->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $faq = $this->parseFaqResponse($content);

            if ($faq === null) {
                Log::error('OpenAI API returned invalid FAQ JSON for category', [
                    'category_id' => $category->id,
                    'content' => $content,
                ]);
            }

            return $faq;
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for category FAQ', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * @return list<array{question: string, answer: string}>|null
     */
    public function generateStoreFaq(Store $store): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('DescriptionGenerationService is not configured');
            return null;
        }

        $storeData = $this->getStoreData($store);

        try {
            $response = Http::timeout(120)
                ->retry(2, 2000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getStoreFaqSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($storeData, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed for store FAQ', [
                    'store_id' => $store->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $content = trim((string) $response->json('choices.0.message.content'));
            $faq = $this->parseFaqResponse($content);

            if ($faq === null) {
                Log::error('OpenAI API returned invalid FAQ JSON for store', [
                    'store_id' => $store->id,
                    'content' => $content,
                ]);
            }

            return $faq;
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for store FAQ', [
                'store_id' => $store->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * @return list<array{question: string, answer: string}>|null
     */
    private function parseFaqResponse(string $content): ?array
    {
        $parsed = json_decode($content, true);

        if (!is_array($parsed) || !is_array($parsed['faq'] ?? null)) {
            return null;
        }

        $faq = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) {
                return null;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                return null;
            }

            return ['question' => $question, 'answer' => $answer];
        }, $parsed['faq'])));

        return $faq !== [] ? $faq : null;
    }

    private function getCategoryFaqSystemPrompt(): string
    {
        return "You are a Lithuanian copywriter for a grocery/retail deals aggregator (SuperAkcijos.lt). You will receive JSON data about ONE product category's currently active discounts.

Your task: generate 3-5 short, genuinely useful, EVERGREEN FAQ question/answer pairs in Lithuanian about THIS specific category. This content will stay on the page for weeks without being regenerated, so it must still read as true and sensible long after the exact discounts in this data have expired and been replaced by different ones.

STRICT RULES:
- Output a single JSON object: {\"faq\": [{\"question\": \"...\", \"answer\": \"...\"}]}. No prose outside the JSON.
- DO NOT mention any specific price, specific discount percent number, specific date, or any single named product/SKU from top_discounts as if it is a current fact (e.g. never write things like '-54% iki 2026-08-31' or 'Kopūstai baltagūžiai (Čia) -54%'). Those exact facts will be stale within days.
- DO use the provided data (top_discounts, store_statistics, discount_distribution) as SILENT RESEARCH to understand what kinds of products, product groups, and store patterns are typical for this category — then phrase answers in general, durable terms (e.g. 'šviežios daržovės, tokios kaip kopūstai ar bulvės' instead of naming one exact discounted item; 'nuolaidos šioje kategorijoje dažniausiai siekia apie 20-40%' instead of '-54% iki 2026-08-31'; 'IKI ir Rimi šioje kategorijoje dažnai turi daugiau pasiūlymų' instead of an exact current count).
- Still be genuinely specific to THIS category (its typical product types, typical discount range, typical shopping patterns) — never fall back to generic filler that could apply to any category (e.g. never mention 'bananas' or 'organic products' unless the category is actually about fruit/vegetables).
- Good evergreen topics: what kinds of products in this category tend to have the biggest discounts; roughly how big discounts in this category typically run; which stores tend to be strong in this category; general tips for finding the best deals here (e.g. checking back regularly, comparing stores, filtering by discount size); whether/when this category tends to have seasonal patterns.
- Keep answers to 1-3 sentences, natural conversational Lithuanian, no marketing fluff, no HTML tags.
- If the data is too thin to support genuinely category-specific evergreen answers, return fewer items (minimum 1) rather than padding with generic ones.
";
    }

    private function getStoreFaqSystemPrompt(): string
    {
        return "You are a Lithuanian copywriter for a grocery/retail deals aggregator (SuperAkcijos.lt). You will receive JSON data about ONE store's currently active discounts.

Your task: generate 3-5 short, genuinely useful, EVERGREEN FAQ question/answer pairs in Lithuanian about THIS specific store. This content will stay on the page for weeks without being regenerated, so it must still read as true and sensible long after the exact discounts in this data have expired and been replaced by different ones.

STRICT RULES:
- Output a single JSON object: {\"faq\": [{\"question\": \"...\", \"answer\": \"...\"}]}. No prose outside the JSON.
- DO NOT mention any specific price, specific discount percent number, specific date, or any single named product/SKU from top_discounts or category_statistics as if it is a current fact. Those exact facts will be stale within days.
- DO use the provided data (category_statistics, top_discounts, discount_distribution, essential_products) as SILENT RESEARCH to understand which product categories and kinds of deals are typical for this store — then phrase answers in general, durable terms (e.g. 'dažniausiai daug pasiūlymų būna [category_name] ir [category_name] kategorijose' instead of exact counts; 'nuolaidos šiame tinkle dažniausiai siekia apie 10-30%' instead of an exact percent).
- Still be genuinely specific to THIS store (its typical strong categories, typical discount range, card/loyalty conditions if relevant) — never fall back to generic filler that could describe any store.
- Good evergreen topics: which product categories this store tends to have the most/biggest discounts in; roughly how big discounts at this store typically run; whether a loyalty card unlocks extra discounts here (use card_discounts > 0 as a signal, but phrase qualitatively, not as an exact count); general tips for finding the best deals at this store.
- Keep answers to 1-3 sentences, natural conversational Lithuanian, no marketing fluff, no HTML tags.
- If the data is too thin to support genuinely store-specific evergreen answers, return fewer items (minimum 1) rather than padding with generic ones.
";
    }

    private function getStoreData(Store $store): array
    {
        $activeDiscounts = Discount::whereHas('store', function($query) use ($store) {
            $query->where('id', $store->id);
        })
            ->where(function ($query) {
                $query->where('end_at', '>=', now()->startOfDay())
                    ->orWhereNull('end_at');
            })
            ->with(['product.category', 'store'])
            ->get();

        $topDiscounts = $this->getDiverseTopDiscounts($activeDiscounts, 10)
            ->map(function($discount) {
                return [
                    'name' => $discount->product->name,
                    'store' => $discount->store->name,
                    'category' => $discount->product->category->name,
                    'category_url' => "@https://superakcijos.lt/akcijos/{$discount->product->category->slug}",
                    'product_url' => "@https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                    'original_price' => $discount->original_price,
                    'discounted_price' => $discount->discounted_price,
                    'discount_percent' => $discount->discount_percent,
                    'savings_amount' => round($discount->original_price - $discount->discounted_price, 2),
                    'valid_until' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
                    'condition' => $discount->condition,
                    'is_essential' => $this->isEssentialProduct($discount->product->name),
                    'value_rating' => $this->getValueRating($discount)
                ];
            })
            ->toArray();

        $categoryStats = $activeDiscounts
            ->groupBy('product.category.name')
            ->map(function($discounts) {
                $firstDiscount = $discounts->first();
                return [
                    'name' => $firstDiscount->product->category->name,
                    'url' => "@https://superakcijos.lt/akcijos/{$firstDiscount->product->category->slug}",
                    'count' => $discounts->count(),
                    'avg_discount' => round($discounts->avg('discount_percent'), 1),
                    'min_price' => $discounts->min('discounted_price'),
                    'max_price' => $discounts->max('discounted_price'),
                    'max_discount' => $discounts->max('discount_percent'),
                    'min_discount' => $discounts->min('discount_percent'),
                    'total_savings' => $discounts->sum(function($d) {
                        return $d->original_price - $d->discounted_price;
                    }),
                    'avg_original_price' => round($discounts->avg('original_price'), 2),
                    'avg_discounted_price' => round($discounts->avg('discounted_price'), 2),
                    'products_with_conditions' => $discounts->whereNotNull('condition')->count(),
                    'card_discounts' => $discounts->where('card', true)->count()
                ];
            })
            ->toArray();

        $allStoreCategoryLinks = $activeDiscounts
            ->groupBy('product.category.id')
            ->map(function($discounts) use ($store) {
                $firstDiscount = $discounts->first();
                $category = $firstDiscount->product->category;
                if (!$category || !$category->slug) {
                    return null;
                }
                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'url' => "/akcijos/{$store->slug}/{$category->slug}",
                    'count' => $discounts->count()
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->values();

        // Feed the writer every category the store has active discounts in (not
        // just the top few) so it can mention/link a wider spread for SEO, while
        // still ranked so it knows which ones are strongest.
        $storeCategoryLinks = $allStoreCategoryLinks->take(8)->values()->toArray();

        // Keyword pages are sourced from ALL of the store's categories (not just
        // the top 3) so the description can point to more long-tail keyword pages.
        $keywordPages = $allStoreCategoryLinks
            ->flatMap(fn ($link) => $this->keywordPageService->listPublishedPagesForCategory($link['slug']))
            ->unique('slug')
            ->take(10)
            ->map(fn ($page) => [
                'title' => $page['title'],
                'url' => "@https://superakcijos.lt{$page['href']}",
            ])
            ->values()
            ->all();

        // Only offer the locations/hours page as a link when there's actually
        // location data behind it — otherwise it's a dead-end for the reader.
        $hasStoreLocations = $store->locations()->exists();

        return [
            'store_name' => $store->name,
            'store_url' => "@https://superakcijos.lt/akcijos/{$store->slug}",
            'store_hours_url' => $hasStoreLocations ? "@https://superakcijos.lt/parduotuves/{$store->slug}" : null,
            'store_semantic_research' => $this->getStoreSemanticResearch($store),
            'keyword_pages' => $keywordPages,
            'total_active_discounts' => $activeDiscounts->count(),
            'total_products' => $activeDiscounts->unique('product_id')->count(),
            'total_categories' => $activeDiscounts->unique('product.category_id')->count(),
            'avg_discount_percent' => round($activeDiscounts->avg('discount_percent'), 1),
            'max_discount_percent' => min($activeDiscounts->max('discount_percent'), 100),
            'min_discount_percent' => max($activeDiscounts->min('discount_percent'), 0),
            'total_savings' => ($totalSavings = $activeDiscounts->sum(function($d) {
                return $d->original_price - $d->discounted_price;
            })),
            'avg_savings_per_product' => $activeDiscounts->count() > 0 ? round($totalSavings / $activeDiscounts->count(), 2) : 0,
            'avg_original_price' => round($activeDiscounts->avg('original_price'), 2),
            'avg_discounted_price' => round($activeDiscounts->avg('discounted_price'), 2),
            'products_with_conditions' => $activeDiscounts->whereNotNull('condition')->count(),
            'card_discounts' => $activeDiscounts->where('card', true)->count(),
            'top_discounts' => $topDiscounts,
            'category_statistics' => $categoryStats,
            'store_category_links' => $storeCategoryLinks,
            'valid_date_range' => [
                'earliest_end' => ($earliest = $activeDiscounts->min('end_at')) ? $earliest->format('Y-m-d') : null,
                'latest_end' => ($latest = $activeDiscounts->max('end_at')) ? $latest->format('Y-m-d') : null
            ],
            'discount_distribution' => [
                'under_10_percent' => $activeDiscounts->where('discount_percent', '<', 10)->count(),
                '10_to_20_percent' => $activeDiscounts->whereBetween('discount_percent', [10, 20])->count(),
                '20_to_30_percent' => $activeDiscounts->whereBetween('discount_percent', [20, 30])->count(),
                '30_to_50_percent' => $activeDiscounts->whereBetween('discount_percent', [30, 50])->count(),
                'over_50_percent' => $activeDiscounts->where('discount_percent', '>', 50)->count()
            ],
            'essential_products' => [
                'total' => $activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name);
                })->count(),
                'avg_discount' => round($activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name);
                })->avg('discount_percent'), 1),
                'best_deals' => $activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name) && $d->discount_percent >= 30;
                })->count()
            ]
        ];
    }

    /**
     * Real-world research (verified via web/Google search, not derived from our own DB)
     * about what this store actually is and how people actually search for it — used so
     * the generated copy reflects this store's real business/category instead of reading
     * like a generic supermarket template for every store. See storage/app/store_semantic_research.json.
     */
    private function getStoreSemanticResearch(Store $store): ?array
    {
        static $research = null;

        if ($research === null) {
            $path = storage_path('app/store_semantic_research.json');
            $research = file_exists($path)
                ? (json_decode(file_get_contents($path), true) ?? [])
                : [];
        }

        return $research[$store->slug] ?? null;
    }

    /**
     * Real-world research (verified via web/Google search) about how people
     * actually shop/search this category — see storage/app/category_semantic_research.json.
     */
    private function getCategorySemanticResearch(Category $category): ?array
    {
        static $research = null;

        if ($research === null) {
            $path = storage_path('app/category_semantic_research.json');
            $research = file_exists($path)
                ? (json_decode(file_get_contents($path), true) ?? [])
                : [];
        }

        return $research[$category->slug] ?? null;
    }

    private function getCategoryData(Category $category): array
    {
        $activeDiscounts = Discount::whereHas('product', function($query) use ($category) {
            $query->where('category_id', $category->id);
        })
            ->where(function ($query) {
                $query->where('end_at', '>=', now()->startOfDay())
                    ->orWhereNull('end_at');
            })
            ->with(['product', 'store'])
            ->get();

        $topDiscounts = $this->getDiverseTopDiscounts($activeDiscounts, 10)
            ->map(function($discount) {
                return [
                    'name' => $discount->product->name,
                    'store' => $discount->store->name,
                    'store_url' => "@https://superakcijos.lt/akcijos/{$discount->store->slug}",
                    'product_url' => "@https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                    'original_price' => $discount->original_price,
                    'discounted_price' => $discount->discounted_price,
                    'discount_percent' => $discount->discount_percent,
                    'savings_amount' => round($discount->original_price - $discount->discounted_price, 2),
                    'valid_until' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
                    'condition' => $discount->condition,
                    'is_essential' => $this->isEssentialProduct($discount->product->name),
                    'value_rating' => $this->getValueRating($discount)
                ];
            })
            ->toArray();

        $storeStats = $activeDiscounts
            ->groupBy('store.name')
            ->map(function($discounts) {
                $firstDiscount = $discounts->first();
                
                $topProducts = $discounts
                    ->sortByDesc('discount_percent')
                    ->take(10)
                    ->map(function($discount) {
                        return [
                            'name' => $discount->product->name,
                            'product_url' => "@https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                            'discount_percent' => $discount->discount_percent,
                            'discounted_price' => $discount->discounted_price,
                            'original_price' => $discount->original_price,
                        ];
                    })
                    ->values()
                    ->toArray();
                
                return [
                    'name' => $firstDiscount->store->name,
                    'url' => "@https://superakcijos.lt/akcijos/{$firstDiscount->store->slug}",
                    'slug' => $firstDiscount->store->slug,
                    'count' => $discounts->count(),
                    'avg_discount' => round($discounts->avg('discount_percent'), 1),
                    'min_price' => $discounts->min('discounted_price'),
                    'max_price' => $discounts->max('discounted_price'),
                    'max_discount' => $discounts->max('discount_percent'),
                    'min_discount' => $discounts->min('discount_percent'),
                    'total_savings' => $discounts->sum(function($d) {
                        return $d->original_price - $d->discounted_price;
                    }),
                    'avg_original_price' => round($discounts->avg('original_price'), 2),
                    'avg_discounted_price' => round($discounts->avg('discounted_price'), 2),
                    'products_with_conditions' => $discounts->whereNotNull('condition')->count(),
                    'card_discounts' => $discounts->where('card', true)->count(),
                    'top_products' => $topProducts
                ];
            })
            ->toArray();

        $storeCategoryLinks = $activeDiscounts
            ->groupBy(function($discount) {
                return $discount->store_id . '_' . $discount->product->category_id;
            })
            ->map(function($discounts) use ($category) {
                $firstDiscount = $discounts->first();
                $store = $firstDiscount->store;
                if (!$store || !$store->slug) {
                    return null;
                }
                return [
                    'store_name' => $store->name,
                    'store_slug' => $store->slug,
                    'category_name' => $category->name,
                    'url' => "/akcijos/{$store->slug}/{$category->slug}",
                    'count' => $discounts->count()
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->toArray();

        $keywordPages = $this->keywordPageService->listPublishedPagesForCategory($category->slug);

        return [
            'category_name' => $category->name,
            'category_slug' => $category->slug,
            'category_url' => "@https://superakcijos.lt/akcijos/{$category->slug}",
            'category_semantic_research' => $this->getCategorySemanticResearch($category),
            'keyword_pages' => array_map(fn ($page) => [
                'title' => $page['title'],
                'url' => "@https://superakcijos.lt{$page['href']}",
            ], $keywordPages),
            'total_active_discounts' => $activeDiscounts->count(),
            'total_products' => $activeDiscounts->unique('product_id')->count(),
            'total_stores' => $activeDiscounts->unique('store_id')->count(),
            'avg_discount_percent' => round($activeDiscounts->avg('discount_percent'), 1),
            'max_discount_percent' => min($activeDiscounts->max('discount_percent'), 100),
            'min_discount_percent' => max($activeDiscounts->min('discount_percent'), 0),
            'total_savings' => ($totalSavings = $activeDiscounts->sum(function($d) {
                return $d->original_price - $d->discounted_price;
            })),
            'avg_savings_per_product' => $activeDiscounts->count() > 0 ? round($totalSavings / $activeDiscounts->count(), 2) : 0,
            'avg_original_price' => round($activeDiscounts->avg('original_price'), 2),
            'avg_discounted_price' => round($activeDiscounts->avg('discounted_price'), 2),
            'products_with_conditions' => $activeDiscounts->whereNotNull('condition')->count(),
            'card_discounts' => $activeDiscounts->where('card', true)->count(),
            'top_discounts' => $topDiscounts,
            'store_statistics' => $storeStats,
            'store_category_links' => $storeCategoryLinks,
            'valid_date_range' => [
                'earliest_end' => ($earliest = $activeDiscounts->min('end_at')) ? $earliest->format('Y-m-d') : null,
                'latest_end' => ($latest = $activeDiscounts->max('end_at')) ? $latest->format('Y-m-d') : null
            ],
            'discount_distribution' => [
                'under_10_percent' => $activeDiscounts->where('discount_percent', '<', 10)->count(),
                '10_to_20_percent' => $activeDiscounts->whereBetween('discount_percent', [10, 20])->count(),
                '20_to_30_percent' => $activeDiscounts->whereBetween('discount_percent', [20, 30])->count(),
                '30_to_50_percent' => $activeDiscounts->whereBetween('discount_percent', [30, 50])->count(),
                'over_50_percent' => $activeDiscounts->where('discount_percent', '>', 50)->count()
            ],
            'essential_products' => [
                'total' => $activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name);
                })->count(),
                'avg_discount' => round($activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name);
                })->avg('discount_percent'), 1),
                'best_deals' => $activeDiscounts->filter(function($d) {
                    return $this->isEssentialProduct($d->product->name) && $d->discount_percent >= 30;
                })->count()
            ]
        ];
    }

    private function getStoreSystemPrompt(): string
    {
        return "You are a Lithuanian copywriter who writes HTML descriptions for a deals-aggregator site (superakcijos.lt). Generate rich, SEO-friendly, EVERGREEN prose that exactly follows the structure below using provided JSON data.

This content will stay on the page for weeks without being regenerated. Treat the discount/category JSON data as SILENT RESEARCH to understand this store's typical scale, typical discount range, and which categories/product types tend to be strong here — not as facts to quote directly. NEVER print an exact number copied straight from the JSON (no exact discount counts, no exact percentages, no exact euro amounts, no specific dates like 'iki 2026-08-31'). Round percentages to the nearest 5 or 10 and express counts as qualitative ranges ('dešimtys', 'keli šimtai', etc.). Never mention a specific current end-date for offers — if you need to reference freshness, use an evergreen phrase like 'atnaujinama kiekvieną savaitę'.

CRITICAL — do not write the same generic 'grocery store' description for every store:
- The JSON may include a 'store_semantic_research' object with real, human-verified facts about what this store actually is: 'business_type' (what it actually sells/does), 'distinctive_angle' (what makes it structurally different from a typical grocery store — e.g. a pharmacy, a DIY/hardware chain, a direct-sales catalog brand, a wholesale cash-and-carry, a fashion chain, a wine specialist, an office-supplies retailer), 'real_search_phrases' (genuine phrases people search for this store, some straight from Google's own 'related searches'), and 'notable_categories_or_products' (its real, defining product range).
- If store_semantic_research is present, you MUST let 'business_type' and 'distinctive_angle' shape paragraph 1 and the overall framing — do not default to grocery/supermarket language ('parduotuvėje rasite maisto produktų akcijų' etc.) for a store whose business_type says otherwise (e.g. a pharmacy should talk about vaistai/vitaminai/kosmetika and things like a loyalty/health card, not 'daržovės ir buitinė chemija'; a DIY chain should talk about statybos/remonto/sodo prekės; a direct-sales catalog brand like Tupperware/Avon/Oriflame/Mary Kay has no physical weekly leaflet or in-store card — do not invent one, phrase around catalog/consultant-based sales instead).
- Naturally weave the vocabulary and phrasing style of 2-4 items from 'real_search_phrases' into the prose where they fit grammatically (adapted to correct Lithuanian sentence grammar, not pasted verbatim as a search query) — this keeps the wording genuinely tied to how people actually search for this specific store, instead of generic phrasing that could apply to any store.
- If store_semantic_research is absent (older/smaller store with no research on file), fall back to inferring the store's nature from category_statistics/top_discounts as before.

STRICT OUTPUT FORMAT:
Wrap everything in a single <div class=\"space-y-4\"> element. Do NOT use <strong>/<b>/<em>/<i> tags anywhere in the body paragraphs — bolding random phrases reads as generated AI text. Write plain sentences and let links (<a>) be the only inline markup. Output ONLY the header and 3-4 prose paragraphs below — no tables, no stats grids, no discount-distribution lists.

1) HEADER
- <h2 class=\"text-2xl md:text-3xl font-semibold leading-tight mb-3\"> with title format:
  '[store_name] akcijos ir nuolaidos – naujausi pasiūlymai atnaujinami kiekvieną savaitę'
  (No specific percent number or date in the title.) Sentence case only.

2) PROSE (4-5 paragraphs, each a <p class=\"leading-relaxed\">)
- Paragraph 1: Describe the store's typical scope in natural Lithuanian, using qualitative terms derived from total_active_discounts/avg_savings_per_product magnitude (e.g. 'čia rasite dešimtis ar šimtus akcijų', 'galite sutaupyti kelis eurus perkant kasdienes prekes') — never an exact digit copied from the JSON. Mention main product areas using category context from the data. Do NOT include any links in this first paragraph.
- Paragraph 2: Describe qualitatively which categories tend to be strongest at this store (using category_statistics, picking the top few by 'count', but describing rank/strength in words, not exact counts/percents). Include 4-6 store+category links naturally across one or two sentences using format '<a href=\"[url]\">[name]</a>' where url/name come from store_category_links (remove leading '@' if present) — use as many of the DISTINCT categories provided in store_category_links as read naturally, favoring breadth over repeating the same one or two categories.
- Paragraph 3 (only if store_category_links has more categories than were used in paragraph 2): Mention the remaining categories not yet linked in paragraph 2, again as natural inline links '<a href=\"[url]\">[name]</a>', framed as the store's wider assortment (e.g. 'Be to, rasite pasiūlymų ir [category] bei [category] kategorijose.'). Skip this paragraph if every category from store_category_links was already linked in paragraph 2, or if store_category_links has 3 or fewer entries.
- Paragraph 4: A durable tip-style paragraph — e.g. general advice for finding the best deals at this store (comparing categories, checking back regularly, using a loyalty card if card_discounts > 0, phrased qualitatively not as an exact count). If 'store_hours_url' is non-null, naturally mention that shoppers can check the store's actual locations and opening hours via a link like '<a href=\"[store_hours_url]\">parduotuvių adresus ir darbo laiką</a>' (adapt the anchor phrase to the sentence, strip leading '@' from the URL) — this is a genuinely useful, non-generic pointer, not filler. Skip this mention entirely if store_hours_url is null.
- Paragraph 5 (only if keyword_pages is non-empty): Naturally mention 4-6 related, popular search topics available at this store as a helpful pointer, linking each via '<a href=\"[url]\">[title]</a>' where url/title come from keyword_pages (remove leading '@' from the url) — use as many distinct keyword_pages entries as read naturally, favoring breadth. Do not invent topics not present in keyword_pages; skip this paragraph entirely if keyword_pages is empty.
- Do NOT add any paragraph about a specific validity window or end date.

OUTPUT RULES:
- Language: Lithuanian.
- Remove any leading '@' from URLs.
- Never invent stores, categories, or keyword topics; only use names present in the provided JSON.
- Never print an exact number, exact percent, exact euro amount, or exact date copied from the JSON anywhere in the output — always round or describe qualitatively.
- Do not use <strong>/<b>/<em>/<i> anywhere — plain sentences read more natural and less like generated text.
- Ensure the heading follows sentence case (only the first word capitalized).
- Keep tone promotional but natural, conversational, varied sentence structure; avoid repeating the same phrase across paragraphs.
- Grammar: NEVER use the construction 'Pas [store_name]' (e.g. 'Pas Rimi rasite...') — this is grammatically incorrect Lithuanian for a store name. Instead decline the store name properly, e.g. '[store_name] parduotuvėje rasite...', '[store_name] siūlo...', or similar correctly-declined phrasing.
";
    }

    private function getCategorySystemPrompt(): string
    {
        return "You are a Lithuanian copywriter who writes HTML descriptions for a deals-aggregator site (superakcijos.lt). Generate rich, SEO-friendly, EVERGREEN prose that exactly follows the structure below using provided JSON data.

This content will stay on the page for weeks without being regenerated. Treat the discount/store JSON data as SILENT RESEARCH to understand this category's typical scale, typical discount range, and which stores/product types tend to be strong here — not as facts to quote directly. NEVER print an exact number copied straight from the JSON (no exact discount counts, no exact percentages, no exact euro amounts, no specific dates like 'iki 2026-08-31'). Round percentages to the nearest 5 or 10 and express counts as qualitative ranges ('dešimtys', 'keli šimtai', etc.). Never mention a specific current end-date for offers — if you need to reference freshness, use an evergreen phrase like 'atnaujinama kiekvieną savaitę'.

CRITICAL — do not write the same generic 'browse the deals' description for every category:
- The JSON may include a 'category_semantic_research' object with real, human-verified facts about how people actually shop this category: 'distinctive_angle' (what makes deal-hunting here different — e.g. highly seasonal fresh produce, brand-loyalty-driven coffee/tea, bulky durable goods rarely discounted, impulse/snack buying, pet-owner needs, baby-safety-conscious buying), 'seasonal_patterns' (a real seasonal buying pattern, or 'none particularly seasonal' if not applicable — never invent a seasonal claim it doesn't support), 'real_search_phrases' (genuine phrases people search, some straight from Google's own 'related searches'), and 'notable_product_types' (its real, defining product range).
- If category_semantic_research is present, let 'distinctive_angle' and 'seasonal_patterns' genuinely shape the framing and tips paragraph — a seasonal produce category should talk about buying in-season and comparing fresh-stock prices; a durable/rarely-discounted category should set realistic expectations instead of promising huge constant discounts; a brand-loyalty category (coffee, cosmetics) should acknowledge that brand preference matters as much as price.
- Naturally weave the vocabulary/style of 2-4 items from 'real_search_phrases' into the prose where grammatically natural (adapted to correct Lithuanian sentence grammar, never pasted verbatim as a raw search query) so wording stays genuinely tied to how people search this specific category, not generic phrasing that could apply to any category.
- If category_semantic_research is absent, fall back to inferring the category's nature from store_statistics/top_discounts as before.

STRICT OUTPUT FORMAT:
Wrap everything in a single <div class=\"category-description-block p-0 lg:p-4\"> element. Do NOT use <strong>/<b>/<em>/<i> tags anywhere in the body paragraphs — bolding random phrases reads as generated AI text. Write plain sentences and let links (<a>) be the only inline markup. Output ONLY the header and 3-4 prose paragraphs below — no tables, no stats grids, no per-store comparison sections.

1) HEADER
- <h2 class=\"text-3xl font-bold mb-6 leading-tight\"> with title format:
  '[category_name] akcijos: atraskite naujausius pasiūlymus'
  (No specific percent number in the title.) Sentence case only.

2) PROSE (3-4 paragraphs, each a <p class=\"mb-4 text-gray-700\">, last one <p class=\"mb-6 text-gray-700\">)
- Paragraph 1: Start with a question or engaging statement about the category, naming it plainly (no bold/quotes-as-emphasis). CRITICAL: When mentioning specific product types, include product links from top_discounts. Match product names from top_discounts to mentioned product types and create links using format: '<a href=\"[product_url]\">[product_type]</a>' where product_url is from top_discounts.product_url (remove leading '@' if present). Example: 'Ruošiate pietus, planuojate šventinį stalą ar tiesiog pildote šaldytuvą? Kategorija [category_name] yra puiki vieta sutaupyti, neaukojant kokybės!' Do not state an exact discount percentage here.
- Paragraph 2: Describe the typical scale qualitatively, e.g. 'Čia rasite dešimtis akcijų' (never the exact total_active_discounts number) and the typical discount range rounded, e.g. 'Nuolaidos dažniausiai siekia apie 10-30 %' (never the exact avg_discount_percent). Do NOT mention any specific validity date. CRITICAL: Include product links from top_discounts when mentioning product types in this paragraph as well.
- Paragraph 3: Describe qualitatively (no exact counts/percents) which stores tend to be strong in this category and what kind of products/assortment they're known for, drawing on store_statistics and store_category_links naturally in running prose (not a table) — e.g. 'Platų pasirinkimą dažnai rasite <a href=\"[store_category_links.url]\">[store_name]</a> parduotuvėje, o <a href=\"[url]\">[store_name]</a> pasižymi patraukliomis kainomis [product type].' Mention 2-3 stores this way, using store_category_links for the hrefs (remove leading '@' if present) and store_statistics for which product types each store tends to be strong in (via their top_products).
- Paragraph 4 (only if keyword_pages is non-empty): Naturally mention 2-4 related, popular search topics within this category as a helpful pointer for the reader, linking each via '<a href=\"[url]\">[title]</a>' where url/title come from keyword_pages (remove leading '@' from the url). Phrase it inviting, e.g. 'Jei ieškote ko nors konkretesnio, pasižiūrėkite ir <a href=\"...\">...</a> ar <a href=\"...\">...</a> pasiūlymus.' Do not invent topics not present in keyword_pages; skip this paragraph entirely if keyword_pages is empty.

OUTPUT RULES:
- Language: Lithuanian.
- Remove any leading '@' from URLs.
- Never invent stores or keyword topics; only use names/titles present in the provided JSON.
- Never print an exact number, exact percent, exact euro amount, or exact date copied from the JSON anywhere in the output — always round or describe qualitatively.
- Do not use <strong>/<b>/<em>/<i> anywhere — plain sentences read more natural and less like generated text.
- Ensure the heading follows sentence case (only the first word capitalized).
- Keep tone promotional but natural, conversational, varied sentence structure; avoid repeating the same phrase across paragraphs.
";
    }

    private function isEssentialProduct(string $productName): bool
    {
        $essentialKeywords = [
            'pienas', 'duona', 'obuoliai', 'bulvės', 'morkos', 'svogūnai', 'kiaušiniai',
            'sviestas', 'sūris', 'jogurtas', 'grietinėlė', 'makaronai', 'ryžiai',
            'aliejus', 'druska', 'cukrus', 'miltai', 'konservai', 'sriubos',
            'šampūnas', 'muilas', 'dantų pasta', 'tualetinis popierius',
            'skalbimo milteliai', 'indų ploviklis', 'valymo priemonės'
        ];

        $productNameLower = mb_strtolower($productName);
        foreach ($essentialKeywords as $keyword) {
            if (mb_strpos($productNameLower, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getValueRating($discount): string
    {
        $discountPercent = $discount->discount_percent;
        $savingsAmount = $discount->original_price - $discount->discounted_price;

        if ($discountPercent >= 50 && $savingsAmount >= 2) {
            return 'excellent';
        } elseif ($discountPercent >= 30 && $savingsAmount >= 1) {
            return 'very_good';
        } elseif ($discountPercent >= 20 && $savingsAmount >= 0.5) {
            return 'good';
        } elseif ($discountPercent >= 10) {
            return 'fair';
        } else {
            return 'basic';
        }
    }

    private function getDiverseTopDiscounts($discounts, int $limit = 10)
    {
        $filteredDiscounts = $discounts->where('product.category_id', '!=', 535);

        $selectedDiscounts = collect();
        $usedBrands = collect();
        $usedProductTypes = collect();

        // Sort by discount percentage descending
        $sortedDiscounts = $filteredDiscounts->sortByDesc('discount_percent');

        foreach ($sortedDiscounts as $discount) {
            if ($selectedDiscounts->count() >= $limit) {
                break;
            }

            $productName = $discount->product->name;
            $brand = $this->extractBrand($productName);
            $productType = $this->extractProductType($productName);

            if ($usedBrands->where('brand', $brand)->count() >= 2) {
                continue;
            }

            if ($usedProductTypes->contains($productType)) {
                continue;
            }

            $selectedDiscounts->push($discount);
            $usedBrands->push(['brand' => $brand, 'product' => $productName]);
            $usedProductTypes->push($productType);
        }
        if ($selectedDiscounts->count() < $limit) {
            $remaining = $limit - $selectedDiscounts->count();
            $selectedIds = $selectedDiscounts->pluck('id');

            $additionalDiscounts = $sortedDiscounts
                ->whereNotIn('id', $selectedIds)
                ->take($remaining);

            $selectedDiscounts = $selectedDiscounts->merge($additionalDiscounts);
        }

        return $selectedDiscounts->take($limit);
    }

    private function extractBrand(string $productName): string
    {
        $brands = [
            'PAMPERS', 'HUGGIES', 'ELMEX', 'COLGATE', 'SENSODYNE',
            'NUTRI', 'BALTIJA', 'ŽEMAITIJA', 'DOVANA', 'VILKYŠKIAI',
            'LIDL', 'MAXIMA', 'RIMI', 'NORFA', 'IKI', 'BARBORA',
            'GOWIPES', 'MAŽYLIS', 'AUKŠTAITIŠKI', 'DOVANA'
        ];

        $productNameUpper = mb_strtoupper($productName);

        foreach ($brands as $brand) {
            if (mb_strpos($productNameUpper, $brand) !== false) {
                return $brand;
            }
        }

        $words = explode(' ', $productName);
        return $words[0] ?? 'UNKNOWN';
    }

    private function extractProductType(string $productName): string
    {
        $productName = mb_strtoupper($productName);

        $patterns = [
            '/\b\d+\s*(CM|ML|G|KG|VNT|PAK|S\d+|M\d+|L\d+|XL\d+)\b/',
            '/\b(S\d+|M\d+|L\d+|XL\d+)\b/',
            '/\b\d+\s*VNT\.?\s*\/\s*PAK\.?\b/',
            '/\b\d+\s*ML\b/',
            '/\b\d+\s*G\b/',
            '/\b\d+\s*CM\b/'
        ];

        foreach ($patterns as $pattern) {
            $productName = preg_replace($pattern, '', $productName);
        }

        $brands = ['PAMPERS', 'HUGGIES', 'ELMEX', 'COLGATE', 'SENSODYNE', 'NUTRI', 'BALTIJA', 'ŽEMAITIJA', 'DOVANA', 'VILKYŠKIAI', 'GOWIPES', 'MAŽYLIS', 'AUKŠTAITIŠKI'];
        foreach ($brands as $brand) {
            $productName = str_replace($brand, '', $productName);
        }

        $productName = trim(preg_replace('/\s+/', ' ', $productName));
        return $productName ?: 'UNKNOWN';
    }

    private function getStoreCategoryData(Store $store, Category $category): ?array
    {
        $activeDiscounts = Discount::where('store_id', $store->id)
            ->whereHas('product', function ($query) use ($category) {
                $query->where('category_id', $category->id);
            })
            ->where(function ($query) {
                $query->where('end_at', '>=', now()->startOfDay())
                    ->orWhereNull('end_at');
            })
            ->with(['product.category', 'store'])
            ->get();

        if ($activeDiscounts->isEmpty()) {
            return null;
        }

        $topDiscounts = $this->getDiverseTopDiscounts($activeDiscounts, 6)
            ->map(function ($discount) {
                return [
                    'name' => $discount->product->name,
                    'product_url' => "@https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                    'discount_percent' => $discount->discount_percent,
                ];
            })
            ->toArray();

        return [
            'store_name' => $store->name,
            'category_name' => $category->name,
            'page_url' => "@https://superakcijos.lt/akcijos/{$store->slug}/{$category->slug}",
            'store_semantic_research' => $this->getStoreSemanticResearch($store),
            'category_semantic_research' => $this->getCategorySemanticResearch($category),
            'total_active_discounts' => $activeDiscounts->count(),
            'avg_discount_percent' => round($activeDiscounts->avg('discount_percent'), 1),
            'max_discount_percent' => min($activeDiscounts->max('discount_percent'), 100),
            'top_discounts' => $topDiscounts,
        ];
    }

    private function getStoreCategorySystemPrompt(): string
    {
        return "You are a Lithuanian copywriter for a deals-aggregator site (superakcijos.lt). Generate a SHORT, SEO-friendly, EVERGREEN intro for a page combining ONE store and ONE product category (e.g. 'Rimi' + 'Vaisiai ir daržovės').

This content stays on the page for weeks. Treat the discount JSON as SILENT RESEARCH — never print an exact count/percent/date copied straight from it; round percentages to the nearest 5 or 10 and use qualitative counts ('keliolika', 'dešimtys'). Never mention a specific end-date; use 'atnaujinama kiekvieną savaitę' if referencing freshness.

CRITICAL — ground this in the REAL Google-search data provided, don't write generic GPT filler:
- This store already has its OWN full description page (covering what kind of business it is, its distinctive angle, its overall category spread) and this category already has its OWN full description page (covering its seasonality, distinctive angle, typical products) — do not restate those generic facts here, that would be duplicate content across pages and hurts SEO.
- 'store_semantic_research.real_search_phrases' and 'category_semantic_research.real_search_phrases' contain ACTUAL phrases real people typed into Google (many straight from Google's own 'related searches' widget) about this store and this category. You MUST select 1-2 phrases from each (where they exist) that are plausible for this specific store+category intersection, and let their exact wording/vocabulary genuinely shape a sentence — adapted to correct Lithuanian grammar, never pasted as a raw query string. This is REQUIRED, not optional decoration: if you skip this and instead write generic filler ('platus asortimentas', 'verta palyginti kainas', 'akcijos atnaujinamos kiekvieną savaitę' with nothing store/category-specific), you have failed the task.
- Do NOT just restate business_type/distinctive_angle as a sentence ('X is a [business_type]') — that belongs on X's own page. Instead let those fields silently guide which real_search_phrases and product angle you pick.
- Also write about the specific intersection using top_discounts: its actual current product range here, a genuinely combo-specific observation — not a generic 'compare prices' tip that could apply to any store+category pair.
- Naturally weave in 1-3 real product names from 'top_discounts' as inline links using '<a href=\"[product_url]\">[name]</a>' (strip leading '@' from URLs) where grammatically natural.
- If the discount data is too thin to say anything genuinely specific beyond generic facts, keep it to a single short sentence rather than padding with restated store/category facts.

STRICT OUTPUT FORMAT: wrap everything in a single <div class=\"space-y-3\">. Output ONLY 1-2 short paragraphs (<p class=\"leading-relaxed\">), no heading, no table, no stats grid — this intro sits ABOVE a separately-rendered top-products table, so don't repeat exact numbers or list many products (top_discounts is just for 1-3 natural inline links).

OUTPUT RULES:
- Language: Lithuanian.
- Remove any leading '@' from URLs.
- Never invent product names, stores, or categories not present in the JSON.
- Never print an exact number/percent/date copied from the JSON.
- Grammar: never use 'Pas [store_name]' — decline the store name properly instead.
";
    }

    public function generateStoreCategoryIntro(Store $store, Category $category): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('DescriptionGenerationService is not configured');
            return null;
        }

        $data = $this->getStoreCategoryData($store, $category);

        if ($data === null) {
            return null;
        }

        try {
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getStoreCategorySystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($data, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if ($response->successful()) {
                return trim($response->json('choices.0.message.content'));
            }

            Log::error('OpenAI API request failed for store+category intro', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for store+category intro', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public function saveStoreCategoryIntro(Store $store, Category $category): bool
    {
        $html = $this->generateStoreCategoryIntro($store, $category);

        if (!$html) {
            return false;
        }

        StoreCategoryDescription::updateOrCreate(
            [
                'store_id' => $store->id,
                'category_id' => $category->id,
            ],
            [
                'intro_html' => $html,
            ]
        );

        return true;
    }

}
