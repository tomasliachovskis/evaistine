<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('coupon_websites')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('type', ['code', 'deal']);
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('discount_label')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('target_url')->nullable();
            $table->boolean('is_exclusive')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->text('editor_tip')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index('website_id');
            $table->index('category_id');
            $table->index(['is_active', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
