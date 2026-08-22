<?php

namespace App\Jobs;

use App\Models\DiscountTemp;
use App\Models\ScraperRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessStoreDiscountsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $tries = 1;

    public $uniqueFor = 3600;

    public function __construct(public string $store)
    {
        // Reuses the existing 'flyers' queue worker (already running via
        // supervisor) instead of standing up a new queue/worker for this.
        $this->onQueue('flyers');
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
        $startedAt = now();

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_PROCESS,
            'store' => $this->store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        $step = function (string $step) use ($run) {
            $run->update(['step' => $step]);
            Log::info("ProcessStoreDiscountsJob[{$this->store}]: {$step}");
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
                '--only-store' => $this->store,
                '--map-categories' => 1,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('discounts:process exited with code ' . $exitCode);
            }

            $step('products:merge-duplicates');
            Artisan::call('products:merge-duplicates');

            $step('discounts:remove-duplicate-active');
            Artisan::call('discounts:remove-duplicate-active');

            $step('discounts:archive-expired');
            Artisan::call('discounts:archive-expired');

             $step('discounts:index-meilisearch');
             Artisan::call('discounts:index-meilisearch', app()->environment('production') ? [] : ['--with-ssh-tunnel' => true]);

             $step('cache:clear-discounts');
             Artisan::call('cache:clear-discounts');

             $step('cache:warm');
             Artisan::call('cache:warm', ['--type' => 'all']);

            $step('done');

            $itemsCount = DiscountTemp::whereRaw('LOWER(store) = ?', [mb_strtolower($this->store)])
                ->where('processed', true)
                ->where('updated_at', '>=', $startedAt)
                ->count();

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
            ]);
        } catch (\Throwable $e) {
            $trace = $e->getMessage() . "\n" . $e->getTraceAsString();
            Log::error("ProcessStoreDiscountsJob[{$this->store}] failed: {$trace}");

            $run->update([
                'status' => ScraperRun::STATUS_FAILED,
                'finished_at' => now(),
                'error' => substr($trace, 0, 4000),
            ]);
        }
    }
}
