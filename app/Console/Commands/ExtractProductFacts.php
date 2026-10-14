<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use App\Services\ProductFactExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

// Turns new or changed product page texts into facts (docs/product-facts.md).
// Runs on the local machine, where the pages are scraped; product-facts:push
// then sends the facts to production. The page's own name and breadcrumb are
// the context, so rows need no product in the local DB.
class ExtractProductFacts extends Command
{
    protected $signature = 'product-facts:extract
                            {--limit=200 : At most this many pages per run}
                            {--model= : Override config(product_facts.model)}';

    protected $description = 'Extract product facts from scraped product page texts';

    public function handle(ProductFactExtractor $extractor): int
    {
        $model = $this->option('model') ?: config('product_facts.model');
        if (!$model) {
            $this->info('No PRODUCT_FACTS_MODEL set, nothing extracted.');

            return 0;
        }

        // A page that gave no text is tried once and then done: empty facts,
        // no model call, pushed like the rest so production won't list it again.
        $empty = ProductFact::whereNull('extracted_at')
            ->whereNull('source_text')
            ->where(fn ($q) => $q->whereNull('attributes')->orWhereRaw('JSON_LENGTH(attributes) = 0'))
            ->update(['facts' => json_encode(['schema' => ProductFactExtractor::SCHEMA_VERSION]), 'extracted_with' => 'none', 'extracted_at' => now()]);

        $rows = ProductFact::with('product:id,name,category_id', 'product.category:id,name')
            ->whereNull('extracted_at')
            ->where(fn ($q) => $q->whereNotNull('source_text')->orWhereNotNull('attributes'))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $in = $out = $ok = $failed = 0;
        foreach ($rows as $row) {
            try {
                $result = $extractor->extract($row, $model);
                $row->update([
                    'facts' => $result['facts'],
                    'extracted_with' => $result['model'],
                    'extracted_at' => now(),
                ]);
                $in += $result['input_tokens'];
                $out += $result['output_tokens'];
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('product-facts:extract failed', ['id' => $row->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
                if ($failed >= 5 && $ok === 0) {
                    $this->error('Five failures in a row, stopping: ' . $e->getMessage());
                    break;
                }
            }
        }

        $cost = ProductFactExtractor::cost($model, $in, $out);
        $summary = "{$empty} empty pages closed, {$ok} extracted with {$model}, {$failed} failed, {$in}/{$out} tokens"
            . ($cost !== null ? ', $' . number_format($cost, 4) : '');
        $this->info($summary);
        if ($ok > 0 || $failed > 0) {
            Log::info("product-facts:extract: {$summary}");
        }

        return $failed > 0 && $ok === 0 ? 1 : 0;
    }
}
