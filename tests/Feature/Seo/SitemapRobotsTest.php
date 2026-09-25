<?php

namespace Tests\Feature\Seo;

use App\Models\Discount;
use App\Models\KeywordPage;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

class SitemapRobotsTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_robots_on_the_public_host_allows_crawling_and_lists_sitemaps(): void
    {
        $this->seedListing();

        $response = $this->get('http://superakcijos.lt/robots.txt');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Sitemap: https://superakcijos.lt/sitemap.xml', $body);
        $this->assertStringContainsString('Sitemap: https://superakcijos.lt/product-sitemap/1', $body);
        $this->assertStringContainsString('Disallow: /akcijos/paieska', $body);
        $this->assertStringContainsString('Disallow: /api/', $body);

        // The first group is the one every real search engine falls into —
        // a blanket "Disallow: /" there would deindex the whole site.
        $firstGroup = strstr($body, "\n\n", true);
        $this->assertStringStartsWith('User-agent: *', $firstGroup);
        $this->assertDoesNotMatchRegularExpression('/^Disallow: \/$/m', $firstGroup);
    }

    public function test_robots_on_any_other_host_blocks_everything(): void
    {
        $response = $this->get('http://api.superakcijos.lt/robots.txt');

        $response->assertOk();
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_sitemap_lists_indexable_listings_only(): void
    {
        $seed = $this->seedListing();

        $leafletOnly = Store::factory()->create(['slug' => 'seo-tik-leidinys', 'show_discounts_page' => false]);
        Discount::factory()->create(['store_id' => $leafletOnly->id, 'product_id' => $seed['product']->id]);

        KeywordPage::create(['slug' => 'seo-publikuotas', 'title' => 'A', 'h1' => 'A', 'search_terms' => ['a'], 'is_published' => true]);
        KeywordPage::create(['slug' => 'seo-juodrastis', 'title' => 'B', 'h1' => 'B', 'search_terms' => ['b'], 'is_published' => false]);

        $locs = $this->sitemapLocs($this->get('/sitemap.xml'));

        $this->assertContains(self::ORIGIN.'/', $locs);
        $this->assertContains(self::ORIGIN.'/akcijos', $locs);
        $this->assertContains(self::ORIGIN.'/pigiausios-prekes', $locs);
        $this->assertContains(self::ORIGIN."/akcijos/{$seed['store']->slug}", $locs);
        $this->assertContains(self::ORIGIN."/akcijos/{$seed['category']->slug}", $locs);
        $this->assertContains(self::ORIGIN.'/akcijos/seo-publikuotas', $locs);

        // A 301 or a 404 must never be listed.
        $this->assertNotContains(self::ORIGIN."/akcijos/{$leafletOnly->slug}", $locs);
        $this->assertNotContains(self::ORIGIN.'/akcijos/seo-juodrastis', $locs);

        $this->assertSame(count($locs), count(array_unique($locs)), 'Duplicate URLs in sitemap.xml');
        foreach ($locs as $loc) {
            $this->assertStringStartsWith(self::ORIGIN.'/', $loc);
        }
    }

    public function test_product_sitemap_lists_live_products_and_drops_stale_one_offs(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $seed = $this->seedListing();

        $stale = Product::factory()->create([
            'slug' => 'seo-sena-preke',
            'category_id' => $seed['category']->id,
        ]);
        Discount::factory()->create([
            'product_id' => $stale->id,
            'store_id' => $seed['store']->id,
            'start_at' => now()->subWeeks(6),
            'end_at' => now()->subWeeks(3),
        ]);

        $locs = $this->sitemapLocs($this->get('/product-sitemap/1'));

        $this->assertContains(self::ORIGIN."/akcijos/{$seed['category']->slug}/{$seed['product']->slug}", $locs);
        $this->assertNotContains(self::ORIGIN."/akcijos/{$seed['category']->slug}/{$stale->slug}", $locs);
    }

    public function test_out_of_range_product_sitemap_page_is_503_not_an_empty_sitemap(): void
    {
        $this->seedListing();

        $this->get('/product-sitemap/99')->assertStatus(503);
    }

    private function sitemapLocs(TestResponse $response): array
    {
        $response->assertOk();
        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap is not valid XML');

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }
        $this->assertNotEmpty($locs);

        return $locs;
    }
}
