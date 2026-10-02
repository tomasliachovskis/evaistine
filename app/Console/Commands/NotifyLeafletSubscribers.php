<?php

namespace App\Console\Commands;

use App\Mail\NewLeafletMail;
use App\Models\EmailSubscriber;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Services\WeeklyDigestBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// "Naujas leidinys": emails confirmed subscribers (wants_new_leaflets) the
// leaflets of their stores that became browsable since they confirmed and
// that they weren't told about yet (email_subscriber_flyers). One email per
// run with every new leaflet, so a store publishing three at once is one
// email, not three.
class NotifyLeafletSubscribers extends Command
{
    protected $signature = 'leaflets:notify-subscribers {--dry-run : List what would be sent}';

    protected $description = 'Email subscribers about new leaflets of their stores';

    private const MAX_PER_EMAIL = 6;

    public function handle(WeeklyDigestBuilder $builder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $storeIds = Store::pluck('id', 'slug');
        $sent = 0;

        $subscribers = EmailSubscriber::query()->sendable()->where('wants_new_leaflets', true)->get();

        foreach ($subscribers as $subscriber) {
            $ids = collect($subscriber->storeSlugs())->map(fn ($slug) => $storeIds[$slug] ?? null)->filter()->values();

            $flyers = StoreFlyer::query()
                ->with('store')
                ->active()
                ->ready()
                ->currentlyValid()
                ->whereIn('store_id', $ids)
                // Only leaflets added after they signed up: no flood of every
                // current leaflet in the first email.
                ->where('created_at', '>=', $subscriber->confirmed_at)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('email_subscriber_flyers')
                    ->whereColumn('email_subscriber_flyers.store_flyer_id', 'store_flyers.id')
                    ->where('email_subscriber_flyers.email_subscriber_id', $subscriber->id))
                ->orderByDesc('valid_from')
                ->get();

            if ($flyers->isEmpty()) {
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '').$subscriber->email.': '.$flyers->map(fn ($f) => $f->store->name)->implode(', '));

            if ($dryRun) {
                continue;
            }

            try {
                Mail::to($subscriber->email)->send(new NewLeafletMail(
                    $flyers->take(self::MAX_PER_EMAIL)->map(fn ($flyer) => $builder->leafletRow($flyer))->values()->all(),
                    $subscriber->settingsUrl(),
                ));
                DB::table('email_subscriber_flyers')->insertOrIgnore($flyers->map(fn ($flyer) => [
                    'email_subscriber_id' => $subscriber->id,
                    'store_flyer_id' => $flyer->id,
                    'sent_at' => now(),
                ])->all());
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('New leaflet email failed', ['subscriber' => $subscriber->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info($dryRun ? 'Dry run, nothing sent.' : "Sent {$sent}.");

        return self::SUCCESS;
    }
}
