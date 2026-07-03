<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->json('primary_keywords')->nullable()->after('h1');
            $table->json('secondary_keywords')->nullable()->after('primary_keywords');
            $table->json('brands')->nullable()->after('secondary_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->dropColumn(['primary_keywords', 'secondary_keywords', 'brands']);
        });
    }
};
