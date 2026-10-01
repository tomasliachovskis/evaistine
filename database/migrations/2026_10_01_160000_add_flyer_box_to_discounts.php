<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Where the offer is printed on its flyer page: Gemini's
    // [ymin, xmin, ymax, xmax], normalized 0-1000 (copied from
    // discount_temp.box). Lets the leaflet viewer make each product on the
    // page clickable.
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->json('flyer_box')->nullable()->after('flyer_page');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn('flyer_box');
        });
    }
};
