<?php

namespace App\Filament\Widgets;

use App\Models\StoreFlyer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ExpiringLeafletsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => StoreFlyer::query()->whereIn('id', $this->latestFlyerIdPerStore())->with('store'))
            ->heading('Leidiniai, kuriuos reikia atnaujinti')
            ->defaultSort('valid_to')
            ->paginated(false)
            ->columns([
                TextColumn::make('store.name')
                    ->label('Parduotuvė')
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Galioja iki')
                    ->date('Y-m-d')
                    ->badge()
                    ->color(fn (StoreFlyer $record): string => self::colorForValidTo($record->valid_to))
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label('Likusios dienos')
                    ->state(fn (StoreFlyer $record): ?int => self::daysRemaining($record->valid_to)),
                TextColumn::make('store.flyer_source_url')
                    ->label('Šaltinis parsisiuntimui')
                    ->url(fn (StoreFlyer $record): ?string => $record->store?->flyer_source_url, shouldOpenInNewTab: true)
                    ->placeholder('Nenurodyta')
                    ->color(fn (StoreFlyer $record): string => $record->store?->flyer_source_url ? 'primary' : 'gray'),
            ]);
    }

    private function latestFlyerIdPerStore(): array
    {
        return StoreFlyer::query()
            ->active()
            ->ready()
            ->orderBy('store_id')
            ->orderByDesc('valid_from')
            ->get(['id', 'store_id'])
            ->unique('store_id')
            ->pluck('id')
            ->all();
    }

    private static function daysRemaining(mixed $validTo): ?int
    {
        if (!$validTo) {
            return null;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($validTo), false);
    }

    private static function colorForValidTo(mixed $validTo): string
    {
        $days = self::daysRemaining($validTo);

        if ($days === null) {
            return 'gray';
        }

        if ($days < 0) {
            return 'danger';
        }

        if ($days <= 3) {
            return 'warning';
        }

        return 'success';
    }
}
