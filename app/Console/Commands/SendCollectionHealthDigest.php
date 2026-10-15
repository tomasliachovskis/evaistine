<?php

namespace App\Console\Commands;

use App\Mail\CollectionHealthDigestMail;
use App\Models\User;
use App\Services\CollectionHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// Morning email to the admins with what the scrapers sent in the last 24 h,
// how much of it became discounts, and the dashboard's critical/warning
// issues. Sent every day, also when all is OK, so a silent gap (rows arrive
// but nothing is added) is seen without opening /admin/collection-health.
// docs/collection-health.md.
class SendCollectionHealthDigest extends Command
{
    protected $signature = 'collection-health:digest {--dry-run : Print instead of sending}';

    protected $description = 'Email the admins the last 24 h of collected rows vs added discounts, plus collection health issues';

    public function handle(CollectionHealthService $health): int
    {
        $report = $health->report();
        $issues = collect($report['issues'])->where('severity', '!=', CollectionHealthService::INFO)->values()->all();
        $eshop = collect($report['eshop'])->sortBy('store', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        foreach ($issues as $issue) {
            $this->line(($issue['severity'] === CollectionHealthService::CRITICAL ? 'KRIT' : 'ĮSP') . " {$issue['store']}: {$issue['message']}");
        }
        foreach ($eshop as $row) {
            $last = $row['last_24h'];
            $this->line(sprintf(
                '%-18s atėjo %5d (be datų %d, pasibaigę %d, be kainos %d), naujų nuolaidų %s, aktyvių %s',
                $row['store'],
                $last['products'],
                $last['no_dates'],
                $last['expired'],
                $last['no_price'],
                $last['created'] ?? '—',
                $row['active'] ?? '—',
            ));
        }

        if ($this->option('dry-run')) {
            return 0;
        }

        $admins = User::where('is_admin', true)->whereNotNull('email')->pluck('email');
        if ($admins->isEmpty()) {
            $this->warn('No admin users to send to.');

            return 1;
        }

        Mail::to($admins->all())->send(new CollectionHealthDigestMail($issues, $eshop, $report['counts']));
        $this->info('Sent to ' . $admins->implode(', '));

        return 0;
    }
}
