<?php

namespace Tests\Feature\Seo;

use App\Models\KeywordPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

class HeadTagsTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    public function test_category_listing_is_indexable_with_self_canonical(): void
    {
        $seed = $this->seedListing();

        $this->assertIndexable($this->get("/akcijos/{$seed['category']->slug}"), "/akcijos/{$seed['category']->slug}");
    }

    public function test_store_listing_is_indexable_with_self_canonical(): void
    {
        $seed = $this->seedListing();

        $head = $this->assertIndexable($this->get("/akcijos/{$seed['store']->slug}"), "/akcijos/{$seed['store']->slug}");
        $this->assertStringContainsString($seed['store']->name, $head['title']);
    }

    public function test_store_category_listing_is_indexable_with_self_canonical(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['store']->slug}/{$seed['category']->slug}";

        $this->assertIndexable($this->get($path), $path);
    }

    public function test_product_page_is_indexable_under_its_category(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $this->assertIndexable($this->get($path), $path);
    }

    public function test_expired_product_page_stays_indexable(): void
    {
        $seed = $this->seedListing();
        $seed['discount']->update(['start_at' => now()->subMonth(), 'end_at' => now()->subWeeks(3)]);
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $this->assertIndexable($this->get($path), $path);
    }

    public function test_product_page_canonical_ignores_query_string(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $this->assertIndexable($this->get("{$path}?utm_source=facebook&fbclid=abc"), $path);
    }

    public function test_published_keyword_page_is_indexable(): void
    {
        $this->seedListing();
        KeywordPage::create([
            'slug' => 'seo-pienas',
            'title' => 'Pienas',
            'h1' => 'Pieno akcijos',
            'meta_title' => 'Pieno akcijos šią savaitę',
            'meta_description' => 'Visos pieno akcijos vienoje vietoje.',
            'search_terms' => ['pienas'],
            'min_active_offers' => 1,
            'is_published' => true,
        ]);

        $this->assertIndexable($this->get('/akcijos/seo-pienas'), '/akcijos/seo-pienas');
    }

    public function test_store_directory_is_indexable(): void
    {
        $this->assertIndexable($this->get('/parduotuves'), '/parduotuves');
    }

    public function test_leaflets_index_is_indexable(): void
    {
        $this->assertIndexable($this->get('/leidiniai'), '/leidiniai');
    }

    public function test_second_page_keeps_its_own_canonical_but_is_noindex(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}";

        $head = $this->seoHead($this->get("{$path}?page=2"));

        $this->assertSame(self::ORIGIN."{$path}?page=2", $head['canonical']);
        $this->assertSame('noindex, follow', $head['robots']);
    }

    public function test_filtered_listing_is_noindex_with_clean_canonical(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}";

        $head = $this->seoHead($this->get("{$path}?store={$seed['store']->slug}&utm_source=facebook"));

        $this->assertSame(self::ORIGIN.$path, $head['canonical']);
        $this->assertSame(self::NOINDEX, $head['robots']);
    }

    public function test_sorted_listing_is_noindex(): void
    {
        $seed = $this->seedListing();

        $this->assertNoindex($this->get("/akcijos/{$seed['category']->slug}?order=price_min"));
    }

    public function test_search_results_are_noindex(): void
    {
        $this->seedListing();

        $response = $this->get('/akcijos/paieska/pienas');

        $response->assertOk();
        $this->assertSame(self::NOINDEX, $this->seoHead($response)['robots']);
    }

    public function test_search_form_is_noindex(): void
    {
        $response = $this->get('/akcijos/paieska');

        $response->assertOk();
        $this->assertNoindex($response);
    }

    public function test_cheapest_products_page_is_indexable(): void
    {
        // Deliberately no seeded discounts: the page used to 500 when there
        // was no freshness date to show.
        $response = $this->get('/pigiausios-prekes');

        $response->assertOk();
        $this->assertSame('index, follow', $this->seoHead($response)['robots']);
    }

    public function test_not_found_page_is_noindex(): void
    {
        $response = $this->get('/akcijos/seo-tokio-puslapio-nera');

        $response->assertNotFound();
        $this->assertNoindex($response);
    }

    public function test_pages_carry_open_graph_tags_matching_the_canonical(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:url" content="'.self::ORIGIN.$path.'">', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        // Product pages share their own product photo, not the site logo.
        $this->assertStringContainsString('<meta property="og:image" content="https://cdn.example.com/seo-pienas.jpg">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
    }

    public function test_meta_description_is_trimmed_to_snippet_length(): void
    {
        $seed = $this->seedListing();
        $html = $this->get("/akcijos/{$seed['category']->slug}")->assertOk()->getContent();

        preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($m[1] ?? '')));
    }
}
