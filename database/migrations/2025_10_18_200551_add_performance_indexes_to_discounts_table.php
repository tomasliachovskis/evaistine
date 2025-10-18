<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->index('store_id');
            $table->index('product_id');
            $table->index('discounted_price');
            $table->index('discount_percent');
            $table->index(['store_id', 'product_id']);
            $table->index(['product_id', 'discounted_price']);
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('slug');
        });
        
        Schema::table('stores', function (Blueprint $table) {
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['discounted_price']);
            $table->dropIndex(['discount_percent']);
            $table->dropIndex(['store_id', 'product_id']);
            $table->dropIndex(['product_id', 'discounted_price']);
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['slug']);
        });
        
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['slug']);
        });
    }
};
