<?php

namespace App\Filament\Resources\CouponWebsites;

use App\Filament\Resources\CouponWebsites\Pages\CreateCouponWebsite;
use App\Filament\Resources\CouponWebsites\Pages\EditCouponWebsite;
use App\Filament\Resources\CouponWebsites\Pages\ListCouponWebsites;
use App\Filament\Resources\CouponWebsites\Schemas\CouponWebsiteForm;
use App\Filament\Resources\CouponWebsites\Tables\CouponWebsitesTable;
use App\Models\CouponWebsite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CouponWebsiteResource extends Resource
{
    protected static ?string $model = CouponWebsite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Kuponų svetainės';

    protected static ?string $modelLabel = 'svetainė';

    protected static ?string $pluralModelLabel = 'svetainės';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CouponWebsiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CouponWebsitesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCouponWebsites::route('/'),
            'create' => CreateCouponWebsite::route('/create'),
            'edit' => EditCouponWebsite::route('/{record}/edit'),
        ];
    }
}
