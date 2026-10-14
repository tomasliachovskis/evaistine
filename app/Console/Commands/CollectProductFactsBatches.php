<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use App\Models\ProductFactBatch;
use App\Services\GeminiBatchClient;
use App\Services\ProductFactExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// Polls the open Gemini batch jobs of product-facts:batch-submit and writes
// the finished facts (docs/product-facts.md); product-facts:push then sends
// them to production. Rows that got no answer, or whose page changed while
// the job ran, are given back for the next submit. Only API calls, so no
// quiet hours.
class CollectProductFactsBatches extends Command
{
    protected $signature = 'product-facts:batch-collect';

    protected $description = 'Collect finished Gemini batch jobs into product facts';

    public function handle(GeminiBatchClient $client): int
    {
        $batches = ProductFactBatch::whereNull('finished_at')->orderBy('id')->get();
        $failed = false;

        foreach ($batches as $batch) {
            try {
                $this->poll($batch, $client);
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$batch->name}: " . $e->getMessage());
                Log::warning('product-facts:batch-collect failed', ['batch' => $batch->name, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }

        return $failed ? 1 : 0;
    }

    private function poll(ProductFactBatch $batch, GeminiBatchClient $client): void
    {
        $job = $client->get($batch->name);
        $state = GeminiBatchClient::state($job) ?? $batch->state;

        if (!in_array($state, ProductFactBatch::FINAL_STATES, true)) {
            if ($state !== $batch->state) {
                $batch->update(['state' => $state]);
            }
            $this->line("{$batch->name}: {$state}");

            return;
        }

        if ($state !== 'JOB_STATE_SUCCEEDED') {
            $released = $batch->facts()->update(['batch_id' => null]);
            $batch->update(['state' => $state, 'error' => GeminiBatchClient::error($job), 'finished_at' => now()]);
            $this->deleteInput($batch, $client);
            $summary = "{$batch->name} {$state}, {$released} pages given back";
            $this->warn($summary);
            Log::warning("product-facts:batch-collect: {$summary}");

            return;
        }

        $resultsFile = GeminiBatchClient::resultsFile($job);
        if (!$resultsFile) {
            throw new RuntimeException('succeeded but no results file in ' . mb_substr(json_encode($job), 0, 300));
        }

        $ok = $changed = $bad = $in = $out = 0;
        foreach (preg_split('/\r?\n/', $client->download($resultsFile)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $result = json_decode($line, true);
            [$id, $hash] = array_pad(explode(':', (string) ($result['key'] ?? ''), 2), 2, null);
            $row = $id ? ProductFact::where('id', $id)->where('batch_id', $batch->id)->first() : null;
            if (!$row) {
                continue;
            }

            if ($row->source_hash !== $hash) {
                // The page changed while the job ran: its facts would be stale.
                $row->update(['batch_id' => null]);
                $changed++;
                continue;
            }

            $answer = ProductFactExtractor::geminiResult($result['response'] ?? []);
            $in += $answer['input'];
            $out += $answer['output'];
            try {
                if (!empty($result['error'])) {
                    throw new RuntimeException(json_encode($result['error']));
                }
                $row->update([
                    'facts' => ProductFactExtractor::parseFacts($answer['text'], $batch->model),
                    'extracted_with' => $batch->model,
                    'extracted_at' => now(),
                    'batch_id' => null,
                ]);
                $ok++;
            } catch (Throwable $e) {
                $row->update(['batch_id' => null]);
                $bad++;
                Log::warning('product-facts:batch-collect: no facts', ['id' => $row->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }

        // Rows the results file didn't mention at all go back too.
        $missing = $batch->facts()->update(['batch_id' => null]);

        $batch->update(['state' => $state, 'input_tokens' => $in, 'output_tokens' => $out, 'finished_at' => now()]);
        $this->deleteInput($batch, $client);

        $cost = ProductFactExtractor::cost($batch->model, $in, $out, batch: true);
        $summary = "{$batch->name}: {$ok} extracted with {$batch->model}, {$bad} failed, {$changed} changed meanwhile, {$missing} missing, {$in}/{$out} tokens"
            . ($cost !== null ? ', $' . number_format($cost, 4) . ' (batch price)' : '');
        $this->info($summary);
        Log::info("product-facts:batch-collect: {$summary}");
    }

    private function deleteInput(ProductFactBatch $batch, GeminiBatchClient $client): void
    {
        if ($batch->input_file) {
            rescue(fn () => $client->deleteFile($batch->input_file), report: false);
        }
    }
}
