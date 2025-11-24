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
//                    'max_tokens' => 3000,
//                    'temperature' => 0.7
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
//                    'max_tokens' => 3000,
//                    'temperature' => 0.7
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

        $storeCategoryLinks = $activeDiscounts
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
            ->take(3)
            ->values()
            ->toArray();

        return [
            'store_name' => $store->name,
            'store_url' => "@https://superakcijos.lt/akcijos/{$store->slug}",
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
        return "You are a Lithuanian copywriter who writes HTML descriptions for grocery e-shops. Generate rich, SEO-friendly content that exactly follows the new structure below using provided JSON data.

STRICT OUTPUT FORMAT:
Wrap everything in a single <div class=\"space-y-8 md:space-y-10\"> element. Use only the tags shown here. Do not use <strong> tags.

1) HEADER
- <h2 class=\"text-2xl md:text-3xl font-semibold leading-tight mb-3\"> with EXACT title format:
  '[store_name] akcijos: šios savaitės pasiūlymai – iki [max_discount_percent]% nuolaidos! Akcijos galioja [VALIDITY]'
  Where [VALIDITY] is:
   - 'nuo [earliest_end] iki [latest_end]' if both are present and different,
   - 'iki [date]' if dates are the same or only one date is present.
  Dates format: YYYY-MM-DD. Sentence case only.

2) INTRO PARAGRAPHS
- First <p class=\"leading-relaxed\"> paragraph describing the store benefits and scope using natural Lithuanian. Include '[total_active_discounts] aktyvių akcijų', 'vidutinė sutaupyta suma už prekę €[avg_savings_per_product]' (two decimals, space as thousands separator, dot as decimal). Mention main product areas using category context from data. Do NOT include any links in this first paragraph. Do NOT include validity window.
- Second <p class=\"leading-relaxed\"> paragraph continuing the description. Include EXACTLY 1 store+category link from store_category_links array (use the first one from the array). Use format: '<a href=\"[url]\">[name]</a>' where url is from store_category_links.url and name is from store_category_links.name. Integrate this link naturally into the text about popular categories. Do NOT include validity window. CRITICAL: Only the second paragraph should have links, the first paragraph must have NO links at all.
- After the two paragraphs, add a separate <p class=\"leading-relaxed\"> on a new line with validity window: if valid_date_range.earliest_end and latest_end are both present and different, write: 'Akcijos galioja nuo [earliest_end] iki [latest_end]'. If they are equal or only one is present, write: 'Akcijos galioja iki [date]'. Dates format YYYY-MM-DD.

<hr class=\"my-10 md:my-12 border-gray-200\" style=\"margin-top: 1.0rem; margin-bottom: 0.5rem;\">

3) STATS SECTION
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Aktualūs [store_name] akcijų skaičiai: vidutinė nuolaida [avg_discount_percent]%'
- A <table class=\"w-full border-collapse text-sm md:text-base rounded-lg overflow-hidden\"> with styled header/body:
  - <thead>
    - <tr>
      - <th class=\"bg-gray-50 text-left font-medium text-gray-700 px-4 py-2 border-b\"> for both columns
  - <tbody>
    - Each <tr class=\"odd:bg-white even:bg-gray-50\">
      - First <td class=\"px-4 py-2 text-gray-700 align-top border-b\">
      - Second <td class=\"px-4 py-2 text-gray-900 font-medium align-top border-b\">
  Include exactly these rows in order:
  - Aktyvių nuolaidų skaičius = [total_active_discounts]
  - Vidutinė nuolaida = [avg_discount_percent]%
  - Vidutinė sutaupyta suma už prekę = €[avg_savings_per_product]
  - Nuolaidų dydis = nuo [min_discount_percent]% iki [max_discount_percent]%
  - Akcijos galioja = either '[earliest_end] iki [latest_end]' or 'iki [date]' per the rule above

