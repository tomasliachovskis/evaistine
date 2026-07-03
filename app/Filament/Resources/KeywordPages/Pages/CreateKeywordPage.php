<?php

namespace App\Filament\Resources\KeywordPages\Pages;

use App\Filament\Resources\KeywordPages\KeywordPageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Cache;

class CreateKeywordPage extends CreateRecord
{
    protected static string $resource = KeywordPageResource::class;

    protected function afterCreate(): void
    {
        Cache::tags(['keywords', 'sitemap'])->flush();
    }
}
