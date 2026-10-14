<?php

namespace App\Support;

use Illuminate\Support\Carbon;

// The hours the owner works on the Mac that scrapes for production: no heavy
// local job may start then (owner, 2026-10-14: 08:00-18:00). Long runs check
// it before each next step (scrapers:run --all, flyers:scrape --all,
// scrapers:retry-failed, product-facts:extract) and stop, so a night run that
// overruns ends at 08:00 instead of carrying on all morning. Only on the
// local machine (APP_ENV=dev); production has no such limit.
class QuietHours
{
    public static function active(?Carbon $now = null): bool
    {
        if (!app()->environment('dev')) {
            return false;
        }

        $range = (string) config('services.scrapers.quiet_hours', '');
        if (!preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', $range, $m)) {
            return false;
        }

        $now ??= now();
        $minutes = $now->hour * 60 + $now->minute;
        $from = (int) $m[1] * 60 + (int) $m[2];
        $to = (int) $m[3] * 60 + (int) $m[4];

        return $from <= $to
            ? $minutes >= $from && $minutes < $to
            : $minutes >= $from || $minutes < $to;
    }
}
