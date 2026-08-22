<?php

namespace App\Filament\Resources\ScraperRuns\Tables;

use App\Models\ScraperRun;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScraperRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->poll('5s')
            ->columns([
                TextColumn::make('store')
                    ->label('Parduotuvė')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ScraperRun::TYPE_SCRAPE ? 'scraping' : 'processing')
                    ->color(fn (string $state): string => $state === ScraperRun::TYPE_SCRAPE ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === ScraperRun::STATUS_SUCCESS) {
                            return 'success';
                        }

                        if ($state === ScraperRun::STATUS_RUNNING) {
                            return 'warning';
                        }

                        if ($state === ScraperRun::STATUS_FAILED) {
                            return 'danger';
                        }

                        return 'gray';
                    }),
                TextColumn::make('step')
                    ->label('Žingsnis')
                    ->limit(60)
                    ->placeholder('—')
                    ->color('gray'),
                TextColumn::make('started_at')
                    ->label('Pradėta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Baigta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('duration')
                    ->label('Trukmė')
                    ->state(function (ScraperRun $record): string {
                        if (!$record->finished_at) {
                            return '—';
                        }

                        return $record->started_at->diffForHumans($record->finished_at, ['parts' => 2, 'short' => true]);
                    }),
                TextColumn::make('items_count')
                    ->label('Įrašų')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('error')
                    ->label('Klaida')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—')
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('store')
                    ->label('Parduotuvė')
                    ->options(fn () => ScraperRun::query()->distinct()->pluck('store', 'store')->all()),
                SelectFilter::make('type')
                    ->label('Tipas')
                    ->options([
                        ScraperRun::TYPE_SCRAPE => 'scraping',
                        ScraperRun::TYPE_PROCESS => 'processing',
                    ]),
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options([
                        ScraperRun::STATUS_RUNNING => 'running',
                        ScraperRun::STATUS_SUCCESS => 'success',
                        ScraperRun::STATUS_FAILED => 'failed',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
