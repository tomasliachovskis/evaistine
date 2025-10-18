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
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'slug']);
            $table->index('created_at');
        });
        
        Schema::table('discounts', function (Blueprint $table) {
            $table->index(['product_id', 'discount_percent']);
            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'slug']);
            $table->dropIndex(['created_at']);
        });
        
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'discount_percent']);
            $table->dropIndex(['product_id', 'created_at']);
        });
    }
};
