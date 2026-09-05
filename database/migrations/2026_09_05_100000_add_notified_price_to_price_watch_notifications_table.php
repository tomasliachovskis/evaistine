<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_watch_notifications', function (Blueprint $table) {
            // Snapshot of the price actually shown to the user for this
            // product at send time — kept here (not looked up from
            // `discounts` later) because the Discount row itself can be
            // gone by then (discounts:archive-expired, or cascade delete on
            // this table's own discount_id FK). NotifyPriceWatchers compares
            // a newly pending discount's price against this to decide
            // whether the product got genuinely cheaper (bypasses the
            // 48h rate limit) or not (still rate-limited).
            $table->decimal('notified_price', 10, 2)->nullable()->after('discount_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_watch_notifications', function (Blueprint $table) {
            $table->dropColumn('notified_price');
        });
    }
};
