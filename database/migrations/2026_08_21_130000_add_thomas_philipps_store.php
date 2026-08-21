<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('stores')->where('slug', 'thomas-philipps')->first();

        if ($existing) {
            DB::table('stores')->where('slug', 'thomas-philipps')->update([
                'name' => 'Thomas Philipps',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('stores')->insert([
            'name' => 'Thomas Philipps',
            'slug' => 'thomas-philipps',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('stores')->where('slug', 'thomas-philipps')->delete();
    }
};
