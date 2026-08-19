<?php

namespace App\Filament\Resources\KeywordPages\Pages;

use App\Filament\Resources\KeywordPages\KeywordPageResource;
use App\Services\KeywordPageService;
use App\Support\CacheVersion;
use Filament\Resources\Pages\CreateRecord;

class CreateKeywordPage extends CreateRecord
{
    protected static string $resource = KeywordPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            $data['h1'] = mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1) . ' akcijos ir nuolaidos šią savaitę';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(KeywordPageService::class)->refreshOfferCounts($this->record->refresh());
        CacheVersion::bump('keywords');
        CacheVersion::bump('sitemap');
    }
}
