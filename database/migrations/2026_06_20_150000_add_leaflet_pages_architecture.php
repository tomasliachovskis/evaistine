<?php

use App\Models\StoreFlyer;
use App\Services\StoreFlyerSlugBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_flyers', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->string('processing_status')->default('pending')->after('source');
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->unique(['store_id', 'slug']);
        });

        Schema::create('store_flyer_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_flyer_id')->constrained('store_flyers')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('image_url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_flyer_id', 'page_number']);
        });

        $slugBuilder = app(StoreFlyerSlugBuilder::class);

        StoreFlyer::query()->with('store')->orderBy('id')->each(function (StoreFlyer $flyer) use ($slugBuilder) {
            if (!$flyer->store || !$flyer->valid_from || !$flyer->valid_to) {
                return;
            }

            $titleForSlug = $flyer->title
                ?: ($flyer->catalog_name ?: $flyer->store->name . ' akciju leidinys');
            $slug = $slugBuilder->build(
                $flyer->store,
                $titleForSlug,
                $flyer->valid_from->format('Y-m-d'),
                $flyer->valid_to->format('Y-m-d'),
                $flyer->id
            );

            $flyer->slug = $slug;
            $flyer->view_url = "/leidinys/{$flyer->store->slug}/{$slug}";
            $flyer->processing_status = $flyer->image_url
                ? StoreFlyer::STATUS_READY
                : ($flyer->pdf_url ? StoreFlyer::STATUS_PENDING : StoreFlyer::STATUS_READY);
            $flyer->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_flyer_pages');

        Schema::table('store_flyers', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'slug']);
            $table->dropColumn(['slug', 'processing_status', 'processing_error']);
        });
    }
};
