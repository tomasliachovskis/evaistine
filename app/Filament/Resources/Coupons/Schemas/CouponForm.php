<?php

namespace App\Filament\Resources\Coupons\Schemas;

use App\Models\Coupon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('website_id')
                    ->label('Svetainė')
                    ->relationship('website', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->label('Tipas')
                    ->options([
                        Coupon::TYPE_CODE => 'Nuolaidos kodas',
                        Coupon::TYPE_DEAL => 'Nuolaida (be kodo)',
                    ])
                    ->required()
                    ->live()
                    ->default(Coupon::TYPE_CODE),
                TextInput::make('code')
                    ->label('Kodas')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => $get('type') === Coupon::TYPE_CODE)
                    ->required(fn (Get $get) => $get('type') === Coupon::TYPE_CODE),
                TextInput::make('title')
                    ->label('Pavadinimas')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Aprašymas')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('terms')
                    ->label('Naudojimo sąlygos')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('editor_tip')
                    ->label('Redaktoriaus patarimas')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('discount_label')
                    ->label('Nuolaidos etiketė')
                    ->placeholder('-30% arba -200€')
                    ->maxLength(255),
                TextInput::make('discount_percent')
                    ->label('Nuolaida (%)')
                    ->numeric()
                    ->step(0.01),
                TextInput::make('target_url')
                    ->label('Nuoroda')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DatePicker::make('valid_from')
                    ->label('Galioja nuo'),
                DatePicker::make('valid_until')
                    ->label('Galioja iki')
                    ->helperText('Palikite tuščią, jei galioja iki pranešimo.'),
                TextInput::make('usage_count')
                    ->label('Panaudojimų skaičius')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Rikiavimas')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_exclusive')
                    ->label('Tik pas mus')
                    ->default(false),
                Toggle::make('is_verified')
                    ->label('Patvirtinta')
                    ->default(true),
                Toggle::make('is_active')
                    ->label('Aktyvus')
                    ->default(true),
                Hidden::make('source')
                    ->default('manual'),
            ]);
    }
}
