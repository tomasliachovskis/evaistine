<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HomeDealPoolService::scoredCandidatesQuery() left-joins a derived table that
// does GROUP BY product_id, store_id (computing MAX(id)) over the whole
// discount_histories table on every call — only single-column indexes existed
// on product_id/store_id, so MySQL couldn't use a tight index scan for that
// grouping and fell back to scanning+sorting all ~163k rows. This is the
// dominant cost behind the ~300 slow (>=3s) php-fpm requests/day traced to
// HomeDealPoolService::bestForCategory() in the slow log.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_histories', function (Blueprint $table) {
            $table->index(['product_id', 'store_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('discount_histories', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'store_id', 'id']);
        });
    }
};
