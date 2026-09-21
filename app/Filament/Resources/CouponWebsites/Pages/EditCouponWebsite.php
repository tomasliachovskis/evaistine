<?php

namespace App\Filament\Resources\CouponWebsites\Pages;

use App\Filament\Resources\CouponWebsites\CouponWebsiteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCouponWebsite extends EditRecord
{
    protected static string $resource = CouponWebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
