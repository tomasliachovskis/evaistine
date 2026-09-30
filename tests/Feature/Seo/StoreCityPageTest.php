<?php

namespace Tests\Feature\Seo;

use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Models\StoreLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Google folded /parduotuves/{store}/{city} into /parduotuves/{store} as a
// duplicate ("Google chose different canonical"): the city page was mostly
// seven repeated weekday rows per address, and the chain page's HTML
// already carried every city's addresses for its map/finder.
class StoreCityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_page_shows_one_hours_line_per_location_and_city_facts(): void
    {
        $this->seedChain();

        $response = $this->get('/parduotuves/seo-tinklas/vilnius')->assertOk();

        $response->assertSee('Pr–Št 08:00–22:00, Sk 09:00–20:00');
        $response->assertDontSee('Ketvirtadienis');
        $response->assertSee('Seo Tinklas darbo laikas Vilnius');
        $response->assertSee('Anksčiausiai darbo dienomis atsidaro – nuo 07:00: Seo g. 1.', false);
    }

    public function test_city_page_links_deals_and_leaflets_only_when_the_store_has_them(): void
    {
        $this->seedChain();

        $this->get('/parduotuves/seo-tinklas/vilnius')
            ->assertDontSee('Žiūrėti Seo Tinklas akcijas')
            ->assertDontSee('Žiūrėti Seo Tinklas leidinius');

        $store = Store::where('slug', 'seo-tinklas')->first();
        $store->update(['show_discounts_page' => true]);
        Discount::factory()->create(['store_id' => $store->id]);
        StoreFlyer::create([
            'store_id' => $store->id,
            'title' => 'Seo leidinys',
            'slug' => 'seo-leidinys',
            'image_url' => '/storage/flyers/pages/seo/page-1.webp',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addWeek(),
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ]);

        $this->get('/parduotuves/seo-tinklas/vilnius')
            ->assertSee('Žiūrėti Seo Tinklas akcijas (1)')
            ->assertSee('Žiūrėti Seo Tinklas leidinius (1)');
    }

    public function test_chain_page_loads_addresses_from_the_api_instead_of_inlining_them(): void
    {
        $this->seedChain();

        $response = $this->get('/parduotuves/seo-tinklas')->assertOk();

        $response->assertDontSee('Seo g. 2');
        $response->assertSee('store-locations\/seo-tinklas', false);
    }

    public function test_locations_api_carries_city_slug_for_links(): void
    {
        $this->seedChain();

        $this->getJson('/api/store-locations/seo-tinklas')
            ->assertOk()
            ->assertJsonPath('locations.0.city_slug', 'vilnius')
            ->assertJsonCount(3, 'locations');
    }

    private function seedChain(): void
    {
        $store = Store::factory()->create(['name' => 'Seo Tinklas', 'slug' => 'seo-tinklas']);

        foreach (['Seo g. 1' => '07:00-22:00', 'Seo g. 2' => '08:00-22:00', 'Seo g. 3' => '08:00-22:00'] as $address => $weekday) {
            StoreLocation::create([
                'store_id' => $store->id,
                'city' => 'Vilnius',
                'address' => $address,
                'slug' => \Illuminate\Support\Str::slug($address),
                'lat' => 54.68,
                'lng' => 25.28,
                'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], $weekday) + ['sunday' => '09:00-20:00'],
                'is_active' => true,
            ]);
        }
    }
}
