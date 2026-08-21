<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('stores')->where('slug', 'vynoteka')->first();

        if ($existing) {
            DB::table('stores')->where('slug', 'vynoteka')->update([
                'name' => 'Vynoteka',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('stores')->insert([
            'name' => 'Vynoteka',
            'slug' => 'vynoteka',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('stores')->where('slug', 'vynoteka')->delete();
    }
};
