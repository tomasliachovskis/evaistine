<?php

use App\Models\KeywordPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        KeywordPage::query()
            ->whereIn('slug', ['kava', 'paulig', 'lavazza', 'dolce-gusto', 'dallmayr'])
            ->update(['category_slugs' => json_encode(['gerimai-kava-arbata'])]);
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
