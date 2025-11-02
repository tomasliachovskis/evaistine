<?php

namespace App\Services;

use App\Models\Category;
use App\Models\DiscountTemp;
use App\Models\UnmappedProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class CategoryMappingService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured in CategoryMappingService');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function bulkMapStoreProducts(string $storeName): void
    {
        if (!$this->isConfigured()) {
            $this->warn("CategoryMappingService is not configured. Skipping bulk mapping for {$storeName}.");
            return;
        }

        $products = DiscountTemp::where('store', $storeName)
            ->where(function($query) {
                $query->whereNull('category')
                      ->orWhere('category', '');
            })
            ->get();

        $this->info("Starting bulk mapping for {$storeName}. Found " . $products->count() . " products to map.");

        $chunks = $products->chunk(20);

        foreach ($chunks as $chunk) {
            $this->mapProductChunk($chunk, $storeName);
            sleep(2);
        }

        $this->info("Completed bulk mapping for {$storeName}");
    }

    public function bulkMapStoreProductsWithExistingCategories(string $storeName): void
    {
        if (!$this->isConfigured()) {
            $this->warn("CategoryMappingService is not configured. Skipping bulk mapping for {$storeName}.");
            return;
        }

        $products = DiscountTemp::where('store', $storeName)->where('processed', 0)
            ->where(function($query) {
                $query->whereNull('category')
                      ->orWhere('category', '');
            })
            ->get();

        $this->info("Starting bulk mapping for {$storeName}. Found " . $products->count() . " products to map.");

        $productsToMap = collect();
        $mappedFromExisting = 0;

        foreach ($products as $product) {
            $existingProduct = Product::where('name', $product->name)->first();

            if ($existingProduct && $existingProduct->category_id) {
                $category = Category::find($existingProduct->category_id);
                if ($category) {
                    $product->category = $category->name;
                    $product->save();
                    $mappedFromExisting++;
                    $this->info("Mapped '{$product->name}' to existing category '{$category->name}'");
                } else {
                    $productsToMap->push($product);
                }
            } else {
                $productsToMap->push($product);
            }
        }

        $this->info("Mapped {$mappedFromExisting} products from existing categories. {$productsToMap->count()} products need GPT mapping.");

        if ($productsToMap->count() > 0) {
            $chunks = $productsToMap->chunk(20);

            foreach ($chunks as $chunk) {
                $this->mapProductChunk($chunk, $storeName);
                sleep(2);
            }
        }

        $this->info("Completed bulk mapping for {$storeName}");
    }

    private function mapProductChunk($products, string $storeName, int $retryCount = 0): void
    {
        $productData = $products->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name
            ];
        })->toArray();

        $this->info("Sending " . count($productData) . " products for mapping" . ($retryCount > 0 ? " (retry #{$retryCount})" : ""));

        $mappings = $this->bulkMapProductsToCategories($productData, $storeName);

        $this->info("Received mappings: " . ($mappings ? json_encode($mappings) : 'null'));

        if ($mappings) {
            $validMappings = $this->validateProductIds($mappings, $productData, $storeName);

            if (empty($validMappings) && $retryCount < 2) {
                $this->warn("No valid product IDs found in mappings, retrying... (attempt " . ($retryCount + 1) . "/3)");
                sleep(1);
                $this->mapProductChunk($products, $storeName, $retryCount + 1);
                return;
            } elseif (empty($validMappings) && $retryCount >= 2) {
                $this->warn("Max retries reached for chunk with invalid product IDs. Storing all products as unmapped.");
                foreach ($products as $product) {
                    $this->storeUnmappedProduct($product, $storeName, UnmappedProduct::STATUS_INVALID_PRODUCT_IDS, 'Max retries reached with invalid product IDs', $productData);
                }
                return;
            }

            foreach ($products as $product) {
                if (isset($validMappings[$product->id])) {
                    $categoryName = $validMappings[$product->id];
                    $product->category = $categoryName;
                    $product->save();
                    $this->info("Mapped '{$product->name}' to category '{$categoryName}'");
                } else {
                    $this->storeUnmappedProduct($product, $storeName, UnmappedProduct::STATUS_NO_RESPONSE, null, $productData);
                }
            }
        } else {
            $this->warn("No mappings received from API");
            foreach ($products as $product) {
                $this->storeUnmappedProduct($product, $storeName, UnmappedProduct::STATUS_NO_RESPONSE, null, $productData);
            }
        }
    }

    public function bulkMapProductsToCategories(array $productData, string $storeName): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured');
            return null;
        }

        try {
            $productJson = json_encode($productData, JSON_UNESCAPED_UNICODE);

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => config('services.openai.model', 'gpt-5-mini'), // Configurable model
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getBulkSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => "Products: {$productJson}"
                    ]
                ],
