<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['scrape', 'process']);
            $table->string('store');
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('items_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['store', 'type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_runs');
    }
};
