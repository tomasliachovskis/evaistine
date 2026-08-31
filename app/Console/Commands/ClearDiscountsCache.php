<?php

namespace App\Console\Commands;

use App\Support\CacheVersion;
use Illuminate\Console\Command;

class ClearDiscountsCache extends Command
{
    protected $signature = 'cache:clear-discounts {--warm-html : Pre-render guest listing pages into Redis after bumping}';

    protected $description = 'Clear API discounts cache';

    public function handle(): int
    {
        CacheVersion::bump('discounts');

        $this->info('Discounts cache cleared.');

        if ($this->option('warm-html')) {
            $this->call('cache:warm', ['--type' => 'page-html']);
        }

        return 0;
    }
}
