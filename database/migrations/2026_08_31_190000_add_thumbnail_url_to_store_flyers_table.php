<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            // Separate, much smaller derivative of page 1 used everywhere a
            // flyer is shown as a small card (hub/index grids, home carousel)
            // — image_url stays the full-size page used by the actual page
            // reader. Nullable/falls back to image_url in the UI until a
            // flyer's been (re)processed since this was added.
            $table->string('thumbnail_url')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            $table->dropColumn('thumbnail_url');
        });
    }
};
