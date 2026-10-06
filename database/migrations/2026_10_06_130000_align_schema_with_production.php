<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The superakcijos.lt production schema drifted from the migrations (columns
// changed by hand over time): discount_temp keeps the raw scraped strings and
// a store *name*, and prices/dates/category ids are nullable. A fresh DB built
// from migrations alone rejected real scraper rows (store_id required,
// original_price NOT NULL). This brings a fresh DB in line with production.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_temp', function (Blueprint $table) {
            $table->dropForeign('discount_temp_store_id_foreign');
            $table->dropColumn('store_id');
        });

        Schema::table('discount_temp', function (Blueprint $table) {
            $table->string('store')->nullable()->after('product_url');
            $table->string('category')->nullable()->default('')->change();
            $table->string('product_url', 2048)->nullable()->change();
            $table->string('original_price')->nullable()->default('')->change();
            $table->string('discounted_price')->nullable()->default('')->change();
            $table->string('discount_percent')->nullable()->default('')->change();
            $table->string('start_at')->nullable()->default('')->change();
            $table->string('end_at')->nullable()->default('')->change();
        });

        foreach (['discounts', 'discount_histories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('original_price', 10, 2)->nullable()->change();
                $table->decimal('discounted_price', 10, 2)->nullable()->change();
                $table->integer('discount_percent')->nullable()->change();
                $table->timestamp('start_at')->nullable()->change();
                $table->timestamp('end_at')->nullable()->change();
                $table->string('product_url', 2048)->nullable()->change();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });

        Schema::table('category_mappers', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->string('store_category')->nullable()->default('')->change();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('meta_title', 1020)->nullable()->change();
        });

        Schema::table('search_results', function (Blueprint $table) {
            $table->string('ip_address', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
    }
};
