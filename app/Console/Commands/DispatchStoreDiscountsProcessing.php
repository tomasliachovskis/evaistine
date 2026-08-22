<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStoreDiscountsJob;
use App\Models\DiscountTemp;
use Illuminate\Console\Command;

class DispatchStoreDiscountsProcessing extends Command
{
    protected $signature = 'discounts:dispatch-store-processing';

    protected $description = 'Queue processing for each store whose unprocessed discount_temp rows have been quiet for 10 minutes';

    public function handle(): int
    {
        $stores = DiscountTemp::query()
            ->where('processed', false)
            ->distinct()
            ->pluck('store')
            ->filter()
            ->values();

        if ($stores->isEmpty()) {
            $this->info('No unprocessed discount_temp rows.');

            return 0;
        }

        foreach ($stores as $store) {
            $latestUnprocessed = DiscountTemp::query()
                ->where('processed', false)
                ->whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
                ->latest('created_at')
                ->first();

            if (!$latestUnprocessed) {
                continue;
            }

            if ($latestUnprocessed->created_at->gt(now()->subMinutes(10))) {
                $this->info("{$store}: still receiving rows, not quiet for 10 minutes yet. Skipping.");

                continue;
            }

            ProcessStoreDiscountsJob::dispatch($store);
            $this->info("Queued processing for {$store}");
        }

        return 0;
    }
}