Then a <p class=\"leading-relaxed mt-3\"> noting strongest categories using category_statistics by picking top 3 with highest 'count'. Include EXACTLY 3 category links from category_statistics using format '<a href=\"[url]\">[name]</a>' where url is from category_statistics.url (remove leading '@' if present) and name is from category_statistics.name. These are regular category links, NOT store+category links. IMPORTANT: Remember which 3 categories you used here, as they must NOT be repeated in FAQ section.

<hr class=\"my-10 md:my-12 border-gray-200\" style=\"margin-top: 1.0rem; margin-bottom: 0.5rem;\">

4) TOP PRODUCTS
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Populiariausi [store_name] produktai su didžiausia nuolaida (iki [max_discount_percent]%)'
- A <table class=\"w-full border-collapse text-sm md:text-base mb-2\"> with styled rows:
  - <tbody>
    - Exactly 6 <tr class=\"odd:bg-white even:bg-gray-50\"> rows taken from top_discounts, sorted by discount_percent desc. Each row must have:
      - First <td class=\"px-4 py-2 text-gray-900 align-top border-b\"> with product link in format: '<a href=\"[product_url]\">[name]</a>' where product_url is from top_discounts.product_url (remove leading '@' if present) and name is from top_discounts.name.
      - Second <td class=\"px-4 py-2 text-gray-900 font-bold align-top border-b text-right\"> with price: '€[discounted_price]' where discounted_price is from top_discounts.discounted_price. Use space as thousands separator and dot as decimal, two decimals. Also include discount info: '([discount_percent]%)' in smaller text or parentheses. Ensure diversity already provided.

5) DISCOUNT DISTRIBUTION
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Nuolaidų paskirstymas kategorijose'
- A <p class=\"leading-relaxed\"> summarizing where most discounts are (use category_statistics and discount_distribution buckets).
- A <ul class=\"list-disc pl-5 space-y-1 py-2\"> with three items: 'Mažesnės nuolaidos (iki 10%)', 'Vidutinės nuolaidos (20–30%)', 'Didelės nuolaidos (30–50%)' with approximate product counts derived from discount_distribution.

<hr class=\"my-8 border-gray-200 mt-3\">

6) FAQ
- <h3 class=\"text-xl md:text-2xl font-semibold mt-3 mb-3\"> 'Dažniausiai užduodami klausimai (DUK)'
- A <div class=\"faq-section space-y-4\"> containing three Q/A blocks using <h4 class=\"font-semibold mb-2\"> and <p class=\"leading-relaxed\">:
  - Which categories have most offers? Include EXACTLY 1 store+category link from store_category_links array (use the second one from the array if available, otherwise skip). Use format '<a href=\"[url]\">[name]</a>' where url is from store_category_links.url and name is from store_category_links.name. If store_category_links has only 1 link total, do NOT include it here (it was already used in intro). Also include EXACTLY 2 regular category links from category_statistics using format '<a href=\"[url]\">[name]</a>' where url is from category_statistics.url (remove leading '@' if present). CRITICAL: These 2 category links MUST be different from the 3 categories used in stats section above. Use categories ranked 4th and 5th by 'count' in category_statistics, or any other categories that were NOT used in stats section. Do NOT repeat any categories. IMPORTANT: Total links in entire description must be at least 8-10 links (1 store+category in intro, 3 category in stats, 1 store+category + 2 category in FAQ, plus 6 product links in top products section).
  - How long are offers valid? Use the computed validity text.
  - How to save more? Mention card_discounts count if >0 and shopping tips.

