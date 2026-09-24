<?php

use App\Models\CategoryMapper;
use App\Models\KeywordPage;
use App\Models\Product;
use App\Support\CacheVersion;
use App\Support\EnergyDrinkCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Energy drinks were split across two roots: ~194 on "Gėrimai, kava, arbata"
 * (380, via store mappers like Maxima's whole "Gėrimai/" bucket or Rimi's
 * "Energiniai gerimai") and a handful on "Nealkoholiniai gėrimai" (397, via
 * flyer/GPT rows and ProcessDiscounts' non-alc split heuristic). The
 * monster/redbull/energetiniai-gerimai keyword pages were scoped to 380
 * only, so e.g. "Energiniai gėrimai, MONSTER, 0.5 l" (397) never showed on
 * /akcijos/monster. Consolidates them on 397 — the product list was
 * reviewed on prod before writing this (EnergyDrinkCategory's exclusions
 * cover the non-alc beers, coffee, juice, water and purée it would
 * otherwise catch). EnergyDrinkCategory::apply() keeps future scrapes there.
 */
return new class extends Migration
{
    private const KEYWORD_PAGE_SLUGS = ['monster', 'redbull', 'energetiniai-gerimai'];

    public function up(): void
    {
        $ids = Product::where('category_id', EnergyDrinkCategory::BROAD_DRINKS_ID)
            ->get(['id', 'name'])
            ->filter(fn (Product $p) => EnergyDrinkCategory::isEnergyDrink($p->name))
            ->pluck('id');

        foreach ($ids->chunk(500) as $chunk) {
            Product::whereIn('id', $chunk)->update(['category_id' => EnergyDrinkCategory::NON_ALCOHOLIC_ID]);
        }

        CategoryMapper::where('category_id', EnergyDrinkCategory::BROAD_DRINKS_ID)
            ->where('store_category', 'like', '%Energiniai gerimai%')
            ->update(['category_id' => EnergyDrinkCategory::NON_ALCOHOLIC_ID]);

        KeywordPage::whereIn('slug', self::KEYWORD_PAGE_SLUGS)
            ->update(['category_slugs' => json_encode(['nealkoholiniai-gerimai'])]);

        CacheVersion::bump('discounts');
        CacheVersion::bump('keywords');
    }

    // Products aren't moved back: which ones came from 380 isn't recorded,
    // and 397 is the correct home either way.
    public function down(): void
    {
        CategoryMapper::where('category_id', EnergyDrinkCategory::NON_ALCOHOLIC_ID)
            ->where('store_category', 'like', '%Energiniai gerimai%')
            ->update(['category_id' => EnergyDrinkCategory::BROAD_DRINKS_ID]);

        KeywordPage::whereIn('slug', self::KEYWORD_PAGE_SLUGS)
            ->update(['category_slugs' => json_encode(['gerimai-kava-arbata'])]);
    }
};
