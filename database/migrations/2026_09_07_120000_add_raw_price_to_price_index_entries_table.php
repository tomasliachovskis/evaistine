<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_index_entries', function (Blueprint $table) {
            // The actual real purchase price of the matched product/pack —
            // 'price' is normalized per kg/l/10vnt for fair store-to-store
            // comparison, which is NOT a real amount anyone pays (e.g. a
            // 0.75l oil bottle's real €5.49 became a synthetic "€7.32/l").
            // Summing normalized values across items with different units
            // (€/l + €/kg + €/kg) into one "basket total" was meaningless —
            // real totals/headline prices must use this raw, actually-payable
            // price instead.
            $table->decimal('raw_price', 8, 2)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('price_index_entries', function (Blueprint $table) {
            $table->dropColumn('raw_price');
        });
    }
};
