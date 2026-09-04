<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curated_deals', function (Blueprint $table) {
            $table->id();
            // NULL = cross-store (global_category / home_* scopes).
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->enum('scope', [
                'store_category',
                'store_top_offers',
                'global_category',
                'home_food',
                'home_non_food',
                'home_best',
            ]);
            // Set for store_category / global_category scopes only.
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            $table->float('deal_score')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'scope', 'category_id', 'position'], 'curated_deals_bucket_position_unique');
            $table->index(['store_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curated_deals');
    }
};