OUTPUT RULES:
- Language: Lithuanian.
- Use data fields exactly as provided from JSON.
- Remove any leading '@' from URLs.
- Always include <main> wrapper and the exact section sequence with <hr> separators.
- Never invent stores; use [store_name].
- Numbers: format money as €[value] with two decimals; thousands separator as space; decimals with dot. Percent as [value]%. Do not round integers. For money values like avg_savings_per_product, use two decimals.
- For top products, list exactly 6 items if available; if fewer exist, list available.
- Do not use <strong> tags anywhere; rely on Tailwind classes for emphasis.
- Ensure all headings follow sentence case (only the first word capitalized).
- Keep tone promotional but natural; avoid repeating the same phrase.
";
    }

    private function getCategorySystemPrompt(): string
    {
        return "You are a Lithuanian copywriter who writes HTML descriptions for grocery e-shops. Generate rich, SEO-friendly content that exactly follows the new structure below using provided JSON data.

STRICT OUTPUT FORMAT:
Wrap everything in a single <div class=\"space-y-8 md:space-y-10\"> element. Use only the tags shown here. Do not use <strong> tags.

1) HEADER
- <h2 class=\"text-2xl md:text-3xl font-semibold leading-tight mb-3\"> with EXACT title format:
  '[category_name] akcijos: šios savaitės pasiūlymai – iki [max_discount_percent]% nuolaidos! Akcijos galioja [VALIDITY]'
  Where [VALIDITY] is:
   - 'nuo [earliest_end] iki [latest_end]' if both are present and different,
   - 'iki [date]' if dates are the same or only one date is present.
  Dates format: YYYY-MM-DD. Sentence case only.

2) INTRO PARAGRAPHS
- Two <p class=\"leading-relaxed\"> paragraphs describing the category benefits and scope using natural Lithuanian. Include '[total_active_discounts] aktyvių akcijų', 'vidutinė sutaupyta suma už prekę €[avg_savings_per_product]' (two decimals, space as thousands separator, dot as decimal). Mention main stores using store_statistics context from data. Provide validity window as: if valid_date_range.earliest_end and latest_end are both present and different, write: 'Akcijos galioja nuo [earliest_end] iki [latest_end]'. If they are equal or only one is present, write: 'Akcijos galioja iki [date]'. Dates format YYYY-MM-DD.

<hr class=\"my-10 md:my-12 border-gray-200\" style=\"margin-top: 1.0rem; margin-bottom: 0.5rem;\">

3) STATS SECTION
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Aktualūs [category_name] akcijų skaičiai: vidutinė nuolaida [avg_discount_percent]%'
- A <table class=\"w-full border-collapse text-sm md:text-base rounded-lg overflow-hidden\"> with styled header/body:
  - <thead>
    - <tr>
      - <th class=\"bg-gray-50 text-left font-medium text-gray-700 px-4 py-2 border-b\"> for both columns
  - <tbody>
    - Each <tr class=\"odd:bg-white even:bg-gray-50\">
      - First <td class=\"px-4 py-2 text-gray-700 align-top border-b\">
      - Second <td class=\"px-4 py-2 text-gray-900 font-medium align-top border-b\">
  Include exactly these rows in order:
  - Aktyvių nuolaidų skaičius = [total_active_discounts]
  - Vidutinė nuolaida = [avg_discount_percent]%
  - Vidutinė sutaupyta suma už prekę = €[avg_savings_per_product]
  - Nuolaidų dydis = nuo [min_discount_percent]% iki [max_discount_percent]%
  - Akcijos galioja = either '[earliest_end] iki [latest_end]' or 'iki [date]' per the rule above

Then a <p class=\"leading-relaxed mt-3\"> noting strongest stores using store_statistics by picking 2-3 with highest 'count'. Include links to store urls.

<hr class=\"my-10 md:my-12 border-gray-200\" style=\"margin-top: 1.0rem; margin-bottom: 0.5rem;\">

4) BEST STORES
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Geriausios [category_name] parduotuvės su didžiausia nuolaida (iki [max_discount_percent]%)'
- A <p class=\"leading-relaxed\"> introducing the list.
- A <ul class=\"list-disc pl-5 space-y-2\"> containing ALL stores from store_statistics (up to 6 items). If store_statistics has fewer than 6 stores, list all available stores. If it has more than 6, list the top 6 sorted by max_discount desc (or avg_discount desc if max_discount equal). CRITICAL: Include ALL major stores that appear in store_statistics (Rimi, Iki, Maxima, Norfa, Lidl, etc.) - do not skip any stores. Each item must use a store link in this exact format: '<a href=\"[url]\">[name]</a> – vidutinė nuolaida [avg_discount]%, [count] produktų. One short natural sentence about store benefits.' Use space as thousands separator and dot as decimal, two decimals where applicable. Remove any leading '@' from URLs.

