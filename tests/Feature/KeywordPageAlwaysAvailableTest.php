<?php

namespace Tests\Feature;

use App\Models\KeywordPage;
use App\Models\KeywordPageProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

// A keyword page URL never 404s: a published page without products shows an
// empty state (200, indexable), a page with even one offer shows it, and an
// unpublished page 301s to its category.
class KeywordPageAlwaysAvailableTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    public function test_published_page_without_products_shows_the_empty_state(): void
    {
        $seed = $this->seedListing();
        $this->createPage('seo-be-prekiu', [$seed['category']->slug]);

        $response = $this->get('/seo-be-prekiu');

        $response->assertOk();
        $response->assertSee('vaistinėse neradome', false);
        $response->assertSee('href="/'.$seed['category']->slug.'"', false);
        $this->assertIndexable($response, '/seo-be-prekiu');
    }

    public function test_page_with_a_single_offer_shows_it(): void
    {
        $seed = $this->seedListing();
        $page = $this->createPage('seo-viena-preke', [$seed['category']->slug]);
        KeywordPageProduct::create(['keyword_page_id' => $page->id, 'product_id' => $seed['product']->id, 'score' => 6]);

        $response = $this->get('/seo-viena-preke');

        $response->assertOk();
        $response->assertSee($seed['product']->name, false);
        $response->assertDontSee('vaistinėse neradome', false);
    }

    public function test_unpublished_page_redirects_to_its_category(): void
    {
        $seed = $this->seedListing();
        $this->createPage('seo-atsauktas', [$seed['category']->slug], published: false);

        $this->get('/seo-atsauktas')
            ->assertStatus(301)
            ->assertRedirect('/'.$seed['category']->slug);
    }

    private function createPage(string $slug, array $categorySlugs, bool $published = true): KeywordPage
    {
        return KeywordPage::create([
            'slug' => $slug,
            'title' => 'Seo raktas',
            'h1' => 'Seo rakto kainos',
            'grammar_genitive' => 'seo rakto',
            'search_terms' => ['seo raktas'],
            'category_slugs' => $categorySlugs,
            'min_active_offers' => 1,
            'is_published' => $published,
        ]);
    }
}
