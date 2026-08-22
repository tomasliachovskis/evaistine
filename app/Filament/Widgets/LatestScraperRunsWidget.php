<?php

namespace App\Filament\Widgets;

use App\Models\ScraperRun;
use Filament\Widgets\Widget;

class LatestScraperRunsWidget extends Widget
{
    protected string $view = 'filament.widgets.latest-scraper-runs';

    protected int | string | array $columnSpan = 'full';

    public function getRuns(): array
    {
        return collect(array_keys(config('scrapers')))
            ->map(function (string $store) {
                $run = ScraperRun::where('type', ScraperRun::TYPE_SCRAPE)
                    ->whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
                    ->latest('started_at')
                    ->first();

                if (!$run) {
                    return [
                        'store' => $store,
                        'value' => '—',
                        'color' => 'gray',
                        'title' => 'niekada nepaleista',
                        'startedAgo' => null,
                    ];
                }

                $value = $run->status === ScraperRun::STATUS_RUNNING
                    ? '…'
                    : (string) ($run->items_count ?? '—');

                $color = match ($run->status) {
                    ScraperRun::STATUS_SUCCESS => 'success',
                    ScraperRun::STATUS_FAILED => 'danger',
                    ScraperRun::STATUS_RUNNING => 'warning',
                    default => 'gray',
                };

                return [
                    'store' => $store,
                    'value' => $value,
                    'color' => $color,
                    'title' => $run->status . ' · ' . $run->started_at->diffForHumans(),
                    'startedAgo' => $run->started_at->diffForHumans(),
                ];
            })
            ->all();
    }
}
