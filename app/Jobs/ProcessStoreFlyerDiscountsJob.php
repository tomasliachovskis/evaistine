<?php

namespace App\Jobs;

use App\Services\StoreFlyerDiscountProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStoreFlyerDiscountsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public function __construct(public int $storeFlyerId)
    {
        // Own queue, not 'flyers' — that one now runs 2 parallel workers
        // (deploy/supervisor-nuolaidos-flyers.conf) for page-splitting,
        // since two of those can safely overlap. Gemini extraction stays
        // capped at exactly 1 concurrent job (deploy/supervisor-
        // nuolaidos-flyers-gemini.conf) — confirmed live 2026-09-18 that
        // mixing page-splitting and Gemini calls on the same 3-core box is
        // safe (disjoint temp files/DB columns), but by explicit choice
        // this queue is kept single-worker regardless.
        $this->onQueue('flyers-gemini');
    }

    public function uniqueId(): string
    {
        return 'store-flyer-discounts-' . $this->storeFlyerId;
    }

    public function handle(StoreFlyerDiscountProcessingService $service): void
    {
        $service->process($this->storeFlyerId);
    }
}
