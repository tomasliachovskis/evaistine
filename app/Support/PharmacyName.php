<?php

namespace App\Support;

// A pharmacy chain's name as a noun phrase in a given case, without doubling
// "vaistinė": "Camelia vaistinėje" but "Benu vaistinėje", "Eurovaistinėje".
// Chains whose name already says "vaistinė" are inflected from
// config('stores.name_forms'); one missing there is returned as is, which is
// always safe.
class PharmacyName
{
    private const NOUN = [
        'nominative' => 'vaistinė',
        'genitive' => 'vaistinės',
        'accusative' => 'vaistinę',
        'locative' => 'vaistinėje',
        'plural' => 'vaistinės',
        'genitive_plural' => 'vaistinių',
        'locative_plural' => 'vaistinėse',
    ];

    public static function phrase(string $storeName, string $case): string
    {
        if (! isset(self::NOUN[$case])) {
            throw new \InvalidArgumentException("Unknown case: {$case}");
        }

        $forms = config("stores.name_forms.{$storeName}");
        if (is_array($forms)) {
            return $forms[$case] ?? $storeName;
        }

        if (mb_stripos($storeName, 'vaistin') !== false) {
            return $storeName;
        }

        return $storeName . ' ' . self::NOUN[$case];
    }
}
