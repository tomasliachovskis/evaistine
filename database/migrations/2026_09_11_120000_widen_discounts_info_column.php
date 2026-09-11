<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// discount_temp.info is TEXT (unlimited), but discounts.info was only
// varchar(255) — a real, longer product description (seen live: a vacuum
// cleaner's feature list, ~350 chars) threw "Data too long for column
// 'info'" on insert, which (uncaught) aborted the entire discounts:process
// run for that store, not just that one row. Widen to match discount_temp.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->text('info')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('info', 255)->nullable()->change();
        });
    }
};
