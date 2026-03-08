<?php

namespace App\Services;

use App\Models\DiscountTemp;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
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

        foreach ($images as $imageUrl) {
            $pageNumber++;
            Log::info("Processing page {$pageNumber} of " . count($images), ['image_url' => $imageUrl]);

            try {
                $result = $this->extractDiscountsFromImage($imageUrl, $store, $validityDates, $pageNumber);

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
                    'image_url' => $imageUrl,
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
            Log::info('Image URLs preserved for debugging', [
                'process_id' => $processId,
                'image_count' => count($images),
                'image_urls' => $images
            ]);
            return [
                'success' => false,
                'message' => 'No discounts extracted from PDF',
                'count' => 0
            ];
        }

        Log::info('Processing completed successfully. Image URLs preserved for potential reprocessing.', [
            'process_id' => $processId,
            'image_count' => count($images),
            'image_urls' => $images
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
        $storageDir = 'flyers';
        Log::info('Setting up public storage directory', ['dir' => $storageDir, 'process_id' => $processId]);

        if (!Storage::disk('public')->exists($storageDir)) {
            Storage::disk('public')->makeDirectory($storageDir);
            Log::info('Created public storage directory', ['dir' => $storageDir]);
        }

        $tempDir = storage_path('app/temp/flyers');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            Log::info('Initializing PDF object', ['path' => $pdfPath, 'process_id' => $processId]);
            $pdf = new Pdf($pdfPath);

            Log::info('Getting number of pages...', ['process_id' => $processId]);
            $numberOfPages = $pdf->getNumberOfPages();
            Log::info('PDF has pages', ['total_pages' => $numberOfPages, 'process_id' => $processId]);

            $imageUrls = [];

            for ($pageNumber = 1; $pageNumber <= $numberOfPages; $pageNumber++) {
                $uniqueFilename = $processId . '_page_' . $pageNumber . '.png';
                $tempImagePath = $tempDir . '/' . $uniqueFilename;
                Log::info("Converting page {$pageNumber}/{$numberOfPages} to image...", [
                    'output' => $tempImagePath,
                    'process_id' => $processId,
                    'unique_filename' => $uniqueFilename,
                    'dpi' => 300
                ]);

                try {
                    if (method_exists($pdf, 'setResolution')) {
                        $pdf->setPage($pageNumber)
                            ->setResolution(300)
                            ->saveImage($tempImagePath);
                        Log::info("Page {$pageNumber} converted with 300 DPI");
                    } elseif (method_exists($pdf, 'resolution')) {
                        $pdf->setPage($pageNumber)
                            ->setResolution(300)
                            ->saveImage($tempImagePath);
                        Log::info("Page {$pageNumber} converted with 300 DPI (using resolution method)");
                    } else {
                        $pdf->setPage($pageNumber)->saveImage($tempImagePath);
                        Log::info("Page {$pageNumber} converted with default quality (resolution method not available)");
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to set resolution, trying default quality', [
                        'error' => $e->getMessage(),
                        'page' => $pageNumber
                    ]);
                    $pdf->setPage($pageNumber)->saveImage($tempImagePath);
                }

                if (file_exists($tempImagePath)) {
                    $fileSize = filesize($tempImagePath);
                    Log::info("Page {$pageNumber} converted successfully", [
                        'size' => $fileSize,
                        'path' => $tempImagePath,
                        'process_id' => $processId
                    ]);

                    $processedImagePath = $this->preprocessImage($tempImagePath, $processId, $pageNumber);
                    $storagePath = $storageDir . '/' . $uniqueFilename;
                    Storage::disk('public')->put($storagePath, file_get_contents($processedImagePath));
                    
                    $imageUrl = Storage::disk('public')->url($storagePath);
                    $imageUrls[] = $imageUrl;
                    
                    Log::info("Page {$pageNumber} preprocessed and saved to public storage", [
                        'url' => $imageUrl,
                        'process_id' => $processId
                    ]);

                    if (file_exists($tempImagePath)) {
                        unlink($tempImagePath);
                    }
                    if (file_exists($processedImagePath) && $processedImagePath !== $tempImagePath) {
                        unlink($processedImagePath);
                    }
                } else {
                    Log::error("Failed to create image for page {$pageNumber}", [
                        'expected_path' => $tempImagePath,
                        'process_id' => $processId
                    ]);
                }
            }

            if (empty($imageUrls)) {
                Log::error('No images were created from PDF');
                throw new \Exception('Failed to convert PDF to images. Make sure pdftoppm or pdftocairo is installed.');
            }

            Log::info('PDF to images conversion completed', ['images_count' => count($imageUrls)]);
            return $imageUrls;
        } catch (\Exception $e) {
            Log::error('Error converting PDF to images', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to convert PDF to images: ' . $e->getMessage());
        }
    }

    private function preprocessImage(string $imagePath, string $processId, int $pageNumber): string
    {
        $outputPath = storage_path('app/temp/flyers/' . $processId . '_page_' . $pageNumber . '_processed.png');
        
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($imagePath);
            
            $image->contrast(5);
            $image->brightness(2);
            $image->sharpen(3);
            
            $image->save($outputPath);
            
            Log::info("Image preprocessed successfully", [
                'original' => $imagePath,
                'processed' => $outputPath,
                'page' => $pageNumber
            ]);
            
            return $outputPath;
        } catch (\Exception $e) {
            Log::warning('Image preprocessing failed, using original', [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);
            return $imagePath;
        }
    }

    private function extractDiscountsFromImage(string $imageUrl, Store $store, ?array $validityDates, int $pageNumber = 0): ?array
    {
        Log::info("Starting extraction from image", ['image_url' => $imageUrl, 'page' => $pageNumber]);

        $userPrompt = $this->getUserPrompt($store, $validityDates);
        Log::info('Preparing OpenAI API request', [
            'model' => 'gpt-4o',
            'image_url' => $imageUrl,
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
                            'url' => $imageUrl
                        ]
                    ]
                ]
            ]
        ];

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            Log::info("OpenAI API attempt {$attempt}/{$maxAttempts}", ['image_url' => $imageUrl]);

            try {
                $startTime = microtime(true);

                $response = Http::timeout(120)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])->post($this->apiUrl, [
                        'model' => 'gpt-4o',
                        'messages' => $messages,
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0
                    ]);

                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);
                Log::info('OpenAI API response received', [
                    'status' => $response->status(),
                    'duration_seconds' => $duration,
                    'attempt' => $attempt
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');

                    if (!$content) {
                        Log::error('No content in OpenAI response', [
                            'response' => $response->json(),
                            'attempt' => $attempt
                        ]);
                        if ($attempt < $maxAttempts) {
                            continue;
                        }
                        return null;
                    }

                    Log::info('Parsing JSON response', [
                        'content_length' => strlen($content),
                        'attempt' => $attempt
                    ]);

                    $data = json_decode($content, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning('Invalid JSON in OpenAI response', [
                            'content_preview' => substr($content, 0, 500),
                            'error' => json_last_error_msg(),
                            'json_error_code' => json_last_error(),
                            'attempt' => $attempt
                        ]);

                        if ($attempt < $maxAttempts) {
                            Log::info("Retrying due to invalid JSON...");
                            continue;
                        }

                        Log::error('Failed to get valid JSON after all attempts', [
                            'attempts' => $attempt
                        ]);
                        return null;
                    }

                    $discountCount = isset($data['discounts']) && is_array($data['discounts']) ? count($data['discounts']) : 0;
                    Log::info('Successfully extracted data', [
                        'discounts_count' => $discountCount,
                        'has_validity_dates' => isset($data['validity_dates']),
                        'attempt' => $attempt
                    ]);

                    return $data;
                }

                Log::error('OpenAI API request failed', [
                    'status' => $response->status(),
                    'response_preview' => substr($response->body(), 0, 500),
                    'headers' => $response->headers(),
                    'attempt' => $attempt
                ]);

                if ($attempt < $maxAttempts) {
                    continue;
                }

            } catch (\Exception $e) {
                Log::error('Error calling OpenAI Vision API', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'attempt' => $attempt,
                    'trace' => $e->getTraceAsString()
                ]);

                if ($attempt < $maxAttempts) {
                    continue;
                }
            }
        }

        Log::error('Failed to extract discounts after all attempts', [
            'max_attempts' => $maxAttempts,
            'image_url' => $imageUrl
        ]);

        return null;
    }

    private function getExtractionPrompt(): string
    {
        return "Extract discount data from Lithuanian supermarket flyer images. Return ONLY valid JSON.

RULES:
- Extract ONLY visible data. Use null for unclear fields. Accuracy over completeness.
- Extract text exactly as shown. Read Lithuanian characters (ą, č, ę, ė, į, š, ų, ū, ž) accurately.
- Skip partially cut off or unreadable products.

FIELDS:
- name: Product name (required, skip if unclear)
- brand, description, weight, condition, info: null if not visible
- original_price, discounted_price, price_per_kg: decimal numbers, null if not visible
- discount_percent: integer, calculate if prices visible: round(((original - discounted) / original) * 100)
- card: true if loyalty card required, else false
- exclusion_markers: array of visible markers, empty if none
- start_at, end_at: YYYY-MM-DD if shown in product block, else null

VALIDITY DATES:
- Extract global start_at/end_at from header/footer (YYYY-MM-DD). null if not visible.
- Product-specific dates override global dates if shown.

FORMAT: Prices as decimals, discount_percent as integer, dates as YYYY-MM-DD.

OUTPUT:
{\"validity_dates\": {\"start_at\": \"YYYY-MM-DD\", \"end_at\": \"YYYY-MM-DD\"}, \"discounts\": [{\"name\": \"\", \"brand\": null, \"description\": null, \"weight\": null, \"original_price\": null, \"discounted_price\": null, \"price_per_kg\": null, \"discount_percent\": null, \"condition\": null, \"card\": false, \"info\": null, \"exclusion_markers\": [], \"start_at\": null, \"end_at\": null}]}";
    }

    private function getUserPrompt(Store $store, ?array $validityDates): string
    {
        $prompt = "Store: {$store->name}. ";

        if ($validityDates) {
            $prompt .= "Validity dates already known: {$validityDates['start_at']} to {$validityDates['end_at']}. ";
        } else {
            $prompt .= "Extract validity dates if visible. ";
        }

        $prompt .= "Extract all visible discounts from this flyer page. Only extract what you can see — use null for anything unclear. ";
        $prompt .= "Read Lithuanian characters accurately (ą, č, ę, ė, į, š, ų, ū, ž). Include all text: descriptions and small print below product names. ";
        $prompt .= "Return valid JSON only.";

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

                // Use product-level dates if available, otherwise fall back to global validity dates
                $productStartAt = !empty($discountData['start_at']) ? $discountData['start_at'] : $startAt;
                $productEndAt = !empty($discountData['end_at']) ? $discountData['end_at'] : $endAt;

                DiscountTemp::create([
                    'name' => $validated['name'],
                    'brand' => $validated['brand'] ?? null,
                    'category' => '',
                    'image_url' => null,
                    'product_url' => '',
                    'store' => $store->name,
                    'original_price' => $validated['original_price'] ?? 0,
                    'discounted_price' => $validated['discounted_price'] ?? 0,
                    'discount_percent' => $discountPercent ?? 0,
                    'condition' => $validated['condition'] ?? null,
                    'card' => $validated['card'] ?? false,
                    'info' => $validated['info'] ?? null,
                    'start_at' => $productStartAt ? date('Y-m-d H:i:s', strtotime($productStartAt)) : now(),
                    'end_at' => $productEndAt ? date('Y-m-d H:i:s', strtotime($productEndAt)) : now()->addDays(7),
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

}
