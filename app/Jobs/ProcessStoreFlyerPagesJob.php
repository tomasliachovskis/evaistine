<?php

namespace App\Jobs;

use App\Services\StoreFlyerPageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStoreFlyerPagesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public function __construct(public int $storeFlyerId)
    {
        // 2 parallel workers on this queue (deploy/supervisor-evaistine-
        // flyers.conf, numprocs=2) — confirmed live 2026-09-18 a single
        // huge catalog (Oriflame, 148 pages, ~114s/page) can otherwise tie
        // up the only worker for ~4.7h, blocking every other flyer behind
        // it. ShouldBeUnique below means these 2 workers only ever
        // parallelize across DIFFERENT flyers, never duplicate the same
        // one. Gemini discount extraction lives on its own single-worker
        // 'flyers-gemini' queue (ProcessStoreFlyerDiscountsJob), by
        // deliberate choice kept at exactly 1 concurrent job.
        $this->onQueue('flyers');
    }

    public function uniqueId(): string
    {
        return 'store-flyer-pages-' . $this->storeFlyerId;
    }

    public function handle(StoreFlyerPageProcessingService $service): void
    {
        $service->process($this->storeFlyerId);
    }
}
