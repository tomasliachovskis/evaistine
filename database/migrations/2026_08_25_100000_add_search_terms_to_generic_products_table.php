<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_products', function (Blueprint $table) {
            $table->json('search_terms')->nullable()->after('emoji');
        });
    }

    public function down(): void
    {
        Schema::table('generic_products', function (Blueprint $table) {
            $table->dropColumn('search_terms');
        });
    }
};
