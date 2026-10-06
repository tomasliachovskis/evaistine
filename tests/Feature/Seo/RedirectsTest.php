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
        $store = Store::factory()->create(['slug' => 'seo-leidinio-parduotuve', 'show_discounts_page' => false]);

        $this->get("/akcijos/{$store->slug}")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
        $this->get("/akcijos/{$store->slug}/pieno-produktai")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
    }

    public function test_store_with_offers_page_is_served_directly(): void
    {
        $seed = $this->seedListing();

        $this->get("/akcijos/{$seed['store']->slug}")->assertOk();
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

        $this->get('/akcijos/seo-pienas')->assertOk();
    }

    public function test_unpublished_keyword_page_is_not_served(): void
    {
        KeywordPage::create([
            'slug' => 'seo-juodrastis',
            'title' => 'Juodraštis',
            'h1' => 'Juodraštis',
            'search_terms' => ['juodrastis'],
            'is_published' => false,
        ]);

        $this->get('/akcijos/seo-juodrastis')->assertNotFound();
    }

    public function test_uppercase_and_lithuanian_letter_slugs_redirect_to_lowercase_ascii(): void
    {
        $seed = $this->seedListing();
        $store = $seed['store']->slug;
        $category = $seed['category']->slug;

        $this->get('/akcijos/'.strtoupper($store))->assertStatus(301)->assertRedirect("/akcijos/{$store}");
        $this->get('/akcijos/seo-pieno-produktaį?page=2')
            ->assertStatus(301)
            ->assertRedirect("/akcijos/{$category}?page=2");
    }

    public function test_first_page_query_redirects_to_clean_url_keeping_other_params(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}";

        $this->get("{$path}?page=1")->assertStatus(301)->assertRedirect($path);
        $this->get("{$path}?page=1&order=price_min")->assertStatus(301)->assertRedirect("{$path}?order=price_min");
    }

    public function test_product_under_wrong_category_redirects_to_its_real_category(): void
    {
        $seed = $this->seedListing();
        $other = Category::factory()->create(['slug' => 'seo-kita-kategorija']);

        $this->get("/akcijos/{$other->slug}/{$seed['product']->slug}")
            ->assertStatus(301)
            ->assertRedirect("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}");
    }

    public function test_missing_product_redirects_to_its_category(): void
    {
        $seed = $this->seedListing();

        $this->get("/akcijos/{$seed['category']->slug}/seo-istrinta-preke")
            ->assertStatus(301)
            ->assertRedirect("/akcijos/{$seed['category']->slug}");
    }

    public function test_missing_product_under_retired_category_redirects_to_its_successor(): void
    {
        $this->get('/akcijos/alkoholiniai-ir-nealkoholiniai-gerimai/seo-istrinta-preke')
            ->assertStatus(301)
            ->assertRedirect('/akcijos/nealkoholiniai-gerimai');
    }

    public function test_missing_flyer_of_a_known_store_redirects_to_its_leaflet_hub(): void
    {
        $store = Store::factory()->create(['slug' => 'seo-leidiniu-parduotuve']);

        $this->get("/leidinys/{$store->slug}/seo-pasibaiges-leidinys")
            ->assertStatus(301)
            ->assertRedirect("/leidinys/{$store->slug}");
    }

    public function test_flyer_of_unknown_store_is_404(): void
    {
        $this->get('/leidinys/seo-nera-parduotuves/seo-leidinys')->assertNotFound();
    }

    public function test_missing_product_under_unknown_category_is_404(): void
    {
        $this->get('/akcijos/seo-nera-kategorijos/seo-nera-prekes')->assertNotFound();
    }

    public function test_unknown_listing_slug_is_404(): void
    {
        $this->get('/akcijos/seo-nezinomas-puslapis')->assertNotFound();
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
            'split drinks category' => ['/akcijos/alkoholiniai-ir-nealkoholiniai-gerimai', '/akcijos/nealkoholiniai-gerimai'],
            'split drinks category under a store' => ['/akcijos/iki/alkoholiniai-ir-nealkoholiniai-gerimai', '/akcijos/iki/nealkoholiniai-gerimai'],
            'store address page' => ['/parduotuves/maxima/vilnius/gedimino-pr-1', '/parduotuves/maxima/vilnius'],
        ];
    }

    public function test_non_canonical_host_redirects_to_canonical_host_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->get('http://api.evaistine.lt/akcijos/iki?page=2')
                ->assertStatus(301)
                ->assertRedirect('https://evaistine.lt/akcijos/iki?page=2');
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
