<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_temp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('image_url')->nullable();
            $table->string('product_url');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->decimal('original_price', 10, 2);
            $table->decimal('discounted_price', 10, 2);
            $table->integer('discount_percent');
            $table->text('condition')->nullable();
            $table->string('card')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_temp');
    }
}; 