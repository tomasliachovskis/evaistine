<?php

use App\Models\KeywordPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $categoryFixes = [
            'ferrero-rocher' => ['bakaleja'],
            'galviju-liezuviai' => ['gyvunu-prekes'],
            'kiausiniai' => [],
            'skumbre' => ['bakaleja'],
        ];

        foreach ($categoryFixes as $slug => $categorySlugs) {
            KeywordPage::query()
                ->where('slug', $slug)
                ->update(['category_slugs' => $categorySlugs === [] ? null : $categorySlugs]);
        }

        KeywordPage::query()
            ->whereIn('slug', [
                'frosch',
                'kondensuotas-pienas',
                'lagaminams',
                'mokyklines-prekes',
                'tigrines-krevetes',
            ])
            ->update(['is_published' => false]);
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
