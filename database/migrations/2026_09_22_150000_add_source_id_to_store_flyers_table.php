<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            // Stable, platform-assigned per-document identifier a scraper
            // already computes (Yumpu/Issuu docId, dcatalog guid, resolved
            // PDF URL, issue number, ...) — used as the primary dedup key in
            // ScrapingController::storeFlyer() instead of title, which can
            // reword between two scrapes of the same physical leaflet. Null
            // for the handful of single-catalog scrapers with no natural
            // per-run ID; MySQL's unique index allows multiple NULLs so
            // those never collide with each other.
            $table->string('source_id')->nullable()->after('title');
            $table->unique(['store_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'source_id']);
            $table->dropColumn('source_id');
        });
    }
};
