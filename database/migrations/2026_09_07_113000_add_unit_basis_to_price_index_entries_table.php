<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_index_entries', function (Blueprint $table) {
            // 'kg', 'l', or '10vnt' — 'price' is normalized to this unit, not
            // the purchasable pack's raw price, so a 220g loaf and a 1kg loaf
            // are actually comparable. See PriceIndexService::parseUnitPrice.
            $table->string('unit_basis')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('price_index_entries', function (Blueprint $table) {
            $table->dropColumn('unit_basis');
        });
    }
};
