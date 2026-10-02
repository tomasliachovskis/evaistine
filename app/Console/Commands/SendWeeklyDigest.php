<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigestMail;
use App\Models\EmailSubscriber;
use App\Services\WeeklyDigestBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// "Savaitės santrauka": every Thursday, the new leaflets and best offers of
// each confirmed subscriber's stores (EmailSubscriber, wants_weekly). Built
// once per distinct store set. Skips a subscriber when there is nothing to
// show, or when one already went out in the last 6 days (a re-run the same
// week sends nothing twice).
class SendWeeklyDigest extends Command
{
    protected $signature = 'weekly-digest:send {--dry-run : List what would be sent} {--email= : Only this subscriber (for a test send)}';

    protected $description = 'Send the Thursday "Šios savaitės akcijos" email to confirmed subscribers';

    public function handle(WeeklyDigestBuilder $builder): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $subscribers = EmailSubscriber::query()
            ->sendable()
            ->where('wants_weekly', true)
            ->when($this->option('email'), fn ($q, $email) => $q->where('email', $email))
            ->when(! $this->option('email'), fn ($q) => $q->where(fn ($q) => $q->whereNull('weekly_sent_at')->orWhere('weekly_sent_at', '<', now()->subDays(6))))
            ->get();

        $builds = [];
        $sent = 0;

        foreach ($subscribers as $subscriber) {
            $slugs = $subscriber->storeSlugs();
            sort($slugs);
            $key = implode(',', $slugs);
            $content = $builds[$key] ??= $builder->build($slugs);

            if ($content['leaflets'] === [] && $content['offers']->isEmpty()) {
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '').$subscriber->email.': '.count($content['leaflets']).' leaflets, '.$content['offers']->count().' offers ('.$key.')');

            if ($dryRun) {
                continue;
            }

            try {
                Mail::to($subscriber->email)->send(new WeeklyDigestMail(
                    $content['stores'],
                    $content['leaflets'],
                    $content['offers'],
                    url('/akcijos?'.http_build_query(['store' => $key])),
                    $subscriber->settingsUrl(),
                ));
                $subscriber->forceFill(['weekly_sent_at' => now()])->save();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Weekly digest send failed', ['subscriber' => $subscriber->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info($dryRun ? 'Dry run, nothing sent.' : "Sent {$sent}.");

        return self::SUCCESS;
    }
}
