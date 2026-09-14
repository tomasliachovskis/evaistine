<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Adds the 'keyword_teaser' scope used by KeywordPageService::refreshHomeTeasers()
// — the homepage/pigiausios-prekes keyword-page price comparisons, persisted
// the same way price_index already is (write-time batch, cheap read),
// instead of the live per-keyword-page Meilisearch computation this
// replaced (measured too slow cold — same reasoning refreshHomePools()'s own
// docblock already documents for the near-identical mistake made once
// before with HomeKeywordDealPoolBuilder). Reuses the existing item_key
// column (already nullable, already used by price_index for its bucket key)
// for the keyword page's slug — no new column needed.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE curated_deals MODIFY scope ENUM(
            'store_category', 'store_top_offers', 'global_category',
            'home_food', 'home_non_food', 'home_best', 'price_index', 'keyword_teaser'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::table('curated_deals')->where('scope', 'keyword_teaser')->delete();

        DB::statement("ALTER TABLE curated_deals MODIFY scope ENUM(
            'store_category', 'store_top_offers', 'global_category',
            'home_food', 'home_non_food', 'home_best', 'price_index'
        ) NOT NULL");
    }
};
