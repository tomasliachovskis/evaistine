<?php

namespace App\Jobs;

use App\Models\DiscountTemp;
use App\Models\Product;
use App\Models\ScraperRun;
use App\Services\CacheWarmingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessStoreDiscountsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $tries = 1;

    public $uniqueFor = 3600;

    // Takes every store that's ready to process in one dispatch instead of
    // one job per store — cache:clear-discounts/cache:warm are global (not
    // scoped to a single store anyway, see CacheVersion), so running them
    // once after the whole batch instead of once per store cuts a 40-store
    // day from 40 full cache warms down to however many batches actually
    // ran. Also incidentally fixes a real bug: uniqueId() below is a
    // constant, so under the old one-job-per-store dispatch, ShouldBeUnique
    // would silently drop every store past the first if more than one
    // became ready in the same dispatch-store-processing tick.
    public function __construct(public array $stores)
    {
        // Its own queue (a dedicated worker, see
        // deploy/supervisor-nuolaidos-discounts.conf), not 'flyers' — this
        // job finalizes scraped data into live discounts and needs to run on
        // schedule (every 5 min via discounts:dispatch-store-processing);
        // sharing a single worker with ProcessPdfFlyerJob/
        // ProcessStoreFlyerPagesJob meant a slow/retrying leaflet (Gemini
        // vision calls, minutes per page) could delay it indefinitely since
        // Laravel can't preempt an in-flight job on the same worker.
        $this->onQueue('discounts');
    }

    public function uniqueId(): string
    {
        return 'store-discount-processing';
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('store-discount-processing'))->releaseAfter(60)->expireAfter(3600),
        ];
    }

    public function handle(): void
    {
        $batchStartedAt = now();

        foreach ($this->stores as $store) {
            $this->processStore($store);
        }

        // Once for the whole batch, not once per store — see class docblock.
        Artisan::call('cache:clear-discounts');

        // product_with_similar isn't wired into CacheVersion (flat 7-day TTL
        // key, see CacheWarmingService::warmPopularProductsCache()), so it's
        // the one warm scoped down to just what this batch actually touched
        // — everything else (stores/categories/store+category pairs/best-by-
        // category/favorites) is cheap enough (bounded by store/category
        // count, not product count) to just fully rewarm every batch.
        $touchedProductSlugs = Product::whereHas('discounts', function ($query) use ($batchStartedAt) {
            $query->where('updated_at', '>=', $batchStartedAt);
        })->pluck('slug')->all();

        // "Best deals" (store carousels, global categories, home pools) are
        // no longer warmed here — discounts:process (called per store in
        // processStore() above) already refreshes App\Services\DealPoolRefresher's
        // curated_deals rows for whichever store(s) it actually touched.
        $cacheWarmingService = app(CacheWarmingService::class);
        $cacheWarmingService->warmStoreCaches();
        $cacheWarmingService->warmCategoryCaches();
        $cacheWarmingService->warmStoreCategoryCaches();
        $cacheWarmingService->warmAllDiscountsCache();
        $cacheWarmingService->warmFavoritesCache();
        $cacheWarmingService->warmPopularProductsCache($touchedProductSlugs);
        $cacheWarmingService->warmGuestHtmlCaches();

        $this->revalidateFrontend();
    }

    private function processStore(string $store): void
    {
        $startedAt = now();

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_PROCESS,
            'store' => $store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        $step = function (string $step) use ($run, $store) {
            $run->update(['step' => $step]);
            Log::info("ProcessStoreDiscountsJob[{$store}]: {$step}");
        };

        try {
            // --map-categories stays on: without it, any new/unmapped category
            // string for this store never gets a category_mappers row and its
            // discount_temp rows would never process. Meilisearch reindexing
            // below stays commented out — it needs an SSH tunnel unreachable
            // from local dev (see CLAUDE.md) and isn't needed to verify the
            // rest of the pipeline.
            $step('discounts:process --map-categories');
            $exitCode = Artisan::call('discounts:process', [
                '--only-store' => $store,
                '--map-categories' => 1,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('discounts:process exited with code '.$exitCode);
            }

            $step('products:merge-duplicates');
            Artisan::call('products:merge-duplicates');

            $step('discounts:remove-duplicate-active');
            Artisan::call('discounts:remove-duplicate-active');

            $step('discounts:archive-expired');
            Artisan::call('discounts:archive-expired');

            $step('discounts:index-meilisearch');
            Artisan::call('discounts:index-meilisearch', app()->environment('production') ? [] : ['--with-ssh-tunnel' => true]);

            $step('done');

            $itemsCount = DiscountTemp::whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
                ->where('processed', true)
                ->where('updated_at', '>=', $startedAt)
                ->count();

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
            ]);
        } catch (\Throwable $e) {
            $trace = $e->getMessage()."\n".$e->getTraceAsString();
            Log::error("ProcessStoreDiscountsJob[{$store}] failed: {$trace}");

            $run->update([
                'status' => ScraperRun::STATUS_FAILED,
                'finished_at' => now(),
                'error' => substr($trace, 0, 4000),
            ]);
        }
    }

    // The Next.js frontend (superakcijos.lt) shares this backend's database
    // but has its own ISR/data-provider cache — bumping cache:clear-discounts
    // here only affects this Laravel app, not the frontend's cache. This is
    // the same lightweight webhook deploy.sh calls after a deploy
    // (POST /api/revalidate), just triggered here too so the live site picks
    // up changes from this per-store pipeline without waiting for a deploy.
    private function revalidateFrontend(): void
    {
        $secret = config('services.frontend.revalidate_secret');

        if (! $secret) {
            Log::info('ProcessStoreDiscountsJob: REVALIDATE_SECRET not configured, skipping frontend revalidation.');

            return;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($secret)
                ->post(config('services.frontend.revalidate_url'));

            if (! $response->successful()) {
                Log::warning("ProcessStoreDiscountsJob: frontend revalidation returned {$response->status()}.");
            }
        } catch (\Throwable $e) {
            Log::warning("ProcessStoreDiscountsJob: frontend revalidation request failed: {$e->getMessage()}");
        }
    }
}
