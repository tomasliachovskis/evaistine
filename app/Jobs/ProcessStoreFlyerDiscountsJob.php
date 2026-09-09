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
        $this->onQueue('flyers');
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
