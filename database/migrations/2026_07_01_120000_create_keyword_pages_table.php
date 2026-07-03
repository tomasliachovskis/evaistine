<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('h1');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('search_terms');
            $table->json('category_slugs')->nullable();
            $table->json('exclude_terms')->nullable();
            $table->longText('intro_html')->nullable();
            $table->json('tips')->nullable();
            $table->json('faq')->nullable();
            $table->json('related_slugs')->nullable();
            $table->unsignedSmallInteger('min_active_offers')->default(1);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_pages');
    }
};
