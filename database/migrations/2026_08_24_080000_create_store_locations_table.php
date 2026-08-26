<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('city');
            $table->string('address');
            $table->string('slug')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->json('hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('nuolaidos.lt');
            $table->timestamps();

            $table->unique(['store_id', 'external_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_locations');
    }
};
