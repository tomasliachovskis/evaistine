<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStoreFlyerDiscountsJob;
use App\Models\StoreFlyer;
use App\Services\StoreFlyerDiscountProcessingService;
use Illuminate\Console\Command;

class ProcessStoreFlyerDiscounts extends Command
{
    protected $signature = 'flyers:process-discounts
                            {id? : Store flyer ID}
                            {--pending : Process recent (last 3 days) flyers with a PDF that have not had discounts extracted yet}
                            {--include-backlog : With --pending, also include older flyers — otherwise flipping a store to extract_discounts_from_flyer=true only affects new flyers going forward, not its whole history}
                            {--sync : Process immediately without queueing}';

    protected $description = 'Extract discounts (Gemini) from a store flyer PDF that already exists at pdf_url — no flyers-incoming/ upload needed';

    public function handle(StoreFlyerDiscountProcessingService $service): int
    {
        $flyers = $this->resolveFlyers();

        if ($flyers->isEmpty()) {
            $this->warn('No store flyers matched.');

            return 1;
        }

        foreach ($flyers as $flyer) {
            $this->line("Queueing flyer #{$flyer->id} ({$flyer->slug})...");

            if ($this->option('sync') || config('queue.default') === 'sync') {
                $service->process($flyer->id, function (string $level, string $message): void {
                    match ($level) {
                        'error' => $this->error($message),
                        'warn' => $this->warn($message),
                        default => $this->line($message),
                    };
                });
            } else {
                ProcessStoreFlyerDiscountsJob::dispatch($flyer->id);
            }
        }

        if (!$this->option('sync') && config('queue.default') !== 'sync') {
            $this->info('Jobs queued on the flyers queue.');
        } else {
            $this->info('Done.');
        }

        return 0;
    }

    private function resolveFlyers()
    {
        if ($id = $this->argument('id')) {
            $flyer = StoreFlyer::find($id);

            return $flyer ? collect([$flyer]) : collect();
        }

        if ($this->option('pending')) {
            // A KNOWN valid_to in the past is excluded (we're certain the
            // leaflet is dead); a NULL valid_to is included — themed/
            // campaign catalogs never carry a date on the store's own
            // listing page at all (see scrapers/flyers/rimi.js), so
            // requiring a known valid_to before ever queueing them meant
            // they could never be processed. StoreFlyerDiscountProcessingService
            // ::process() itself now lets Gemini try to find the date on
            // the page when valid_to is null, instead of skipping outright
            // — this query just needs to stop filtering those rows out
            // before they ever reach that service.
            $query = StoreFlyer::query()
                ->whereNotNull('pdf_url')
                ->whereNull('discounts_processed_at')
                ->where(function ($q) {
                    $q->whereNull('valid_to')->orWhere('valid_to', '>', now()->startOfDay());
                })
                ->whereHas('store', fn ($q) => $q->where('extract_discounts_from_flyer', true));

            // Without this, flipping a store's extract_discounts_from_flyer
            // to true would make the very next scheduled --pending run
            // (every 5 min, Kernel.php) queue that store's ENTIRE flyer
            // history at once — every already-downloaded backlog flyer has
            // discounts_processed_at still null, since the column is new.
            // Processing the backlog is a real, deliberate decision (Gemini
            // cost, hours of queue time) that should never happen as a side
            // effect of a flag flip — --include-backlog opts into it
            // explicitly.
            if (!$this->option('include-backlog')) {
                $query->where('created_at', '>=', now()->subDays(3));
            }

            return $query->get();
        }

        $this->error('Provide an ID or use --pending.');

        return collect();
    }
}
