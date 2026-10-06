<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StoreFlyer;
use App\Support\CanonicalUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Tells IndexNow (Bing, Yandex, Seznam, Naver — and ChatGPT search, which
// reads Bing's index) which product pages just got a new or changed offer,
// so they get recrawled within hours instead of whenever the sitemap is
// next read. Google doesn't use IndexNow; it still relies on the sitemap.
//
// Run from FinalizeScrapedStoresJob after every scrape batch with --since set
// to the batch start. Only pings from production (IndexNow verifies the key
// file on the real public host), and never throws — a failed ping must not
// break the batch that called it.
class IndexNowPing extends Command
{
    protected $signature = 'seo:indexnow
        {--since= : Ping product and leaflet pages whose discounts changed since this datetime (default: 1 day ago)}
        {--dry-run : List the URLs without sending them}';

    protected $description = 'Submit recently changed product and leaflet page URLs to IndexNow';

    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    // IndexNow's per-request limit.
    private const MAX_URLS_PER_REQUEST = 10000;

    public function handle(): int
    {
        $key = (string) config('services.indexnow.key');
        $dryRun = (bool) $this->option('dry-run');

        if ($key === '' && ! $dryRun) {
            $this->info('INDEXNOW_KEY not set, skipping.');

            return self::SUCCESS;
        }

        if (! app()->environment('production') && ! $dryRun) {
            $this->info('Not production, skipping (use --dry-run to list URLs).');

            return self::SUCCESS;
        }

        $since = $this->option('since') ? Carbon::parse($this->option('since')) : now()->subDay();

        $urls = Product::whereHas('discounts', fn ($q) => $q->where('updated_at', '>=', $since))
            ->get(['id', 'slug'])
            ->map(fn (Product $p) => CanonicalUrl::build(\App\Support\PageUrl::product($p->slug)))
            ->unique()
            ->values();

        // Leaflet pages whose offers changed: the flyer page itself and the
        // store's evergreen /leidinys/{store} hub, which lists them too.
        $flyerUrls = StoreFlyer::active()->ready()->currentlyValid()
            ->whereHas('discounts', fn ($q) => $q->where('updated_at', '>=', $since))
            ->with('store:id,slug')
            ->get(['id', 'slug', 'store_id'])
            ->filter(fn (StoreFlyer $f) => $f->store)
            ->flatMap(fn (StoreFlyer $f) => [
                CanonicalUrl::build("/leidinys/{$f->store->slug}/{$f->slug}"),
                CanonicalUrl::build("/leidinys/{$f->store->slug}"),
            ]);
        $urls = $urls->merge($flyerUrls)->unique()->values();

        if ($dryRun) {
            $urls->each(fn ($url) => $this->line($url));
            $this->info("{$urls->count()} URLs (dry run, nothing sent).");

            return self::SUCCESS;
        }

        if ($urls->isEmpty()) {
            $this->info('No changed product or leaflet pages.');

            return self::SUCCESS;
        }

        $host = parse_url(CanonicalUrl::build('/'), PHP_URL_HOST);
        $failed = false;

        foreach ($urls->chunk(self::MAX_URLS_PER_REQUEST) as $chunk) {
            try {
                $response = Http::timeout(30)->post(self::ENDPOINT, [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => CanonicalUrl::build("/{$key}.txt"),
                    'urlList' => $chunk->values()->all(),
                ]);
                $status = $response->status();
            } catch (\Throwable $e) {
                $status = null;
                Log::warning('IndexNow ping failed', ['error' => $e->getMessage()]);
            }

            // 200 = accepted, 202 = accepted, key validation pending.
            if (in_array($status, [200, 202], true)) {
                $this->info("Submitted {$chunk->count()} URLs (HTTP {$status}).");
            } else {
                $failed = true;
                $this->warn("IndexNow rejected {$chunk->count()} URLs (HTTP ".($status ?? 'error').').');
                Log::warning('IndexNow ping rejected', ['status' => $status, 'count' => $chunk->count()]);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