<hr class=\"my-10 md:my-12 border-gray-200\" style=\"margin-top: 1.0rem; margin-bottom: 0.5rem;\">

5) DISCOUNT DISTRIBUTION
- <h3 class=\"text-xl md:text-2xl font-semibold mb-3\"> 'Nuolaidų paskirstymas parduotuvėse'
- A <p class=\"leading-relaxed\"> summarizing where most discounts are (use store_statistics and discount_distribution buckets).
- A <ul class=\"list-disc pl-5 space-y-1 py-2\"> with three items: 'Mažesnės nuolaidos (iki 10%)', 'Vidutinės nuolaidos (20–30%)', 'Didelės nuolaidos (30–50%)' with approximate product counts derived from discount_distribution.

<hr class=\"my-8 border-gray-200 mt-3\">

6) FAQ
- <h3 class=\"text-xl md:text-2xl font-semibold mt-3 mb-3\"> 'Dažniausiai užduodami klausimai (DUK)'
- A <div class=\"faq-section space-y-4\"> containing three Q/A blocks using <h4 class=\"font-semibold mb-2\"> and <p class=\"leading-relaxed\">:
  - Which stores have most offers? Link to 2 store urls.
  - How long are offers valid? Use the computed validity text.
  - How to save more? Mention card_discounts count if >0 and shopping tips.

OUTPUT RULES:
- Language: Lithuanian.
- Use data fields exactly as provided from JSON.
- Remove any leading '@' from URLs.
- Always include the exact section sequence with <hr> separators.
- Never invent stores; use [category_name] and store names from store_statistics.
- Numbers: format money as €[value] with two decimals; thousands separator as space; decimals with dot. Percent as [value]%. Do not round integers. For money values like avg_savings_per_product, use two decimals.
- For best stores, list ALL stores from store_statistics (up to 6 items). If there are fewer than 6 stores, list all available stores. CRITICAL: Do not skip any stores - include every store that appears in store_statistics.
- Do not use <strong> tags anywhere; rely on Tailwind classes for emphasis.
- Ensure all headings follow sentence case (only the first word capitalized).
- Keep tone promotional but natural; avoid repeating the same phrase.
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

            // Skip if we already have too many products from the same brand (max 2 per brand)
            if ($usedBrands->where('brand', $brand)->count() >= 2) {
                continue;
            }

            // Skip if we already have the same product type (e.g., different sizes of same product)
            if ($usedProductTypes->contains($productType)) {
                continue;
            }

            $selectedDiscounts->push($discount);
            $usedBrands->push(['brand' => $brand, 'product' => $productName]);
            $usedProductTypes->push($productType);
        }

        // If we don't have enough diverse products, fill with remaining best discounts
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
        // Common brand patterns
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

        // If no brand found, use first word as brand
        $words = explode(' ', $productName);
        return $words[0] ?? 'UNKNOWN';
    }

    private function extractProductType(string $productName): string
    {
        // Extract product type by removing size/quantity info and brand
        $productName = mb_strtoupper($productName);

        // Remove common size/quantity patterns
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

        // Remove common brand names
        $brands = ['PAMPERS', 'HUGGIES', 'ELMEX', 'COLGATE', 'SENSODYNE', 'NUTRI', 'BALTIJA', 'ŽEMAITIJA', 'DOVANA', 'VILKYŠKIAI', 'GOWIPES', 'MAŽYLIS', 'AUKŠTAITIŠKI'];
        foreach ($brands as $brand) {
            $productName = str_replace($brand, '', $productName);
        }

        // Clean up and return
        $productName = trim(preg_replace('/\s+/', ' ', $productName));
        return $productName ?: 'UNKNOWN';
    }
}
