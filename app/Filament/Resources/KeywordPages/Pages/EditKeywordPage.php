<?php

namespace App\Filament\Resources\KeywordPages\Pages;

use App\Filament\Resources\KeywordPages\KeywordPageResource;
use App\Services\KeywordPageService;
use App\Support\CacheVersion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

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
        CacheVersion::bump('keywords');
        CacheVersion::bump('sitemap');
    }

    protected function afterDelete(): void
    {
        CacheVersion::bump('keywords');
        CacheVersion::bump('sitemap');
    }
}
