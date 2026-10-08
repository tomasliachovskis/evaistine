<?php

namespace App\Support;

/**
 * Keys that identify a product line inside one curated list (a category
 * carousel, the home "best deals" pool): the brand, and the first two words
 * of the name ("crescina transdermic", "plaukų atauginimui", "dermatologinis
 * kosmetinis"). Two products sharing either key count as the same line. The
 * name key catches lines whose brand is empty or spelled differently between
 * pharmacies. Owner's rule (2026-10-08): a list shows different lines, and
 * repeats one only when nothing else is left.
 */
class ProductLineKey
{
    /**
     * @return list<string>
     */
    public static function for(?string $brand, ?string $name): array
    {
        $keys = [];

        $brand = mb_strtolower(trim((string) $brand));
        if ($brand !== '') {
            $keys[] = "brand:{$brand}";
        }

        $plain = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $name));
        $words = preg_split('/\s+/u', trim((string) $plain), -1, PREG_SPLIT_NO_EMPTY);
        if ($words !== []) {
            $keys[] = 'name:' . implode(' ', array_slice($words, 0, 2));
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, true>  $used
     */
    public static function taken(array $keys, array $used): bool
    {
        foreach ($keys as $key) {
            if (isset($used[$key])) {
                return true;
            }
        }

        return false;
    }
}
