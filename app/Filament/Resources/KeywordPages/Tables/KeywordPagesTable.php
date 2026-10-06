<?php

namespace App\Filament\Resources\KeywordPages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeywordPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->url(fn ($record) => 'https://evaistine.lt/' . $record->slug, shouldOpenInNewTab: true),
                IconColumn::make('is_published')
                    ->label('Publikuota')
                    ->boolean(),
                IconColumn::make('is_chip')
                    ->label('Chip')
                    ->boolean(),
                TextColumn::make('matching_offers_count')
                    ->label('Atitikmenys')
                    ->sortable(),
                TextColumn::make('displayed_offers_count')
                    ->label('Rodoma')
                    ->sortable(),
                TextColumn::make('offers_counted_at')
                    ->label('Skaičiuota')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Atnaujinta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
