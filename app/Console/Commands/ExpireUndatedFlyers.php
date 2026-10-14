<?php

namespace App\Console\Commands;

use App\Models\StoreFlyer;
use App\Support\CacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// A flyer without valid_to counts as valid forever (StoreFlyer::
// scopeCurrentlyValid), so an old undated leaflet stays on the site
// indefinitely (on superakcijos, Lidl #21-26 from 2026-08-24 were still
// shown in October; ported from there 2026-10-13).
// Once it is older than --days, close it as of yesterday.
class ExpireUndatedFlyers extends Command
{
    protected $signature = 'flyers:expire-undated
                            {--days=21 : Close undated flyers created more than this many days ago}
                            {--dry-run : Only list the flyers, change nothing}';

    protected $description = 'Set valid_to = yesterday on flyers without valid_to that were created more than N days ago';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $query = StoreFlyer::query()
            ->whereNull('valid_to')
            ->where('created_at', '<', now()->subDays($days));

        $flyers = (clone $query)->with('store:id,name')->orderBy('id')->get(['id', 'store_id', 'created_at']);

        if ($flyers->isEmpty()) {
            $this->info("No undated flyers older than {$days} days.");

            return 0;
        }

        foreach ($flyers as $flyer) {
            $this->line("#{$flyer->id} {$flyer->store?->name} gautas {$flyer->created_at->format('m-d')}");
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$flyers->count()} flyers would get valid_to " . Carbon::yesterday()->toDateString() . '.');

            return 0;
        }

        $updated = $query->update(['valid_to' => Carbon::yesterday()->toDateString()]);

        // /leidiniai and /leidinys/{store} payloads are cached under the
        // 'flyers' group (same as StoreFlyerPageProcessingService).
        if ($updated > 0) {
            CacheVersion::bump('flyers');
        }

        $this->info("Closed {$updated} undated flyers (valid_to " . Carbon::yesterday()->toDateString() . ').');

        return 0;
    }
}
