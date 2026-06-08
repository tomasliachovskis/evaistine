<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearDiscountsCache extends Command
{
    protected $signature = 'cache:clear-discounts';

    protected $description = 'Clear API discounts cache (tagged cache)';

    public function handle(): int
    {
        try {
            Cache::tags(['discounts'])->flush();
        } catch (\BadMethodCallException $e) {
            Cache::flush();
            $this->warn('Tagged cache not supported, full application cache cleared.');
        }

        $this->info('Discounts cache cleared.');

        return 0;
    }
}
