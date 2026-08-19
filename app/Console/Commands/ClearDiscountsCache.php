<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\CacheVersion;

class ClearDiscountsCache extends Command
{
    protected $signature = 'cache:clear-discounts';

    protected $description = 'Clear API discounts cache';

    public function handle(): int
    {
        CacheVersion::bump('discounts');

        $this->info('Discounts cache cleared.');

        return 0;
    }
}
