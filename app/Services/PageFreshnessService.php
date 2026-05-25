<?php

namespace App\Services;

use Carbon\Carbon;

class PageFreshnessService
{
    private const LT_MONTHS_SHORT = [
        'saus.', 'vas.', 'kov.', 'bal.', 'geg.', 'birž.',
        'liep.', 'rugp.', 'rugs.', 'spal.', 'lapkr.', 'gruod.',
    ];

    public function build(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $range = $this->getCurrentWeekRange($now);

        return [
            'updated_at' => $now->toIso8601String(),
            'valid_to' => $range['valid_to'],
            'updated_label' => $this->formatLtDate($now->format('Y-m-d'), true),
            'valid_until_label' => $this->formatLtDate($range['valid_to'], true),
        ];
    }

    public function getCurrentWeekRange(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $monday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->addDays(6);

        return [
            'valid_from' => $monday->format('Y-m-d'),
            'valid_to' => $sunday->format('Y-m-d'),
        ];
    }

    public function formatLtDate(string $iso, bool $withYear = false): string
    {
        $date = Carbon::parse($iso);
        $day = $date->day;
        $month = self::LT_MONTHS_SHORT[$date->month - 1];

        if ($withYear) {
            return "{$day} {$month} {$date->year}";
        }

        return "{$day} {$month}";
    }

    public function getOfferValidityLabel(string $validTo, ?Carbon $now = null): ?array
    {
        $now = $now ?? Carbon::now();
        $end = Carbon::parse($validTo)->startOfDay();
        $today = $now->copy()->startOfDay();
        $daysLeft = $today->diffInDays($end, false);

        if ($daysLeft < 0) {
            return null;
        }

        if ($daysLeft === 0) {
            return ['kind' => 'today', 'text' => 'tik šiandien'];
        }

        return ['kind' => 'until', 'text' => 'iki ' . $this->formatLtDate($validTo)];
    }
}
