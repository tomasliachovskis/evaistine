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
            // ISO 3166-1 alpha-2 from Cloudflare's CF-IPCountry header.
            $table->char('country_code', 2)->nullable()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('search_results', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
