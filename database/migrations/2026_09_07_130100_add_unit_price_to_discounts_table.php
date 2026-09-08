<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            // See 2026_09_07_130000_add_unit_price_to_discount_temp_table.php
            // for the field design rationale — mirrored here since this is
            // the table actually queried for display / PriceIndexService.
            $table->decimal('unit_price', 8, 2)->nullable()->after('info');
            $table->string('unit_price_basis')->nullable()->after('unit_price');
            $table->boolean('unit_price_estimated')->default(false)->after('unit_price_basis');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'unit_price_basis', 'unit_price_estimated']);
        });
    }
};
