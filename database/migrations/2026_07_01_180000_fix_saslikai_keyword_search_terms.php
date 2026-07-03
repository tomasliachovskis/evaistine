<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('keyword_pages')
            ->where('slug', 'saslikai')
            ->update([
                'search_terms' => json_encode([
                    'šašlyk',
                    'saslyk',
                    'šašlykas',
                    'saslykas',
                    'šašlik',
                    'saslik',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('keyword_pages')
            ->where('slug', 'saslikai')
            ->update([
                'search_terms' => json_encode([
                    'šašlik',
                    'saslik',
                    'šašlikai',
                    'saslikai',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
