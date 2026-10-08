<?php

namespace Tests\Unit;

use App\Services\KeywordPageCategoryResolver;
use Tests\TestCase;

class KeywordPageCategoryResolverTest extends TestCase
{
    public function test_pharmacy_root_slugs_resolve_to_themselves(): void
    {
        $resolver = new KeywordPageCategoryResolver();

        $this->assertSame(
            ['vitaminai-ir-maisto-papildai'],
            $resolver->resolvePrimaryListingCategorySlugs(['vitaminai-ir-maisto-papildai']),
        );
        $this->assertSame(
            ['nereceptiniai-vaistai', 'higiena'],
            $resolver->resolveListingCategorySlugs(['nereceptiniai-vaistai', 'higiena']),
        );
    }

    public function test_grocery_slugs_no_longer_resolve(): void
    {
        $this->assertSame([], (new KeywordPageCategoryResolver())->resolveListingCategorySlugs(['vaisiai-ir-darzoves']));
    }
}
