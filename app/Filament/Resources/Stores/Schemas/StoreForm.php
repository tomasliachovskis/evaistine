<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\TextInput;
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
                    ->helperText('Parduotuvės puslapis, kuriame skelbiamas naujausias leidinys/PDF – iš čia jį parsisiųsti ir įkelti per "Leidiniai".')
                    ->url()
                    ->maxLength(500),
            ]);
    }
}
