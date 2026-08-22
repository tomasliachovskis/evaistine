<?php

namespace App\Filament\Resources\ScraperRuns;

use App\Filament\Resources\ScraperRuns\Pages\ListScraperRuns;
use App\Filament\Resources\ScraperRuns\Tables\ScraperRunsTable;
use App\Models\ScraperRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScraperRunResource extends Resource
{
    protected static ?string $model = ScraperRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = "Scraper'ių eiga";

    protected static ?string $modelLabel = 'paleidimas';

    protected static ?string $pluralModelLabel = 'paleidimai';

    public static function table(Table $table): Table
    {
        return ScraperRunsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScraperRuns::route('/'),
        ];
    }
}
