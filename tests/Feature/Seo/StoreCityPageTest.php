<?php

namespace Tests\Feature\Seo;

use App\Models\Discount;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Models\StoreLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Google folded /vaistines/{store}/{city} into /vaistines/{store} as a
// duplicate ("Google chose different canonical"): the city page was mostly
// seven repeated weekday rows per address, and the chain page's HTML
// already carried every city's addresses for its map/finder.
class StoreCityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_page_shows_one_hours_line_per_location_and_city_facts(): void
    {
        $this->seedChain();

        $response = $this->get('/vaistines/seo-tinklas/vilnius')->assertOk();

        $response->assertSee('Pr–Št 08:00–22:00, Sk 09:00–20:00');
        $response->assertDontSee('Ketvirtadienis');
        $response->assertSee('Seo Tinklas darbo laikas Vilnius');
        $response->assertSee('Anksčiausiai darbo dienomis atsidaro – nuo 07:00: Seo g. 1.', false);
    }

    // Query words ("{store} {city} darbo laikas") lead the title, and the
    // description carries the whole week's hours rather than today's row,
    // which Google's cached snippet would show on the wrong day.
    public function test_city_page_meta_leads_with_darbo_laikas_and_weekly_hours(): void
    {
        $this->seedChain();

        $response = $this->get('/vaistines/seo-tinklas/vilnius')->assertOk();

        $response->assertSee('<title>Seo Tinklas Vilnius darbo laikas – 3 vaistinės', false);
        $response->assertSee('content="Seo Tinklas Vilnius: Seo g. 1 (Pr–Št 07:00–22:00, Sk 09:00–20:00), Seo g. 2 (Pr–Št 08:00–22:00, Sk 09:00–20:00) ir kt. Visi adresai', false);
        $response->assertDontSee('šiandien');
    }

    public function test_single_location_city_names_the_address_in_the_title(): void
    {
        $this->seedChain();
        StoreLocation::create([
            'store_id' => Store::where('slug', 'seo-tinklas')->value('id'),
            'city' => 'Utena',
            'address' => 'Aukštakalnio g. 5',
            'slug' => 'aukstakalnio-g-5',
            'lat' => 55.5,
            'lng' => 25.6,
            'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '08:00-21:00'),
            'is_active' => true,
        ]);

        $this->get('/vaistines/seo-tinklas/utena')
            ->assertOk()
            ->assertSee('<title>Seo Tinklas Utena darbo laikas – Aukštakalnio g. 5', false)
            ->assertSee('content="Seo Tinklas Utena, Aukštakalnio g. 5 (Kasdien 08:00–21:00). Adresas, kontaktai ir vieta žemėlapyje."', false)
            ->assertSee('1 vaistinė Utena mieste');
    }

    public function test_city_with_shared_hours_states_them_once(): void
    {
        $this->seedChain();
        $storeId = Store::where('slug', 'seo-tinklas')->value('id');

        foreach (['Kauno g. 1', 'Kauno g. 2', 'Kauno g. 3'] as $address) {
            StoreLocation::create([
                'store_id' => $storeId,
                'city' => 'Kaunas',
                'address' => $address,
                'slug' => \Illuminate\Support\Str::slug($address),
                'lat' => 54.9,
                'lng' => 23.9,
                'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '08:00-22:00'),
                'is_active' => true,
            ]);
        }

        $this->get('/vaistines/seo-tinklas/kaunas')
            ->assertOk()
            ->assertSee('content="Seo Tinklas Kaunas: Kauno g. 1, Kauno g. 2 ir kt. Darbo laikas: Kasdien 08:00–22:00. Visi adresai ir kontaktai."', false);
    }

    public function test_chain_page_meta_names_top_cities_and_typical_hours(): void
    {
        $this->seedChain();

        $this->get('/vaistines/seo-tinklas')
            ->assertOk()
            ->assertSee('<title>Seo Tinklas vaistinių adresai ir darbo laikas', false)
            // Only one city, so no city list; 2 of 3 locations share the hours.
            ->assertSee('content="Seo Tinklas Lietuvoje – 3 vaistinės. Dažniausias darbo laikas: Pr–Št 08:00–22:00, Sk 09:00–20:00. Adresai, darbo laikas ir kontaktai pagal miestą."', false);
    }

    public function test_city_page_links_deals_and_leaflets_only_when_the_store_has_them(): void
    {
        $this->seedChain();

        $this->get('/vaistines/seo-tinklas/vilnius')
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

        $this->get('/vaistines/seo-tinklas/vilnius')
            ->assertSee('Žiūrėti Seo Tinklas akcijas (1)')
            ->assertSee('Žiūrėti Seo Tinklas leidinius (1)');
    }

    public function test_chain_page_loads_addresses_from_the_api_instead_of_inlining_them(): void
    {
        $this->seedChain();

        $response = $this->get('/vaistines/seo-tinklas')->assertOk();

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
