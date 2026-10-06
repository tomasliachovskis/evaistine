<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// This fork lists pharmacies only. Earlier migrations seeded every
// superakcijos.lt store; drop the rest (fresh DB, so no discounts/flyers
// reference them yet). Not reversible: those stores aren't coming back.
return new class extends Migration
{
    private const PHARMACY_SLUGS = [
        'eurovaistine', 'gintarine-vaistine', 'camelia', 'benu-vaistine',
        'apotheka', 'nvaistine', 'ramuneles-vaistine',
    ];

    public function up(): void
    {
        DB::table('stores')->whereNotIn('slug', self::PHARMACY_SLUGS)->delete();

        // scrapers/flyers/n-vaistine.js and config/flyer_scrapers.php send
        // "N vaistinė"; stores are matched by name, so "Nvaistinė" never
        // received its leaflets.
        DB::table('stores')->where('slug', 'nvaistine')->update(['name' => 'N vaistinė']);
    }

    public function down(): void
    {
    }
};
