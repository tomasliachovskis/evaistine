<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use App\Services\ProductFactExtractor;
use App\Support\QuietHours;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

// Turns new or changed product page texts into facts (docs/product-facts.md),
// one model call per page. The nightly run uses the Gemini Batch API instead
// (product-facts:batch-submit/-collect, half the price); this one is for
// manual and small runs. Runs on the local machine, where the pages are
// scraped; product-facts:push then sends the facts to production. The page's own name and breadcrumb are
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
        if (QuietHours::active() && !$this->option('model')) {
            return 0;
        }
        if (!$model) {
            $this->info('No PRODUCT_FACTS_MODEL set, nothing extracted.');

            return 0;
        }

        $empty = ProductFactExtractor::closeEmptyPages();

        // Rows waiting in a Gemini batch (product-facts:batch-submit) are left to it.
        $rows = ProductFact::with('product:id,name,category_id', 'product.category:id,name')
            ->whereNull('extracted_at')
            ->whereNull('batch_id')
            ->where(fn ($q) => $q->whereNotNull('source_text')->orWhereNotNull('attributes'))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $in = $out = $ok = $failed = 0;
        foreach ($rows as $row) {
            if (QuietHours::active() && !$this->option('model')) {
                $this->warn('Quiet hours, stopping.');
                break;
            }
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
