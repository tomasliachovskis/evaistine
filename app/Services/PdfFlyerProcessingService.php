<?php

namespace App\Services;

use App\Models\DiscountTemp;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;

class PdfFlyerProcessingService
{
    private ?string $apiKey;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured in PdfFlyerProcessingService');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function processPdf(string $pdfPath, Store $store): array
    {
        $processId = uniqid('pdf_' . time() . '_', true);
        Log::info('Starting PDF processing', ['pdf' => $pdfPath, 'store' => $store->name, 'process_id' => $processId]);
        
        if (!$this->isConfigured()) {
            Log::error('OpenAI API key not configured');
            throw new \Exception('OpenAI API key not configured');
        }

        if (!file_exists($pdfPath)) {
            Log::error('PDF file not found', ['path' => $pdfPath]);
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        Log::info('Converting PDF to images...', ['pdf' => $pdfPath, 'process_id' => $processId]);
        $images = $this->convertPdfToImages($pdfPath, $processId);
        Log::info('PDF conversion completed', ['total_pages' => count($images), 'process_id' => $processId]);
        
        $validityDates = null;
        $pageNumber = 0;
        $totalSavedCount = 0;
        $totalExtractedCount = 0;

        foreach ($images as $imagePath) {
            $pageNumber++;
            Log::info("Processing page {$pageNumber} of " . count($images), ['image' => $imagePath]);
            
            try {
                $result = $this->extractDiscountsFromImage($imagePath, $store, $validityDates, $pageNumber);
                
                if ($result && isset($result['validity_dates'])) {
                    if (!$validityDates) {
                        $validityDates = $result['validity_dates'];
                        Log::info('Validity dates extracted', ['dates' => $validityDates]);
                    }
                }

                if ($result && isset($result['discounts']) && is_array($result['discounts'])) {
                    $discountCount = count($result['discounts']);
                    $totalExtractedCount += $discountCount;
                    Log::info("Extracted {$discountCount} discounts from page {$pageNumber}");
                    
                    if ($discountCount > 0) {
                        Log::info("Saving discounts from page {$pageNumber} to database...", ['count' => $discountCount]);
                        $savedCount = $this->saveToDiscountTemp($result['discounts'], $store, $validityDates);
                        $totalSavedCount += $savedCount;
                        Log::info("Page {$pageNumber} discounts saved", ['saved' => $savedCount, 'extracted' => $discountCount]);
                    }
                } else {
                    Log::warning("No discounts found on page {$pageNumber}", ['result' => $result]);
                }
            } catch (\Exception $e) {
                Log::error('Error extracting discounts from image', [
                    'image' => $imagePath,
                    'page' => $pageNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('Finished processing all pages', [
            'total_pages' => count($images),
            'total_discounts_extracted' => $totalExtractedCount,
            'total_discounts_saved' => $totalSavedCount,
            'process_id' => $processId
        ]);

        if ($totalSavedCount === 0) {
            Log::warning('No discounts saved from PDF', ['process_id' => $processId]);
            Log::info('Image files preserved for debugging', [
                'process_id' => $processId,
                'image_count' => count($images),
                'images' => $images
            ]);
            return [
                'success' => false,
                'message' => 'No discounts extracted from PDF',
                'count' => 0
            ];
        }

        Log::info('Processing completed successfully. Image files preserved for potential reprocessing.', [
            'process_id' => $processId,
            'image_count' => count($images),
            'image_paths' => $images
        ]);

        return [
            'success' => true,
            'count' => $totalSavedCount,
            'total_extracted' => $totalExtractedCount,
            'process_id' => $processId,
            'images' => $images
        ];
    }

    private function convertPdfToImages(string $pdfPath, string $processId): array
    {
        $tempDir = storage_path('app/temp/flyers');
        Log::info('Setting up temp directory', ['dir' => $tempDir, 'process_id' => $processId]);
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
            Log::info('Created temp directory', ['dir' => $tempDir]);
        }

        try {
            Log::info('Initializing PDF object', ['path' => $pdfPath, 'process_id' => $processId]);
            $pdf = new Pdf($pdfPath);
            
            Log::info('Getting number of pages...', ['process_id' => $processId]);
            $numberOfPages = $pdf->getNumberOfPages();
            Log::info('PDF has pages', ['total_pages' => $numberOfPages, 'process_id' => $processId]);
            
            $images = [];

            for ($pageNumber = 1; $pageNumber <= $numberOfPages; $pageNumber++) {
                $uniqueFilename = $processId . '_page_' . $pageNumber . '.png';
                $imagePath = $tempDir . '/' . $uniqueFilename;
                Log::info("Converting page {$pageNumber}/{$numberOfPages} to image...", [
                    'output' => $imagePath,
                    'process_id' => $processId,
                    'unique_filename' => $uniqueFilename
                ]);
                
                $pdf->setPage($pageNumber)->saveImage($imagePath);
                
                if (file_exists($imagePath)) {
                    $fileSize = filesize($imagePath);
                    Log::info("Page {$pageNumber} converted successfully", [
                        'size' => $fileSize,
                        'path' => $imagePath,
                        'process_id' => $processId
                    ]);
                    $images[] = $imagePath;
                } else {
                    Log::error("Failed to create image for page {$pageNumber}", [
                        'expected_path' => $imagePath,
                        'process_id' => $processId
                    ]);
                }
            }

            if (empty($images)) {
                Log::error('No images were created from PDF');
                throw new \Exception('Failed to convert PDF to images. Make sure pdftoppm or pdftocairo is installed.');
            }

            Log::info('PDF to images conversion completed', ['images_count' => count($images)]);
            return $images;
        } catch (\Exception $e) {
            Log::error('Error converting PDF to images', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to convert PDF to images: ' . $e->getMessage());
        }
    }

    private function extractDiscountsFromImage(string $imagePath, Store $store, ?array $validityDates, int $pageNumber = 0): ?array
    {
        Log::info("Starting extraction from image", ['image' => $imagePath, 'page' => $pageNumber]);
        
        if (!file_exists($imagePath)) {
            Log::error('Image file does not exist', ['path' => $imagePath]);
            return null;
        }

        $fileSize = filesize($imagePath);
        Log::info('Reading image file', ['size' => $fileSize, 'path' => $imagePath]);
        
        $imageData = file_get_contents($imagePath);
        $imageSizeBytes = strlen($imageData);
        Log::info('Image file read', ['bytes' => $imageSizeBytes]);
        
        Log::info('Encoding image to base64...');
        $base64Image = base64_encode($imageData);
        $base64Size = strlen($base64Image);
        Log::info('Image encoded to base64', ['base64_length' => $base64Size]);

        $userPrompt = $this->getUserPrompt($store, $validityDates);
        Log::info('Preparing OpenAI API request', [
            'model' => 'gpt-4o',
            'image_size' => $base64Size,
            'prompt_length' => strlen($userPrompt)
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getExtractionPrompt()
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $userPrompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/png;base64,' . $base64Image
                        ]
                    ]
                ]
            ]
        ];

        try {
            Log::info('Sending request to OpenAI Vision API...', ['url' => $this->apiUrl]);
            $startTime = microtime(true);
            
            $response = Http::timeout(120)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->apiUrl, [
                    'model' => 'gpt-4o',
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object']
                ]);

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            Log::info('OpenAI API response received', [
                'status' => $response->status(),
                'duration_seconds' => $duration
            ]);

            if ($response->successful()) {
                Log::info('Processing successful response...');
                $content = $response->json('choices.0.message.content');
                
                if (!$content) {
                    Log::error('No content in OpenAI response', ['response' => $response->json()]);
                    return null;
                }

                Log::info('Parsing JSON response', ['content_length' => strlen($content)]);
                $data = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Invalid JSON in OpenAI response', [
                        'content_preview' => substr($content, 0, 500),
                        'error' => json_last_error_msg(),
                        'json_error_code' => json_last_error()
                    ]);
                    return null;
                }

                $discountCount = isset($data['discounts']) && is_array($data['discounts']) ? count($data['discounts']) : 0;
                Log::info('Successfully extracted data', [
                    'discounts_count' => $discountCount,
                    'has_validity_dates' => isset($data['validity_dates'])
                ]);

                return $data;
            }

            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'response_preview' => substr($response->body(), 0, 500),
                'headers' => $response->headers()
            ]);

        } catch (\Exception $e) {
            Log::error('Error calling OpenAI Vision API', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return null;
    }

    private function getExtractionPrompt(): string
    {
        return "You are an expert at extracting discount information from Lithuanian grocery store flyers. Your task is to analyze the flyer image and extract all discount information in a structured JSON format.

CRITICAL: Return ONLY valid JSON without any markdown formatting, code blocks, or explanatory text.

STRICT EXTRACTION RULES - READ CAREFULLY:
- ONLY extract information that is ACTUALLY VISIBLE in the image
- DO NOT infer, guess, or invent any information
- DO NOT use your general knowledge to fill in missing data
- DO NOT assume or estimate values that are not clearly visible
- If text is blurry, unclear, or partially cut off, use null for that field
- If a field is not visible on the image, use null - DO NOT make up values
- Extract text EXACTLY as it appears - do not translate, correct, or modify it
- If you cannot clearly see a value, use null - accuracy is more important than completeness

EXTRACT THE FOLLOWING INFORMATION:

1. Validity dates: Look for date ranges in the header or footer (e.g., 'Kainos galioja 2026 m. kovo 4-24 d.'). Extract start_at and end_at dates in YYYY-MM-DD format.
   - If dates are not visible or unclear, use null for both start_at and end_at
   - DO NOT guess dates based on context or current date

2. For each product discount, extract ONLY what is visible:
   - name: Full product name in Lithuanian EXACTLY as shown (e.g., 'Smulkinta kiauliena', 'Viščiukų broilerių filė')
     * If product name is not clearly visible, DO NOT include this product
   - brand: Brand name ONLY if clearly visible on the image (e.g., 'BIOVELA', 'Vyniaus Paukštynas')
     * If brand is not visible, use null - DO NOT infer brand from product name
   - description: Full product description ONLY if visible (e.g., 'Chilled minced pork, 450 g')
     * If description is not visible, use null
   - weight: Weight or quantity ONLY if clearly specified and visible (e.g., '450 g', '1 kg', '800 g')
     * If weight is not visible, use null - DO NOT estimate weight
   - original_price: Original price as decimal number ONLY if clearly visible (e.g., 2.85, 5.40)
     * If original price is not visible, use null - DO NOT estimate or calculate
   - discounted_price: Discounted price as decimal number ONLY if clearly visible (e.g., 1.89, 4.09)
     * If discounted price is not visible, use null - DO NOT estimate
   - price_per_kg: Price per kilogram ONLY if explicitly shown (e.g., 4.20, 10.23)
     * If price per kg is not visible, use null - DO NOT calculate from other prices
   - discount_percent: Discount percentage ONLY if clearly visible as integer (e.g., 33, 25, 20)
     * If discount percent is visible, use the exact number shown
     * If discount percent is NOT visible but both original_price and discounted_price are visible, calculate: ((original_price - discounted_price) / original_price) * 100
     * If discount percent is not visible and prices are missing, use null
   - condition: Special conditions ONLY if clearly visible (e.g., '1+1', 'Pirk 2 už')
     * If no conditions are visible, use null - DO NOT assume conditions
   - card: Boolean indicating if discount requires loyalty card
     * Use false by default unless you can clearly see text indicating card requirement (e.g., 'su kortele', 'lojalumo kortelė')
     * DO NOT assume card requirement
   - info: Additional information ONLY if clearly visible (e.g., 'be antibiotikų', 'a. r.')
     * If no additional info is visible, use null - DO NOT add information
   - exclusion_markers: Array of markers ONLY if clearly visible (e.g., ['*'] or ['**'])
     * If no markers are visible, use empty array []
     * DO NOT assume markers exist

IMPORTANT RULES:
- All prices should be decimal numbers (e.g., 2.85, not '2.85€')
- Discount percent should be integer (e.g., 33, not '33%')
- Dates must be in YYYY-MM-DD format
- Product names must be EXACTLY as shown on the flyer - do not modify, translate, or correct
- If a field is not available or not clearly visible, use null
- Extract ALL products that are clearly visible on the page
- If a product section is partially cut off or unclear, skip that product entirely
- When in doubt, use null - it's better to have incomplete data than incorrect data

OUTPUT FORMAT:
{
  \"validity_dates\": {
    \"start_at\": \"2026-03-04\",
    \"end_at\": \"2026-03-24\"
  },
  \"discounts\": [
    {
      \"name\": \"Product name\",
      \"brand\": \"Brand name or null\",
      \"description\": \"Full description\",
      \"weight\": \"450 g or null\",
      \"original_price\": 2.85,
      \"discounted_price\": 1.89,
      \"price_per_kg\": 4.20,
      \"discount_percent\": 33,
      \"condition\": null,
      \"card\": false,
      \"info\": \"Additional info or null\",
      \"exclusion_markers\": []
    }
  ]
}";
    }

    private function getUserPrompt(Store $store, ?array $validityDates): string
    {
        $prompt = "Extract all discount information from this {$store->name} store flyer page. ";
        
        if ($validityDates) {
            $prompt .= "The validity dates have already been extracted: from {$validityDates['start_at']} to {$validityDates['end_at']}. ";
        } else {
            $prompt .= "Extract the validity dates from the flyer header or footer ONLY if clearly visible. ";
        }
        
        $prompt .= "Extract all products with discounts that are clearly visible on this page. ";
        $prompt .= "CRITICAL: Only extract information that you can clearly see in the image. ";
        $prompt .= "DO NOT invent, infer, or guess any values. ";
        $prompt .= "If something is not visible or unclear, use null for that field. ";
        $prompt .= "Return the data in the exact JSON format specified.";
        
        return $prompt;
    }

    private function saveToDiscountTemp(array $discounts, Store $store, ?array $validityDates): int
    {
        Log::info('Starting to save discounts to database', ['total' => count($discounts)]);
        
        $savedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $startAt = null;
        $endAt = null;

        if ($validityDates) {
            $startAt = $validityDates['start_at'] ?? null;
            $endAt = $validityDates['end_at'] ?? null;
            Log::info('Using validity dates', ['start' => $startAt, 'end' => $endAt]);
        } else {
            Log::warning('No validity dates provided, using defaults');
        }

        foreach ($discounts as $index => $discountData) {
            $discountNumber = $index + 1;
            Log::info("Processing discount {$discountNumber}/" . count($discounts), [
                'name' => $discountData['name'] ?? 'N/A'
            ]);
            
            try {
                $validated = $this->validateDiscountData($discountData);
                
                if (!$validated) {
                    $skippedCount++;
                    Log::warning('Invalid discount data skipped', [
                        'discount_number' => $discountNumber,
                        'data' => $discountData
                    ]);
                    continue;
                }

                $discountPercent = $validated['discount_percent'] ?? null;
                if ($discountPercent === null && isset($validated['original_price']) && isset($validated['discounted_price'])) {
                    if ($validated['original_price'] > 0) {
                        $discountPercent = round((($validated['original_price'] - $validated['discounted_price']) / $validated['original_price']) * 100);
                        Log::info('Calculated discount percent', [
                            'original' => $validated['original_price'],
                            'discounted' => $validated['discounted_price'],
                            'percent' => $discountPercent
                        ]);
                    }
                }

                Log::info('Creating discount record', [
                    'name' => $validated['name'],
                    'store' => $store->name,
                    'original_price' => $validated['original_price'] ?? 0,
                    'discounted_price' => $validated['discounted_price'] ?? 0
                ]);

                DiscountTemp::create([
                    'name' => $validated['name'],
                    'brand' => $validated['brand'] ?? null,
                    'category' => '',
                    'image_url' => null,
                    'product_url' => $this->generateProductUrl($validated['name']),
                    'store' => $store->name,
                    'original_price' => $validated['original_price'] ?? 0,
                    'discounted_price' => $validated['discounted_price'] ?? 0,
                    'discount_percent' => $discountPercent ?? 0,
                    'condition' => $validated['condition'] ?? null,
                    'card' => $validated['card'] ?? false,
                    'info' => $validated['info'] ?? null,
                    'start_at' => $startAt ? date('Y-m-d H:i:s', strtotime($startAt)) : now(),
                    'end_at' => $endAt ? date('Y-m-d H:i:s', strtotime($endAt)) : now()->addDays(7),
                    'processed' => false,
                ]);

                $savedCount++;
                Log::info("Discount {$discountNumber} saved successfully", ['id' => $savedCount]);
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error saving discount to temp table', [
                    'discount_number' => $discountNumber,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'data' => $discountData,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('Finished saving discounts', [
            'total' => count($discounts),
            'saved' => $savedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount
        ]);

        return $savedCount;
    }

    private function validateDiscountData(array $data): ?array
    {
        if (empty($data['name'])) {
            return null;
        }

        $validated = [
            'name' => trim($data['name']),
            'brand' => isset($data['brand']) && !empty($data['brand']) ? trim($data['brand']) : null,
            'original_price' => $this->parsePrice($data['original_price'] ?? null),
            'discounted_price' => $this->parsePrice($data['discounted_price'] ?? null),
            'discount_percent' => isset($data['discount_percent']) ? (int)$data['discount_percent'] : null,
            'condition' => isset($data['condition']) && !empty($data['condition']) ? trim($data['condition']) : null,
            'card' => isset($data['card']) ? (bool)$data['card'] : false,
            'info' => isset($data['info']) && !empty($data['info']) ? trim($data['info']) : null,
        ];

        $hasPrices = $validated['original_price'] > 0 || $validated['discounted_price'] > 0;
        $hasDiscountPercent = !empty($validated['discount_percent']);

        if (!$hasPrices && !$hasDiscountPercent) {
            return null;
        }

        if ($validated['original_price'] > 0 && $validated['discounted_price'] > 0) {
            if ($validated['discounted_price'] >= $validated['original_price']) {
                Log::warning('Discounted price must be less than original price', [
                    'original' => $validated['original_price'],
                    'discounted' => $validated['discounted_price']
                ]);
                return null;
            }
        }

        return $validated;
    }

    private function parsePrice($price): float
    {
        if (is_null($price)) {
            return 0.0;
        }

        if (is_numeric($price)) {
            return (float)$price;
        }

        if (is_string($price)) {
            $price = str_replace(['€', ' ', ','], ['', '', '.'], $price);
            $price = preg_replace('/[^0-9.]/', '', $price);
            return (float)$price;
        }

        return 0.0;
    }

    private function generateProductUrl(string $productName): string
    {
        $slug = strtolower($productName);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return 'https://superakcijos.lt/akcijos/' . $slug;
    }
}
