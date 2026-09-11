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

class FinalizeScrapedStoresJob implements ShouldBeUnique, ShouldQueue
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
        // sharing a single worker with the 'flyers' queue's jobs
        // (ProcessStoreFlyerDiscountsJob/ProcessStoreFlyerPagesJob) meant a
        // slow/retrying leaflet (Gemini vision calls, minutes per page)
        // could delay it indefinitely since Laravel can't preempt an
        // in-flight job on the same worker.
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

        $anyStoreProcessed = false;
        foreach ($this->stores as $store) {
            if ($this->processStore($store)) {
                $anyStoreProcessed = true;
            }
        }

        // products:merge-duplicates/discounts:remove-duplicate-active/
        // discounts:archive-expired/discounts:index-meilisearch are just as
        // global as cache:clear-discounts below (none of them take a store
        // filter — checked: they all scan/operate across every
        // product/discount regardless of which store triggered them) but
        // used to run once PER STORE inside processStore()'s loop — a
        // 5-store batch redundantly re-scanned the whole product table 5
        // times. Moved out here, same "once per batch" reasoning as the
        // cache warms below. Only worth running at all if some store in
        // this batch actually had real work (all-skipped batches are just
        // stores whose pending rows vanished before their turn, see
        // processStore()).
        if ($anyStoreProcessed) {
            Artisan::call('products:merge-duplicates');
            Artisan::call('discounts:remove-duplicate-active');
            Artisan::call('discounts:archive-expired');
            Artisan::call('discounts:index-meilisearch', app()->environment('production') ? [] : ['--with-ssh-tunnel' => true]);
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

    // Returns whether this store actually had pending work processed
    // (false for a skip) — handle() uses this to decide whether the
    // batch-wide cleanup/reindex steps are worth running at all.
    private function processStore(string $store): bool
    {
        $startedAt = now();

        // discounts:dispatch-store-processing snapshots which stores are
        // "ready" (quiet for 10min) and dispatches this job for the whole
        // batch — by the time THIS store's turn comes up in that batch, its
        // own pending rows can already be gone (a concurrent run elsewhere
        // beat it to them, or nothing ever really arrived). Without this
        // check, a 0-item store still pays for the full pipeline below
        // (categories:bulk-map, merge-duplicates, archive-expired,
        // Meilisearch reindex — several of which aren't even scoped to one
        // store) — seen live: 2m11s spent processing Thomas Philipps with
        // discount_temp genuinely empty for it at run time.
        $pendingCount = DiscountTemp::whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
            ->where('processed', false)
            ->count();

        if ($pendingCount === 0) {
            ScraperRun::create([
                'type' => ScraperRun::TYPE_PROCESS,
                'store' => $store,
                'status' => ScraperRun::STATUS_SUCCESS,
                'step' => 'skipped — no pending discount_temp rows at run time',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'items_count' => 0,
            ]);
            Log::info("FinalizeScrapedStoresJob[{$store}]: skipped, no pending rows at run time");

            return false;
        }

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_PROCESS,
            'store' => $store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        $step = function (string $step) use ($run, $store) {
            $run->update(['step' => $step]);
            Log::info("FinalizeScrapedStoresJob[{$store}]: {$step}");
        };

        try {
            // --map-categories stays on: without it, any new/unmapped category
            // string for this store never gets a category_mappers row and its
            // discount_temp rows would never process. The global cleanup/
            // reindex steps (merge-duplicates, remove-duplicate-active,
            // archive-expired, Meilisearch) now run once per BATCH in
            // handle(), not here per store — see its comment.
            $step('discounts:process --map-categories');
            $exitCode = Artisan::call('discounts:process', [
                '--only-store' => $store,
                '--map-categories' => 1,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('discounts:process exited with code '.$exitCode);
            }

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

            return true;
        } catch (\Throwable $e) {
            $trace = $e->getMessage()."\n".$e->getTraceAsString();
            Log::error("FinalizeScrapedStoresJob[{$store}] failed: {$trace}");

            $run->update([
                'status' => ScraperRun::STATUS_FAILED,
                'finished_at' => now(),
                'error' => substr($trace, 0, 4000),
            ]);

            // A failure still means the store had real, attempted work —
            // the batch-wide cleanup below is still worth running.
            return true;
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
            Log::info('FinalizeScrapedStoresJob: REVALIDATE_SECRET not configured, skipping frontend revalidation.');

            return;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($secret)
                ->post(config('services.frontend.revalidate_url'));

            if (! $response->successful()) {
                Log::warning("FinalizeScrapedStoresJob: frontend revalidation returned {$response->status()}.");
            }
        } catch (\Throwable $e) {
            Log::warning("FinalizeScrapedStoresJob: frontend revalidation request failed: {$e->getMessage()}");
        }
    }
}
