<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CollectionHealthService reads the last 15 days of discount_temp
        // every time /admin/collection-health refreshes; without this it
        // scans the whole table.
        Schema::table('discount_temp', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('discount_temp', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
