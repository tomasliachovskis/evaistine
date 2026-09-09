<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Stores that already have a real e-shop scraper (scrapers/{slug}.js —
    // Barbora for Maxima) as their discount source. Their flyer PDF is only
    // used for the /leidinys page-viewer (flyers:process-pages); running
    // Gemini discount extraction on it too would be redundant spend and a
    // second, less accurate source for products the e-shop scraper already
    // covers well.
    private const NO_FLYER_DISCOUNTS = ['maxima', 'lidl', 'rimi', 'norfa', 'iki', 'thomas-philipps', 'vynoteka', 'gulbele'];

    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('extract_discounts_from_flyer')->default(true)->after('flyer_source_url');
        });

        DB::table('stores')
            ->whereIn('slug', self::NO_FLYER_DISCOUNTS)
            ->update(['extract_discounts_from_flyer' => false]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('extract_discounts_from_flyer');
        });
    }
};
