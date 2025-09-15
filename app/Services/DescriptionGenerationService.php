<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DescriptionGenerationService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => config('services.openai.model', 'gpt-4o'),
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
//                'max_tokens' => 500,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => config('services.openai.model', 'gpt-4o'),
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
//                'max_tokens' => 400,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
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

    private function getStoreData(Store $store): array
    {
        $activeDiscounts = Discount::whereHas('store', function($query) use ($store) {
            $query->where('id', $store->id);
        })
            ->where(function ($query) {
                $query->where('end_at', '>=', now()->startOfDay())
                    ->orWhereNull('end_at');
            })
            ->with(['product.category'])
            ->get();

        $topDiscounts = $activeDiscounts
            ->sortByDesc('discount_percent')
            ->take(15)
            ->map(function($discount) {
                return [
                    'name' => $discount->product->name,
                    'category' => $discount->product->category->name,
                    'category_url' => "/{$discount->product->category->slug}",
                    'product_url' => "/{$discount->product->category->slug}/{$discount->product->slug}",
                    'original_price' => $discount->original_price,
                    'discounted_price' => $discount->discounted_price,
                    'discount_percent' => $discount->discount_percent,
                    'savings_amount' => round($discount->original_price - $discount->discounted_price, 2),
                    'valid_until' => $discount->end_at->format('Y-m-d'),
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
                    'url' => "/{$firstDiscount->product->category->slug}",
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

        return [
            'store_name' => $store->name,
            'store_url' => "/{$store->slug}",
            'total_active_discounts' => $activeDiscounts->count(),
            'total_products' => $activeDiscounts->unique('product_id')->count(),
            'total_categories' => $activeDiscounts->unique('product.category_id')->count(),
            'avg_discount_percent' => round($activeDiscounts->avg('discount_percent'), 1),
            'max_discount_percent' => $activeDiscounts->max('discount_percent'),
            'min_discount_percent' => $activeDiscounts->min('discount_percent'),
            'total_savings' => $activeDiscounts->sum(function($d) {
                return $d->original_price - $d->discounted_price;
            }),
            'avg_original_price' => round($activeDiscounts->avg('original_price'), 2),
            'avg_discounted_price' => round($activeDiscounts->avg('discounted_price'), 2),
            'products_with_conditions' => $activeDiscounts->whereNotNull('condition')->count(),
            'card_discounts' => $activeDiscounts->where('card', true)->count(),
            'top_discounts' => $topDiscounts,
            'category_statistics' => $categoryStats,
            'valid_date_range' => [
                'earliest_end' => $activeDiscounts->min('end_at') ? $activeDiscounts->min('end_at')->format('Y-m-d') : null,
                'latest_end' => $activeDiscounts->max('end_at') ? $activeDiscounts->max('end_at')->format('Y-m-d') : null
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

        $topDiscounts = $activeDiscounts
            ->sortByDesc('discount_percent')
            ->take(12)
            ->map(function($discount) {
                return [
                    'name' => $discount->product->name,
                    'store' => $discount->store->name,
                    'store_url' => "/{$discount->store->slug}",
                    'product_url' => "/{$discount->product->category->slug}/{$discount->product->slug}",
                    'original_price' => $discount->original_price,
                    'discounted_price' => $discount->discounted_price,
                    'discount_percent' => $discount->discount_percent,
                    'savings_amount' => round($discount->original_price - $discount->discounted_price, 2),
                    'valid_until' => $discount->end_at->format('Y-m-d'),
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
                return [
                    'name' => $firstDiscount->store->name,
                    'url' => "/{$firstDiscount->store->slug}",
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

        return [
            'category_name' => $category->name,
            'category_url' => "/{$category->slug}",
            'total_active_discounts' => $activeDiscounts->count(),
            'total_products' => $activeDiscounts->unique('product_id')->count(),
            'total_stores' => $activeDiscounts->unique('store_id')->count(),
            'avg_discount_percent' => round($activeDiscounts->avg('discount_percent'), 1),
            'max_discount_percent' => $activeDiscounts->max('discount_percent'),
            'min_discount_percent' => $activeDiscounts->min('discount_percent'),
            'total_savings' => $activeDiscounts->sum(function($d) {
                return $d->original_price - $d->discounted_price;
            }),
            'avg_original_price' => round($activeDiscounts->avg('original_price'), 2),
            'avg_discounted_price' => round($activeDiscounts->avg('discounted_price'), 2),
            'products_with_conditions' => $activeDiscounts->whereNotNull('condition')->count(),
            'card_discounts' => $activeDiscounts->where('card', true)->count(),
            'top_discounts' => $topDiscounts,
            'store_statistics' => $storeStats,
            'valid_date_range' => [
                'earliest_end' => $activeDiscounts->min('end_at') ? $activeDiscounts->min('end_at')->format('Y-m-d') : null,
                'latest_end' => $activeDiscounts->max('end_at') ? $activeDiscounts->max('end_at')->format('Y-m-d') : null
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
        return "You are a marketing expert specializing in Lithuanian grocery store promotions. Your task is to create compelling, SEO-friendly descriptions for grocery stores based on their current discount offerings.

TASK: Analyze the store's active discounts and create a compelling description that highlights:
- The best deals and savings available
- Product variety and categories with discounts
- Valid dates and urgency
- Store's value proposition
- Total savings potential
- Discount distribution statistics
- Practical benefits for average users
- Essential products with good discounts

GUIDELINES:
- Write in Lithuanian language
- Keep descriptions engaging and promotional
- Highlight the best discount percentages and total savings
- Mention product variety and popular categories
- Include urgency based on valid dates
- Make it SEO-friendly with relevant keywords
- Keep it under 500 words
- Focus on customer benefits and savings
- Include links to categories and products using the provided URLs
- Use HTML anchor tags for links: <a href=\"URL\">text</a>
- Link category names to category URLs
- Link product names to product URLs
- Link store name to store URL
- Use statistical data to provide specific numbers and percentages
- Mention discount distribution (how many products have different discount ranges)
- Highlight total savings potential across all discounts
- Focus on practical, everyday products that average users need
- Highlight essential products with good discounts
- Provide practical shopping advice and recommendations
- ALWAYS add a unique twist or angle to make each description stand out (e.g., seasonal theme, special occasion, unexpected benefit, creative comparison, or interesting fact)
- When mentioning promotion dates, always specify the full date range (from date to date) instead of just end date

HTML FORMATTING:
- Use <strong> tags for important numbers and percentages (e.g., <strong>50%</strong> nuolaida)
- Use <em> tags for emphasis on key benefits (e.g., <em>nepraleiskite progos</em>)
- Use <ul> and <li> tags for lists of benefits or features
- Use <p> tags to separate paragraphs
- Use <h3> tags for section headings
- Use <span class=\"highlight\"> for highlighting special offers
- Use <div class=\"stats\"> for statistical information sections
- Use <div class=\"top-products\"> for best product offers
- Use <div class=\"discount-distribution\"> for discount distribution statistics
- Use <div class=\"urgency\"> for time-sensitive information
- Add proper spacing between sections with empty lines

EXAMPLE STRUCTURE:
<p><a href=\"/store\">Store Name</a> siūlo <strong>150 aktyvių akcijų</strong> su vidutine <strong>25%</strong> nuolaida! Tai puiki proga papildyti atsargas ir mėgautis skaniais desertais už mažesnę kainą.</p>

<div class=\"stats\">
<h3>Statistika:</h3>
<ul>
<li><strong>Iš viso produktų:</strong> 150</li>
<li><strong>Vidutinė nuolaida:</strong> 25%</li>
<li><strong>Maksimali nuolaida:</strong> 60%</li>
<li><strong>Iš viso parduotuvių:</strong> 3</li>
<li><strong>Vidutinė pradinė kaina:</strong> €3.61</li>
<li><strong>Vidutinė nuolaidinė kaina:</strong> €2.67</li>
<li><strong>Bendra taupymo suma:</strong> €127.27</li>
</ul>
</div>

<p>Geriausi pasiūlymai šioje kategorijoje laukia <a href=\"/store\">parduotuvėje</a>, kur galite rasti populiariausius produktus su <strong>60%</strong> nuolaida!</p>

<div class=\"top-products\">
<h3>Geriausi pasiūlymai:</h3>
<ul>
<li><a href=\"/product\">Produktas 1</a> - <strong>60%</strong> nuolaida</li>
<li><a href=\"/product\">Produktas 2</a> - <strong>55%</strong> nuolaida</li>
<li><a href=\"/product\">Produktas 3</a> - <strong>50%</strong> nuolaida</li>
</ul>
</div>

<div class=\"discount-distribution\">
<h3>Nuolaidų paskirstymas:</h3>
<ul>
<li><strong>10% ir mažiau:</strong> 13 produktų</li>
<li><strong>10-20%:</strong> 31 produktas</li>
<li><strong>20-30%:</strong> 117 produktų</li>
<li><strong>30-50%:</strong> 130 produktų</li>
<li><strong>Daugiau nei 50%:</strong> 14 produktų</li>
</ul>
</div>

<div class=\"urgency\">
<p><em>Šios akcijos galioja nuo <strong>2025-08-15</strong> iki <strong>2025-09-01</strong>, tad nepraleiskite progos sutaupyti!</em></p>
</div>

OUTPUT: Return only the description text with HTML formatting and links included.";
    }

    private function getCategorySystemPrompt(): string
    {
        return "You are a marketing expert specializing in Lithuanian grocery store promotions. Your task is to create compelling, SEO-friendly descriptions for product categories based on current discount offerings.

TASK: Analyze the category's active discounts and create a compelling description that highlights:
- The best deals in this category
- Price savings and value
- Product variety and popular items
- Valid dates and urgency
- Total savings potential
- Store availability and variety
- Practical benefits for average users

GUIDELINES:
- Write in Lithuanian language
- Keep descriptions engaging and promotional
- Highlight the best discount percentages and total savings
- Mention popular products and brands
- Include urgency based on valid dates
- Make it SEO-friendly with relevant keywords
- Keep it under 500 words
- Focus on customer benefits and savings
- Include links to products and stores using the provided URLs
- Use HTML anchor tags for links: <a href=\"URL\">text</a>
- Link product names to product URLs
- Link store names to store URLs
- Link category name to category URL
- Use statistical data to provide specific numbers and percentages
- Mention which stores offer the best deals in this category
- Highlight total savings potential across all discounts
- Include discount distribution statistics
- Focus on practical, everyday products that average users need
- Highlight essential products with good discounts
- Provide practical shopping advice
- ALWAYS add a unique twist or angle to make each description stand out (e.g., seasonal theme, special occasion, unexpected benefit, creative comparison, or interesting fact)
- When mentioning promotion dates, always specify the full date range (from date to date) instead of just end date

HTML FORMATTING:
- Use <strong> tags for important numbers and percentages (e.g., <strong>50%</strong> nuolaida)
- Use <em> tags for emphasis on key benefits (e.g., <em>nepraleiskite progos</em>)
- Use <ul> and <li> tags for lists of benefits or features
- Use <p> tags to separate paragraphs
- Use <h3> tags for section headings
- Use <span class=\"highlight\"> for highlighting special offers
- Use <div class=\"stats\"> for statistical information sections
- Use <div class=\"top-products\"> for best product offers
- Use <div class=\"discount-distribution\"> for discount distribution statistics
- Use <div class=\"urgency\"> for time-sensitive information
- Add proper spacing between sections with empty lines

EXAMPLE STRUCTURE:
<p><a href=\"/category\">Category Name</a> kategorijoje raskite <strong>45 aktyvių akcijų</strong> su vidutine <strong>30%</strong> nuolaida! Tai puiki proga papildyti atsargas ir mėgautis skaniais desertais už mažesnę kainą.</p>

<div class=\"stats\">
<h3>Statistika:</h3>
<ul>
<li><strong>Iš viso produktų:</strong> 45</li>
<li><strong>Vidutinė nuolaida:</strong> 30%</li>
<li><strong>Maksimali nuolaida:</strong> 55%</li>
<li><strong>Iš viso parduotuvių:</strong> 3</li>
<li><strong>Vidutinė pradinė kaina:</strong> €3.61</li>
<li><strong>Vidutinė nuolaidinė kaina:</strong> €2.67</li>
<li><strong>Bendra taupymo suma:</strong> €127.27</li>
<li><strong>Produktai su sąlygomis:</strong> 187</li>
</ul>
</div>

<p>Geriausi pasiūlymai <a href=\"/store\">parduotuvėje</a>, kur galite rasti populiariausius produktus su <strong>55%</strong> nuolaida!</p>

<div class=\"top-products\">
<h3>Geriausi pasiūlymai:</h3>
<ul>
<li><a href=\"/product\">Produktas 1</a> - <strong>55%</strong> nuolaida</li>
<li><a href=\"/product\">Produktas 2</a> - <strong>50%</strong> nuolaida</li>
<li><a href=\"/product\">Produktas 3</a> - <strong>45%</strong> nuolaida</li>
</ul>
</div>

<div class=\"discount-distribution\">
<h3>Nuolaidų paskirstymas:</h3>
<ul>
<li><strong>10% ir mažiau:</strong> 5 produktų</li>
<li><strong>10-20%:</strong> 10 produktų</li>
<li><strong>20-30%:</strong> 15 produktų</li>
<li><strong>30-50%:</strong> 12 produktų</li>
<li><strong>Daugiau nei 50%:</strong> 3 produktai</li>
</ul>
</div>

<div class=\"urgency\">
<p><em>Šios akcijos galioja nuo <strong>2025-08-15</strong> iki <strong>2025-09-01</strong>, tad nepraleiskite progos sutaupyti!</em></p>
</div>

OUTPUT: Return only the description text with HTML formatting and links included.";
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
}
