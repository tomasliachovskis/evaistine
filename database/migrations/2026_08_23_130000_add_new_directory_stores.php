<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STORES = [
        ['name' => 'Apotheka', 'slug' => 'apotheka'],
        ['name' => 'Avon', 'slug' => 'avon'],
        ['name' => 'AVS', 'slug' => 'avs'],
        ['name' => 'Benu vaistinė', 'slug' => 'benu-vaistine'],
        ['name' => 'Bikuva', 'slug' => 'bikuva'],
        ['name' => 'Camelia', 'slug' => 'camelia'],
        ['name' => 'Elimart', 'slug' => 'elimart'],
        ['name' => 'Ermitažas', 'slug' => 'ermitazas'],
        ['name' => 'Eurokos', 'slug' => 'eurokos'],
        ['name' => 'Eurovaistinė', 'slug' => 'eurovaistine'],
        ['name' => 'Gintarinė vaistinė', 'slug' => 'gintarine-vaistine'],
        ['name' => 'Jupoja', 'slug' => 'jupoja'],
        ['name' => 'Jysk', 'slug' => 'jysk'],
        ['name' => 'Lankava', 'slug' => 'lankava'],
        ['name' => 'Moki Veži', 'slug' => 'moki-vezi'],
        ['name' => 'Nvaistinė', 'slug' => 'nvaistine'],
        ['name' => 'Officeday', 'slug' => 'officeday'],
        ['name' => 'Oriflame', 'slug' => 'oriflame'],
        ['name' => 'Pepco', 'slug' => 'pepco'],
        ['name' => 'Promo Cash&Carry', 'slug' => 'promo-cash-carry'],
        ['name' => 'Ramunėlės vaistinė', 'slug' => 'ramuneles-vaistine'],
        ['name' => 'Senukai', 'slug' => 'senukai'],
        ['name' => 'Takko', 'slug' => 'takko'],
        ['name' => 'Tupperware', 'slug' => 'tupperware'],
    ];

    public function up(): void
    {
        foreach (self::STORES as $store) {
            $existing = DB::table('stores')->where('slug', $store['slug'])->first();

            if ($existing) {
                DB::table('stores')->where('slug', $store['slug'])->update([
                    'name' => $store['name'],
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('stores')->insert([
                'name' => $store['name'],
                'slug' => $store['slug'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stores')->whereIn('slug', array_column(self::STORES, 'slug'))->delete();
    }
};
