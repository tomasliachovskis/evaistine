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
            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->withHeaders([
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
                    'max_tokens' => 2000,
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
            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->withHeaders([
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
                    'max_tokens' => 2000,
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
            ->with(['product.category', 'store'])
            ->get();

        $topDiscounts = $activeDiscounts
            ->where('product.category_id', '!=', 535)
            ->sortByDesc('discount_percent')
            ->take(10)
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

        return [
            'store_name' => $store->name,
            'store_url' => "@https://superakcijos.lt/akcijos/{$store->slug}",
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
            ->where('product.category_id', '!=', 535)
            ->sortByDesc('discount_percent')
            ->take(10)
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
                return [
                    'name' => $firstDiscount->store->name,
                    'url' => "@https://superakcijos.lt/akcijos/{$firstDiscount->store->slug}",
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
            'category_url' => "@https://superakcijos.lt/akcijos/{$category->slug}",
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
- IMPORTANT: Remove the @ symbol from URLs in the final output - use clean URLs like https://superakcijos.lt/akcijos/...
- Use statistical data to provide specific numbers and percentages
- Mention discount distribution (how many products have different discount ranges)
- Highlight total savings potential across all discounts
- Focus on practical, everyday products that average users need
- Highlight essential products with good discounts
- Provide practical shopping advice and recommendations
- ALWAYS add a unique twist or angle to make each description stand out (e.g., seasonal theme, special occasion, unexpected benefit, creative comparison, or interesting fact)
- When mentioning promotion dates, always specify the full date range (from date to date) instead of just end date

SEO OPTIMIZATION REQUIREMENTS:
- Include primary keywords: \"akcijos\", \"nuolaidos\", \"parduotuvė\", \"prekės\", \"taupymas\", \"kainos\"
- Use long-tail keywords: \"greičiausios akcijos\", \"geriausios kainos\", \"didelės nuolaidos\", \"kasdienės prekės\", \"šeimos biudžetas\"
- Include local SEO keywords: \"Lietuvoje\", \"Vilniuje\", \"Kaune\", \"Klaipėdoje\", \"šalyje\"
- Add seasonal keywords when relevant: \"šventinės akcijos\", \"vasaros nuolaidos\", \"žiemos pasiūlymai\", \"šventinės prekės\"
- Include question-based keywords: \"kur pirkti\", \"kada akcijos\", \"kiek sutaupyti\", \"kokios kainos\"
- Use power words: \"ekskluzyvus\", \"ribotas laikas\", \"nepraleiskite\", \"tik šiandien\", \"greičiausiai\"
- Include brand names and product categories naturally
- Add FAQ-style content answering common shopping questions
- Use semantic keywords related to shopping, savings, and grocery
- Include call-to-action phrases: \"apsilankykite\", \"nusipirkite dabar\", \"sutaupykite\"

HTML FORMATTING:
- Use <strong> tags for important numbers and percentages (e.g., <strong>50%</strong> nuolaida)
- Use <em> tags for emphasis on key benefits (e.g., <em>nepraleiskite progos</em>)
- Use <ul> and <li> tags for lists of benefits or features
- Use <p> tags to separate paragraphs
- Use <h3> tags for section headings
- Use <span class=\"highlight\"> for highlighting special offers
- Use <div class=\"stats\"> for statistical information sections
- Use <div class=\"top-products\"> for best product offers
- In top products section, include product name, discounted price, discount percentage, and store name
- Format: Product Name - Price, X% nuolaida, Store Name
- Always show exactly 10 products in the top products section
- Use <div class=\"discount-distribution\"> for discount distribution statistics
- Use <div class=\"urgency\"> for time-sensitive information
- Add proper spacing between sections with empty lines

STRUCTURED DATA MARKUP:
- Include FAQ sections using <div class=\"faq\"> with <h3> questions and <p> answers
- Add product comparison tables using <table> with <thead> and <tbody>
- Use <time> tags for dates: <time datetime=\"2025-01-15\">sausio 15 d.</time>
- Include <address> tags for store locations when relevant
- Add <div class=\"breadcrumbs\"> for navigation context
- Use <section> tags to group related content
- Include <aside> tags for additional tips and recommendations

EXAMPLE STRUCTURE:
<p><a href=\"https://superakcijos.lt/akcijos/store\">Store Name</a> siūlo <strong>150 aktyvių akcijų</strong> su vidutine <strong>25%</strong> nuolaida! Tai puiki proga papildyti atsargas ir mėgautis skaniais desertais už mažesnę kainą.</p>

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

<p>Geriausi pasiūlymai šioje kategorijoje laukia <a href=\"https://superakcijos.lt/akcijos/store\">parduotuvėje</a>, kur galite rasti populiariausius produktus su <strong>60%</strong> nuolaida!</p>

<div class=\"top-products\">
<h3>Geriausi pasiūlymai:</h3>
<ul>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Žemaitijos varškė</a> - 4.66€, <strong>40%</strong> nuolaida, Iki</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Dvaro pienas</a> - 2.15€, <strong>35%</strong> nuolaida, Rimi</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Vilkyškių duona</a> - 1.89€, <strong>30%</strong> nuolaida, Maxima</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Lietuvos sūris</a> - 3.45€, <strong>25%</strong> nuolaida, Norfa</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Ekstra jogurtas</a> - 1.25€, <strong>20%</strong> nuolaida, Lidl</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Kiaušiniai</a> - 2.50€, <strong>18%</strong> nuolaida, Iki</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Bulvės</a> - 0.89€, <strong>15%</strong> nuolaida, Rimi</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Morkos</a> - 1.15€, <strong>12%</strong> nuolaida, Maxima</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Svogūnai</a> - 0.95€, <strong>10%</strong> nuolaida, Norfa</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Obuoliai</a> - 1.45€, <strong>8%</strong> nuolaida, Lidl</li>
</ul>
</div>

<div class=\"discount-distribution\">
<h3 class=\"mt-3\">Nuolaidų paskirstymas:</h3>
<ul>
<li><strong>10% ir mažiau:</strong> 13 produktų</li>
<li><strong>10-20%:</strong> 31 produktas</li>
<li><strong>20-30%:</strong> 117 produktų</li>
<li><strong>30-50%:</strong> 130 produktų</li>
<li><strong>Daugiau nei 50%:</strong> 14 produktų</li>
</ul>
</div>

<div class=\"urgency\">
<p class=\"mt-2\"><em>Šios akcijos galioja nuo <strong>2025-08-15</strong> iki <strong>2025-09-01</strong>, tad nepraleiskite progos sutaupyti!</em></p>
</div>

<div class=\"faq\">
<h3 class=\"mt-3\">Dažniausi klausimai apie parduotuvę:</h3>
<h4>Kada geriausia apsilankyti parduotuvėje?</h4>
<p>Geriausias laikas apsilankyti yra ryte arba darbo dienomis, kai mažiau žmonių. Taip pat rekomenduojame sekti akcijų kalendorių.</p>
<h4 class=\"mt-2\">Kiek galima sutaupyti šiose akcijose?</h4>
<p>Vidutiniškai galite sutaupyti iki <strong>€127</strong> už pilną krepšelį, o kai kurie produktai siūlo net <strong>60%</strong> nuolaidą!</p>
<h4 class=\"mt-2\">Ar parduotuvė siūlo pristatymą?</h4>
<p>Taip, dauguma parduotuvių siūlo pristatymą į namus. Patikrinkite jų svetainėje arba skambinkite tiesiogiai parduotuvei.</p>
<h4 class=\"mt-2\">Kokios darbo valandos?</h4>
<p>Parduotuvės paprastai dirba 7-22 val., bet patikrinkite konkrečias darbo valandas jų svetainėje.</p>
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

SEO OPTIMIZATION REQUIREMENTS:
- Include primary keywords: \"akcijos\", \"nuolaidos\", \"kategorija\", \"prekės\", \"taupymas\", \"kainos\"
- Use long-tail keywords: \"greičiausios akcijos\", \"geriausios kainos\", \"didelės nuolaidos\", \"kasdienės prekės\", \"šeimos biudžetas\"
- Include local SEO keywords: \"Lietuvoje\", \"Vilniuje\", \"Kaune\", \"Klaipėdoje\", \"šalyje\"
- Add seasonal keywords when relevant: \"šventinės akcijos\", \"vasaros nuolaidos\", \"žiemos pasiūlymai\", \"šventinės prekės\"
- Include question-based keywords: \"kur pirkti\", \"kada akcijos\", \"kiek sutaupyti\", \"kokios kainos\"
- Use power words: \"ekskluzyvus\", \"ribotas laikas\", \"nepraleiskite\", \"tik šiandien\", \"greičiausiai\"
- Include brand names and product categories naturally
- Add FAQ-style content answering common shopping questions
- Use semantic keywords related to shopping, savings, and grocery
- Include call-to-action phrases: \"apsilankykite\", \"nusipirkite dabar\", \"sutaupykite\"

HTML FORMATTING:
- Use <strong> tags for important numbers and percentages (e.g., <strong>50%</strong> nuolaida)
- Use <em> tags for emphasis on key benefits (e.g., <em>nepraleiskite progos</em>)
- Use <ul> and <li> tags for lists of benefits or features
- Use <p> tags to separate paragraphs
- Use <h3> tags for section headings
- Use <span class=\"highlight\"> for highlighting special offers
- Use <div class=\"stats\"> for statistical information sections
- Use <div class=\"top-products\"> for best product offers
- In top products section, include product name, discounted price, discount percentage, and store name
- Format: Product Name - Price, X% nuolaida, Store Name
- Always show exactly 10 products in the top products section
- Use <div class=\"discount-distribution\"> for discount distribution statistics
- Use <div class=\"urgency\"> for time-sensitive information
- Add proper spacing between sections with empty lines

STRUCTURED DATA MARKUP:
- Include FAQ sections using <div class=\"faq\"> with <h3> questions and <p> answers
- Add product comparison tables using <table> with <thead> and <tbody>
- Use <time> tags for dates: <time datetime=\"2025-01-15\">sausio 15 d.</time>
- Include <address> tags for store locations when relevant
- Add <div class=\"breadcrumbs\"> for navigation context
- Use <section> tags to group related content
- Include <aside> tags for additional tips and recommendations

EXAMPLE STRUCTURE:
<p><a href=\"https://superakcijos.lt/akcijos/category\">Category Name</a> kategorijoje raskite <strong>45 aktyvių akcijų</strong> su vidutine <strong>30%</strong> nuolaida! Tai puiki proga papildyti atsargas ir mėgautis skaniais desertais už mažesnę kainą.</p>

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

<p>Geriausi pasiūlymai <a href=\"https://superakcijos.lt/akcijos/store\">parduotuvėje</a>, kur galite rasti populiariausius produktus su <strong>55%</strong> nuolaida!</p>

<div class=\"top-products\">
<h3>Geriausi pasiūlymai:</h3>
<ul>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Produktas 1</a> - <strong>55%</strong> nuolaida</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Produktas 2</a> - <strong>50%</strong> nuolaida</li>
<li><a href=\"https://superakcijos.lt/akcijos/product\">Produktas 3</a> - <strong>45%</strong> nuolaida</li>
</ul>
</div>

<div class=\"discount-distribution\">
<h3 class=\"mt-3\">Nuolaidų paskirstymas:</h3>
<ul>
<li><strong>10% ir mažiau:</strong> 5 produktų</li>
<li><strong>10-20%:</strong> 10 produktų</li>
<li><strong>20-30%:</strong> 15 produktų</li>
<li><strong>30-50%:</strong> 12 produktų</li>
<li><strong>Daugiau nei 50%:</strong> 3 produktai</li>
</ul>
</div>

<div class=\"urgency\">
<p class=\"mt-2\"><em>Šios akcijos galioja nuo <strong>2025-08-15</strong> iki <strong>2025-09-01</strong>, tad nepraleiskite progos sutaupyti!</em></p>
</div>

<div class=\"faq\">
<h3 class=\"mt-3\">Dažniausi klausimai apie kategoriją:</h3>
<h4>Kokie produktai šioje kategorijoje turi geriausias nuolaidas?</h4>
<p>Geriausios nuolaidos paprastai būna kasdieniniams produktams: pieno produktams, duonai, mėsai ir daržovėms. Taip pat stebėkite sezoninius pasiūlymus.</p>
<h4 class=\"mt-2\">Kiek galima sutaupyti šioje kategorijoje?</h4>
<p>Vidutiniškai galite sutaupyti iki <strong>€127</strong> už pilną krepšelį, o kai kurie produktai siūlo net <strong>60%</strong> nuolaidą!</p>
<h4 class=\"mt-2\">Kurios parduotuvės siūlo geriausias kainas šioje kategorijoje?</h4>
<p>Skirtingos parduotuvės siūlo skirtingus pasiūlymus. Rekomenduojame palyginti kainas ir pasirinkti geriausią variantą jūsų poreikiams.</p>
<h4 class=\"mt-2\">Ar šie produktai tinka ilgalaikiam saugojimui?</h4>
<p>Dauguma šios kategorijos produktų tinka ilgalaikiam saugojimui šaldytuve arba sandėliuojant saugiai. Patikrinkite etiketes dėl saugojimo instrukcijų.</p>
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
