<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Correction to the 2026_09_09_150000 migration: Maxima/Iki/Lidl/Rimi/
    // Norfa DO already have a direct e-shop scraper, but per explicit
    // instruction their flyer PDFs should still be run through Gemini
    // discount extraction too — the leaflet is a genuinely different,
    // complementary source (weekly rotating "leidinys" specials with their
    // own validity window), not a redundant duplicate of the e-shop scrape.
    // Live counts checked before this change (active discounts / food-
    // category discounts): Maxima 4702/827, Rimi 4920/1803, Iki 566/406,
    // Norfa 596/371, Lidl 270/207 — healthy already, this adds to it rather
    // than fixing a gap.
    private const ENABLE = ['maxima', 'iki', 'lidl', 'rimi', 'norfa'];

    public function up(): void
    {
        DB::table('stores')
            ->whereIn('slug', self::ENABLE)
            ->update(['extract_discounts_from_flyer' => true]);
    }

    public function down(): void
    {
        DB::table('stores')
            ->whereIn('slug', self::ENABLE)
            ->update(['extract_discounts_from_flyer' => false]);
    }
};
