<?php

namespace App\Support;

use App\Models\Store;
use App\Models\StoreLocation;

// Builds schema.org LocalBusiness + OpeningHoursSpecification for one
// physical store location's own page. NOTE: this does NOT (and can't) feed
// Google's "Open now" Knowledge Panel/hours box — that's sourced from the
// business's own verified Google Business Profile, not from third-party
// page markup. This is a smaller, honest win: helps Google parse/trust our
// own page's entity data correctly. Cheap to have, not a growth lever.
class StoreLocationSchema
{
    private const DAY_TO_SCHEMA = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ];

    public static function build(Store $store, StoreLocation $location, string $path): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => "{$store->name} {$location->address}",
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $location->address,
                'addressLocality' => $location->city,
                'addressCountry' => 'LT',
            ],
            'url' => url($path),
        ];

        if ($location->lat !== null && $location->lng !== null) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $location->lat,
                'longitude' => $location->lng,
            ];
        }

        if (!empty($location->phone)) {
            $schema['telephone'] = $location->phone;
        }

        $hours = self::openingHours($location->hours ?? []);
        if (!empty($hours)) {
            $schema['openingHoursSpecification'] = $hours;
        }

        return $schema;
    }

    private static function openingHours(array $hours): array
    {
        $specs = [];

        foreach (self::DAY_TO_SCHEMA as $key => $schemaDay) {
            $range = $hours[$key] ?? null;
            if (!$range || !str_contains($range, '-')) {
                continue;
            }

            [$opens, $closes] = array_map('trim', explode('-', $range, 2));

            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => "https://schema.org/{$schemaDay}",
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $specs;
    }
}
