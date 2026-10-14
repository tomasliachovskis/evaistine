<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// The Gemini Batch API (https://ai.google.dev/gemini-api/docs/batch-mode):
// upload a JSONL file of requests, create a job, poll it, download the
// results file. Half the price of single calls, results within 24 h; a job
// still pending after 48 h expires. Used by product-facts:batch-submit and
// product-facts:batch-collect (docs/product-facts.md).
class GeminiBatchClient
{
    private const BASE = 'https://generativelanguage.googleapis.com';

    /** Uploads a JSONL file (resumable upload) and returns its name, "files/...". */
    public function uploadJsonl(string $path, string $displayName): string
    {
        $size = filesize($path);
        $start = $this->http()
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $size,
                'X-Goog-Upload-Header-Content-Type' => 'application/jsonl',
            ])
            ->post(self::BASE . '/upload/v1beta/files', ['file' => ['display_name' => $displayName]]);
        $this->check($start, 'upload start');

        $uploadUrl = $start->header('x-goog-upload-url');
        if (!$uploadUrl) {
            throw new RuntimeException('Gemini upload start: no x-goog-upload-url header.');
        }

        $upload = $this->http()
            ->withHeaders([
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize',
            ])
            ->withBody(file_get_contents($path), 'application/jsonl')
            ->post($uploadUrl);
        $this->check($upload, 'upload');

        return (string) ($upload->json('file.name') ?: throw new RuntimeException('Gemini upload: no file name in ' . mb_substr($upload->body(), 0, 300)));
    }

    /** Creates a batch job from an uploaded file; returns the job (its "name" is "batches/..."). */
    public function create(string $model, string $fileName, string $displayName): array
    {
        $response = $this->http()->post(self::BASE . "/v1beta/models/{$model}:batchGenerateContent", [
            'batch' => [
                'display_name' => $displayName,
                'input_config' => ['file_name' => $fileName],
            ],
        ]);
        $this->check($response, 'create batch');

        $job = $response->json() ?? [];
        if (empty($job['name'])) {
            throw new RuntimeException('Gemini create batch: no name in ' . mb_substr($response->body(), 0, 300));
        }

        return $job;
    }

    public function get(string $name): array
    {
        $response = $this->http()->get(self::BASE . "/v1beta/{$name}");
        $this->check($response, "get {$name}");

        return $response->json() ?? [];
    }

    /** The results file's raw JSONL. */
    public function download(string $fileName): string
    {
        $response = $this->http()->timeout(600)->get(self::BASE . "/download/v1beta/{$fileName}:download", ['alt' => 'media']);
        $this->check($response, "download {$fileName}");

        return $response->body();
    }

    public function deleteFile(string $fileName): void
    {
        $this->http()->delete(self::BASE . "/v1beta/{$fileName}");
    }

    // Google's docs show the job both as an operation (metadata.state,
    // response.responsesFile) and as the batch itself (state,
    // dest.fileName); read whichever is there.
    public static function state(array $job): ?string
    {
        $state = data_get($job, 'metadata.state') ?? data_get($job, 'state');

        // The batch resource names states BATCH_STATE_*, the docs JOB_STATE_*.
        return $state ? str_replace('BATCH_STATE_', 'JOB_STATE_', $state) : null;
    }

    public static function resultsFile(array $job): ?string
    {
        return data_get($job, 'response.responsesFile')
            ?? data_get($job, 'metadata.output.responsesFile')
            ?? data_get($job, 'output.responsesFile')
            ?? data_get($job, 'dest.fileName');
    }

    public static function error(array $job): ?string
    {
        $error = data_get($job, 'error');

        return $error ? mb_substr(json_encode($error), 0, 1000) : null;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(120)->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')]);
    }

    private function check(Response $response, string $what): void
    {
        if (!$response->successful()) {
            throw new RuntimeException("Gemini {$what}: HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }
    }
}
