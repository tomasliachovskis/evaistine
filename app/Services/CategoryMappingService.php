<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryMapper;
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

    // What typically belongs in each root category, for the GPT prompts.
    // Keys must match config('categories.roots').
    private const CATEGORY_EXAMPLES = [
        'Nereceptiniai vaistai' => 'nereceptiniai vaistai nuo skausmo, peršalimo, kosulio, alergijos, virškinimo sutrikimų; tabletės, sirupai, purškalai, tepalai, pastilės (e.g. ibuprofenas, paracetamolis, Gripex, Strepsils)',
        'Vitaminai ir maisto papildai' => 'vitaminai, mineralai, maisto papildai, probiotikai, omega-3, kolagenas, magnis, cinkas, geležis, žuvų taukai',
        'Veido priežiūra' => 'veido kremai, serumai, prausikliai, tonikai, veido kaukės, paakių priemonės, aknės priežiūra',
        'Kūno priežiūra ir apsauga nuo saulės' => 'kūno kremai ir losjonai, dezodorantai, antiperspirantai, dušo želė, prausikliai kūnui, rankų ir pėdų kremai, kremai nuo saulės',
        'Plaukų priežiūra' => 'šampūnai, kondicionieriai, plaukų kaukės, priemonės nuo pleiskanų ir plaukų slinkimo',
        'Dekoratyvinė kosmetika ir kvepalai' => 'pudros, makiažo pagrindai, lūpų dažai, tušai, akių šešėliai, nagų lakai, kvepalai',
        'Higiena' => 'dantų pastos ir šepetėliai, burnos skalavimo skysčiai, intymi higiena, higieniniai paketai, servetėlės, prezervatyvai, lubrikantai',
        'Mamai ir vaikui' => 'sauskelnės, kūdikių mišiniai ir maistas, vaikų kosmetika, žindymo prekės, nėštumo testai, prekės nėščiosioms',
        'Medicinos prekės ir prietaisai' => 'kraujospūdžio matuokliai, termometrai, gliukomačiai, inhaliatoriai, greitieji testai, pleistrai, tvarsčiai, pirmosios pagalbos ir slaugos priemonės',
        'Ortopedija ir kompresinės prekės' => 'įtvarai, kompresinės kojinės, ortopediniai vidpadžiai, tvarsčiai sąnariams, ramentai',
        'Akių priežiūra ir optika' => 'kontaktiniai lęšiai, lęšių skysčiai, akių lašai, akinių priežiūra',
        'Sportas, svorio kontrolė, arbatos ir spec. maistas' => 'sporto papildai, baltymai, svorio kontrolės produktai, vaistažolių arbatos, sveikas ir specializuotas maistas, saldikliai',
    ];

    private function categoryPromptList(): string
    {
        $lines = [];
        $i = 1;
        foreach (array_keys(config('categories.roots', [])) as $name) {
            $lines[] = $i++ . ". {$name}\n   - Examples: " . (self::CATEGORY_EXAMPLES[$name] ?? '');
        }

        return implode("\n\n", $lines);
    }

    private function getBulkSystemPrompt(): string
    {
        $categories = $this->categoryPromptList();
        $first = array_keys(config('categories.roots', []))[0] ?? '';
        $second = array_keys(config('categories.roots', []))[1] ?? '';

        return "You are a product categorization expert for Lithuanian pharmacy (vaistinė) products. Your task is to map each product name to the most appropriate category.

CRITICAL: Return ONLY raw JSON without any markdown formatting, code blocks, or explanatory text.

TASK: For each product name provided, determine which category it belongs to and return a JSON mapping of product ID to category name.

IMPORTANT: All product names are in Lithuanian language. Pay attention to Lithuanian terms, active substances, dosage forms (tabletės, kapsulės, sirupas, tepalas) and brand names.

CRITICAL INSTRUCTION: You MUST use the EXACT product IDs that are provided in the input. Do NOT generate your own IDs or use sequential numbering. Use the exact same IDs that appear in the input data.

Available categories with examples:

{$categories}

MAPPING INSTRUCTIONS:
- Use exact category names as listed above (case-sensitive).
- A medicine (registered drug, has a strength like \"400 mg\" and a pack size like \"N20\") goes to \"Nereceptiniai vaistai\"; a supplement with similar ingredients goes to \"Vitaminai ir maisto papildai\".
- Prescription-only medicines are not listed on this site: OMIT them from the output.
- Deodorants, body lotions and sunscreen go to \"Kūno priežiūra ir apsauga nuo saulės\"; face creams and face masks to \"Veido priežiūra\".
- Baby and children's products go to \"Mamai ir vaikui\", except children's vitamins and supplements, which go to \"Vitaminai ir maisto papildai\".
- If a product doesn't clearly fit any category, OMIT its id rather than guessing.

CRITICAL: You will receive a JSON array with products. You MUST use the EXACT product IDs from the input data. Do NOT generate new IDs or use sequential numbering.

OUTPUT FORMAT: Return ONLY a JSON object mapping the EXACT product IDs from the input to category names. Do NOT include any markdown formatting, code blocks, or explanatory text - just the raw JSON:
{\"14740\": \"{$first}\", \"14741\": \"{$second}\"}";
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
                $validCategories = array_keys(config('categories.roots', []));

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

    /**
     * Map unmapped category_mappers rows (category_id null) to an existing root category
     * (a Category with parent_id null), grouping by identical store_category text so every
     * store sharing that text gets classified together in a single GPT call.
     *
     * @return array{mapped: int, unresolved: array<int, string>}
     */
    public function mapCategoryMapperRows(bool $dryRun = false): array
    {
        if (!$this->isConfigured()) {
            $this->warn('CategoryMappingService is not configured. Skipping category_mappers mapping.');
            return ['mapped' => 0, 'unresolved' => []];
        }

        $rootIdByName = [];
        foreach (Category::whereNull('parent_id')->get(['id', 'name']) as $category) {
            $rootIdByName[trim($category->name)] = $category->id;
        }
        $rootNames = array_keys($rootIdByName);

        $groups = CategoryMapper::whereNull('category_id')->get()
            ->groupBy(fn (CategoryMapper $mapper) => trim($mapper->store_category));

        $entries = $groups->map(fn ($rows, $text) => ['id' => $rows->first()->id, 'text' => $text])->values();

        $mapped = 0;
        $unresolved = [];

        foreach ($entries->chunk(30) as $chunk) {
            $classification = $this->classifyStoreCategories($chunk->all(), $rootNames);

            foreach ($chunk as $entry) {
                $text = $entry['text'];
                $ids = $groups[$text]->pluck('id');
                $categoryName = $classification[$entry['id']] ?? null;
                $categoryId = $categoryName ? ($rootIdByName[$categoryName] ?? null) : null;

                if (!$categoryId) {
                    $unresolved[] = $text;
                    continue;
                }

                if (!$dryRun) {
                    CategoryMapper::whereIn('id', $ids)->update(['category_id' => $categoryId]);
                }

                $mapped += $ids->count();
            }

            sleep(1);
        }

        return ['mapped' => $mapped, 'unresolved' => $unresolved];
    }

    private function classifyStoreCategories(array $entries, array $rootNames): array
    {
        try {
            $payload = array_map(fn ($e) => ['id' => $e['id'], 'text' => $e['text']], $entries);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $categoriesJson = json_encode(array_values($rootNames), JSON_UNESCAPED_UNICODE);

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => config('services.openai.model', 'gpt-5-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getStoreCategorySystemPrompt($categoriesJson),
                        ],
                        [
                            'role' => 'user',
                            'content' => "Categories: {$payloadJson}",
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('OpenAI API request failed while classifying store categories', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [];
            }

            $content = trim($response->json('choices.0.message.content') ?? '');
            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                Log::warning('Failed to parse store category classification response', ['content' => $content]);

                return [];
            }

            $validIds = array_column($entries, 'id');
            $result = [];
            foreach ($decoded as $id => $categoryName) {
                $id = (int) $id;
                if (in_array($id, $validIds, true) && in_array($categoryName, $rootNames, true)) {
                    $result[$id] = $categoryName;
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API for store category classification', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function getStoreCategorySystemPrompt(string $categoriesJson): string
    {
        return "You are classifying raw e-commerce category labels scraped from Lithuanian pharmacy (vaistinė) websites into one of our existing top-level categories.

CRITICAL: Return ONLY raw JSON without any markdown formatting, code blocks, or explanatory text.

Our existing top-level categories (use these EXACT names, case-sensitive):
{$categoriesJson}

CRITICAL INSTRUCTION: You MUST use the EXACT numeric IDs provided in the input. Do NOT generate your own IDs or use sequential numbering.

TASK: Each input item is {\"id\": <id>, \"text\": <raw store category label>}. The label is sometimes a breadcrumb path separated by '>' or '/'. Pick the single best-matching category from the list above for each item. If a label is too generic or doesn't clearly fit any category (e.g. \"Visos prekės\", \"All products\"), OMIT that id from the output entirely rather than guessing.

OUTPUT FORMAT: Return ONLY a JSON object mapping the EXACT input id to one of the exact category names above:
{\"14\": \"Nereceptiniai vaistai\", \"27\": \"Veido priežiūra\"}";
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
