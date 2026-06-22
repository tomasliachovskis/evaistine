<?php

namespace App\Filament\Resources\StoreFlyers\Tables;

use App\Models\StoreFlyer;
use App\Services\StoreFlyerTitleBuilder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreFlyersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('store')->withCount('pages'))
            ->defaultSort('valid_from', 'desc')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Viršelis')
                    ->square()
                    ->height(56),
                TextColumn::make('store.name')
                    ->label('Parduotuvė')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('display_title')
                    ->label('Pavadinimas')
                    ->state(function (StoreFlyer $record): string {
                        if (!$record->store) {
                            return $record->title ?? '—';
                        }

                        return app(StoreFlyerTitleBuilder::class)->build($record, $record->store);
                    })
                    ->searchable(['title', 'catalog_name', 'issue_number'])
                    ->wrap(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('valid_from')
                    ->label('Nuo')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Iki')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('pages_count')
                    ->label('Puslapiai')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('processing_status')
                    ->label('Būsena')
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === StoreFlyer::STATUS_READY) {
                            return 'success';
                        }

                        if ($state === StoreFlyer::STATUS_PROCESSING) {
                            return 'warning';
                        }

                        if ($state === StoreFlyer::STATUS_FAILED) {
                            return 'danger';
                        }

                        return 'gray';
                    }),
                TextColumn::make('sort_order')
                    ->label('Rikiavimas')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktyvus'),
                TextColumn::make('updated_at')
                    ->label('Atnaujinta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Parduotuvė')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Aktyvus'),
                SelectFilter::make('processing_status')
                    ->label('Būsena')
                    ->options([
                        StoreFlyer::STATUS_PENDING => 'pending',
                        StoreFlyer::STATUS_PROCESSING => 'processing',
                        StoreFlyer::STATUS_READY => 'ready',
                        StoreFlyer::STATUS_FAILED => 'failed',
                    ]),
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
