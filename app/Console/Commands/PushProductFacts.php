<?php

namespace App\Console\Commands;

use App\Models\ProductFact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

// Sends the facts extracted on this machine to production
// (SCRAPER_API_URL/product-facts, the same endpoint the local scraper posts
// pages to; production only stores and links them). Rows go again when they
// were re-extracted after the last push. See docs/product-facts.md.
class PushProductFacts extends Command
{
    protected $signature = 'product-facts:push {--limit=2000 : At most this many rows per run}';

    protected $description = 'Send locally extracted product facts to production';

    public function handle(): int
    {
        $apiUrl = rtrim((string) config('services.scrapers.api_url'), '/');
        if ($apiUrl === '') {
            $this->info('No SCRAPER_API_URL set, nothing pushed.');

            return 0;
        }

        $rows = ProductFact::with('store:id,name')
            ->whereNotNull('extracted_at')
            ->where(fn ($q) => $q->whereNull('pushed_at')->orWhereColumn('pushed_at', '<', 'extracted_at'))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $pushed = $failed = 0;
        foreach ($rows->groupBy('store_id') as $group) {
            foreach ($group->chunk(100) as $chunk) {
                $response = Http::timeout(60)->retry(2, 2000, throw: false)->post("{$apiUrl}/product-facts", [
                    'store' => $chunk->first()->store->name,
                    'pages' => $chunk->map(fn (ProductFact $row) => [
                        'product_url' => $row->product_url,
                        'name' => $row->page_name,
                        'category' => $row->page_category,
                        'text' => $row->source_text,
                        'attributes' => $row->attributes ?? [],
                        'facts' => $row->facts,
                        'extracted_with' => $row->extracted_with,
                    ])->values()->all(),
                ]);

                if ($response->successful()) {
                    ProductFact::whereIn('id', $chunk->pluck('id'))->update(['pushed_at' => now()]);
                    $pushed += $chunk->count();
                } else {
                    $failed += $chunk->count();
                    $this->warn("HTTP {$response->status()}: " . mb_substr($response->body(), 0, 200));
                }
            }
        }

        $this->info("{$pushed} pushed to {$apiUrl}, {$failed} failed.");

        return $failed > 0 && $pushed === 0 ? 1 : 0;
    }
}
