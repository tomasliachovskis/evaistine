<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Which flyer a Gemini-extracted discount came from, so the flyer page
    // can list its offers as text (the page itself is only images). Matching
    // by store + dates isn't reliable: a store often has several flyers with
    // the same validity window. Null for e-shop scraper rows.
    public function up(): void
    {
        foreach (['discount_temp', 'discounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('store_flyer_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['discount_temp', 'discounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['store_flyer_id']);
                $table->dropColumn('store_flyer_id');
            });
        }
    }
};