//                'max_tokens' => 3000,
//                'temperature' => 0
            ]);

            if ($response->successful()) {
//                dump($productData);
//                dump($response->json());

                $mappings = $this->parseBulkResponse($response->json());
                return $mappings;
            }

            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for bulk mapping', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function getBulkSystemPrompt(): string
    {
        return "You are a product categorization expert specializing in Lithuanian grocery store products. Your task is to map each product name to the most appropriate category.

CRITICAL: Return ONLY raw JSON without any markdown formatting, code blocks, or explanatory text.

TASK: For each product name provided, determine which category it belongs to and return a JSON mapping of product ID to category name.

IMPORTANT: All product names are in Lithuanian language. Pay attention to Lithuanian terms and product descriptions.

CRITICAL INSTRUCTION: You MUST use the EXACT product IDs that are provided in the input. Do NOT generate your own IDs or use sequential numbering. Use the exact same IDs that appear in the input data.

Available categories with detailed examples:

1. Vaisiai ir daržovės (Fruits and Vegetables)
   - Examples: obuoliai, bananai, pomidorai, agurkai, bulvės, morkos, svogūnai, česnakai, citrinos, apelsinai, braškės, vyšnios, avietės, kopūstai, salotos, špinatai, petražolės, krapai, bazilikas, vaisiai, daržovės, sveži produktai
   - NOTE: Fresh fruits and vegetables only. Marinated/preserved items go to Bakalėja

2. Pieno produktai ir kiaušiniai (Dairy Products and Eggs)
   - Examples: pienas, sviestas, sūris, jogurtas, grietinėlė, kefyras, varškė, kiaušiniai, rūgpienis, sūreliai, kremas, sūrio lazdelės, kiaušinių baltymai, kefyras, pieno produktai, sūrio produktai
   - NOTE: All dairy products including kefyras, milk products, cheese products

3. Duonos gaminiai (Bread Products)
   - Examples: duona, batonas, bandelės, pyragaičiai, sausainiai, blynai, tešla, duonos riekės, duonos grūdai, duonos tešla, duonos miltai, kepiniai, pyragai, bandelės, skruzdėlynas, duonos gaminiai, bandelė su šokolado gabaliukais
   - NOTE: All bread products, pastries, cakes, cookies, baked goods

4. Mėsa ir žuvis (Meat and Fish)
   - Examples: kiauliena, jautiena, vištiena, aviena, šalmonas, silkė, tunas, lašiša, karpis, ešerys, kumpis, dešrelės, dešros, mėsainiai, mėsos gaminiai, dešra, daktariška dešra, sveriama dešra, krevetės, žuvis, mėsa
   - NOTE: All meat products, fish, seafood, sausages, deli meats

5. Šaldytas maistas ir ledai (Frozen Food and Ice Cream)
   - Examples: šaldyti daržovės, šaldyti vaisiai, šaldyti pica, šaldyti koldūnai, ledai, šaldyti žuvis, šaldyti mėsa, šaldyti gaminiai, ledų kūgiai, šaldytos krevetės, šaldytas maistas, ledai
   - NOTE: Frozen foods, ice cream, frozen seafood, frozen meals

6. Bakalėja (Groceries)
   - Examples: ryžiai, makaronai, miltai, cukrus, druska, aliejus, sviestas, konservai, sriubos, padažai, majonezas, kečupas, garstyčios, riešutai, saulėgrąžų sėklos, marinuoti agurkai, konservuoti produktai, padažai, marinuoti produktai
   - NOTE: Dry goods, canned foods, preserved foods, marinades, sauces

7. Vaikų ir kūdikių prekės (Children and Baby Products)
   - Examples: kūdikių pienas, sauskelės, vaikiški sausainiai, kūdikių vanduo, vaikiški gėrimai, kūdikių šampūnas, vaikiški šampūnai, kūdikių kremas, košėms, tyrelėms, užkandžiams, mamuko, vaikų prekės, kūdikių prekės
   - NOTE: Baby food, baby products, children's products, baby cereals

8. Saldumynai ir užkandžiai (Sweets and Snacks)
   - Examples: šokoladas, saldainiai, sausainiai, čipsai, riešutai, saulėgrąžų sėklos, popcornas, kukurūzų lazdelės, marmeladas, džemas, dražė, šokoladiniai saldainiai, karamelės, ledai, šokoladiniai batonėliai, čokoladai, šokoladiniai gaminiai, šokoladiniai produktai, saldainiai
   - NOTE: Candy, chocolate, sweets, snacks, edible treats

9. Gėrimai, kava, arbata (Drinks, Coffee, Tea)
   - Examples: kava, arbata, sulčių gėrimai, mineralinis vanduo, kavos pupelės, arbatos lapeliai, kavos kapsulės, kavos milteliai, žalioji arbata, juodoji arbata, obuolių ir morkų sultys, gazuotas mineralinis vanduo, gėrimai, sultys, vanduo, kava aroma gold, himmel, lavazza, nescafe
   - NOTE: All drinks, juices, water, coffee, tea, beverages

10. Alkoholiniai ir nealkoholiniai gėrimai (Alcoholic and Non-alcoholic Drinks)
    - Examples: alus, vynas, degtinė, likeris, kokteiliai, nealkoholiniai gėrimai, limonadas, kola, sprite, fanta, energetiniai gėrimai
    - NOTE: Alcoholic beverages, soft drinks, energy drinks

11. Kosmetika ir higiena (Cosmetics and Hygiene)
    - Examples: šampūnas, muilas, dantų pasta, šepetukai, rankų kremas, veido kremas, dezodorantas, higienos priemonės, servetėlės, tualetinis popierius, higieniniai įklotai, kosmetinės servetėlės, maisto papildai, aktyvinta anglis, kosmetika, higiena
    - NOTE: Cosmetics, hygiene products, toiletries, health supplements

12. Buitinė chemija, valymo priemonės (Household Chemistry, Cleaning Products)
    - Examples: skalbimo milteliai, valymo priemonės, indų ploviklis, grindų valymo priemonės, vonios valymo priemonės, tualeto valymo priemonės, dulkių siurblys, šluostės, valymo skysčiai, dezinfekcijos priemonės, valymo milteliai, šiukšlių maišai, valymo priemonės, chemija
    - NOTE: Cleaning products, detergents, garbage bags, household chemicals

13. Namų ūkio ir laisvalaikio prekės (Household and Leisure Products)
    - Examples: indai, stikliniai, puodeliai, šaukštai, šakutės, peiliai, keptuvės, puodai, servetėlės, staltiesės, šluostės, šepečiai, plastikiniai indai, folija, maisto plėvelė, indų plovimo šepečiai, virtuvės priemonės, indų džiovintuvai, biuro popierius, elektrinis virdulys, elektrinė orkaitė, vasarinėms kepurėms, žvakės, įrankiai, dėžės, audiniai, namų prekės, buitinės prekės
    - NOTE: Kitchenware, household items, office supplies, tools, containers, fabrics

14. Gyvūnų prekės (Pet Products)
    - Examples: šunų ėdalas, kačių ėdalas, šunų sausainiai, kačių sausainiai, šunų žaislai, kačių žaislai, šunų šampūnas, kačių šampūnas, šunų antblakiai, kačių antblakiai
    - NOTE: Pet food, pet products, animal supplies

15. Augalai, gėlės (Plants, Flowers)
    - Examples: gėlės, augalai, sėklos, tręšimas, pušys, puokštės, vazonai, augalų žemė, augalų tręšimas, svogūnėliai, gėlių puokštės, kambariniai augalai, sodo augalai, gėlių vazonai, augalų sėklos, tręšimo priemonės, augalų žemės maišai
    - NOTE: Plants, flowers, gardening supplies, seeds, soil

MAPPING INSTRUCTIONS:
- For each product name, identify the most appropriate category from the list above
- Look for key Lithuanian words in the product name that match the category examples
- Consider the product's primary function and typical usage
- If a product could fit multiple categories, choose the most specific one
- Use exact category names as listed above (case-sensitive)
- Pay special attention to household items like bags, containers, and kitchen supplies - these belong to \"Namų ūkio ir laisvalaikio prekės\"
- Items like garbage bags, plastic containers, foil, and kitchen supplies are household items, not plants or flowers
- IMPORTANT: Food items like chocolate, candy, dražė, šokoladas, saldainiai are sweets and snacks, NOT cleaning products
- Do NOT categorize food items as cleaning products - if it's edible, it belongs in food categories
- Office supplies (biuro popierius) belong to \"Namų ūkio ir laisvalaikio prekės\"
- Juices and drinks belong to \"Gėrimai, kava, arbata\"
- Baby food and products belong to \"Vaikų ir kūdikių prekės\"
- Meat products (dešra, dešrelės) belong to \"Mėsa ir žuvis\"
- Electrical appliances belong to \"Namų ūkio ir laisvalaikio prekės\"
- Hygiene products belong to \"Kosmetika ir higiena\"
- Bread and pastries belong to \"Duonos gaminiai\"
- Frozen seafood belongs to \"Šaldytas maistas ir ledai\"
- Marinated/preserved foods belong to \"Bakalėja\"

CRITICAL: You will receive a JSON array with products. You MUST use the EXACT product IDs from the input data. Do NOT generate new IDs or use sequential numbering.

OUTPUT FORMAT: Return ONLY a JSON object mapping the EXACT product IDs from the input to category names. Do NOT include any markdown formatting, code blocks, or explanatory text - just the raw JSON:
{\"14740\": \"Vaisiai ir daržovės\", \"14741\": \"Pieno produktai ir kiaušiniai\"}";
    }

    private function parseBulkResponse(array $response): ?array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $content = trim($content);

        // Debug: Log the raw content
        Log::info('Raw GPT response content', ['content' => $content]);

        try {
            $mappings = json_decode($content, true);

            // Debug: Log the parsed mappings
            Log::info('Parsed mappings', ['mappings' => $mappings]);

            if (is_array($mappings) && !empty($mappings)) {
                $validCategories = [
                    'Vaisiai ir daržovės', 'Pieno produktai ir kiaušiniai', 'Duonos gaminiai',
                    'Mėsa ir žuvis', 'Šaldytas maistas ir ledai', 'Bakalėja',
                    'Vaikų ir kūdikių prekės', 'Saldumynai ir užkandžiai', 'Gėrimai, kava, arbata',
                    'Alkoholiniai ir nealkoholiniai gėrimai', 'Kosmetika ir higiena',
                    'Buitinė chemija, valymo priemonės', 'Namų ūkio ir laisvalaikio prekės',
                    'Gyvūnų prekės', 'Augalai, gėlės'
                ];

                // Convert string keys to integers to match database IDs
                $normalizedMappings = [];
                $invalidCategories = [];

                foreach ($mappings as $productId => $category) {
                    $normalizedId = (int) $productId; // Convert string ID to integer

                    if (!in_array($category, $validCategories)) {
                        $invalidCategories[] = [
                            'product_id' => $normalizedId,
                            'category' => $category
                        ];
                        Log::warning('Invalid category in bulk response', [
                            'product_id' => $productId,
                            'category' => $category,
                            'valid_categories' => $validCategories
                        ]);
                        continue;
                    }

                    $normalizedMappings[$normalizedId] = $category;
                }

                if (!empty($invalidCategories)) {
                    $this->storeInvalidCategories($invalidCategories, $content);
                }

                Log::info('Successfully parsed mappings', ['mappings' => $normalizedMappings]);
                return $normalizedMappings;
            } else {
                Log::warning('Mappings is not a valid array or is empty', ['mappings' => $mappings]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse JSON response', ['content' => $content, 'error' => $e->getMessage()]);
            $this->storeParseError($content, $e->getMessage());
        }

        return null;
    }

    private function info(string $message): void
    {
        echo "[INFO] {$message}\n";
        Log::info($message);
    }

    private function warn(string $message): void
    {
        echo "[WARN] {$message}\n";
        Log::warning($message);
    }

    private function storeUnmappedProduct($product, string $storeName, string $status, ?string $errorMessage = null, ?array $productData = null): void
    {
        UnmappedProduct::create([
            'name' => $product->name,
            'store' => $storeName,
            'gpt_response' => null,
            'error_message' => $errorMessage,
            'product_data' => $productData,
            'mapping_status' => $status,
            'attempted_at' => now()
        ]);

        $this->warn("Stored unmapped product: {$product->name} (Status: {$status})");
    }

    private function storeInvalidCategories(array $invalidCategories, string $gptResponse): void
    {
        foreach ($invalidCategories as $invalid) {
            $product = DiscountTemp::find($invalid['product_id']);
            if ($product) {
                UnmappedProduct::create([
                    'name' => $product->name,
                    'store' => $product->store,
                    'gpt_response' => $gptResponse,
                    'error_message' => "Invalid category: {$invalid['category']}",
                    'product_data' => ['id' => $product->id, 'name' => $product->name],
                    'mapping_status' => UnmappedProduct::STATUS_INVALID_CATEGORY,
                    'attempted_at' => now()
                ]);

                $this->warn("Stored product with invalid category: {$product->name} -> {$invalid['category']}");
            }
        }
    }

    private function storeParseError(string $gptResponse, string $errorMessage): void
    {
        UnmappedProduct::create([
            'name' => 'Bulk mapping parse error',
            'store' => 'Unknown',
            'gpt_response' => $gptResponse,
            'error_message' => $errorMessage,
            'product_data' => null,
            'mapping_status' => UnmappedProduct::STATUS_PARSE_ERROR,
            'attempted_at' => now()
        ]);

        $this->warn("Stored parse error: {$errorMessage}");
    }

    private function validateProductIds(array $mappings, array $productData, string $storeName): array
    {
        $validProductIds = array_column($productData, 'id');
        $validMappings = [];
        $invalidIds = [];

        foreach ($mappings as $productId => $category) {
            if (in_array($productId, $validProductIds)) {
                $validMappings[$productId] = $category;
            } else {
                $invalidIds[] = $productId;
            }
        }

        if (!empty($invalidIds)) {
            $this->warn("Found invalid product IDs in GPT response: " . implode(', ', $invalidIds));
            $this->warn("Valid product IDs were: " . implode(', ', $validProductIds));

            $this->storeInvalidProductIds($invalidIds, $validProductIds, $storeName);
        }

        $this->info("Validated mappings: " . count($validMappings) . " valid, " . count($invalidIds) . " invalid");

        return $validMappings;
    }

    private function storeInvalidProductIds(array $invalidIds, array $validIds, string $storeName): void
    {
        UnmappedProduct::create([
            'name' => 'Invalid product IDs in GPT response',
            'store' => $storeName,
            'gpt_response' => json_encode(['invalid_ids' => $invalidIds, 'valid_ids' => $validIds]),
            'error_message' => 'GPT returned product IDs that do not match sent products',
            'product_data' => null,
            'mapping_status' => UnmappedProduct::STATUS_INVALID_PRODUCT_IDS,
            'attempted_at' => now()
        ]);

        $this->warn("Stored invalid product IDs: " . implode(', ', $invalidIds));
    }
}
