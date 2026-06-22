<?php

use App\Models\StoreFlyer;
use App\Services\StoreFlyerSlugBuilder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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
            $flyer->saveQuietly();
        });
    }

    public function down(): void
    {
    }
};
