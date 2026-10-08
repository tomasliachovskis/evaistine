<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Coupons (/kuponai) were a superakcijos feature with no pharmacy use; both
// tables were empty when the feature was removed (2026-10-08).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('coupon_websites');
    }

    public function down(): void
    {
        (require database_path('migrations/2026_09_21_120000_create_coupon_websites_table.php'))->up();
        (require database_path('migrations/2026_09_21_120100_create_coupons_table.php'))->up();
    }
};
