<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_category_descriptions', function (Blueprint $table) {
            $table->text('intro_html')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_category_descriptions', function (Blueprint $table) {
            $table->dropColumn('intro_html');
        });
    }
};
