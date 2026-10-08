<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Unused tables, dropped with the owner's approval (2026-10-08):
// - generic_products (+ products.generic_product_id): superakcijos' grocery
//   commodity groups ("bananai", "pienas"), empty here, no seeder.
// - personal_access_tokens: Sanctum API tokens for the old Next.js app,
//   whose token API and the sanctum package were removed the same day.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'generic_product_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('generic_product_id');
            });
        }

        Schema::dropIfExists('generic_products');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function down(): void
    {
        (require database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_08_25_090000_create_generic_products_table.php'))->up();
        (require database_path('migrations/2026_08_25_100000_add_search_terms_to_generic_products_table.php'))->up();
    }
};
