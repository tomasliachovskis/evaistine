<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Online pharmacies from VVKT's official list of pharmacies allowed to sell
// medicines remotely (checked 2026-10-07), the ones with a working e-shop.
// The big physical chains were already in. See docs/evaistine.md "Stores".
return new class extends Migration
{
    private const STORES = [
        'internetine-vaistine' => 'InternetineVaistine.lt',
        'mano-vaistine' => 'Mano vaistinė',
        'piliule' => 'Piliulė',
        'universiteto-vaistine' => 'Universiteto vaistinė',
        'azuolyno-vaistine' => 'Ąžuolyno vaistinė',
        'rx-vaistine' => 'Rx vaistinė',
        'lsmu-vaistine' => 'LSMU vaistinė',
        '100-metu-vaistine' => '100 metų vaistinė',
    ];

    public function up(): void
    {
        foreach (self::STORES as $slug => $name) {
            if (DB::table('stores')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('stores')->insert([
                'name' => $name,
                'slug' => $slug,
                // E-shops: their /{slug} page lists their offers once scraped.
                'show_discounts_page' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stores')->whereIn('slug', array_keys(self::STORES))->delete();
    }
};
