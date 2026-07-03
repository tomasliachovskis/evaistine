<?php

namespace App\Filament\Resources\KeywordPages;

use App\Filament\Resources\KeywordPages\Pages\CreateKeywordPage;
use App\Filament\Resources\KeywordPages\Pages\EditKeywordPage;
use App\Filament\Resources\KeywordPages\Pages\ListKeywordPages;
use App\Filament\Resources\KeywordPages\Schemas\KeywordPageForm;
use App\Filament\Resources\KeywordPages\Tables\KeywordPagesTable;
use App\Models\KeywordPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KeywordPageResource extends Resource
{
    protected static ?string $model = KeywordPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Keyword puslapiai';

    protected static ?string $modelLabel = 'keyword puslapis';

    protected static ?string $pluralModelLabel = 'keyword puslapiai';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return KeywordPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeywordPagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeywordPages::route('/'),
            'create' => CreateKeywordPage::route('/create'),
            'edit' => EditKeywordPage::route('/{record}/edit'),
        ];
    }
}
