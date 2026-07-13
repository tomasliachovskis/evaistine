<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->unsignedInteger('matching_offers_count')->default(0)->after('min_active_offers');
            $table->unsignedInteger('displayed_offers_count')->default(0)->after('matching_offers_count');
            $table->timestamp('offers_counted_at')->nullable()->after('displayed_offers_count');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->dropColumn(['matching_offers_count', 'displayed_offers_count', 'offers_counted_at']);
        });
    }
};
