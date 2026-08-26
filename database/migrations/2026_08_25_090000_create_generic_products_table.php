<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generic_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('emoji')->nullable();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('keyword_page_id')->nullable()->constrained('keyword_pages')->nullOnDelete();
            $table->enum('source', ['keyword_page', 'manual'])->default('keyword_page');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('generic_product_id')->nullable()->after('category_id')
                ->constrained('generic_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generic_product_id');
        });

        Schema::dropIfExists('generic_products');
    }
};
