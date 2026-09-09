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
                            {--pending : Process all flyers with a PDF that have not had discounts extracted yet}
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
            return StoreFlyer::query()
                ->whereNotNull('pdf_url')
                ->whereNull('discounts_processed_at')
                ->whereHas('store', fn ($q) => $q->where('extract_discounts_from_flyer', true))
                ->get();
        }

        $this->error('Provide an ID or use --pending.');

        return collect();
    }
}
