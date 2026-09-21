<?php

namespace App\Filament\Resources\CouponWebsites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CouponWebsiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Pavadinimas')
                    ->required()
                    ->live()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Automatiškai sugeneruojama iš pavadinimo, jei palikta tuščia.'),
                TextInput::make('url')
                    ->label('Svetainės adresas')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://www.pavyzdys.lt'),
                TextInput::make('logo_url')
                    ->label('Logotipo nuoroda')
                    ->url()
                    ->maxLength(255)
                    ->helperText('Nuoroda į logotipo failą (SVG/PNG).'),
                Textarea::make('description')
                    ->label('Aprašymas')
                    ->rows(3),
            ]);
    }
}
