<?php

namespace App\Filament\Resources\StoreFlyers\Pages;

use App\Filament\Resources\StoreFlyers\StoreFlyerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreFlyers extends ListRecords
{
    protected static string $resource = StoreFlyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
