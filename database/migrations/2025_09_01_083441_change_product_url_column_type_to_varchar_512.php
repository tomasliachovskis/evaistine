<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('product_url', 512)->nullable()->change();
        });

        Schema::table('discount_temp', function (Blueprint $table) {
            $table->string('product_url', 512)->nullable()->change();
        });

        Schema::table('discount_histories', function (Blueprint $table) {
            $table->string('product_url', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('product_url', 255)->nullable()->change();
        });

        Schema::table('discount_temp', function (Blueprint $table) {
            $table->string('product_url', 255)->change();
        });

        Schema::table('discount_histories', function (Blueprint $table) {
            $table->string('product_url', 255)->change();
        });
    }
};
