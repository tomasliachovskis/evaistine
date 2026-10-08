<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keyword pages hid their products below 10 offers (the publish threshold of
// the importer was stored as the display threshold). A price comparison page
// with 3 products is still useful, so every page shows its offers from the
// first one; the empty state is only for none at all.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('keyword_pages')->update(['min_active_offers' => 1]);
    }

    public function down(): void
    {
        // Not restored: 10 hid real products.
    }
};
