<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use App\Models\ProductFactBatch;
use App\Services\GeminiBatchClient;
use App\Services\ProductFactExtractor;
use App\Support\QuietHours;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Sends the pages that still need facts to the Gemini Batch API as one job
// (docs/product-facts.md): half the price of product-facts:extract, results
// within 24 h. The rows are claimed (batch_id) until
// product-facts:batch-collect writes their facts or gives them back.
class SubmitProductFactsBatch extends Command
{
    protected $signature = 'product-facts:batch-submit
                            {--limit= : At most this many pages (default config(product_facts.batch_limit))}
                            {--model= : Override config(product_facts.model)}';

    protected $description = 'Submit product pages without facts as a Gemini batch job';

    public function handle(ProductFactExtractor $extractor, GeminiBatchClient $client): int
    {
        $model = $this->option('model') ?: config('product_facts.model');
        if (QuietHours::active() && !$this->option('model')) {
            return 0;
        }
        if (!$model) {
            $this->info('No PRODUCT_FACTS_MODEL set, nothing submitted.');

            return 0;
        }
        if (!str_starts_with($model, 'gemini-')) {
            $this->error("{$model}: only Gemini models have a batch here; use product-facts:extract.");

            return 1;
        }

        $empty = ProductFactExtractor::closeEmptyPages();

        $rows = ProductFact::with('product:id,name,category_id', 'product.category:id,name')
            ->whereNull('extracted_at')
            ->whereNull('batch_id')
            ->where(fn ($q) => $q->whereNotNull('source_text')->orWhereNotNull('attributes'))
            ->orderBy('id')
            ->limit((int) ($this->option('limit') ?: config('product_facts.batch_limit')))
            ->get();

        if ($rows->isEmpty()) {
            $this->info("{$empty} empty pages closed, nothing to submit.");

            return 0;
        }

        $displayName = 'evaistine-product-facts-' . now()->format('Ymd-His');
        $dir = storage_path('app/product-facts-batches');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = "{$dir}/{$displayName}.jsonl";
        $file = fopen($path, 'w');
        foreach ($rows as $row) {
            fwrite($file, json_encode($extractor->geminiBatchLine($row, $model), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        fclose($file);

        $fileName = $client->uploadJsonl($path, $displayName);
        $job = $client->create($model, $fileName, $displayName);
        @unlink($path);

        $batch = DB::transaction(function () use ($job, $model, $rows, $fileName) {
            $batch = ProductFactBatch::create([
                'name' => $job['name'],
                'model' => $model,
                'state' => GeminiBatchClient::state($job) ?? 'JOB_STATE_PENDING',
                'row_count' => $rows->count(),
                'input_file' => $fileName,
                'submitted_at' => now(),
            ]);
            foreach ($rows->pluck('id')->chunk(1000) as $ids) {
                ProductFact::whereIn('id', $ids)->update(['batch_id' => $batch->id]);
            }

            return $batch;
        });

        $summary = "{$empty} empty pages closed, {$batch->row_count} pages submitted as {$batch->name} ({$model})";
        $this->info($summary);
        Log::info("product-facts:batch-submit: {$summary}");

        return 0;
    }
}
