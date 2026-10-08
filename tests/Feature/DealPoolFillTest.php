<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use App\Services\HomeDealPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealPoolFillTest extends TestCase
{
    use RefreshDatabase;

    private function offer(Store $store, Category $category, string $name, float $price, ?int $percent, ?string $brand = null): Discount
    {
        return Discount::factory()->create([
            'store_id' => $store->id,
            'product_id' => Product::factory()->create(['category_id' => $category->id, 'name' => $name, 'brand' => $brand])->id,
            'discounted_price' => $price,
            'original_price' => $percent ? round($price / (1 - $percent / 100), 2) : $price,
            'discount_percent' => $percent ?? 0,
        ]);
    }

    public function test_thin_carousel_is_filled_with_discounts_then_lowest_prices(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $this->offer($store, $category, 'Saulėgrąžų aliejus', 1.99, 50);
        $this->offer($store, $category, 'Ryžiai ilgagrūdžiai', 2.49, 20);
        $expensive = $this->offer($store, $category, 'Makaronai spageti', 3.10, null);
        $cheap = $this->offer($store, $category, 'Druska jūros', 0.59, null);
        $middle = $this->offer($store, $category, 'Cukrus baltas', 1.20, null);

        $result = app(HomeDealPoolService::class)->bestForCategory($category->id, 8, $store->id);

        $this->assertCount(5, $result);
        // Discounted offers first, then the rest by lowest price.
        $this->assertEquals([50, 20], $result->take(2)->pluck('discount_percent')->map(fn ($p) => (int) $p)->sortDesc()->values()->all());
        $this->assertSame([$cheap->id, $middle->id, $expensive->id], $result->slice(2)->pluck('id')->values()->all());
    }

    public function test_category_without_discounts_shows_lowest_prices(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $b = $this->offer($store, $category, 'Kava malta', 4.99, null);
        $a = $this->offer($store, $category, 'Arbata juodoji', 1.49, null);

        $result = app(HomeDealPoolService::class)->bestForCategory($category->id, 8, $store->id);

        $this->assertSame([$a->id, $b->id], $result->pluck('id')->all());
    }

    public function test_full_carousel_is_not_padded_past_the_limit(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        foreach (['Pienas', 'Sviestas', 'Kefyras'] as $i => $name) {
            $this->offer($store, $category, $name, 1 + $i, 30);
        }
        $this->offer($store, $category, 'Varškė', 0.99, null);

        $this->assertCount(2, app(HomeDealPoolService::class)->bestForCategory($category->id, 2, $store->id));
    }

    public function test_carousel_shows_different_product_lines_before_repeating_one(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        // The expensive line scores highest (big absolute savings), so plain
        // score order would fill the whole carousel with it.
        foreach (['1300 vyrams', '1300 moterims', '500 moterims', '500 vyrams'] as $i => $variant) {
            $this->offer($store, $category, "CRESCINA TRANSDERMIC HFSC {$variant}", 150 - $i, 40, 'Crescina');
        }
        $this->offer($store, $category, 'Plaukų šampūnas nuo pleiskanų', 9.99, 20, 'Dermedic');
        $this->offer($store, $category, 'NIOXIN plaukų serumas', 39.99, 25, 'NIOXIN');
        $plain = $this->offer($store, $category, 'Kondicionierius sausiems plaukams', 6.49, null);

        $result = app(HomeDealPoolService::class)->bestForCategory($category->id, 4, $store->id);

        $this->assertCount(4, $result);
        $this->assertSame(1, $result->filter(fn (Discount $d) => $d->product->brand === 'Crescina')->count());
        // A new line without a discount beats a second CRESCINA kit.
        $this->assertContains($plain->id, $result->pluck('id')->all());

        // With more slots than lines, repeats come back to fill the carousel.
        $this->assertCount(7, app(HomeDealPoolService::class)->bestForCategory($category->id, 8, $store->id));
    }
}
