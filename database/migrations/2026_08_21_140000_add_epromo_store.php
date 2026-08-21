<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('stores')->where('slug', 'epromo')->first();

        if ($existing) {
            DB::table('stores')->where('slug', 'epromo')->update([
                'name' => 'ePromo',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('stores')->insert([
            'name' => 'ePromo',
            'slug' => 'epromo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('stores')->where('slug', 'epromo')->delete();
    }
};
