<?php

namespace App\Support;

// Energy drinks belong under "Nealkoholiniai gėrimai" (397), but most store
// category mappers send every drink to the broad "Gėrimai, kava, arbata"
// (380) — e.g. Maxima's whole "Gėrimai/" tree — while flyer/GPT-classified
// rows land in 397 via ProcessDiscounts' non-alc split heuristic. That
// split the same product type across two roots, so the monster/redbull/
// energetiniai-gerimai keyword pages (scoped to one root) missed part of
// it. Mappers alone can't separate energy drinks out of a store's generic
// drinks bucket, so this name-based override runs after mapper resolution.
class EnergyDrinkCategory
{
    public const BROAD_DRINKS_ID = 380;

    public const NON_ALCOHOLIC_ID = 397;

    private const MATCH = '/energ|en\.\s?g[eė]r|red\s?bull|monster|dynami|battery/iu';

    // Matched by MATCH but not energy drinks: non-alc beer ("...HELL",
    // "RADLER ENERGY"), coffee, iced tea, water/juice with an "Energy" or
    // "Monster" line name, and energy-drink purée ("tyrė en. gėrimui").
    // Word-bounded beer terms — plain "alus" also matches "natūralus".
    private const EXCLUDE = '/\balus\b|\balaus\b|kavos|coffee|ice tea|vanduo|sulč|tyrė/iu';

    public static function isEnergyDrink(string $productName): bool
    {
        return preg_match(self::MATCH, $productName) === 1
            && preg_match(self::EXCLUDE, $productName) !== 1;
    }

    public static function apply(?int $categoryId, string $productName): ?int
    {
        if ($categoryId === self::BROAD_DRINKS_ID && self::isEnergyDrink($productName)) {
            return self::NON_ALCOHOLIC_ID;
        }

        return $categoryId;
    }
}
