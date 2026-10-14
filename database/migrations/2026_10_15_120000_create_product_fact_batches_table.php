<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gemini Batch API jobs for product facts (docs/product-facts.md): half the
// price of one call per page, results within 24 h. product-facts:batch-submit
// creates a row and claims its product_facts rows (batch_id), and
// product-facts:batch-collect fills their facts when the job is done. Local
// only in practice; production gets the same schema but never a batch.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fact_batches', function (Blueprint $table) {
            $table->id();
            // Gemini's job name, "batches/...".
            $table->string('name')->unique();
            $table->string('model', 64);
            $table->string('state', 32);
            $table->unsignedInteger('row_count');
            // The uploaded JSONL, "files/...", deleted once collected.
            $table->string('input_file')->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['finished_at']);
        });

        Schema::table('product_facts', function (Blueprint $table) {
            // Set while the row waits in an open batch: the sync extract and
            // the next submit skip it.
            $table->foreignId('batch_id')->nullable()->after('extracted_with')
                ->constrained('product_fact_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_facts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('product_fact_batches');
    }
};
