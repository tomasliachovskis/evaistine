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

    private function offer(Store $store, Category $category, string $name, float $price, ?int $percent): Discount
    {
        return Discount::factory()->create([
            'store_id' => $store->id,
            'product_id' => Product::factory()->create(['category_id' => $category->id, 'name' => $name])->id,
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
}
