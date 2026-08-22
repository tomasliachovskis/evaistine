<?php

namespace App\Filament\Resources\ScraperRuns\Pages;

use App\Filament\Resources\ScraperRuns\ScraperRunResource;
use App\Filament\Widgets\LatestScraperRunsWidget;
use Filament\Resources\Pages\ListRecords;

class ListScraperRuns extends ListRecords
{
    protected static string $resource = ScraperRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LatestScraperRunsWidget::class,
        ];
    }
}
