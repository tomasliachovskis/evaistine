<?php

namespace App\Filament\Resources\StoreFlyers\Pages;

use App\Filament\Resources\StoreFlyers\Concerns\HandlesStoreFlyerUploads;
use App\Filament\Resources\StoreFlyers\StoreFlyerResource;
use App\Models\StoreFlyer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreFlyer extends EditRecord
{
    use HandlesStoreFlyerUploads;

    protected static string $resource = StoreFlyerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprocess')
                ->label('Perprocesuoti puslapius')
                ->visible(fn (StoreFlyer $record) => (bool) $record->pdf_url)
                ->action(function (StoreFlyer $record) {
                    $record->update([
                        'processing_status' => StoreFlyer::STATUS_PENDING,
                        'processing_error' => null,
                    ]);
                    $this->dispatchStoreFlyerPageProcessing($record);
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillUploadFields(parent::mutateFormDataBeforeFill($data));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->processFlyerFormData($data, $this->record);
    }

    protected function afterSave(): void
    {
        $this->syncPdfUploadFromForm($this->record);
        $this->dispatchPageProcessing($this->record);
    }
}
