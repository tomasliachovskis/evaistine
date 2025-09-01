<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmapped_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('store');
            $table->text('gpt_response')->nullable();
            $table->text('error_message')->nullable();
            $table->json('product_data')->nullable();
            $table->string('mapping_status')->default('failed');
            $table->timestamp('attempted_at');
            $table->timestamps();
            
            $table->index(['store', 'mapping_status']);
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmapped_products');
    }
};
