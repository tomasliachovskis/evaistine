<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What a pharmacy's product page says about the product (docs/product-facts.md):
// the page text and labelled attributes as scraped, and the facts a language
// model pulled out of them. One row per store and product URL. Filled on the
// local machine (scrape + extraction), then pushed to production, which only
// stores and links the rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->text('product_url');
            // sha1 of product_url, for the unique key (the URL itself is too long).
            $table->char('url_hash', 40);
            // Resolved from discounts (store + product_url); null until a
            // discount for that URL exists (product-facts:link fills it later).
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // The page's own product name and category (breadcrumb), context
            // for the extraction: the local DB doesn't know new products.
            $table->string('page_name', 500)->nullable();
            $table->string('page_category', 500)->nullable();
            $table->mediumText('source_text')->nullable();
            $table->json('attributes')->nullable();
            // sha1 of source_text + attributes: a changed page resets `facts`.
            $table->char('source_hash', 40);
            $table->json('facts')->nullable();
            $table->string('extracted_with', 64)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('extracted_at')->nullable();
            // Local only: when product-facts:push last sent this row's facts.
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'url_hash']);
            $table->index(['product_id']);
            $table->index(['extracted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_facts');
    }
};
