<?php

namespace App\Filament\Resources\CouponWebsites\Pages;

use App\Filament\Resources\CouponWebsites\CouponWebsiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCouponWebsites extends ListRecords
{
    protected static string $resource = CouponWebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
