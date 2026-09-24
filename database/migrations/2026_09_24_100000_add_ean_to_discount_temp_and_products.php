<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Barcode (EAN-8/UPC-A/EAN-13/GTIN-14) as scraped from stores that
        // publish it (Ermitažas `barcode[]`, Senukai `gtin`). Lets
        // discounts:process match the same product across stores by barcode
        // instead of relying only on name/slug matching.
        Schema::table('discount_temp', function (Blueprint $table) {
            $table->string('ean', 14)->nullable()->after('brand');
        });

        // Not unique: existing name-matched duplicate product rows mean one
        // barcode can legitimately be attached to more than one product until
        // those duplicates are merged; lookups just take the lowest id.
        Schema::table('products', function (Blueprint $table) {
            $table->string('ean', 14)->nullable()->after('brand');
            $table->index('ean');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['ean']);
            $table->dropColumn('ean');
        });

        Schema::table('discount_temp', function (Blueprint $table) {
            $table->dropColumn('ean');
        });
    }
};
