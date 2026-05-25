<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('flyer_pdf_url')->nullable()->after('meta_description');
            $table->string('flyer_image_url')->nullable()->after('flyer_pdf_url');
            $table->date('flyer_valid_from')->nullable()->after('flyer_image_url');
            $table->date('flyer_valid_to')->nullable()->after('flyer_valid_from');
            $table->timestamp('flyer_updated_at')->nullable()->after('flyer_valid_to');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'flyer_pdf_url',
                'flyer_image_url',
                'flyer_valid_from',
                'flyer_valid_to',
                'flyer_updated_at',
            ]);
        });
    }
};
