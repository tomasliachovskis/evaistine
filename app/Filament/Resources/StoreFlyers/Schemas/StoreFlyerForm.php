<?php

namespace App\Filament\Resources\StoreFlyers\Schemas;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Services\StoreFlyerTitleBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StoreFlyerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('store_id')
                    ->label('Vaistinė')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('Pavadinimas')
                    ->required()
                    ->maxLength(255)
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('catalog_name')
                    ->label('Katalogo pavadinimas')
                    ->maxLength(255)
                    ->live(),
                TextInput::make('issue_number')
                    ->label('Leidinio numeris')
                    ->maxLength(255)
                    ->live(),
                TextEntry::make('title_preview')
                    ->label('Pavadinimo peržiūra')
                    ->state(function (Get $get): string {
                        $store = Store::find($get('store_id'));

                        if (!$store) {
                            return '—';
                        }

                        $flyer = new StoreFlyer([
                            'title' => $get('title'),
                            'catalog_name' => $get('catalog_name'),
                            'issue_number' => $get('issue_number'),
                            'valid_from' => $get('valid_from'),
                            'valid_to' => $get('valid_to'),
                        ]);

                        return app(StoreFlyerTitleBuilder::class)->build($flyer, $store);
                    })
                    ->columnSpanFull(),
                DatePicker::make('valid_from')
                    ->label('Galioja nuo')
                    ->required()
                    ->live(),
                DatePicker::make('valid_to')
                    ->label('Galioja iki')
                    ->required()
                    ->live(),
                FileUpload::make('pdf_upload')
                    ->label('PDF failas')
                    ->disk('public')
                    ->directory('flyers/pdfs')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(51200)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpanFull(),
                TextEntry::make('processing_status')
                    ->label('Apdorojimo būsena')
                    ->state(fn (?StoreFlyer $record) => $record?->processing_status ?? StoreFlyer::STATUS_PENDING)
                    ->visible(fn (string $operation) => $operation === 'edit'),
                TextInput::make('sort_order')
                    ->label('Rikiavimas')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktyvus')
                    ->default(true)
                    ->required(),
                Hidden::make('source')
                    ->default('manual'),
            ]);
    }
}
