<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Precomputed product -> keyword page mapping (e.g. one Jacobs ground-coffee
// product -> "Kava" and "Malta kava"), built once by
// keywords:map-products instead of scanned live on every product-page
// request. Mirrors this codebase's existing convention (curated_deals,
// category_mappers): write-time batch job, cheap read at request time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_page_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Match specificity (currently: matched search-term/brand string
            // length) — longer/more specific matches rank first when a
            // product page shows only its top few related keyword pages.
            $table->unsignedInteger('score');
            $table->timestamps();

            $table->unique(['keyword_page_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_page_products');
    }
};
