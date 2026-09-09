<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            $table->timestamp('discounts_processed_at')->nullable()->after('processing_error');
            $table->json('discounts_retry_state')->nullable()->after('discounts_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            $table->dropColumn(['discounts_processed_at', 'discounts_retry_state']);
        });
    }
};
