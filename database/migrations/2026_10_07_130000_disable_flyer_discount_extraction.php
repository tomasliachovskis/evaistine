<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// extract_discounts_from_flyer defaults to true (superakcijos, where most
// grocery stores had no e-shop scraper). Every pharmacy chain with a
// leaflet except N vaistinė and Ramunėlės also has an e-shop scraper, and
// extracting its leaflet offers with Gemini would duplicate those prices.
// The owner decided (2026-10-07) to collect and show the leaflet PDFs only,
// so extraction is off for every pharmacy; turning it on for N vaistinė /
// Ramunėlės later is a separate decision.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stores')->update(['extract_discounts_from_flyer' => false]);
    }

    public function down(): void
    {
        DB::table('stores')->update(['extract_discounts_from_flyer' => true]);
    }
};
