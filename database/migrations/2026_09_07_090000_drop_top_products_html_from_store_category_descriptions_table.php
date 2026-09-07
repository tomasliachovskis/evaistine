<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_category_descriptions', function (Blueprint $table) {
            $table->dropColumn('top_products_html');
        });
    }

    public function down(): void
    {
        Schema::table('store_category_descriptions', function (Blueprint $table) {
            $table->text('top_products_html')->nullable();
        });
    }
};
