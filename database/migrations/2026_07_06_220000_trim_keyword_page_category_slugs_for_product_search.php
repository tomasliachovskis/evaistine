<?php

use App\Models\KeywordPage;
use App\Services\KeywordPageCategoryResolver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const BEVERAGE_CATEGORY_SLUGS = [
        'gerimai-kava-arbata',
        'alkoholiniai-ir-nealkoholiniai-gerimai',
    ];

    public function up(): void
    {
        $resolver = app(KeywordPageCategoryResolver::class);

        KeywordPage::query()
            ->whereNotNull('category_slugs')
            ->each(function (KeywordPage $page) use ($resolver) {
                $slugs = array_values(array_filter((array) ($page->category_slugs ?? [])));
                if ($slugs === []) {
                    return;
                }

                $primary = $resolver->resolvePrimaryListingCategorySlugs($slugs);
                $primarySlug = $primary[0] ?? null;

                $cleaned = $slugs;

                if ($primarySlug === 'vaisiai-ir-darzoves') {
                    $cleaned = array_values(array_filter(
                        $slugs,
                        fn (string $slug) => !in_array($slug, self::BEVERAGE_CATEGORY_SLUGS, true),
                    ));
                }

                $cleaned = $resolver->resolvePrimaryListingCategorySlugs($cleaned);

                if ($cleaned === (array) ($page->category_slugs ?? [])) {
                    return;
                }

                $page->update(['category_slugs' => $cleaned !== [] ? $cleaned : null]);
            });
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
