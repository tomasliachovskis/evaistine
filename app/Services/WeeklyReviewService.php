<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class WeeklyReviewService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured in WeeklyReviewService');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function generateWeeklyReview(?Carbon $weekStart = null): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('WeeklyReviewService is not configured');
            return null;
        }

        if (!$weekStart) {
            $weekStart = $this->getPreviousWeekStart();
        }

        $weekEnd = (clone $weekStart)->endOfWeek();

        $discounts = $this->getDiscountsForWeek($weekStart, $weekEnd);

        if ($discounts->isEmpty()) {
            Log::warning("No discounts found for week {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");
            return null;
        }

        $data = $this->prepareReviewData($discounts, $weekStart, $weekEnd);

        try {
            $response = Http::timeout(180)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt()
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($data, JSON_UNESCAPED_UNICODE)
                        ]
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $articleContent = trim($content);

                $title = $this->generateTitle($data, $weekStart);
                $slug = $this->generateSlug($title);
                $metaTitle = $this->generateMetaTitle($title);
                $metaDescription = $this->generateMetaDescription($data, $weekStart);

                return [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $articleContent,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'published_at' => now(),
                ];
            }

            Log::error('OpenAI API request failed for weekly review', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for weekly review', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function getPreviousWeekStart(): Carbon
    {
        $now = now();
        $lastWeek = $now->copy()->subWeek();
        return $lastWeek->startOfWeek();
    }

    private function getDiscountsForWeek(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        $excludedCategoryNames = ['Namų ūkio ir laisvalaikio prekės'];
        $excludedCategoryIds = Category::whereIn('name', $excludedCategoryNames)->pluck('id')->toArray();

        $discountsQuery = Discount::where(function ($query) use ($weekStart, $weekEnd) {
            $query->where(function ($q) use ($weekStart, $weekEnd) {
                $q->where(function ($subQ) use ($weekStart, $weekEnd) {
                    $subQ->where('start_at', '<=', $weekEnd)
                        ->where(function ($subSubQ) use ($weekStart) {
                            $subSubQ->where('end_at', '>=', $weekStart)
                                ->orWhereNull('end_at');
                        });
                })
                ->orWhere(function ($subQ) use ($weekStart, $weekEnd) {
                    $subQ->whereNull('start_at')
                        ->where(function ($subSubQ) use ($weekStart, $weekEnd) {
                            $subSubQ->whereBetween('end_at', [$weekStart, $weekEnd])
                                ->orWhereNull('end_at');
                        });
                });
            });
        })
        ->with(['product.category', 'store'])
        ->whereHas('product', function ($query) use ($excludedCategoryIds) {
            $query->whereNotNull('slug')
                ->whereNotIn('category_id', $excludedCategoryIds);
        })
        ->whereHas('store', function ($query) {
            $query->whereNotNull('slug');
        });

        $historyQuery = DiscountHistory::where(function ($query) use ($weekStart, $weekEnd) {
            $query->where(function ($q) use ($weekStart, $weekEnd) {
                $q->where(function ($subQ) use ($weekStart, $weekEnd) {
                    $subQ->where('start_at', '<=', $weekEnd)
                        ->where(function ($subSubQ) use ($weekStart) {
                            $subSubQ->where('end_at', '>=', $weekStart)
                                ->orWhereNull('end_at');
                        });
                })
                ->orWhere(function ($subQ) use ($weekStart, $weekEnd) {
                    $subQ->whereNull('start_at')
                        ->where(function ($subSubQ) use ($weekStart, $weekEnd) {
                            $subSubQ->whereBetween('end_at', [$weekStart, $weekEnd])
                                ->orWhereNull('end_at');
                        });
                });
            });
        })
        ->with(['product.category', 'store'])
        ->whereHas('product', function ($query) use ($excludedCategoryIds) {
            $query->whereNotNull('slug')
                ->whereNotIn('category_id', $excludedCategoryIds);
        })
        ->whereHas('store', function ($query) {
            $query->whereNotNull('slug');
        });

        $discounts = $discountsQuery->get();
        $histories = $historyQuery->get();

        $histories = $histories->map(function ($history) {
            if (is_int($history->discount_percent)) {
                $history->discount_percent = (float) $history->discount_percent;
            }
            return $history;
        });

        $merged = $discounts->merge($histories);

        $unique = $merged->unique(function ($item) {
            return $item->product_id . '_' . $item->store_id;
        });

        return $unique;
    }

    private function prepareReviewData(Collection $discounts, Carbon $weekStart, Carbon $weekEnd): array
    {
        $topProducts = $this->getTopProducts($discounts, 25);
        $storeStats = $this->getStoreStatistics($discounts);
        $categoryStats = $this->getCategoryStatistics($discounts);
        $categoryComparisons = $this->getCategoryPriceComparisons($discounts);
        
        return [
            'week_period' => [
                'start' => $weekStart->format('Y-m-d'),
                'end' => $weekEnd->format('Y-m-d'),
                'start_formatted' => $weekStart->format('Y mėn. d d.'),
                'end_formatted' => $weekEnd->format('Y mėn. d d.'),
            ],
            'summary' => [
                'total_discounts' => $discounts->count(),
                'total_products' => $discounts->unique('product_id')->count(),
                'total_stores' => $discounts->unique('store_id')->count(),
                'total_categories' => $discounts->unique('product.category_id')->count(),
                'avg_discount_percent' => round($discounts->avg('discount_percent'), 1),
                'max_discount_percent' => min($discounts->max('discount_percent'), 100),
                'total_savings' => round($discounts->sum(function ($d) {
                    return $d->original_price - $d->discounted_price;
                }), 2),
            ],
            'top_products' => $topProducts,
            'store_statistics' => $storeStats,
            'category_statistics' => $categoryStats,
            'category_price_comparisons' => $categoryComparisons,
        ];
    }

    private function getTopProducts(Collection $discounts, int $limit): array
    {
        $sorted = $discounts->sortByDesc(function ($discount) {
            $discountScore = $discount->discount_percent;
            $savingsScore = ($discount->original_price - $discount->discounted_price) * 10;
            return $discountScore + $savingsScore;
        });

        $usedImages = [];
        $products = [];

        foreach ($sorted as $discount) {
            if (count($products) >= $limit) {
                break;
            }

            $imageUrl = $discount->product->image_url;
            if ($imageUrl && in_array($imageUrl, $usedImages)) {
                continue;
            }

            if ($imageUrl) {
                $usedImages[] = $imageUrl;
            }

            $products[] = [
                'name' => $discount->product->name,
                'store' => $discount->store->name,
                'store_slug' => $discount->store->slug,
                'store_url' => "https://superakcijos.lt/akcijos/{$discount->store->slug}",
                'category' => $discount->product->category->name,
                'category_slug' => $discount->product->category->slug,
                'category_url' => "https://superakcijos.lt/akcijos/{$discount->product->category->slug}",
                'product_slug' => $discount->product->slug,
                'product_url' => "https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                'image_url' => $imageUrl,
                'original_price' => $discount->original_price,
                'discounted_price' => $discount->discounted_price,
                'discount_percent' => $discount->discount_percent,
                'savings_amount' => round($discount->original_price - $discount->discounted_price, 2),
                'valid_until' => $discount->end_at ? $discount->end_at->format('Y-m-d') : null,
            ];
        }

        return $products;
    }

    private function getStoreStatistics(Collection $discounts): array
    {
        return $discounts->groupBy('store_id')->map(function ($storeDiscounts) {
            $first = $storeDiscounts->first();
            $store = $first->store;

            return [
                'name' => $store->name,
                'slug' => $store->slug,
                'url' => "https://superakcijos.lt/akcijos/{$store->slug}",
                'discount_count' => $storeDiscounts->count(),
                'product_count' => $storeDiscounts->unique('product_id')->count(),
                'avg_discount_percent' => round($storeDiscounts->avg('discount_percent'), 1),
                'max_discount_percent' => min($storeDiscounts->max('discount_percent'), 100),
                'total_savings' => round($storeDiscounts->sum(function ($d) {
                    return $d->original_price - $d->discounted_price;
                }), 2),
                'top_products' => $storeDiscounts->sortByDesc('discount_percent')->take(5)->map(function ($d) {
                    return [
                        'name' => $d->product->name,
                        'product_url' => "https://superakcijos.lt/akcijos/{$d->product->category->slug}/{$d->product->slug}",
                        'discount_percent' => $d->discount_percent,
                        'discounted_price' => $d->discounted_price,
                    ];
                })->values()->toArray(),
            ];
        })->sortByDesc('discount_count')->values()->toArray();
    }

    private function getCategoryStatistics(Collection $discounts): array
    {
        return $discounts->groupBy('product.category_id')->map(function ($categoryDiscounts) {
            $first = $categoryDiscounts->first();
            $category = $first->product->category;

            return [
                'name' => $category->name,
                'slug' => $category->slug,
                'url' => "https://superakcijos.lt/akcijos/{$category->slug}",
                'discount_count' => $categoryDiscounts->count(),
                'product_count' => $categoryDiscounts->unique('product_id')->count(),
                'store_count' => $categoryDiscounts->unique('store_id')->count(),
                'avg_discount_percent' => round($categoryDiscounts->avg('discount_percent'), 1),
                'max_discount_percent' => min($categoryDiscounts->max('discount_percent'), 100),
                'min_price' => $categoryDiscounts->min('discounted_price'),
                'max_price' => $categoryDiscounts->max('discounted_price'),
                'top_products' => $categoryDiscounts->sortByDesc('discount_percent')->take(5)->map(function ($d) {
                    return [
                        'name' => $d->product->name,
                        'store' => $d->store->name,
                        'product_url' => "https://superakcijos.lt/akcijos/{$d->product->category->slug}/{$d->product->slug}",
                        'discount_percent' => $d->discount_percent,
                        'discounted_price' => $d->discounted_price,
                    ];
                })->values()->toArray(),
            ];
        })->sortByDesc('discount_count')->take(15)->values()->toArray();
    }

    private function getCategoryPriceComparisons(Collection $discounts): array
    {
        $categories = $discounts->groupBy('product.category_id');
        $usedImages = [];
        $comparisons = [];

        foreach ($categories as $categoryId => $categoryDiscounts) {
            $first = $categoryDiscounts->first();
            $category = $first->product->category;

            $cheapestByStore = $categoryDiscounts->groupBy('store_id')->map(function ($storeDiscounts) {
                return $storeDiscounts->sortBy('discounted_price')->first();
            })->sortBy('discounted_price');

            if ($cheapestByStore->isEmpty()) {
                continue;
            }

            $cheapest = $cheapestByStore->first();
            $allStores = [];
            $storeCount = 0;

            foreach ($cheapestByStore->take(3) as $discount) {
                $imageUrl = $discount->product->image_url;
                if ($imageUrl && in_array($imageUrl, $usedImages)) {
                    $imageUrl = null;
                } elseif ($imageUrl) {
                    $usedImages[] = $imageUrl;
                }

                $allStores[] = [
                    'store_name' => $discount->store->name,
                    'store_slug' => $discount->store->slug,
                    'store_url' => "https://superakcijos.lt/akcijos/{$discount->store->slug}",
                    'product_name' => $discount->product->name,
                    'product_url' => "https://superakcijos.lt/akcijos/{$discount->product->category->slug}/{$discount->product->slug}",
                    'product_image_url' => $imageUrl,
                    'discounted_price' => $discount->discounted_price,
                    'original_price' => $discount->original_price,
                    'discount_percent' => $discount->discount_percent,
                ];
                $storeCount++;
            }

            if ($storeCount === 0) {
                continue;
            }

            $comparisons[] = [
                'category_name' => $category->name,
                'category_slug' => $category->slug,
                'category_url' => "https://superakcijos.lt/akcijos/{$category->slug}",
                'cheapest_store' => $cheapest->store->name,
                'cheapest_store_slug' => $cheapest->store->slug,
                'cheapest_product' => $cheapest->product->name,
                'cheapest_price' => $cheapest->discounted_price,
                'stores_comparison' => $allStores,
            ];
        }

        return array_slice($comparisons, 0, 8);
    }

    private function getSystemPrompt(): string
    {
        return "You are a Lithuanian copywriter who writes SEO-optimized weekly review blog posts about grocery store discounts. Generate rich, engaging content that follows the exact structure below using provided JSON data.

STRICT OUTPUT FORMAT:
Use simple HTML markup without Tailwind classes. Use only: <p>, <h2>, <h3>, <b>, <a>, <img> tags.

HTML MARKUP RULES:
- Use <p> tags for paragraphs
- Use <h2> for main section headings
- Use <h3> for subsection headings
- Use <b> tags (not <strong>) for bold emphasis
- Use <a href=\"https://superakcijos.lt/akcijos/{slug}\"> for internal links
- Include product images using <img src=\"{image_url}\" alt=\"{product_name}\"> tags naturally in content
- Natural integration of links within paragraph text

CONTENT STRUCTURE (700-1000+ words):

1) INTRODUCTION (H2: \"Savaitės akcijos: geriausios nuolaidos ir pasiūlymai\")
- Engaging paragraph about the week's deals (include \"savaitės akcijos\" keyword)
- Mention total_discounts, total_stores, avg_discount_percent from summary
- Include 2-3 store links naturally in text

2) STORE REVIEW SECTION (H2: \"Parduotuvių apžvalga: kur buvo geriausios akcijos?\")
- CRITICAL: Include ALL stores from store_statistics (Maxima, Iki, Rimi, Lidl, Norfa - all that appear in data)
- For each store in store_statistics, create H3 subsection: \"{store_name} akcijos šią savaitę\"
- Include store-specific keywords (e.g., \"Maxima akcija\", \"Iki akcija\", \"Rimi akcija\", \"Lidl akcija\", \"Norfa akcija\")
- Include store link: <a href=\"{store_url}\">{store_name} akcijos</a>
- Mention discount_count, avg_discount_percent, total_savings
- Include 2-3 product links from top_products per store
- Compare stores naturally
- Do NOT skip any stores - if a store appears in store_statistics, it MUST be included in the review

3) CATEGORY PRICE COMPARISON SECTIONS (H2 or H3)
- Create 5-8 sections with headings like:
  - \"Kur buvo pigiausia {category_name} šią savaitę?\"
  - \"Kur pigiausia {category_name}?\"
- For each category in category_price_comparisons:
  - Use heading: \"Kur buvo pigiausia {category_name} šią savaitę?\" or \"Kur pigiausia {category_name}?\"
  - Compare prices across stores from stores_comparison
  - Identify cheapest_store and cheapest_product
  - Include 2-3 product links per section
  - Include store links
  - Include product images using <img> tags
  - Use natural language to describe the comparison

4) CATEGORY REVIEW SECTION (H2: \"Kategorijų apžvalga: kurios kategorijos turėjo geriausias nuolaidas?\")
- Cover top 5-8 categories from category_statistics
- Include category-specific keywords (category name + \"akcijos\")
- Include category links: <a href=\"{category_url}\">{category_name} akcijos</a>
- Mention discount_count, avg_discount_percent
- Include 2-3 product links per category from top_products

5) FEATURED PRODUCTS SECTION (H2: \"Pigiausios prekės šią savaitę\")
- Highlight top 10-15 products from top_products
- Include product links: <a href=\"{product_url}\">{product_name}</a>
- Include product images using <img> tags (each image should appear only once in the entire article)
- Mention discount_percent and savings_amount
- Use \"pigiausios prekės\" or \"geriausios nuolaidos\" keywords
- IMPORTANT: Do not repeat the same product image multiple times in the article

6) SUMMARY AND RECOMMENDATIONS (H2: \"Išvados ir rekomendacijos\")
- Summarize the week's best deals
- Provide shopping tips
- Include 2-3 final store/category links

SEO KEYWORD REQUIREMENTS:
- Primary keywords: \"akcija\", \"akcijos\", \"nuolaidos\", \"savaitės akcijos\", \"pigiausios prekės\", \"geriausios nuolaidos\"
- Store-specific keywords (MUST include): \"Maxima akcija\", \"Iki akcija\", \"Rimi akcija\", \"Lidl akcija\", \"Norfa akcija\"
- Category-specific keywords: category name + \"akcijos\" (e.g., \"vaisių akcijos\", \"mėsos akcijos\")
- Long-tail keywords: \"kur pigiausia X\", \"kur buvo pigiausia X šią savaitę\"
- Use keywords naturally in headings (H2, H3) and throughout content
- Include keywords in image alt text

LINK REQUIREMENTS (MINIMUM 15-25+ LINKS):
- At least 1 link per major store (Maxima, Iki, Rimi, Lidl, Norfa) = 5 links
- At least 5-8 category links
- At least 10-15+ product links (more is better)
- Links must be naturally integrated in text, not just listed
- Store links: <a href=\"{store_url}\">{store_name} akcijos</a>
- Category links: <a href=\"{category_url}\">{category_name} akcijos</a>
- Product links: <a href=\"{product_url}\">{product_name}</a>

OUTPUT RULES:
- Language: Lithuanian
- Use data fields exactly as provided from JSON
- Remove any leading '@' from URLs if present
- Always include the exact section sequence
- Never invent stores or categories; use only provided data
- Numbers: format money as €{value} with two decimals; percent as {value}%
- Ensure 700-1000+ words total
- Include product images naturally in content sections
- Keywords should appear in first paragraph and throughout content
- Avoid keyword stuffing - use naturally in context";
    }

    private function generateTitle(array $data, Carbon $weekStart): string
    {
        $weekNumber = $weekStart->week;
        $year = $weekStart->year;
        $maxDiscount = $data['summary']['max_discount_percent'];
        $totalDiscounts = $data['summary']['total_discounts'];

        $titles = [
            "{$weekNumber} savaitės akcijos: geriausios nuolaidos net {$maxDiscount}%, {$year} m. ",
            "{$year} m. {$weekNumber} savaitės apžvalga: {$totalDiscounts} akcijų su nuolaidomis iki {$maxDiscount}%",
            "Geriausios nuolaidos {$year} m. {$weekNumber} savaitę: kur buvo pigiausios prekės?",
        ];

        return $titles[0];
    }

    private function generateSlug(string $title): string
    {
        $slug = mb_strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;
        while (\App\Models\BlogPost::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function generateMetaTitle(string $title): string
    {
        if (mb_strlen($title) <= 60) {
            return $title;
        }
        return mb_substr($title, 0, 57) . '...';
    }

    private function generateMetaDescription(array $data, Carbon $weekStart): string
    {
        $weekEnd = (clone $weekStart)->endOfWeek();
        $totalDiscounts = $data['summary']['total_discounts'];
        $avgDiscount = $data['summary']['avg_discount_percent'];
        $totalStores = $data['summary']['total_stores'];

        $description = "Savaitės akcijos apžvalga: {$totalDiscounts} nuolaidų iš {$totalStores} parduotuvių. Vidutinė nuolaida {$avgDiscount}%. ";
        $description .= "Atraskite pigiausias prekes Maxima, Iki, Rimi, Lidl ir Norfa akcijose ({$weekStart->format('m d')} - {$weekEnd->format('m d')}).";

        if (mb_strlen($description) > 160) {
            $description = mb_substr($description, 0, 157) . '...';
        }

        return $description;
    }
}

