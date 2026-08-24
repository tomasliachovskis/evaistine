<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STORES = [
        ['name' => 'TECHasas', 'slug' => 'techasas'],
        ['name' => 'Technorama', 'slug' => 'technorama'],
        ['name' => 'Douglas', 'slug' => 'douglas'],
        ['name' => 'Ikea', 'slug' => 'ikea'],
        ['name' => 'Lytagra', 'slug' => 'lytagra'],
        ['name' => 'Mary Kay', 'slug' => 'mary-kay'],
        ['name' => 'Švaros Prekės', 'slug' => 'svaros-prekes'],
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
