<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Cover')
                    ->height(70)
                    ->width(120)
                    ->extraImgAttributes(['class' => 'object-cover rounded'])
                    ->placeholder('—'),
                TextColumn::make('title')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('source_name')
                    ->label('Šaltinis')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('published_at')
                    ->label('Publikuota')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Sukurta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options([
                        'draft' => 'draft',
                        'published' => 'published',
                    ]),
            ])
            ->recordActions([
                Action::make('viewSource')
                    ->label('Šaltinis')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (BlogPost $record) => $record->source_url)
                    ->openUrlInNewTab()
                    ->visible(fn (BlogPost $record) => !empty($record->source_url)),
                Action::make('publish')
                    ->label('Publikuoti')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (BlogPost $record) => $record->status !== 'published')
                    ->action(function (BlogPost $record) {
                        $record->update([
                            'status' => 'published',
                            'published_at' => $record->published_at ?? now(),
                        ]);

                        Notification::make()
                            ->title('Publikuota')
                            ->success()
                            ->send();
                    }),
                Action::make('unpublish')
                    ->label('Grąžinti į draft')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (BlogPost $record) => $record->status === 'published')
                    ->action(function (BlogPost $record) {
                        $record->update(['status' => 'draft']);

                        Notification::make()
                            ->title('Grąžinta į draft')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
