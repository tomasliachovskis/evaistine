<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            // Tracks the real Google News story this post was generated from —
            // lets the generator skip stories it has already written about, and
            // gives every generated article a citable original source.
            $table->string('source_url', 2048)->nullable()->after('content');
            $table->string('source_name')->nullable()->after('source_url');
            $table->timestamp('source_published_at')->nullable()->after('source_name');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'source_name', 'source_published_at']);
        });
    }
};
