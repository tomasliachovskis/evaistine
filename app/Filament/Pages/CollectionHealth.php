<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StoreFlyers\StoreFlyerResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Services\CollectionHealthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class CollectionHealth extends Page
{
    private const CACHE_KEY = 'collection-health:report';

    // The page polls every 60 s; the report is a dozen grouped queries over
    // discount_temp/discounts, so several open tabs share one result.
    private const CACHE_SECONDS = 120;

    protected string $view = 'filament.pages.collection-health';

    protected static ?string $slug = 'collection-health';

    protected static ?string $title = 'Duomenų surinkimas';

    protected static ?string $navigationLabel = 'Duomenų surinkimas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = -1;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getNavigationBadge(): ?string
    {
        $critical = self::report()['counts'][CollectionHealthService::CRITICAL];

        return $critical > 0 ? (string) $critical : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSubheading(): ?string
    {
        return 'Tik faktai iš prod DB: kas realiai atėjo į discount_temp, discounts, store_flyers. Scraper run\'ai čia nenaudojami. Taisyklės: docs/collection-health.md';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Perskaičiuoti')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => Cache::forget(self::CACHE_KEY)),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'report' => self::report(),
            'linkUrl' => fn (?array $link): ?string => self::linkUrl($link),
        ];
    }

    private static function report(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => app(CollectionHealthService::class)->report());
    }

    private static function linkUrl(?array $link): ?string
    {
        if (!$link) {
            return null;
        }

        [$type, $id] = $link;

        return match ($type) {
            'store' => StoreResource::getUrl('edit', ['record' => $id]),
            'flyer' => StoreFlyerResource::getUrl('edit', ['record' => $id]),
            default => null,
        };
    }
}
