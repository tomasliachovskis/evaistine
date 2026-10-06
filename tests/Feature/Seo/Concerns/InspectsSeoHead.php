<?php

namespace Tests\Feature\Seo\Concerns;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;

trait InspectsSeoHead
{
    protected const ORIGIN = 'https://evaistine.lt';

    protected const NOINDEX = 'noindex, nofollow, noarchive, nosnippet';

    /**
     * A Product's individual Offer nodes, whether it carries one Offer, a
     * list, or an AggregateOffer wrapping several stores.
     */
    protected function offersOf(array $product): array
    {
        $offers = $product['offers'];

        if (($offers['@type'] ?? null) === 'AggregateOffer') {
            return $offers['offers'];
        }

        return isset($offers['@type']) ? [$offers] : $offers;
    }

    /**
     * Title, description, canonical and robots from the rendered <head>.
     * Each must appear exactly once — a duplicate canonical or robots tag
     * leaves Google to pick one, which is a bug in itself. Error pages carry
     * no canonical at all ($canonicalRequired false: zero or one allowed).
     */
    protected function seoHead(TestResponse $response, bool $canonicalRequired = true): array
    {
        $xpath = $this->xpath($response);

        $single = function (string $query, string $label, bool $required = true) use ($xpath) {
            $nodes = $xpath->query($query);
            if (!$required && $nodes->length === 0) {
                return null;
            }
            $this->assertSame(1, $nodes->length, "Expected exactly one {$label}, found {$nodes->length}");

            return trim($nodes->item(0)->nodeValue ?? '');
        };

        return [
            'title' => $single('//head/title', '<title>'),
            'description' => $single('//head/meta[@name="description"]/@content', 'meta description'),
            'canonical' => $single('//head/link[@rel="canonical"]/@href', 'canonical', $canonicalRequired),
            'robots' => $single('//head/meta[@name="robots"]/@content', 'robots meta'),
        ];
    }

    protected function assertIndexable(TestResponse $response, string $canonicalPath): array
    {
        $response->assertOk();
        $head = $this->seoHead($response);

        $this->assertNotSame('', $head['title'], 'Empty <title>');
        $this->assertNotSame('', $head['description'], 'Empty meta description');
        $this->assertSame(self::ORIGIN.$canonicalPath, $head['canonical']);
        $this->assertSame('index, follow', $head['robots']);

        return $head;
    }

    protected function assertNoindex(TestResponse $response): array
    {
        $head = $this->seoHead($response, canonicalRequired: false);
        $this->assertStringStartsWith('noindex', $head['robots']);

        return $head;
    }

    /**
     * Every JSON-LD block on the page, decoded. A block that isn't valid
     * JSON fails the test — Google silently drops broken structured data.
     */
    protected function jsonLd(TestResponse $response): array
    {
        $blocks = [];

        foreach ($this->xpath($response)->query('//script[@type="application/ld+json"]') as $node) {
            $decoded = json_decode($node->nodeValue, true);
            $this->assertIsArray($decoded, 'Invalid JSON-LD block: '.json_last_error_msg()."\n".$node->nodeValue);
            $this->assertSame('https://schema.org', $decoded['@context'] ?? null, 'JSON-LD block without schema.org @context');
            $blocks[] = $decoded;
        }

        return $blocks;
    }

    protected function jsonLdOfType(TestResponse $response, string $type): array
    {
        return array_values(array_filter($this->jsonLd($response), fn ($block) => ($block['@type'] ?? null) === $type));
    }

    /**
     * A root category, a store with an offers page, and one product with
     * an image and an active discount there.
     */
    protected function seedListing(): array
    {
        $category = Category::factory()->create(['name' => 'Pieno produktai', 'slug' => 'seo-pieno-produktai']);
        $store = Store::factory()->create(['name' => 'Seo Parduotuve', 'slug' => 'seo-parduotuve', 'show_discounts_page' => true]);
        $product = Product::factory()->create([
            'name' => 'Seo pienas 2.5% 1 l',
            'slug' => 'seo-pienas-1-l',
            'category_id' => $category->id,
            'image_url' => 'https://cdn.example.com/seo-pienas.jpg',
        ]);
        $discount = Discount::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'original_price' => 1.99,
            'discounted_price' => 1.29,
            'discount_percent' => 35,
        ]);

        return compact('category', 'store', 'product', 'discount');
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
