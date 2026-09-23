<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * price-watch:notify decides per product + last notified price (see
     * NotifyPriceWatchers), so this table is the user's notification
     * history. discount_id used to cascade-delete it: Rimi/Maxima re-create
     * most Discount rows on nearly every scrape, and deleting the old row
     * wiped the history with it, so the next run saw the product as never
     * emailed and sent the same price again. The row now survives with
     * discount_id = null.
     */
    public function up(): void
    {
        Schema::table('price_watch_notifications', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
        });

        Schema::table('price_watch_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_id')->nullable()->change();
            $table->foreign('discount_id')->references('id')->on('discounts')->nullOnDelete();
            // The per-product lookup in NotifyPriceWatchers.
            $table->index(['user_id', 'product_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('price_watch_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'product_id', 'sent_at']);
            $table->dropForeign(['discount_id']);
        });

        \Illuminate\Support\Facades\DB::table('price_watch_notifications')->whereNull('discount_id')->delete();

        Schema::table('price_watch_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_id')->nullable(false)->change();
            $table->foreign('discount_id')->references('id')->on('discounts')->cascadeOnDelete();
        });
    }
};
