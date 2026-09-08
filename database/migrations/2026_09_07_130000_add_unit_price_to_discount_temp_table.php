<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_temp', function (Blueprint $table) {
            // The store's own printed/scraped price per kg/l/vnt — see
            // CLAUDE.md "Unit price normalization" for per-store source
            // details. Kept distinct from PriceIndexService's 'basis' (which
            // normalizes 'vnt' to '10vnt' for basket comparability) — here
            // 'vnt' means exactly what the store printed.
            $table->decimal('unit_price', 8, 2)->nullable()->after('info');
            $table->string('unit_price_basis')->nullable()->after('unit_price');
            // true when we computed this ourselves (price ÷ pack size)
            // instead of the store publishing it directly — e.g. Thomas
            // Philipps (never publishes it) or Vynoteka's flyer (its live
            // site does, but its printed flyer never does).
            $table->boolean('unit_price_estimated')->default(false)->after('unit_price_basis');
        });
    }

    public function down(): void
    {
        Schema::table('discount_temp', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'unit_price_basis', 'unit_price_estimated']);
        });
    }
};
