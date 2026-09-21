<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A coupon "website" is deliberately its own table, not App\Models\Store —
// kuplio's coupon merchants (AliExpress, Booking.com, Notino, Samsung, ...)
// are mostly online-only retailers unrelated to the scraped-discounts
// `stores` table (physical/product-catalog chains with their own
// StoreRules/ProcessDiscounts/scraper wiring). Keeping coupons on a
// separate one-to-many parent avoids polluting that table and its
// onboarding checklist for a completely different content type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_websites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('url')->nullable();
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_websites');
    }
};
