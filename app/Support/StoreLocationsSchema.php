<?php

namespace App\Support;

use Illuminate\Support\Collection;

// ItemList of Store entities for /vaistines/{store}/{city} — address,
// phone, geo and opening hours per location, all already shown on the page.
// Added after the 2026-09-28 SEO audit: these pages target "{store} {city}
// darbo laikas / adresai" queries but carried only a BreadcrumbList.
// Generic `Store` on purpose — the chain list mixes groceries, pharmacies
// and DIY, and a wrong subtype is worse than the plain parent type.
class StoreLocationsSchema
{
    private const DAY_OF_WEEK = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ];

    public static function build(string $storeName, string $cityName, Collection $locations, string $pageUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => PharmacyName::phrase($storeName, 'plural') . " {$cityName}",
            'numberOfItems' => $locations->count(),
            'itemListElement' => $locations->values()->map(fn (array $location, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => self::location($storeName, $cityName, $location, $pageUrl),
            ])->all(),
        ];
    }

    private static function location(string $storeName, string $cityName, array $location, string $pageUrl): array
    {
        $store = [
            '@type' => 'Store',
            'name' => "{$storeName}, {$location['address']}",
            'url' => $pageUrl,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $location['address'],
                'addressLocality' => $location['city'] ?? $cityName,
                'addressCountry' => 'LT',
            ],
        ];

        if (!empty($location['phone'])) {
            $store['telephone'] = $location['phone'];
        }

        if (isset($location['lat'], $location['lng']) && $location['lat'] && $location['lng']) {
            $store['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $location['lat'],
                'longitude' => (float) $location['lng'],
            ];
        }

        $hours = self::openingHours((array) ($location['hours'] ?? []));
        if ($hours !== []) {
            $store['openingHoursSpecification'] = $hours;
        }

        return $store;
    }

    /**
     * "08:00-22:00" per day → one OpeningHoursSpecification per distinct
     * range, grouping the days that share it. Anything not matching HH:MM-HH:MM
     * ("Nedirba", empty) is left out, i.e. closed that day.
     */
    private static function openingHours(array $hours): array
    {
        $byRange = [];

        foreach (self::DAY_OF_WEEK as $key => $day) {
            $value = trim((string) ($hours[$key] ?? ''));
            if (!preg_match('/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/u', $value, $m)) {
                continue;
            }

            $byRange["{$m[1]}-{$m[2]}"][] = $day;
        }

        $specs = [];
        foreach ($byRange as $range => $days) {
            [$opens, $closes] = explode('-', $range);
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days,
                'opens' => str_pad($opens, 5, '0', STR_PAD_LEFT),
                'closes' => str_pad($closes, 5, '0', STR_PAD_LEFT),
            ];
        }

        return $specs;
    }
}
