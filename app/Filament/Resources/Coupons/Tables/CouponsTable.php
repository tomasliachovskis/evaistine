<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['website', 'category']))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('website.name')
                    ->label('Svetainė')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tipas')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === Coupon::TYPE_CODE ? 'Kodas' : 'Nuolaida')
                    ->color(fn (string $state) => $state === Coupon::TYPE_CODE ? 'info' : 'success'),
                TextColumn::make('discount_label')
                    ->label('Nuolaida'),
                TextColumn::make('code')
                    ->label('Kodas')
                    ->fontFamily('mono')
                    ->toggleable(),
                TextColumn::make('valid_until')
                    ->label('Galioja iki')
                    ->date('Y-m-d')
                    ->placeholder('iki pranešimo')
                    ->sortable(),
                TextColumn::make('usage_count')
                    ->label('Panaudota')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_exclusive')
                    ->label('Tik pas mus'),
                ToggleColumn::make('is_verified')
                    ->label('Patvirtinta'),
                ToggleColumn::make('is_active')
                    ->label('Aktyvus'),
                TextColumn::make('source')
                    ->label('Šaltinis')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Atnaujinta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('website_id')
                    ->label('Svetainė')
                    ->relationship('website', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Tipas')
                    ->options([
                        Coupon::TYPE_CODE => 'Kodas',
                        Coupon::TYPE_DEAL => 'Nuolaida',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Aktyvus'),
                TernaryFilter::make('is_exclusive')
                    ->label('Tik pas mus'),
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
