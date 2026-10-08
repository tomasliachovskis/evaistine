<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\KeywordPage;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    public function test_store_without_offers_page_redirects_to_its_leaflet_hub(): void
    {
        $store = Store::factory()->create(['slug' => 'seo-leidinio-vaistine', 'show_discounts_page' => false]);

        $this->get("/{$store->slug}")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
        $this->get("/{$store->slug}/pieno-produktai")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
    }

    public function test_store_with_offers_page_is_served_directly(): void
    {
        $seed = $this->seedListing();

        $this->get("/{$seed['store']->slug}")->assertOk();
    }

    public function test_keyword_page_wins_over_a_leaflet_only_store_with_the_same_slug(): void
    {
        $this->seedListing();
        Store::factory()->create(['slug' => 'seo-pienas', 'show_discounts_page' => false]);
        KeywordPage::create([
            'slug' => 'seo-pienas',
            'title' => 'Pienas',
            'h1' => 'Pieno akcijos',
            'search_terms' => ['pienas'],
            'is_published' => true,
        ]);

        $this->get('/seo-pienas')->assertOk();
    }

    // Not served, but not a 404 either: without a category it goes to /akcijos
    // (with one, to the category — KeywordPageAlwaysAvailableTest).
    public function test_unpublished_keyword_page_redirects_instead_of_404(): void
    {
        KeywordPage::create([
            'slug' => 'seo-juodrastis',
            'title' => 'Juodraštis',
            'h1' => 'Juodraštis',
            'search_terms' => ['juodrastis'],
            'is_published' => false,
        ]);

        $this->get('/seo-juodrastis')->assertStatus(301)->assertRedirect('/akcijos');
    }

    public function test_uppercase_and_lithuanian_letter_slugs_redirect_to_lowercase_ascii(): void
    {
        $seed = $this->seedListing();
        $store = $seed['store']->slug;
        $category = $seed['category']->slug;

        $this->get('/'.strtoupper($store))->assertStatus(301)->assertRedirect("/{$store}");
        $this->get('/seo-pieno-produktaį?page=2')
            ->assertStatus(301)
            ->assertRedirect("/{$category}?page=2");
    }

    public function test_first_page_query_redirects_to_clean_url_keeping_other_params(): void
    {
        $seed = $this->seedListing();
        $path = "/{$seed['category']->slug}";

        $this->get("{$path}?page=1")->assertStatus(301)->assertRedirect($path);
        $this->get("{$path}?page=1&order=price_min")->assertStatus(301)->assertRedirect("{$path}?order=price_min");
    }

    public function test_product_url_does_not_change_with_its_category(): void
    {
        $seed = $this->seedListing();
        $path = "/p/{$seed['product']->slug}";
        $canonical = fn () => $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('rel="canonical" href="https://evaistine.lt'.$path.'"', $canonical());

        $other = Category::factory()->create(['slug' => 'seo-kita-kategorija']);
        $seed['product']->update(['category_id' => $other->id]);

        $this->assertStringContainsString('rel="canonical" href="https://evaistine.lt'.$path.'"', $canonical());
    }

    public function test_category_and_product_two_segment_path_is_404(): void
    {
        $seed = $this->seedListing();

        $this->get("/{$seed['category']->slug}/{$seed['product']->slug}")->assertNotFound();
    }

    public function test_missing_product_is_404(): void
    {
        $this->get('/p/seo-istrinta-preke')->assertNotFound();
    }

    public function test_uppercase_product_slug_redirects_to_lowercase(): void
    {
        $seed = $this->seedListing();

        $this->get('/p/'.strtoupper($seed['product']->slug))
            ->assertStatus(301)
            ->assertRedirect("/p/{$seed['product']->slug}");
    }

    public function test_real_routes_win_over_flat_listing_slugs(): void
    {
        $this->get('/apie')->assertOk();
        $this->get('/vaistines')->assertOk();
        $this->get('/akcijos')->assertOk();
    }

    public function test_missing_flyer_of_a_known_store_redirects_to_its_leaflet_hub(): void
    {
        $store = Store::factory()->create(['slug' => 'seo-leidiniu-vaistine']);

        $this->get("/leidinys/{$store->slug}/seo-pasibaiges-leidinys")
            ->assertStatus(301)
            ->assertRedirect("/leidinys/{$store->slug}");
    }

    public function test_flyer_of_unknown_store_is_404(): void
    {
        $this->get('/leidinys/seo-nera-vaistines/seo-leidinys')->assertNotFound();
    }

    public function test_unknown_two_segment_path_is_404(): void
    {
        $this->get('/seo-nera-kategorijos/seo-nera-prekes')->assertNotFound();
    }

    public function test_unknown_listing_slug_is_404(): void
    {
        $this->get('/seo-nezinomas-puslapis')->assertNotFound();
    }

    /**
     * @dataProvider legacyRedirectProvider
     */
    public function test_legacy_urls_redirect_permanently(string $from, string $to): void
    {
        $this->get($from)->assertStatus(301)->assertRedirect($to);
    }

    public static function legacyRedirectProvider(): array
    {
        return [
            'contacts' => ['/kontaktai', '/apie#kontaktai'],
            'old homepage' => ['/nauja-pradzia', '/'],
            'store address page' => ['/vaistines/maxima/vilnius/gedimino-pr-1', '/vaistines/maxima/vilnius'],
        ];
    }

    public function test_non_canonical_host_redirects_to_canonical_host_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->get('http://api.evaistine.lt/iki?page=2')
                ->assertStatus(301)
                ->assertRedirect('https://evaistine.lt/iki?page=2');
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
