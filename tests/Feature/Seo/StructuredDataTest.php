<?php

namespace Tests\Feature\Seo;

use App\Models\BlogPost;
use App\Models\Discount;
use App\Models\DiscountHistory;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Models\StoreFlyerPage;
use App\Models\StoreLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    public function test_every_main_page_type_emits_only_valid_json_ld(): void
    {
        $seed = $this->seedListing();

        $paths = [
            '/',
            '/akcijos',
            "/akcijos/{$seed['category']->slug}",
            "/akcijos/{$seed['store']->slug}",
            "/akcijos/{$seed['store']->slug}/{$seed['category']->slug}",
            "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}",
            '/parduotuves',
            '/leidiniai',
        ];

        foreach ($paths as $path) {
            $response = $this->get($path);
            $response->assertOk();
            // jsonLd() fails on any block that doesn't decode.
            $this->assertNotEmpty($this->jsonLd($response), "No JSON-LD on {$path}");
        }
    }

    public function test_product_page_has_product_with_live_offer(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $products = $this->jsonLdOfType($this->get($path), 'Product');

        $this->assertCount(1, $products);
        $product = $products[0];
        $this->assertNotEmpty($product['name']);
        $this->assertSame($seed['product']->image_url, $product['image']);
        $this->assertSame(self::ORIGIN.$path, $product['url']);

        $this->assertSame($seed['product']->name, $product['name']);
        $offers = $this->offersOf($product);
        $this->assertNotEmpty($offers);
        foreach ($offers as $offer) {
            $this->assertSame('Offer', $offer['@type']);
            $this->assertSame('EUR', $offer['priceCurrency']);
            $this->assertGreaterThan(0, (float) $offer['price']);
        }
        $this->assertSame('1.29', $offers[0]['price']);
        $this->assertSame('https://schema.org/InStock', $offers[0]['availability']);
    }

    public function test_product_page_lists_every_store_offer(): void
    {
        $seed = $this->seedListing();
        $otherStore = Store::factory()->create(['slug' => 'seo-kita-parduotuve', 'show_discounts_page' => true]);
        Discount::factory()->create([
            'product_id' => $seed['product']->id,
            'store_id' => $otherStore->id,
            'original_price' => 1.99,
            'discounted_price' => 1.49,
            'discount_percent' => 25,
        ]);

        $product = $this->jsonLdOfType($this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"), 'Product')[0];

        $prices = collect($this->offersOf($product))->pluck('price')->sort()->values()->all();
        $this->assertSame(['1.29', '1.49'], $prices);

        $this->assertSame('AggregateOffer', $product['offers']['@type']);
        $this->assertSame('1.29', $product['offers']['lowPrice']);
        $this->assertSame('1.49', $product['offers']['highPrice']);
        $this->assertSame(2, $product['offers']['offerCount']);

        $strikethrough = collect($this->offersOf($product))->firstWhere('price', '1.49')['priceSpecification'];
        $this->assertSame('https://schema.org/StrikethroughPrice', $strikethrough['priceType']);
        $this->assertSame('1.99', $strikethrough['price']);
    }

    public function test_product_schema_carries_gtin_from_ean(): void
    {
        $seed = $this->seedListing();
        $seed['product']->update(['ean' => '4770001234567']);

        $product = $this->jsonLdOfType($this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"), 'Product')[0];

        $this->assertSame('4770001234567', $product['gtin']);
    }

    public function test_expired_product_offer_is_out_of_stock(): void
    {
        $seed = $this->seedListing();
        $seed['discount']->update(['start_at' => now()->subMonth(), 'end_at' => now()->subWeeks(3)]);

        $products = $this->jsonLdOfType($this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"), 'Product');

        $this->assertCount(1, $products);
        $offers = $this->offersOf($products[0]);
        foreach ($offers as $offer) {
            $this->assertSame('https://schema.org/OutOfStock', $offer['availability']);
        }
    }

    public function test_product_without_image_emits_no_product_schema(): void
    {
        $seed = $this->seedListing();
        $seed['product']->update(['image_url' => null]);

        $response = $this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}");

        $response->assertOk();
        $this->assertSame([], $this->jsonLdOfType($response, 'Product'));
    }

    public function test_product_page_breadcrumb_ends_at_the_product(): void
    {
        $seed = $this->seedListing();
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $breadcrumbs = $this->jsonLdOfType($this->get($path), 'BreadcrumbList');

        $this->assertCount(1, $breadcrumbs);
        $items = $breadcrumbs[0]['itemListElement'];
        $this->assertGreaterThanOrEqual(2, count($items));
        $this->assertSame(range(1, count($items)), array_column($items, 'position'));
        $this->assertStringEndsWith($path, end($items)['item']);
        $this->assertContains(url("/akcijos/{$seed['category']->slug}"), array_column($items, 'item'));
    }

    public function test_category_listing_has_breadcrumb_and_item_list_of_its_products(): void
    {
        $seed = $this->seedListing();
        $response = $this->get("/akcijos/{$seed['category']->slug}");

        $breadcrumbs = $this->jsonLdOfType($response, 'BreadcrumbList');
        $this->assertCount(1, $breadcrumbs);
        $this->assertStringEndsWith("/akcijos/{$seed['category']->slug}", end($breadcrumbs[0]['itemListElement'])['item']);

        $lists = $this->jsonLdOfType($response, 'ItemList');
        $this->assertCount(1, $lists);
        $this->assertGreaterThanOrEqual(1, $lists[0]['numberOfItems']);
        $this->assertStringEndsWith(
            "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}",
            $lists[0]['itemListElement'][0]['url']
        );
    }

    public function test_search_breadcrumb_ends_at_the_search_page(): void
    {
        $this->seedListing();

        $breadcrumbs = $this->getJson('/api/search/pienas')->assertOk()->json('breadcrumbs');

        $this->assertSame(['Akcijos', 'Paieška'], array_column($breadcrumbs, 'name'));
        $this->assertSame('akcijos/paieska/pienas', end($breadcrumbs)['slug']);
    }

    public function test_leaflet_hub_has_breadcrumb_and_item_list_of_current_leaflets(): void
    {
        ['store' => $store, 'flyer' => $flyer] = $this->seedLeaflet();
        $expired = $this->createFlyer($store, 'seo-senas-leidinys', now()->subWeeks(3), now()->subWeeks(2));

        $response = $this->get("/leidinys/{$store->slug}");

        $this->assertIndexable($response, "/leidinys/{$store->slug}");

        $breadcrumbs = $this->jsonLdOfType($response, 'BreadcrumbList');
        $this->assertCount(1, $breadcrumbs);
        $items = $breadcrumbs[0]['itemListElement'];
        $this->assertSame([url('/leidiniai'), url("/leidinys/{$store->slug}")], array_column($items, 'item'));

        $lists = $this->jsonLdOfType($response, 'ItemList');
        $this->assertCount(1, $lists);
        $urls = array_column($lists[0]['itemListElement'], 'url');
        $this->assertContains(url("/leidinys/{$store->slug}/{$flyer->slug}"), $urls);
        // Only currently valid leaflets, not the expired archive.
        $this->assertNotContains(url("/leidinys/{$store->slug}/{$expired->slug}"), $urls);
    }

    public function test_leaflet_page_has_breadcrumb_and_cover_image(): void
    {
        ['store' => $store, 'flyer' => $flyer] = $this->seedLeaflet();
        $path = "/leidinys/{$store->slug}/{$flyer->slug}";

        $response = $this->get($path);

        $this->assertIndexable($response, $path);

        $breadcrumbs = $this->jsonLdOfType($response, 'BreadcrumbList');
        $this->assertCount(1, $breadcrumbs);
        $this->assertSame(
            [url('/leidiniai'), url("/leidinys/{$store->slug}"), url($path)],
            array_column($breadcrumbs[0]['itemListElement'], 'item')
        );

        $images = $this->jsonLdOfType($response, 'ImageObject');
        $this->assertCount(1, $images);
        $this->assertStringEndsWith('/storage/flyers/pages/seo/page-1.webp', $images[0]['contentUrl']);
        $this->assertNotEmpty($images[0]['name']);
        $this->assertTrue($images[0]['representativeOfPage']);
    }

    public function test_leaflet_page_title_leads_with_bare_store_name(): void
    {
        $store = Store::factory()->create(['name' => 'Seo Leidiniai', 'slug' => 'seo-leidiniai']);
        $this->createFlyer($store, 'seo-ne-maisto', '2026-09-21', '2026-09-27', [
            'title' => 'NE MAISTO PREKIŲ PASIŪLYMAI',
            'issue_number' => 39,
        ]);

        $response = $this->get("/leidinys/{$store->slug}/seo-ne-maisto");

        $this->assertSame(
            'Seo Leidiniai NE MAISTO PREKIŲ PASIŪLYMAI Nr.39 – 2026.09.21–2026.09.27 | eVaistinė.lt',
            $this->seoHead($response)['title']
        );
        // H1 keeps the builder's own wording.
        $response->assertSee('Naujas Seo Leidiniai nuolaidų leidinys - NE MAISTO PREKIŲ PASIŪLYMAI Nr.39', false);
    }

    public function test_news_article_has_news_article_schema(): void
    {
        BlogPost::create([
            'title' => 'Seo naujiena apie kainas',
            'slug' => 'seo-naujiena',
            'content' => '<p>Turinys.</p>',
            'meta_description' => 'Trumpas aprašymas.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        [$article] = $this->jsonLdOfType($this->get('/naujienos/seo-naujiena'), 'NewsArticle');

        $this->assertSame('Seo naujiena apie kainas', $article['headline']);
        $this->assertSame(self::ORIGIN.'/naujienos/seo-naujiena', $article['mainEntityOfPage']['@id']);
        $this->assertNotEmpty($article['datePublished']);
        $this->assertSame('eVaistinė.lt', $article['publisher']['name']);
    }

    public function test_store_city_page_lists_locations_with_address_and_hours(): void
    {
        $store = Store::factory()->create(['name' => 'Seo Tinklas', 'slug' => 'seo-tinklas']);
        StoreLocation::create([
            'store_id' => $store->id,
            'city' => 'Vilnius',
            'address' => 'Seo g. 1',
            'slug' => 'seo-g-1',
            'lat' => 54.68,
            'lng' => 25.28,
            'phone' => '+37060000000',
            'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], '08:00-22:00') + ['sunday' => '09:00-20:00'],
            'is_active' => true,
        ]);

        [$list] = $this->jsonLdOfType($this->get('/parduotuves/seo-tinklas/vilnius'), 'ItemList');
        $location = $list['itemListElement'][0]['item'];

        $this->assertSame('Store', $location['@type']);
        $this->assertSame('Seo g. 1', $location['address']['streetAddress']);
        $this->assertSame('Vilnius', $location['address']['addressLocality']);
        $this->assertCount(2, $location['openingHoursSpecification']);
        $this->assertSame(['Sunday'], $location['openingHoursSpecification'][1]['dayOfWeek']);
    }

    public function test_inactive_or_unprocessed_leaflet_redirects_to_store_hub(): void
    {
        ['store' => $store] = $this->seedLeaflet();
        $this->createFlyer($store, 'seo-isjungtas', now()->subDay(), now()->addWeek(), ['is_active' => false]);
        $this->createFlyer($store, 'seo-neapdorotas', now()->subDay(), now()->addWeek(), ['processing_status' => StoreFlyer::STATUS_PENDING]);

        $this->get("/leidinys/{$store->slug}/seo-isjungtas")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
        $this->get("/leidinys/{$store->slug}/seo-neapdorotas")->assertStatus(301)->assertRedirect("/leidinys/{$store->slug}");
    }

    public function test_homepage_has_organization_and_site_search(): void
    {
        $this->seedListing();
        $response = $this->get('/');

        $this->assertCount(1, $this->jsonLdOfType($response, 'Organization'));

        $sites = $this->jsonLdOfType($response, 'WebSite');
        $this->assertCount(1, $sites);
        $this->assertSame(
            'https://evaistine.lt/akcijos/paieska/{search_term_string}',
            $sites[0]['potentialAction']['target']['urlTemplate']
        );
    }

    public function test_organization_schema_is_only_on_the_homepage(): void
    {
        $seed = $this->seedListing();

        $response = $this->get("/akcijos/{$seed['category']->slug}");

        $this->assertSame([], $this->jsonLdOfType($response, 'Organization'));
        $this->assertSame([], $this->jsonLdOfType($response, 'WebSite'));
    }

    /**
     * A leaflet-only store with one current, processed leaflet.
     */
    public function test_product_faq_states_real_price_history_facts(): void
    {
        $seed = $this->seedListing();
        foreach ([[1.49, 1.99, 60], [1.19, 1.99, 40], [1.79, 1.79, 20]] as [$price, $original, $daysAgo]) {
            DiscountHistory::create([
                'product_id' => $seed['product']->id,
                'store_id' => $seed['store']->id,
                'original_price' => $original,
                'discounted_price' => $price,
                'discount_percent' => round((1 - $price / $original) * 100),
                'start_at' => now()->subDays($daysAgo + 6),
                'end_at' => now()->subDays($daysAgo),
            ]);
        }

        $response = $this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}");

        $answers = collect($this->jsonLdOfType($response, 'FAQPage')[0]['mainEntity'])
            ->pluck('acceptedAnswer.text', 'name');
        $minQuestion = $answers->keys()->first(fn ($q) => str_contains($q, 'mažiausia kaina'));
        $this->assertNotNull($minQuestion);
        $this->assertStringContainsString('1,19 € (Seo Parduotuve), '.now()->subDays(46)->format('Y.m.d'), $answers[$minQuestion]);
        $this->assertStringContainsString('Vidutinė kaina per tą laikotarpį – 1,44 €', $answers[$minQuestion]);
        $frequency = $answers->first(fn ($a, $q) => str_contains($q, 'būna akcijoje'));
        $this->assertStringContainsString('akcijoje buvo 2 kartus', $frequency);
        // The current 1.29 € is above the 1.19 € low: no "lowest" claim.
        $this->assertStringNotContainsString('Mažiausia kaina per 90 d.', $this->seoHead($response)['description']);
    }

    public function test_product_meta_description_flags_a_90_day_low(): void
    {
        $seed = $this->seedListing();
        foreach ([1.49, 1.59, 1.69] as $i => $price) {
            DiscountHistory::create([
                'product_id' => $seed['product']->id,
                'store_id' => $seed['store']->id,
                'original_price' => 1.99,
                'discounted_price' => $price,
                'discount_percent' => round((1 - $price / 1.99) * 100),
                'start_at' => now()->subDays(30 * $i + 20),
                'end_at' => now()->subDays(30 * $i + 14),
            ]);
        }

        $response = $this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}");

        $this->assertStringContainsString('Mažiausia kaina per 90 d.', $this->seoHead($response)['description']);
    }

    public function test_product_offer_carries_stated_unit_price_only(): void
    {
        $seed = $this->seedListing();
        $seed['discount']->update(['unit_price' => 1.29, 'unit_price_basis' => 'l', 'unit_price_estimated' => false]);
        $path = "/akcijos/{$seed['category']->slug}/{$seed['product']->slug}";

        $response = $this->get($path);
        $specs = $this->offersOf($this->jsonLdOfType($response, 'Product')[0])[0]['priceSpecification'];
        $unit = collect($specs)->first(fn ($s) => isset($s['referenceQuantity']));
        $this->assertSame('1.29', $unit['price']);
        $this->assertSame('LTR', $unit['referenceQuantity']['unitCode']);
        $response->assertSee('1,29 €/l');
    }

    public function test_estimated_unit_price_stays_out_of_product_schema(): void
    {
        $seed = $this->seedListing();
        $seed['discount']->update(['unit_price' => 1.29, 'unit_price_basis' => 'l', 'unit_price_estimated' => true]);

        $offer = $this->offersOf($this->jsonLdOfType($this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"), 'Product')[0])[0];
        $specs = $offer['priceSpecification'] ?? [];

        $this->assertNull(collect(isset($specs['@type']) ? [$specs] : $specs)->first(fn ($s) => isset($s['referenceQuantity'])));
    }

    public function test_leaflet_page_lists_its_own_offers_as_text(): void
    {
        ['store' => $store, 'flyer' => $flyer] = $this->seedLeaflet();
        $sibling = $this->createFlyer($store, 'seo-kitas-leidinys', now()->subDay(), now()->addWeek());
        $category = \App\Models\Category::factory()->create(['slug' => 'seo-leidinio-kategorija']);
        $make = function (string $name, ?int $flyerId) use ($store, $category) {
            $product = \App\Models\Product::factory()->create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'category_id' => $category->id,
                'image_url' => 'https://cdn.example.com/x.jpg',
            ]);
            Discount::factory()->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'store_flyer_id' => $flyerId,
                'discounted_price' => 2.49,
                'original_price' => 3.49,
                'discount_percent' => 29,
            ]);
        };
        $make('Seo leidinio kava 500 g', $flyer->id);
        $make('Seo kito leidinio arbata', $sibling->id);

        $response = $this->get("/leidinys/{$store->slug}/{$flyer->slug}");

        $response->assertSee('Šio leidinio akcijos');
        $response->assertSee('Seo leidinio kava 500 g');
        $response->assertDontSee('Seo kito leidinio arbata');
        $this->assertStringContainsString('2 psl., 1 nuolaida', $this->seoHead($response)['description']);
        $lists = $this->jsonLdOfType($response, 'ItemList');
        $this->assertCount(1, $lists);
        $this->assertSame('Seo leidinio kava 500 g', $lists[0]['itemListElement'][0]['name']);
        $this->assertSame('2.49', $lists[0]['itemListElement'][0]['item']['offers']['price']);
    }

    private function seedLeaflet(): array
    {
        $store = Store::factory()->create(['name' => 'Seo Leidiniai', 'slug' => 'seo-leidiniai']);
        $flyer = $this->createFlyer($store, 'seo-savaites-leidinys', now()->subDay(), now()->addWeek());

        return compact('store', 'flyer');
    }

    private function createFlyer(Store $store, string $slug, $validFrom, $validTo, array $attributes = []): StoreFlyer
    {
        $flyer = StoreFlyer::create(array_merge([
            'store_id' => $store->id,
            'slug' => $slug,
            'title' => 'Savaitės leidinys',
            'image_url' => '/storage/flyers/pages/seo/page-1.webp',
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ], $attributes));

        foreach ([1, 2] as $pageNumber) {
            StoreFlyerPage::create([
                'store_flyer_id' => $flyer->id,
                'page_number' => $pageNumber,
                'sort_order' => $pageNumber,
                'image_url' => "/storage/flyers/pages/seo/page-{$pageNumber}.webp",
            ]);
        }

        return $flyer;
    }
}
