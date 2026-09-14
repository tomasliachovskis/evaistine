<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enums need a raw MODIFY COLUMN — Blueprint::enum() via
        // change() can't append a value to an existing column.
        DB::statement("ALTER TABLE curated_deals MODIFY scope ENUM(
            'store_category', 'store_top_offers', 'global_category',
            'home_food', 'home_non_food', 'home_best', 'price_index'
        ) NOT NULL");

        Schema::table('curated_deals', function (Blueprint $table) {
            // Set for the price_index scope only — PriceIndexService's
            // generic_products.slug this row is a match for (e.g. "kava").
            // The bucket_position unique key below is null-safe for every
            // other scope since MySQL treats NULLs as distinct in a unique
            // index, so this doesn't loosen uniqueness for existing rows.
            $table->string('item_key')->nullable()->after('category_id');
        });

        Schema::table('curated_deals', function (Blueprint $table) {
            $table->dropUnique('curated_deals_bucket_position_unique');
            $table->unique(['store_id', 'scope', 'category_id', 'item_key', 'position'], 'curated_deals_bucket_position_unique');
        });
    }

    public function down(): void
    {
        Schema::table('curated_deals', function (Blueprint $table) {
            $table->dropUnique('curated_deals_bucket_position_unique');
        });

        DB::table('curated_deals')->where('scope', 'price_index')->delete();

        Schema::table('curated_deals', function (Blueprint $table) {
            $table->dropColumn('item_key');
        });

        Schema::table('curated_deals', function (Blueprint $table) {
            $table->unique(['store_id', 'scope', 'category_id', 'position'], 'curated_deals_bucket_position_unique');
        });

        DB::statement("ALTER TABLE curated_deals MODIFY scope ENUM(
            'store_category', 'store_top_offers', 'global_category',
            'home_food', 'home_non_food', 'home_best'
        ) NOT NULL");
    }
};
