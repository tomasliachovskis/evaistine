<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Pavadinimas')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('flyer_source_url')
                    ->label('Leidinio parsisiuntimo šaltinis (URL)')
                    ->helperText('Vaistinės puslapis, kuriame skelbiamas naujausias leidinys/PDF – iš čia jį parsisiųsti ir įkelti per "Leidiniai".')
                    ->url()
                    ->maxLength(500),
                Toggle::make('show_discounts_page')
                    ->label('Rodyti akcijų puslapį')
                    ->helperText('Įjungus /akcijos/{slug} rodo šios vaistinės prekes. Išjungus – nukreipia į leidinių puslapį.'),
                Toggle::make('extract_discounts_from_flyer')
                    ->label('Ištraukti nuolaidas iš leidinių (Gemini)')
                    ->helperText('Nuolaidas ištraukia iš leidinio PDF. Nereikia vaistinėms, turinčioms savo e. vaistinės scraperį.'),
            ]);
    }
}
