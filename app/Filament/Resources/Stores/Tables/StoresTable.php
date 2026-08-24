<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Parduotuvė')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('flyer_source_url')
                    ->label('Leidinio šaltinis')
                    ->url(fn (Store $record) => $record->flyer_source_url, shouldOpenInNewTab: true)
                    ->limit(50)
                    ->placeholder('Nenurodyta')
                    ->color(fn (Store $record) => $record->flyer_source_url ? 'primary' : 'gray'),
                TextColumn::make('current_leaflet_valid_to')
                    ->label('Esamas leidinys galioja iki')
                    ->state(fn (Store $record) => $record->latestFlyerValidity()['valid_to'] ?? '—'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Nurodyti šaltinį'),
            ]);
    }
}
