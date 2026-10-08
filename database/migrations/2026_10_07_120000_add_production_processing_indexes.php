<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// superakcijos' 2025_10_28_112130_add_process_discounts_performance_indexes
// has every index commented out as "Already exists": they were created by
// hand on its production database, so a fresh database never gets them.
// Without the product_url index, discounts:process' duplicate-row DELETE
// (a self-join on discount_temp.product_url) runs for many minutes on a
// full pharmacy scrape (~70k rows) and holds locks that make the scrapers'
// own inserts fail with lock-wait timeouts (found 2026-10-07). Same indexes
// as that migration describes, each created only if missing.
return new class extends Migration
{
    private const INDEXES = [
        ['discount_temp', 'discount_temp_product_url_prefix', 'product_url(191)'],
        ['discount_temp', 'discount_temp_processed_category_index', 'processed, category'],
        ['discount_temp', 'discount_temp_store_index', 'store'],
        ['stores', 'stores_name_index', 'name'],
        ['discounts', 'discounts_product_store_dates_index', 'product_id, store_id, start_at, end_at'],
        ['category_mappers', 'category_mappers_store_store_category_prefix', 'store, store_category(100)'],
        ['categories', 'categories_name_index', 'name'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $name, $columns]) {
            if (!$this->hasIndex($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$columns})");
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $name]) {
            if ($this->hasIndex($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]) !== [];
    }
};
