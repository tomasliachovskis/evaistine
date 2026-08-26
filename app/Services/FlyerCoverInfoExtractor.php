<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Narrow sibling of PdfFlyerProcessingService's Gemini OCR call — that one
// extracts full discount data from every page of a flyer; this one only
// asks a single cover page for title/validity dates/summary, for leaflets
// whose listing page doesn't expose a reliable date any other way (e.g.
// Lidl's "Katalogai" — grill/back-to-school/seasonal catalogs with no date
// in their slug or listing text).
class FlyerCoverInfoExtractor
{
    private ?string $geminiApiKey;

    private string $geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->geminiApiKey = config('services.gemini.api_key');

        if (empty($this->geminiApiKey)) {
            Log::channel('flyer')->warning('Gemini API key not configured in FlyerCoverInfoExtractor');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->geminiApiKey);
    }

    public function extract(string $imageBinary, string $storeName): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $prompt = <<<PROMPT
        SYSTEM INSTRUCTION:
        NO THOUGHTS. NO REASONING. NO INTERNAL ANALYSIS.
        Output ONLY the final JSON starting with { and ending with }.
        Do not generate any text, explanations, or thinking blocks before or after the JSON.

        You are looking at the cover page of a "{$storeName}" store promotional catalog/flyer (Lithuanian retail).

        Extract:
        - title: a short descriptive title for this catalog, in Lithuanian, based on what's shown on the page.
        - valid_from: the validity start date shown on the page, ISO format YYYY-MM-DD, or null if no date is visible.
        - valid_to: the validity end date shown on the page, ISO format YYYY-MM-DD, or null if no date is visible.
        - summary: one short Lithuanian sentence describing what this catalog is about.

        If no validity dates are visible anywhere on the page, use null for valid_from and valid_to — do not guess, calculate, or infer dates that aren't actually printed on the page.

        Return ONLY valid JSON, no markdown, no explanation:
        {"title": "...", "valid_from": "YYYY-MM-DD" or null, "valid_to": "YYYY-MM-DD" or null, "summary": "..."}
        PROMPT;

        $mimeType = str_starts_with($imageBinary, "\xFF\xD8") ? 'image/jpeg' : 'image/png';

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-goog-api-key' => $this->geminiApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->geminiApiUrl, [
                    'contents' => [[
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => base64_encode($imageBinary),
                                ],
                            ],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (!$response->successful()) {
                Log::channel('flyer')->warning('FlyerCoverInfoExtractor: Gemini request failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $content = $response->json('candidates.0.content.parts.0.text');

            if (!$content) {
                Log::channel('flyer')->warning('FlyerCoverInfoExtractor: no content in Gemini response');

                return null;
            }

            $data = json_decode($content, true);

            if (!is_array($data)) {
                Log::channel('flyer')->warning('FlyerCoverInfoExtractor: invalid JSON from Gemini', ['content' => $content]);

                return null;
            }

            return [
                'title' => $data['title'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'summary' => $data['summary'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::channel('flyer')->error('FlyerCoverInfoExtractor: exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
