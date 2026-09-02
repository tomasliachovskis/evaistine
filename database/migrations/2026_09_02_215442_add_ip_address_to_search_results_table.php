<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('search_results', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable()->after('query');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_results', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
