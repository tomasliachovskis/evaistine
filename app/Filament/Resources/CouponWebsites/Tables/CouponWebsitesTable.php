<?php

namespace App\Filament\Resources\CouponWebsites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponWebsitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('coupons'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo_url')
                    ->label('Logotipas')
                    ->square()
                    ->height(40),
                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('url')
                    ->label('Svetainė')
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true)
                    ->limit(40)
                    ->placeholder('Nenurodyta'),
                TextColumn::make('coupons_count')
                    ->label('Kuponai')
                    ->numeric()
                    ->sortable(),
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
