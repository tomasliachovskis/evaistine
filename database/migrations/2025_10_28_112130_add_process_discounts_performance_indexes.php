<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // discount_temp table indexes
        Schema::table('discount_temp', function (Blueprint $table) {
            // $table->index(['processed', 'category']); // Already exists
            // $table->index('store'); // Already exists
        });
        
        // Add prefix index for product_url using raw SQL
        // DB::statement('ALTER TABLE discount_temp ADD INDEX discount_temp_product_url_prefix (product_url(191))'); // Already exists

        // stores table indexes
        Schema::table('stores', function (Blueprint $table) {
            // $table->index('name'); // Already exists
        });

        // discounts table indexes
        Schema::table('discounts', function (Blueprint $table) {
            // $table->index(['product_id', 'store_id', 'start_at', 'end_at']); // Already exists
        });

        // category_mappers table indexes
        Schema::table('category_mappers', function (Blueprint $table) {
            // $table->index(['store', 'store_category']); // Already exists
        });
        
        // Add prefix index for store_category using raw SQL
        // DB::statement('ALTER TABLE category_mappers ADD INDEX category_mappers_store_category_prefix (store_category(100))'); // Already exists

        // categories table indexes
        Schema::table('categories', function (Blueprint $table) {
            // $table->index('name'); // Already exists
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // discount_temp table indexes
        Schema::table('discount_temp', function (Blueprint $table) {
            // $table->dropIndex(['processed', 'category']); // Already exists
            // $table->dropIndex(['store']); // Already exists
        });
        
        // Drop prefix index for product_url using raw SQL
        // DB::statement('ALTER TABLE discount_temp DROP INDEX discount_temp_product_url_prefix'); // Already exists

        // stores table indexes
        Schema::table('stores', function (Blueprint $table) {
            // $table->dropIndex(['name']); // Already exists
        });

        // discounts table indexes
        Schema::table('discounts', function (Blueprint $table) {
            // $table->dropIndex(['product_id', 'store_id', 'start_at', 'end_at']); // Already exists
        });

        // category_mappers table indexes
        Schema::table('category_mappers', function (Blueprint $table) {
            // $table->dropIndex(['store', 'store_category']); // Already exists
        });
        
        // Drop prefix index for store_category using raw SQL
        // DB::statement('ALTER TABLE category_mappers DROP INDEX category_mappers_store_category_prefix'); // Already exists

        // categories table indexes
        Schema::table('categories', function (Blueprint $table) {
            // $table->dropIndex(['name']); // Already exists
        });
    }
};