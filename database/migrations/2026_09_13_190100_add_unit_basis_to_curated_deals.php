<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curated_deals', function (Blueprint $table) {
            // price_index scope only — the normalized unit basis
            // (kg/l/10vnt) PriceIndexService resolved for this row at write
            // time, so reads never need to re-parse the product name.
            // deal_score (already on this table) doubles as the resolved
            // €/kg-€/l-€/10vnt price for these rows.
            $table->string('unit_basis', 16)->nullable()->after('item_key');
        });
    }

    public function down(): void
    {
        Schema::table('curated_deals', function (Blueprint $table) {
            $table->dropColumn('unit_basis');
        });
    }
};
