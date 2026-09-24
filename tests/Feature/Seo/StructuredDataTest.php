<?php

namespace Tests\Feature\Seo;

use App\Models\Discount;
use App\Models\Store;
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

        $offers = isset($product['offers']['@type']) ? [$product['offers']] : $product['offers'];
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

        $prices = collect(isset($product['offers']['@type']) ? [$product['offers']] : $product['offers'])->pluck('price')->sort()->values()->all();
        $this->assertSame(['1.29', '1.49'], $prices);
    }

    public function test_expired_product_offer_is_out_of_stock(): void
    {
        $seed = $this->seedListing();
        $seed['discount']->update(['start_at' => now()->subMonth(), 'end_at' => now()->subWeeks(3)]);

        $products = $this->jsonLdOfType($this->get("/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"), 'Product');

        $this->assertCount(1, $products);
        $offers = isset($products[0]['offers']['@type']) ? [$products[0]['offers']] : $products[0]['offers'];
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

    public function test_homepage_has_organization_and_site_search(): void
    {
        $this->seedListing();
        $response = $this->get('/');

        $this->assertCount(1, $this->jsonLdOfType($response, 'Organization'));

        $sites = $this->jsonLdOfType($response, 'WebSite');
        $this->assertCount(1, $sites);
        $this->assertSame(
            'https://superakcijos.lt/akcijos/paieska/{search_term_string}',
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
}
