<?php

use App\Models\KeywordPage;
use App\Services\KeywordPageCategoryResolver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $resolver = app(KeywordPageCategoryResolver::class);

        KeywordPage::query()
            ->whereNotNull('category_slugs')
            ->each(function (KeywordPage $page) use ($resolver) {
                $resolved = $resolver->resolveListingCategorySlugs((array) ($page->category_slugs ?? []));

                if ($resolved === (array) ($page->category_slugs ?? [])) {
                    return;
                }

                $page->update(['category_slugs' => $resolved !== [] ? $resolved : null]);
            });
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
