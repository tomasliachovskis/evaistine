<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStoreFlyerPagesJob;
use App\Models\StoreFlyer;
use App\Services\StoreFlyerPageProcessingService;
use App\Support\FlyerStorage;
use Illuminate\Console\Command;

class ProcessStoreFlyerPages extends Command
{
    protected $signature = 'flyers:process-pages
                            {id? : Store flyer ID}
                            {--pending : Process all pending flyers with a PDF}
                            {--sync : Process immediately without queueing}';

    protected $description = 'Split store flyer PDFs into page images';

    public function handle(StoreFlyerPageProcessingService $service): int
    {
        $flyers = $this->resolveFlyers();

        if ($flyers->isEmpty()) {
            $this->warn('No store flyers matched.');

            return 1;
        }

        foreach ($flyers as $flyer) {
            $flyer->load('store');

            if (!$flyer->pdf_url) {
                FlyerStorage::finalizeFlyerPdf($flyer);
                $flyer->refresh();
            }

            if (!$flyer->pdf_url) {
                $this->warn("Flyer #{$flyer->id} has no PDF, skipping.");

                continue;
            }

            $this->line("Processing flyer #{$flyer->id} ({$flyer->slug})...");

            if ($this->option('sync') || config('queue.default') === 'sync') {
                $service->process($flyer->id, function (string $message, array $context = [], string $level = 'info'): void {
                    if ($level === 'error') {
                        $this->error($message);

                        return;
                    }

                    if ($level === 'warning') {
                        $this->warn($message);

                        return;
                    }

                    $this->line($message);
                });
            } else {
                ProcessStoreFlyerPagesJob::dispatch($flyer->id);
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
            // Includes rows with pdf_url still null (e.g. scraped locally,
            // not yet deployed to this machine) — the loop below attempts
            // FlyerStorage::finalizeFlyerPdf() for those, and just skips
            // (without marking failed) whichever ones still have no file on
            // disk here, so the next scheduled run retries them once the
            // file actually arrives.
            return StoreFlyer::query()
                ->where('processing_status', StoreFlyer::STATUS_PENDING)
                ->get();
        }

        $this->error('Provide an ID or use --pending.');

        return collect();
    }
}
