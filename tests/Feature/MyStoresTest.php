<?php

namespace Tests\Feature;

use App\Livewire\DiscountFilters;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyStoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_saves_only_real_store_slugs(): void
    {
        Store::factory()->create(['slug' => 'iki']);
        Store::factory()->create(['slug' => 'lidl']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/mano-parduotuves', ['stores' => ['lidl', 'nera-tokios', 'iki', 'lidl']])
            ->assertOk()
            ->assertJson(['stores' => ['lidl', 'iki']]);

        $this->assertSame(['lidl', 'iki'], $user->fresh()->preferred_store_slugs);

        $this->actingAs($user)->postJson('/mano-parduotuves', ['stores' => []])->assertOk();
        $this->assertNull($user->fresh()->preferred_store_slugs);
    }

    public function test_the_page_hands_a_signed_in_users_stores_to_the_browser(): void
    {
        $user = User::factory()->create(['preferred_store_slugs' => ['iki', 'lidl']]);

        $this->actingAs($user)->get('/parduotuves')
            ->assertOk()
            ->assertSee('const account = ["iki","lidl"];', false);
    }

    public function test_guests_cannot_save_to_an_account(): void
    {
        $this->postJson('/mano-parduotuves', ['stores' => ['iki']])->assertUnauthorized();
    }

    public function test_apply_stores_narrows_a_multi_store_listing(): void
    {
        $category = Category::factory()->create(['slug' => 'groceries']);
        foreach (['iki', 'lidl', 'rimi'] as $slug) {
            $store = Store::factory()->create(['slug' => $slug]);
            Discount::factory()->create([
                'store_id' => $store->id,
                'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            ]);
        }

        $component = Livewire::test(DiscountFilters::class, [
            'mode' => 'discounts',
            'primarySlug' => 'groceries',
            'secondarySlug' => null,
            'initialDeals' => [],
            'initialPagination' => [],
            'multiStore' => true,
        ])->call('applyStores', 'iki,lidl,<script>');

        $component->assertSet('storeFilter', 'iki,lidl');
        $storeIds = collect($component->get('deals'))->pluck('store_id')->sort()->values()->all();
        $this->assertSame(Store::whereIn('slug', ['iki', 'lidl'])->orderBy('id')->pluck('id')->all(), $storeIds);
    }

    public function test_apply_stores_does_nothing_on_a_single_store_page(): void
    {
        Livewire::test(DiscountFilters::class, [
            'mode' => 'discounts',
            'primarySlug' => 'iki',
            'secondarySlug' => null,
            'initialDeals' => [],
            'initialPagination' => [],
            'multiStore' => false,
        ])->call('applyStores', 'lidl')->assertSet('storeFilter', '');
    }
}
