<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Split "does /akcijos/{slug} exist" off extract_discounts_from_flyer,
        // which until now meant both that and "run Gemini discount extraction
        // on this store's flyers". A store with its own e-shop scraper
        // (Ermitažas) needs the offers page without paying for a second,
        // less accurate flyer-extracted source for the same products.
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('show_discounts_page')->default(false)->after('extract_discounts_from_flyer');
        });

        // Carry over today's behaviour exactly: every store that currently
        // shows an offers page keeps showing it.
        DB::table('stores')->update(['show_discounts_page' => DB::raw('extract_discounts_from_flyer')]);

        // Ermitažas' products come from its e-shop scraper, not its flyers.
        DB::table('stores')->where('slug', 'ermitazas')->update(['show_discounts_page' => true]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('show_discounts_page');
        });
    }
};
