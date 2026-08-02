<?php

namespace App\Filament\Resources\KeywordPages\Pages;

use App\Filament\Resources\KeywordPages\KeywordPageResource;
use App\Services\KeywordPageService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditKeywordPage extends EditRecord
{
    protected static string $resource = KeywordPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            $data['h1'] = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1) . ' akcijos ir nuolaidos šią savaitę';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(KeywordPageService::class)->refreshOfferCounts($this->record->refresh());
        Cache::tags(['keywords', 'sitemap'])->flush();
    }

    protected function afterDelete(): void
    {
        Cache::tags(['keywords', 'sitemap'])->flush();
    }
}
