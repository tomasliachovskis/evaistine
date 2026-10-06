<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Store;
use App\Rules\FreeTopLevelSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeTopLevelSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_segments_are_taken(): void
    {
        foreach (['apie', 'vaistines', 'akcijos', 'p', 'paieska', 'leidiniai'] as $slug) {
            $this->assertNotNull(FreeTopLevelSlug::conflict($slug), $slug);
        }
    }

    public function test_pharmacy_and_category_slugs_are_taken(): void
    {
        Store::factory()->create(['slug' => 'seo-vaistine']);
        Category::factory()->create(['slug' => 'seo-kategorija']);

        $this->assertSame('vaistinė', FreeTopLevelSlug::conflict('seo-vaistine'));
        $this->assertSame('kategorija', FreeTopLevelSlug::conflict('seo-kategorija'));
    }

    public function test_free_slug_passes(): void
    {
        $this->assertNull(FreeTopLevelSlug::conflict('ibuprofenas'));
    }

    public function test_the_flat_listing_route_is_not_reserved(): void
    {
        $this->assertNotContains('{slug1}', FreeTopLevelSlug::reservedSegments());
    }
}
