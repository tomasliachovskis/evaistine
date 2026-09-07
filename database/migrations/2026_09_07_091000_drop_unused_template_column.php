<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('template');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('template')->nullable()->after('name');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->text('template')->nullable()->after('name');
        });
    }
};
