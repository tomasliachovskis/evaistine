<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StoreFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_discounts_can_be_filtered_by_single_store()
    {
        $store1 = Store::factory()->create(['slug' => 'iki']);
        $store2 = Store::factory()->create(['slug' => 'lidl']);
        
        $category = Category::factory()->create(['slug' => 'groceries']);
        
        $product1 = Product::factory()->create(['category_id' => $category->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id]);
        
        Discount::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product1->id
        ]);
        
        Discount::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product2->id
        ]);

        $response = $this->get('/api/discount/groceries?store=iki');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_discounts_can_be_filtered_by_multiple_stores()
    {
        $store1 = Store::factory()->create(['slug' => 'iki']);
        $store2 = Store::factory()->create(['slug' => 'lidl']);
        $store3 = Store::factory()->create(['slug' => 'rimi']);
        
        $category = Category::factory()->create(['slug' => 'groceries']);
        
        $product1 = Product::factory()->create(['category_id' => $category->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id]);
        $product3 = Product::factory()->create(['category_id' => $category->id]);
        
        Discount::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product1->id
        ]);
        
        Discount::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product2->id
        ]);
        
        Discount::factory()->create([
            'store_id' => $store3->id,
            'product_id' => $product3->id
        ]);

        $response = $this->get('/api/discount/groceries?store=iki,lidl');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.data');
    }

    public function test_store_filter_works_with_store_route()
    {
        $store1 = Store::factory()->create(['slug' => 'iki']);
        $store2 = Store::factory()->create(['slug' => 'lidl']);
        
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        
        Discount::factory()->create([
            'store_id' => $store1->id,
            'product_id' => $product1->id
        ]);
        
        Discount::factory()->create([
            'store_id' => $store2->id,
            'product_id' => $product2->id
        ]);

        // The ?store= filter narrows further within the route's own store
        // (buildDiscountQuery ANDs it onto the route's store_id constraint),
        // it doesn't override the route — so filtering /iki by a different
        // store (lidl) correctly yields no results.
        $response = $this->get('/api/discount/iki?store=lidl');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.data');

        // Filtering by the same store as the route is the realistic case
        // (e.g. the store chip stays selected) and should still match.
        $response = $this->get('/api/discount/iki?store=iki');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
    }
}

