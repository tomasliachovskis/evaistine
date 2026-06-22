<?php

namespace App\Filament\Resources\StoreFlyers;

use App\Filament\Resources\StoreFlyers\Pages\CreateStoreFlyer;
use App\Filament\Resources\StoreFlyers\Pages\EditStoreFlyer;
use App\Filament\Resources\StoreFlyers\Pages\ListStoreFlyers;
use App\Filament\Resources\StoreFlyers\Schemas\StoreFlyerForm;
use App\Filament\Resources\StoreFlyers\Tables\StoreFlyersTable;
use App\Models\StoreFlyer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreFlyerResource extends Resource
{
    protected static ?string $model = StoreFlyer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Leidiniai';

    protected static ?string $modelLabel = 'leidinys';

    protected static ?string $pluralModelLabel = 'leidiniai';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return StoreFlyerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreFlyersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('store');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreFlyers::route('/'),
            'create' => CreateStoreFlyer::route('/create'),
            'edit' => EditStoreFlyer::route('/{record}/edit'),
        ];
    }
}
