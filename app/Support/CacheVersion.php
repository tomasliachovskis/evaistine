<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cheap replacement for Cache::tags() — a tagged read/write costs one extra
 * Redis round trip per tag to resolve the tag's namespace. Groups here cost
 * one Cache::get() per group instead, and bump() invalidates everything
 * keyed under that group in a single atomic increment.
 */
class CacheVersion
{
    public static function get(string $group): int
    {
        return (int) Cache::get(self::key($group), 0);
    }

    public static function bump(string $group): void
    {
        Cache::increment(self::key($group));
    }

    public static function suffix(array $groups): string
    {
        $parts = [];

        foreach ($groups as $group) {
            $parts[] = $group . '.' . self::get($group);
        }

        return implode('_', $parts);
    }

    private static function key(string $group): string
    {
        return "cache_version:{$group}";
    }
}
