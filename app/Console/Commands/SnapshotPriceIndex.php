<?php

namespace App\Console\Commands;

use App\Services\PriceIndexService;
use Illuminate\Console\Command;

class SnapshotPriceIndex extends Command
{
    protected $signature = 'price-index:snapshot {--items=5 : Number of basket items to select}';

    protected $description = 'Pick this week\'s best-covered basket of grocery items and snapshot each store\'s cheapest current price for them';

    public function handle(PriceIndexService $service): int
    {
        $snapshot = $service->snapshotThisWeek((int) $this->option('items'));
        $snapshot->load('entries.store');

        $this->info("Snapshot for week starting {$snapshot->week_start->toDateString()}:");

        foreach ($snapshot->entries->groupBy('item_key') as $key => $entries) {
            $basis = $entries->first()->unit_basis;
            $this->line("  {$entries->first()->item_name} (€/{$basis}):");
            foreach ($entries->sortBy('price') as $entry) {
                $this->line("    {$entry->store->name}: {$entry->price} €");
            }
        }

        if ($snapshot->entries->isEmpty()) {
            $this->warn('No items had any store coverage this week — snapshot is empty.');
        }

        return 0;
    }
}
