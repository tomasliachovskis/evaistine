<?php

namespace App\Filament\Resources\StoreFlyers\Pages;

use App\Filament\Resources\StoreFlyers\Concerns\HandlesStoreFlyerUploads;
use App\Filament\Resources\StoreFlyers\StoreFlyerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreFlyer extends CreateRecord
{
    use HandlesStoreFlyerUploads;

    protected static string $resource = StoreFlyerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->processFlyerFormData($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPdfUploadFromForm($this->record);
        $this->dispatchPageProcessing($this->record);
    }
}
