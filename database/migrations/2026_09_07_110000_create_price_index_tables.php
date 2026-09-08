<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_index_snapshots', function (Blueprint $table) {
            $table->id();
            // Monday of the ISO week this snapshot represents — unique so
            // re-running the weekly command is idempotent (see PriceIndexService).
            $table->date('week_start')->unique();
            $table->timestamps();
        });

        Schema::create('price_index_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_index_snapshot_id')->constrained()->cascadeOnDelete();
            // Basket composition is chosen dynamically per week (whichever
            // candidate items have the best cross-store coverage that week —
            // see PriceIndexService::CANDIDATE_ITEMS), so the item itself is
            // data on the row, not a foreign key to a fixed items table.
            $table->string('item_key');
            $table->string('item_name');
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->unique(['price_index_snapshot_id', 'item_key', 'store_id'], 'price_index_entries_unique_item_store');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_index_entries');
        Schema::dropIfExists('price_index_snapshots');
    }
};
