<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->string('emoji', 16)->nullable()->after('title');
            $table->string('grammar_plural', 64)->nullable()->after('emoji');
            $table->string('grammar_genitive', 64)->nullable()->after('grammar_plural');
            $table->string('grammar_dative', 64)->nullable()->after('grammar_genitive');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_pages', function (Blueprint $table) {
            $table->dropColumn([
                'emoji',
                'grammar_plural',
                'grammar_genitive',
                'grammar_dative',
            ]);
        });
    }
};
