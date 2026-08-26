<?php

namespace App\Support;

class StoreSocialProof
{
    // Ported from lib/store-social-proof.ts's resolveStoreFollowerCount — a
    // deterministic (not random) per-store "social proof" number, same hash
    // function so it matches what the original Next.js app showed for each store.
    private const FOLLOWER_OPTIONS = [842, 1247, 1784, 2136, 2891, 3420, 4156, 5284, 6731, 8420, 9124, 10583, 12470, 14236, 15842, 17620, 19405, 21340, 23891, 26750];

    public static function followerCount(string $storeSlug): int
    {
        $hash = 0;
        foreach (str_split($storeSlug) as $ch) {
            $hash = ($hash * 31 + ord($ch)) & 0xFFFFFFFF;
        }

        return self::FOLLOWER_OPTIONS[$hash % count(self::FOLLOWER_OPTIONS)];
    }

    public static function followerLabel(string $storeSlug, string $storeName): string
    {
        $followerCount = self::followerCount($storeSlug);
        $followerNoun = match (true) {
            $followerCount % 10 === 1 && $followerCount % 100 !== 11 => 'žmogus',
            in_array($followerCount % 10, [2, 3, 4, 5, 6, 7, 8, 9]) && !in_array($followerCount % 100, range(11, 19)) => 'žmonės',
            default => 'žmonių',
        };

        return number_format($followerCount, 0, ',', ' ') . " {$followerNoun} jau seka {$storeName} akcijas";
    }
}
