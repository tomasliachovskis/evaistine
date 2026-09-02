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
    private ?string $geminiApiKey;
    private string $geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    // Set right before extractDiscountsFromImageGemini() returns null on a
    // final (all-attempts-exhausted) failure, read back by processPdf() so
    // the per-page failure reason (e.g. Gemini's actual error message, not
    // just "it failed") makes it into the INCOMPLETE FLYER summary instead
    // of only the raw request log line, which is what used to require
    // manually grepping the flyer log to find.
    private ?string $lastGeminiFailureReason = null;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');

        if (empty($this->apiKey)) {
            Log::channel('flyer')->warning('OpenAI API key not configured in PdfFlyerProcessingService');
        }

        $this->geminiApiKey = config('services.gemini.api_key');

        if (empty($this->geminiApiKey)) {
            Log::channel('flyer')->warning('Gemini API key not configured in PdfFlyerProcessingService');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function isGeminiConfigured(): bool
    {
        return !empty($this->geminiApiKey);
    }

    /**
     * @param  array<int>|null  $targetPages  Specific 1-based page numbers to
     *   process (e.g. a retry of just the pages that failed last time);
     *   null processes every page in the PDF.
     * @param  array{start_at?: ?string, end_at?: ?string}|null  $seedValidityDates
     *   Validity dates already known from an earlier (successful) page of
     *   this same leaflet — a page-only retry never sees page 1 again (that's
     *   usually where these come from), so without seeding them here the
     *   retried pages' discounts would fall back to no validity dates.
     */
    public function processPdf(string $pdfPath, Store $store, ?array $targetPages = null, ?array $seedValidityDates = null): array
    {
        $processId = uniqid('pdf_' . time() . '_', true);
        Log::channel('flyer')->info('Starting PDF processing', ['pdf' => $pdfPath, 'store' => $store->name, 'process_id' => $processId, 'target_pages' => $targetPages]);

        if (!$this->isGeminiConfigured()) {
            Log::channel('flyer')->error('Gemini API key not configured');
            throw new \Exception('Gemini API key not configured');
        }

        if (!file_exists($pdfPath)) {
            Log::channel('flyer')->error('PDF file not found', ['path' => $pdfPath]);
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        Log::channel('flyer')->info('Converting PDF to images...', ['pdf' => $pdfPath, 'process_id' => $processId]);
        $images = $this->convertPdfToImages($pdfPath, $processId, $targetPages);
        Log::channel('flyer')->info('PDF conversion completed',
            ['total_pages' => count($images), 'process_id' => $processId, 'target_pages' => $targetPages]);

        $validityDates = $seedValidityDates;
        $currentPageNumber = 0;
        $totalSavedCount = 0;
        $totalExtractedCount = 0;
        $failedPages = [];

        foreach ($images as $imageData) {
            $currentPageNumber++;
            $processedImageUrl = $imageData['processed_url'];
            $originalImagePath = $imageData['original_path'];
            $pageNum = $imageData['page_number'];

            Log::channel('flyer')->info("Processing page {$currentPageNumber} of " . count($images), [
                'processed_url' => $processedImageUrl,
                'original_path' => $originalImagePath,
                'page_number' => $pageNum
            ]);

            try {
                $result = $this->extractDiscountsFromImageGemini($processedImageUrl, $store, $validityDates, $pageNum);

                if ($result && isset($result['validity_dates'])) {
                    if (!$validityDates) {
                        $validityDates = $result['validity_dates'];
                        Log::channel('flyer')->info('Validity dates extracted', ['dates' => $validityDates]);
                    }
                }

                if ($result && isset($result['discounts']) && is_array($result['discounts'])) {
                    $discountCount = count($result['discounts']);
                    $totalExtractedCount += $discountCount;
                    Log::channel('flyer')->info("Extracted {$discountCount} discounts from page {$pageNum}");

                    if ($discountCount > 0) {
                        Log::channel('flyer')->info("Saving discounts from page {$currentPageNumber} to database...",
                            ['count' => $discountCount]);
                        $savedCount = $this->saveToDiscountTemp($result['discounts'], $store, $validityDates,
                            $originalImagePath);
                        $totalSavedCount += $savedCount;
                        Log::channel('flyer')->info("Page {$currentPageNumber} discounts saved",
                            ['saved' => $savedCount, 'extracted' => $discountCount]);
                    }
                } else {
                    // $result === null specifically means extractDiscountsFromImageGemini
                    // exhausted all retry attempts (an API-level failure) — a
                    // genuinely empty page instead returns an array with an
                    // empty 'discounts' list, not null. Only the former is a
                    // page we should flag as unprocessed, not a normal "no
                    // deals on this page" outcome.
                    if ($result === null) {
                        $failedPages[$pageNum] = $this->lastGeminiFailureReason ?? 'unknown error';
                    }
                    Log::channel('flyer')->warning("No discounts found on page {$currentPageNumber}", ['result' => $result]);
                }
            } catch (\Exception $e) {
                $failedPages[$pageNum] = $e->getMessage();
                Log::channel('flyer')->error('Error extracting discounts from image', [
                    'processed_url' => $processedImageUrl,
                    'original_path' => $originalImagePath,
                    'page' => $currentPageNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::channel('flyer')->info('Finished processing all pages', [
            'total_pages' => count($images),
            'total_discounts_extracted' => $totalExtractedCount,
            'total_discounts_saved' => $totalSavedCount,
            'failed_pages' => $failedPages,
            'process_id' => $processId
        ]);

        if (!empty($failedPages)) {
            // Deliberately ERROR (not warning) — this is the signal that used
            // to be silently swallowed: a real leaflet where most pages
            // failed (e.g. a sustained Gemini 503 outage, seen live: 6 of 8
            // pages, 17 of an expected ~114 discounts saved) finished with
            // the same log shape as a fully successful run, so nobody
            // noticed short of manually reading the flyer log.
            Log::channel('flyer')->error('PDF processing finished with unprocessed pages — INCOMPLETE FLYER', [
                'process_id' => $processId,
                'store' => $store->name,
                'failed_pages' => $failedPages,
                'total_pages' => count($images),
                'total_discounts_saved' => $totalSavedCount,
            ]);
        }

        if ($totalSavedCount === 0) {
            Log::channel('flyer')->warning('No discounts saved from PDF', ['process_id' => $processId]);
            Log::channel('flyer')->info('Image URLs preserved for debugging', [
                'process_id' => $processId,
                'image_count' => count($images),
                'image_urls' => $images
            ]);
            return [
                'success' => false,
                'message' => 'No discounts extracted from PDF',
                'count' => 0,
                'failed_pages' => $failedPages,
                'validity_dates' => $validityDates,
            ];
        }

        Log::channel('flyer')->info('Processing completed successfully. Image URLs preserved for potential reprocessing.', [
            'process_id' => $processId,
            'image_count' => count($images),
            'image_urls' => $images
        ]);

        return [
            'success' => true,
            'partial' => !empty($failedPages),
            'failed_pages' => $failedPages,
            'validity_dates' => $validityDates,
            'count' => $totalSavedCount,
            'total_extracted' => $totalExtractedCount,
            'process_id' => $processId,
            'images' => $images
        ];
    }

    /** @param array<int>|null $targetPages */
    private function convertPdfToImages(string $pdfPath, string $processId, ?array $targetPages = null): array
    {
        $storageDir = 'flyers';
        $originalStorageDir = 'flyers/originals';
        Log::channel('flyer')->info('Setting up public storage directory', ['dir' => $storageDir, 'process_id' => $processId]);

        if (!Storage::disk('public')->exists($storageDir)) {
            Storage::disk('public')->makeDirectory($storageDir);
            Log::channel('flyer')->info('Created public storage directory', ['dir' => $storageDir]);
        }

        if (!Storage::disk('public')->exists($originalStorageDir)) {
            Storage::disk('public')->makeDirectory($originalStorageDir);
            Log::channel('flyer')->info('Created original images storage directory', ['dir' => $originalStorageDir]);
        }

        $tempDir = storage_path('app/temp/flyers');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            Log::channel('flyer')->info('Initializing PDF object', ['path' => $pdfPath, 'process_id' => $processId]);
            $pdf = new Pdf($pdfPath);

            Log::channel('flyer')->info('Getting number of pages...', ['process_id' => $processId]);
            $numberOfPages = $pdf->getNumberOfPages();
            Log::channel('flyer')->info('PDF has pages',
                ['total_pages' => $numberOfPages, 'process_id' => $processId, 'target_pages' => $targetPages]);

            foreach ($targetPages ?? [] as $targetPage) {
                if ($targetPage < 1 || $targetPage > $numberOfPages) {
                    throw new \Exception("Target page {$targetPage} is out of range. PDF has {$numberOfPages} pages.");
                }
            }

            $imageData = [];
            $pagesToProcess = $targetPages ?? range(1, $numberOfPages);

            foreach ($pagesToProcess as $pageNumber) {
                $uniqueFilename = $processId . '_page_' . $pageNumber . '.png';
                $tempImagePath = $tempDir . '/' . $uniqueFilename;
                Log::channel('flyer')->info("Converting page {$pageNumber}/{$numberOfPages} to image...", [
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
                        Log::channel('flyer')->info("Page {$pageNumber} converted with 300 DPI");
                    } elseif (method_exists($pdf, 'resolution')) {
                        $pdf->setPage($pageNumber)
                            ->setResolution(300)
                            ->saveImage($tempImagePath);
                        Log::channel('flyer')->info("Page {$pageNumber} converted with 300 DPI (using resolution method)");
                    } else {
                        $pdf->setPage($pageNumber)->saveImage($tempImagePath);
                        Log::channel('flyer')->info("Page {$pageNumber} converted with default quality (resolution method not available)");
                    }
                } catch (\Exception $e) {
                    Log::channel('flyer')->warning('Failed to set resolution, trying default quality', [
                        'error' => $e->getMessage(),
                        'page' => $pageNumber
                    ]);
                    $pdf->setPage($pageNumber)->saveImage($tempImagePath);
                }

                if (file_exists($tempImagePath)) {
                    $fileSize = filesize($tempImagePath);
                    Log::channel('flyer')->info("Page {$pageNumber} converted successfully", [
                        'size' => $fileSize,
                        'path' => $tempImagePath,
                        'process_id' => $processId
                    ]);

                    $originalStoragePath = $originalStorageDir . '/' . $uniqueFilename;
                    Storage::disk('public')->put($originalStoragePath, file_get_contents($tempImagePath));
                    $originalImagePath = $originalStoragePath;

                    Log::channel('flyer')->info("Page {$pageNumber} original image saved", [
                        'path' => $originalImagePath,
                        'process_id' => $processId
                    ]);

                    $processedImagePath = $this->preprocessImage($tempImagePath, $processId, $pageNumber);
                    $storagePath = $storageDir . '/' . $uniqueFilename;
                    Storage::disk('public')->put($storagePath, file_get_contents($processedImagePath));

                    $imageUrl = Storage::disk('public')->url($storagePath);
                    $imageData[] = [
                        'processed_url' => $imageUrl,
                        'original_path' => $originalImagePath,
                        'page_number' => $pageNumber
                    ];

                    Log::channel('flyer')->info("Page {$pageNumber} preprocessed and saved to public storage", [
                        'url' => $imageUrl,
                        'original_path' => $originalImagePath,
                        'process_id' => $processId
                    ]);

                    if (file_exists($tempImagePath)) {
                        unlink($tempImagePath);
                    }
                    if (file_exists($processedImagePath) && $processedImagePath !== $tempImagePath) {
                        unlink($processedImagePath);
                    }
                } else {
                    Log::channel('flyer')->error("Failed to create image for page {$pageNumber}", [
                        'expected_path' => $tempImagePath,
                        'process_id' => $processId
                    ]);
                }
            }

            if (empty($imageData)) {
                Log::channel('flyer')->error('No images were created from PDF');
                throw new \Exception('Failed to convert PDF to images. Make sure pdftoppm or pdftocairo is installed.');
            }

            Log::channel('flyer')->info('PDF to images conversion completed', ['images_count' => count($imageData)]);
            return $imageData;
        } catch (\Exception $e) {
            Log::channel('flyer')->error('Error converting PDF to images', [
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

//            $image->scale($image->width() * 2);

            $image->greyscale();
            $image->contrast(20);
            $image->brightness(4);
            $image->sharpen(10);

            $image->save($outputPath);

            Log::channel('flyer')->info("Image preprocessed successfully", [
                'original' => $imagePath,
                'processed' => $outputPath,
                'page' => $pageNumber
            ]);

            return $outputPath;
        } catch (\Exception $e) {
            Log::channel('flyer')->warning('Image preprocessing failed, using original', [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);
            return $imagePath;
        }
    }

    private function extractDiscountsFromImageGemini(
        string $imageUrl,
        Store $store,
        ?array $validityDates,
        int $pageNumber = 0
    ): ?array {
        Log::channel('flyer')->info("Starting Gemini extraction from image", ['image_url' => $imageUrl, 'page' => $pageNumber]);
        $this->lastGeminiFailureReason = null;

        if (empty($this->geminiApiKey)) {
            $this->lastGeminiFailureReason = 'Gemini API key not configured';
            Log::channel('flyer')->error('Gemini API key not configured');
            return null;
        }

        $userPrompt = $this->getUserPrompt($store, $validityDates);
        $systemPrompt = $this->getExtractionPromptStatic();
        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        try {
            $imagePath = $this->getImagePathFromUrl($imageUrl);
            if (!$imagePath) {
                Log::channel('flyer')->error('Failed to extract path from image URL', ['image_url' => $imageUrl]);
                return null;
            }

            if (!Storage::disk('public')->exists($imagePath)) {
                Log::channel('flyer')->error('Image file not found in storage', ['path' => $imagePath, 'image_url' => $imageUrl]);
                return null;
            }

            $imageContent = Storage::disk('public')->get($imagePath);
            if ($imageContent === false) {
                Log::channel('flyer')->error('Failed to read image from storage', ['path' => $imagePath]);
                return null;
            }

            $imageBase64 = base64_encode($imageContent);
            $mimeType = 'image/png';

            $estimatedPromptTokens = (int)(strlen($fullPrompt) / 4);
            $imageWidth = 0;
            $imageHeight = 0;
            try {
                $imageInfo = getimagesize(storage_path('app/public/' . $imagePath));
                if ($imageInfo) {
                    $imageWidth = $imageInfo[0];
                    $imageHeight = $imageInfo[1];
                }
            } catch (\Exception $e) {
            }

            $estimatedImageTokens = 0;
            if ($imageWidth > 0 && $imageHeight > 0) {
                $tilesX = (int)ceil($imageWidth / 512);
                $tilesY = (int)ceil($imageHeight / 512);
                $estimatedImageTokens = $tilesX * $tilesY * 256 + 85;
            }

            $estimatedInputTokens = $estimatedPromptTokens + $estimatedImageTokens;
            $estimatedMaxOutputTokens = 8192;
            $estimatedInputCost = ($estimatedInputTokens / 1000000) * 1.25;
            $estimatedOutputCost = ($estimatedMaxOutputTokens / 1000000) * 5.00;
            $estimatedTotalCost = $estimatedInputCost + $estimatedOutputCost;

            Log::channel('flyer')->info('Preparing Gemini API request', [
                'model' => 'gemini-2.5-flash',
                'image_url' => $imageUrl,
                'prompt_length' => strlen($fullPrompt),
                'image_size' => strlen($imageContent),
                'image_dimensions' => $imageWidth > 0 ? "{$imageWidth}x{$imageHeight}" : 'unknown',
                'estimated_tokens' => [
                    'prompt' => $estimatedPromptTokens,
                    'image' => $estimatedImageTokens,
                    'input_total' => $estimatedInputTokens,
                    'output_max' => $estimatedMaxOutputTokens
                ],
                'estimated_cost_usd' => [
                    'input' => round($estimatedInputCost, 6),
                    'output' => round($estimatedOutputCost, 6),
                    'total' => round($estimatedTotalCost, 6)
                ],
                'estimated_cost_eur' => [
                    'total' => round($estimatedTotalCost * 0.92, 6)
                ]
            ]);

            $maxAttempts = 3;
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                $attempt++;

                // A transient outage (seen live: ~70s of Gemini returning 503
                // for every request) fails all 3 attempts identically when
                // they're only ~4s apart with no backoff — by the time attempt
                // 3 runs, the outage hasn't had any chance to clear. Backs off
                // 5s/10s between attempts instead of hammering the same
                // failing endpoint seconds later.
                if ($attempt > 1) {
                    sleep(5 * ($attempt - 1));
                }

                Log::channel('flyer')->info("Gemini API attempt {$attempt}/{$maxAttempts}", ['image_url' => $imageUrl]);

                try {
                    $startTime = microtime(true);

                    $requestPayload = [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $fullPrompt
                                    ],
                                    [
                                        'inline_data' => [
                                            'mime_type' => $mimeType,
                                            'data' => $imageBase64
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0,
//                            "topP" => 0.95,
                            'maxOutputTokens' => 10000,
                            'responseMimeType' => 'application/json',
                        ],
                    ];

                    $requestPayloadForLog = $requestPayload;
                    if (isset($requestPayloadForLog['contents'][0]['parts'])) {
                        foreach ($requestPayloadForLog['contents'][0]['parts'] as $key => &$part) {
                            if (isset($part['inline_data'])) {
                                unset($requestPayloadForLog['contents'][0]['parts'][$key]);
                            }
                        }
                        unset($part);
                        $requestPayloadForLog['contents'][0]['parts'] = array_values($requestPayloadForLog['contents'][0]['parts']);
                    }

                    $requestForLog = [
                        'url' => $this->geminiApiUrl,
                        'headers' => [
                            'X-goog-api-key' => substr($this->geminiApiKey, 0, 10) . '...',
                            'Content-Type' => 'application/json',
                        ],
                        'payload' => $requestPayloadForLog
                    ];

                    Log::channel('flyer')->info('Gemini API request', [
                        'request' => json_encode($requestForLog,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'attempt' => $attempt
                    ]);

                    $response = Http::timeout(120)
                        ->withHeaders([
                            'X-goog-api-key' => $this->geminiApiKey,
                            'Content-Type' => 'application/json',
                        ])->post($this->geminiApiUrl, $requestPayload);

                    $endTime = microtime(true);
                    $duration = round($endTime - $startTime, 2);

                    $finishReason = $response->json('candidates.0.finishReason');
                    $usageMetadata = $response->json('usageMetadata');
                    $promptTokenCount = $usageMetadata['promptTokenCount'] ?? 0;
                    $candidatesTokenCount = $usageMetadata['candidatesTokenCount'] ?? 0;
                    $totalTokenCount = $usageMetadata['totalTokenCount'] ?? 0;

                    $inputCost = ($promptTokenCount / 1000000) * 1.25;
                    $outputCost = ($candidatesTokenCount / 1000000) * 5.00;
                    $totalCost = $inputCost + $outputCost;

                    Log::channel('flyer')->info('Gemini API response received', [
                        'status' => $response->status(),
                        'duration_seconds' => $duration,
                        'finish_reason' => $finishReason,
                        'tokens' => [
                            'prompt' => $promptTokenCount,
                            'candidates' => $candidatesTokenCount,
                            'total' => $totalTokenCount
                        ],
                        'cost_usd' => [
                            'input' => round($inputCost, 6),
                            'output' => round($outputCost, 6),
                            'total' => round($totalCost, 6)
                        ],
                        'cost_eur' => [
                            'total' => round($totalCost * 0.92, 6)
                        ],
                        'attempt' => $attempt
                    ]);

                    if ($finishReason === 'MAX_TOKENS' || $finishReason === 'OTHER') {
                        Log::channel('flyer')->warning('Gemini response was truncated', [
                            'finish_reason' => $finishReason,
//                            'max_tokens' => 16000,
                            'page' => $pageNumber,
                            'attempt' => $attempt
                        ]);
                    }

                    if ($response->successful()) {
                        $rawResponse = $response->json();

                        Log::channel('flyer')->info('Gemini API raw response', [
                            'raw_response' => json_encode($rawResponse,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'attempt' => $attempt
                        ]);

                        $content = $response->json('candidates.0.content.parts.0.text');

                        if (!$content) {
                            Log::channel('flyer')->error('No content in Gemini response', [
                                'response' => $rawResponse,
                                'attempt' => $attempt
                            ]);
                            if ($attempt < $maxAttempts) {
                                continue;
                            }
                            return null;
                        }

                        Log::channel('flyer')->info('Parsing JSON response from Gemini', [
                            'content_length' => strlen($content),
                            'raw_content' => $content,
                            'attempt' => $attempt
                        ]);

                        $cleanedContent = $this->cleanJsonContent($content);
                        $data = json_decode($cleanedContent, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Log::channel('flyer')->warning('Invalid JSON in Gemini response', [
                                'content_length' => strlen($content),
                                'content_preview' => substr($content, 0, 1000),
                                'cleaned_preview' => substr($cleanedContent, 0, 1000),
                                'error' => json_last_error_msg(),
                                'json_error_code' => json_last_error(),
                                'finish_reason' => $finishReason,
                                'attempt' => $attempt
                            ]);

                            if ($finishReason === 'MAX_TOKENS' || $finishReason === 'OTHER') {
                                Log::channel('flyer')->error('Response truncated - increase maxOutputTokens or split into smaller requests',
                                    [
                                        'current_max_tokens' => 16000,
                                        'content_length' => strlen($content)
                                    ]);
                            }

                            if ($attempt < $maxAttempts) {
                                Log::channel('flyer')->info("Retrying due to invalid JSON...");
                                continue;
                            }

                            Log::channel('flyer')->error('Failed to get valid JSON after all attempts', [
                                'attempts' => $attempt
                            ]);
                            return null;
                        }

                        Log::channel('flyer')->info('Raw JSON data before mapping', [
                            'has_vd' => isset($data['vd']),
                            'has_d' => isset($data['d']),
                            'd_count' => isset($data['d']) && is_array($data['d']) ? count($data['d']) : 0,
                            'raw_data' => json_encode($data,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        ]);

                        $data = $this->mapShortFieldsToLong($data);

                        $discountCount = isset($data['discounts']) && is_array($data['discounts']) ? count($data['discounts']) : 0;

                        Log::channel('flyer')->info('Gemini API response (formatted)', [
                            'response' => json_encode($data,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'discounts_count' => $discountCount,
                            'has_validity_dates' => isset($data['validity_dates']),
                            'attempt' => $attempt
                        ]);

                        Log::channel('flyer')->info('Successfully extracted data from Gemini', [
                            'discounts_count' => $discountCount,
                            'has_validity_dates' => isset($data['validity_dates']),
                            'attempt' => $attempt
                        ]);

                        return $data;
                    }

                    $geminiErrorMessage = json_decode($response->body(), true)['error']['message'] ?? null;
                    $this->lastGeminiFailureReason = "HTTP {$response->status()}" . ($geminiErrorMessage ? ": {$geminiErrorMessage}" : '');

                    Log::channel('flyer')->error('Gemini API request failed', [
                        'status' => $response->status(),
                        'response_preview' => substr($response->body(), 0, 500),
                        'headers' => $response->headers(),
                        'attempt' => $attempt
                    ]);

                    if ($attempt < $maxAttempts) {
                        continue;
                    }

                } catch (\Exception $e) {
                    $this->lastGeminiFailureReason = get_class($e) . ': ' . $e->getMessage();

                    Log::channel('flyer')->error('Error calling Gemini API', [
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

            Log::channel('flyer')->error('Failed to extract discounts from Gemini after all attempts', [
                'max_attempts' => $maxAttempts,
                'image_url' => $imageUrl,
                'reason' => $this->lastGeminiFailureReason,
            ]);

            return null;

        } catch (\Exception $e) {
            $this->lastGeminiFailureReason = get_class($e) . ': ' . $e->getMessage();

            Log::channel('flyer')->error('Error in Gemini extraction', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'image_url' => $imageUrl,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function getImagePathFromUrl(string $imageUrl): ?string
    {
        $storageUrl = Storage::disk('public')->url('');
        $urlPath = parse_url($imageUrl, PHP_URL_PATH);

        if (!$urlPath) {
            return null;
        }

        if (strpos($urlPath, '/storage/') !== false) {
            $path = str_replace('/storage/', '', $urlPath);
            return ltrim($path, '/');
        }

        return null;
    }

    private function cleanJsonContent(string $content): string
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/(\{[\s\S]*\})/s', $content, $matches)) {
            $content = $matches[1];
        }

        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        $content = trim($content);

        $content = $this->fixTruncatedJson($content);

        return $content;
    }

    private function fixTruncatedJson(string $json): string
    {
        $json = rtrim($json);

        if (preg_match('/,\s*$/', $json)) {
            $json = preg_replace('/,\s*$/', '', $json);
        }

        $inString = false;
        $escapeNext = false;
        $lastQuotePos = -1;

        for ($i = strlen($json) - 1; $i >= 0; $i--) {
            $char = $json[$i];

            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }

            if ($char === '"') {
                if (!$inString) {
                    $lastQuotePos = $i;
                    $inString = true;
                } else {
                    $inString = false;
                }
            }
        }

        if ($inString && $lastQuotePos >= 0) {
            $json = substr($json, 0, $lastQuotePos + 1);
        } elseif ($inString) {
            $json .= '"';
        }

        $openBraces = substr_count($json, '{') - substr_count($json, '}');
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');

        if ($openBrackets > 0) {
            $json .= str_repeat(']', $openBrackets);
        }

        if ($openBraces > 0) {
            $json .= str_repeat('}', $openBraces);
        }

        return $json;
    }

    private function mapShortFieldsToLong(array $data): array
    {
        $mapped = [];

        if (isset($data['vd'])) {
            $mapped['validity_dates'] = [
                'start_at' => $data['vd']['sa'] ?? null,
                'end_at' => $data['vd']['ea'] ?? null,
            ];
        }

        if (isset($data['d']) && is_array($data['d'])) {
            $mapped['discounts'] = [];
            foreach ($data['d'] as $discount) {
                $mappedDiscount = [
                    'name' => $discount['n'] ?? null,
                    'brand' => $discount['b'] ?? null,
                    'description' => $discount['desc'] ?? null,
                    'original_price' => $discount['op'] ?? null,
                    'discounted_price' => $discount['dp'] ?? null,
                    'discount_percent' => $discount['dpct'] ?? null,
                    'condition' => $discount['c'] ?? null,
                    'card' => $discount['card'] ?? false,
                    'info' => $discount['info'] ?? null,
                    'exclusion_markers' => $discount['em'] ?? [],
                    'start_at' => $discount['sa'] ?? null,
                    'end_at' => $discount['ea'] ?? null,
                    'box' => $discount['box'] ?? null,
                ];
                $mapped['discounts'][] = $mappedDiscount;
            }
        }

        return $mapped;
    }

    private function getExtractionPromptStatic(): string
    {
        return '
        SYSTEM INSTRUCTION:
NO THOUGHTS. NO REASONING. NO INTERNAL ANALYSIS.
Output ONLY the final JSON starting with { and ending with }.
Do not generate any text, explanations, or thinking blocks before or after the JSON.

        Extract discount data from Lithuanian grocery flyer images.
Return ONLY valid JSON using SHORT field names.

### FIELD MAP

* vd = validity_dates object
* d = discounts array
* n = product name (required)
* box = bounding box [ymin, xmin, ymax, xmax] (normalized 0-1000)
* b = brand
* desc = short description
* info = small descriptive text after product name (weight, fat %, packaging, type, price/kg). Separate items with commas.
* op = original_price
* dp = discounted_price
* dpct = discount_percent
* c = promotion condition
* card = loyalty card required
* em = exclusion markers
* sa = start_at
* ea = end_at

### IGNORE
Ignore banners, decorative text, store slogans and section titles such as: TOP PREKĖS, AKCIJA, SUPER KAINA.

### PRODUCT BLOCK DETECTION & COORDINATES
Flyer pages often contain multiple products arranged in grid layouts.
A product block usually contains: product image, product text, and price area.
Treat each product name as a separate product block.
If two product names appear in the same grid cell, split them into two separate products.

### COORDINATE RULE:
1. Provide a box [ymin, xmin, ymax, xmax] using normalized coordinates (0-1000).
2. BOUNDING BOX STRATEGY: The box must be a "minimal encompassing rectangle." It must include both the product image and the price tag even if they are separated by white space.
3. SPATIAL AGGREGATION: If the product image is on the right and the price tag is on the left, the xmin must start at the left-most edge of the price tag, and xmax must end at the right-most edge of the product image. The box must cover the full horizontal and vertical span required to touch all these elements.
4. NO TEXT-ONLY BIAS: Do not define the box size based on the text description location. The text is secondary; the image and the price are the primary "anchors" for the box dimensions.

OVER-EXTEND IF UNSURE: If the product area is non-rectangular or fragmented, err on the side of making the box larger to ensure both the product visual and the price are fully contained inside.
SAFETY MARGIN: If unsure, use a wider margin to ensure no part of any product or price is cut off. The goal is to provide a complete, uncropped view of the grid area.
### STRICT PRODUCT NAME RULE
1. The product name (n) MUST include ALL text that identifies the product.
2. Start the name from the VERY FIRST word of the text block, even if it is a brand (e.g., "VIČI", "Bocmano").
3. If a comma (,) exists: text BEFORE it is "n", text AFTER is "info".
   - Example: "Lašišų filė VIČI, su oda, 160 g" -> n: "Lašišų filė VIČI", info: "su oda, 160 g".
4. If there is no comma, but a line break separates the name from details (like weight), everything on the top line(s) is the name.
5. DO NOT shorten or simplify the name. If you see "BOCMANO silkių filė", the name is "BOCMANO silkių filė", not just "silkių filė".

### BRAND RULE
* Brand (b): Extract the brand name if it is part of the product name or shown as a logo/label.
* IMPORTANT: Even if you extract the brand into the "b" field, it MUST also remain in the "n" (name) field if it is written as part of the text.

### PRICE EXTRACTION RULES
* dp (discounted_price): Large prominent price.
* op (original_price): Price with a strikethrough.
* dpct: Extract ONLY if "%" symbol is visible. Do NOT calculate.
* c (promotion condition): Extract text like "1+1", "2 už", "Antras pigiau" exactly as shown.

### GENERAL RULES
* Accuracy: Read Lithuanian characters accurately (ą č ę ė į š ų ū ž).
* Text Extraction: Extract text EXACTLY as shown. Do NOT correct spelling or translate.
* Dates: Use ISO format (YYYY-MM-DD). If a product has its own dates (e.g., "KOVO 4–6 d."), use them for sa and ea to override global dates.
* No Guessing: If a word is unclear, return the visible part only. If unsure about the product name, skip it.
* No Math: Do NOT perform any mathematical operations or price calculations.

1. COMPLETENESS: It is mandatory to return ALL visible products. Do not truncate the list.

### STRICT OUTPUT FORMAT
Return ONLY valid JSON. No explanations. No markdown.

{
 "vd": {"sa": "YYYY-MM-DD", "ea": "YYYY-MM-DD"},
 "d": [
   {
     "n": "Product Name",
     "box": [ymin, xmin, ymax, xmax],
     "b": null,
     "desc": null,
     "info": null,
     "op": null,
     "dp": null,
     "dpct": null,
     "c": null,
     "card": false,
     "em": [],
     "sa": null,
     "ea": null
   }
 ]
}';
    }

    private function getUserPrompt(Store $store, ?array $validityDates): string
    {
        $prompt = "Store: {$store->name}. ";

        if ($validityDates) {
            $prompt .= "Validity dates already known: {$validityDates['start_at']} to {$validityDates['end_at']}. If you see different validity dates on this page, replace them with the visible ones. ";
        } else {
            $prompt .= "Extract validity dates if visible. ";
        }

        $prompt .= "Extract all visible discounts from this flyer page. Only extract what you can see — use null for anything unclear. ";
        $prompt .= "Read Lithuanian characters accurately (ą, č, ę, ė, į, š, ų, ū, ž). Include all text: descriptions and small print below product names. ";
        $prompt .= "Return valid JSON only.";

        return $prompt;
    }

    private function saveToDiscountTemp(
        array $discounts,
        Store $store,
        ?array $validityDates,
        ?string $pageImagePath = null
    ): int {
        Log::channel('flyer')->info('Starting to save discounts to database',
            ['total' => count($discounts), 'page_image_path' => $pageImagePath]);

        $savedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $startAt = null;
        $endAt = null;

        if ($validityDates) {
            $startAt = $validityDates['start_at'] ?? null;
            $endAt = $validityDates['end_at'] ?? null;
            Log::channel('flyer')->info('Using validity dates', ['start' => $startAt, 'end' => $endAt]);
        } else {
            Log::channel('flyer')->warning('No validity dates provided, using defaults');
        }

        foreach ($discounts as $index => $discountData) {
            $discountNumber = $index + 1;
            Log::channel('flyer')->info("Processing discount {$discountNumber}/" . count($discounts), [
                'name' => $discountData['name'] ?? 'N/A'
            ]);

            try {
                $validated = $this->validateDiscountData($discountData);

                if (!$validated) {
                    $skippedCount++;
                    Log::channel('flyer')->warning('Invalid discount data skipped', [
                        'discount_number' => $discountNumber,
                        'data' => $discountData
                    ]);
                    continue;
                }

                $discountPercent = $validated['discount_percent'] ?? null;
                if ($discountPercent === null && isset($validated['original_price']) && isset($validated['discounted_price'])) {
                    if ($validated['original_price'] > 0) {
                        $discountPercent = round((($validated['original_price'] - $validated['discounted_price']) / $validated['original_price']) * 100);
                        Log::channel('flyer')->info('Calculated discount percent', [
                            'original' => $validated['original_price'],
                            'discounted' => $validated['discounted_price'],
                            'percent' => $discountPercent
                        ]);
                    }
                }

                Log::channel('flyer')->info('Creating discount record', [
                    'name' => $validated['name'],
                    'store' => $store->name,
                    'original_price' => $validated['original_price'] ?? 0,
                    'discounted_price' => $validated['discounted_price'] ?? 0
                ]);

                // Use product-level dates if available, otherwise fall back to global validity dates
                $productStartAt = !empty($discountData['start_at']) ? $discountData['start_at'] : $startAt;
                $productEndAt = !empty($discountData['end_at']) ? $discountData['end_at'] : $endAt;

                $boxData = null;
                if (isset($validated['box']) && is_array($validated['box'])) {
                    $boxData = json_encode($validated['box']);
                }

                DiscountTemp::create([
                    'name' => $validated['name'],
                    'brand' => $validated['brand'] ?? null,
                    'category' => '',
                    'image_url' => null,
                    'store' => $store->name,
                    'original_price' => $validated['original_price'] ?? 0,
                    'discounted_price' => $validated['discounted_price'] ?? 0,
                    'discount_percent' => $discountPercent ?? 0,
                    'condition' => $validated['condition'] ?? null,
                    'card' => $validated['card'] ?? false,
                    'info' => $validated['info'] ?? null,
                    'start_at' => $productStartAt ?: now(),
                    'end_at' => $productEndAt ?: now()->addDays(7),
                    'processed' => false,
                    'box' => $boxData,
                    'page_image_path' => $pageImagePath,
                ]);

                $savedCount++;
                Log::channel('flyer')->info("Discount {$discountNumber} saved successfully", ['id' => $savedCount]);
            } catch (\Exception $e) {
                $errorCount++;
                Log::channel('flyer')->error('Error saving discount to temp table', [
                    'discount_number' => $discountNumber,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'data' => $discountData,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::channel('flyer')->info('Finished saving discounts', [
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

        $box = null;
        if (isset($data['box']) && is_array($data['box']) && count($data['box']) === 4) {
            $boxValues = array_values($data['box']);
            $allNumeric = true;
            foreach ($boxValues as $value) {
                if (!is_numeric($value)) {
                    $allNumeric = false;
                    break;
                }
            }
            if ($allNumeric) {
                $box = $data['box'];
            }
        }

        $data['brand'] = !empty($data['brand']) && is_array($data['brand']) ? $data['brand'][0] : $data['brand'];

        $validated = [
            'name' => trim($data['name']),
            'brand' => !empty($data['brand']) ? trim($data['brand']) : null,
            'original_price' => $this->parsePrice($data['original_price'] ?? null),
            'discounted_price' => $this->parsePrice($data['discounted_price'] ?? null),
            'discount_percent' => isset($data['discount_percent']) ? (int)$data['discount_percent'] : null,
            'condition' => isset($data['condition']) && !empty($data['condition']) ? trim($data['condition']) : null,
            'card' => isset($data['card']) && (bool)$data['card'],
            'info' => !empty($data['info']) ? trim($data['info']) : null,
            'box' => $box,
        ];

        $hasPrices = $validated['original_price'] > 0 || $validated['discounted_price'] > 0;
        $hasDiscountPercent = !empty($validated['discount_percent']);

        if (!$hasPrices && !$hasDiscountPercent) {
            return null;
        }

        if ($validated['original_price'] > 0 && $validated['discounted_price'] > 0) {
            if ($validated['discounted_price'] >= $validated['original_price']) {
                Log::channel('flyer')->warning('Discounted price must be less than original price', [
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
