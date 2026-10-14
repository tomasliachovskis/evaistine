<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use App\Services\ProductFactExtractor;
use Illuminate\Console\Command;
use Throwable;

// Runs the same product pages through several models and reports tokens, cost
// and time, so the model for product-facts:extract is picked on numbers
// (owner, 2026-10-14). Results go to storage/app/product-facts-benchmark/ for
// a side-by-side read; nothing is written to product_facts.
class BenchmarkProductFacts extends Command
{
    protected $signature = 'product-facts:benchmark
                            {--models=gpt-5-nano,gpt-5-mini,gemini-2.5-flash-lite,gemini-3.5-flash-lite : Comma-separated models}
                            {--limit=30 : How many product pages}';

    protected $description = 'Compare models for product facts extraction on the same pages';

    public function handle(ProductFactExtractor $extractor): int
    {
        $facts = ProductFact::with('product:id,name,category_id', 'product.category:id,name')
            ->whereNotNull('source_text')
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();
        if ($facts->isEmpty()) {
            $this->error('No product_facts rows with text yet.');

            return 1;
        }

        $dir = storage_path('app/product-facts-benchmark');
        @mkdir($dir, 0775, true);
        // The pages themselves, so every answer can be checked against its source.
        file_put_contents("{$dir}/_sources.json", json_encode($facts->mapWithKeys(fn ($f) => [$f->id => [
            'name' => $f->page_name ?? $f->product?->name,
            'category' => $f->page_category,
            'url' => $f->product_url,
            'attributes' => $f->attributes,
            'text' => $f->source_text,
        ]]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $rows = [];
        $catalog = ProductFact::whereNotNull('source_text')->count();

        foreach (array_filter(array_map('trim', explode(',', $this->option('models')))) as $model) {
            $in = $out = $ok = $failed = 0;
            $seconds = 0.0;
            $results = [];

            foreach ($facts as $fact) {
                $started = microtime(true);
                try {
                    $result = $extractor->extract($fact, $model);
                    $in += $result['input_tokens'];
                    $out += $result['output_tokens'];
                    $ok++;
                    $results[$fact->id] = ['name' => $fact->page_name ?? $fact->product?->name, 'facts' => $result['facts']];
                } catch (Throwable $e) {
                    $failed++;
                    $results[$fact->id] = ['name' => $fact->page_name ?? $fact->product?->name, 'error' => $e->getMessage()];
                    if ($failed === 1) {
                        $this->warn($e->getMessage());
                    }
                }
                $seconds += microtime(true) - $started;
            }

            file_put_contents("{$dir}/{$model}.json", json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $cost = ProductFactExtractor::cost($model, $in, $out);
            $perPage = $ok ? $cost / $ok : null;
            $rows[] = [
                $model,
                "{$ok}/" . $facts->count(),
                $ok ? round($in / $ok) : '-',
                $ok ? round($out / $ok) : '-',
                $ok ? round($seconds / ($ok + $failed), 1) . ' s' : '-',
                $cost !== null ? '$' . number_format($cost, 4) : '?',
                $perPage !== null ? '$' . number_format($perPage * 60000, 2) : '?',
            ];
        }

        $this->table(['Model', 'OK', 'In/page', 'Out/page', 'Time/page', 'Cost here', 'Cost for 60 000 pages'], $rows);
        $this->line("Answers per model: {$dir}/<model>.json ({$catalog} pages with text so far).");

        return 0;
    }
}
