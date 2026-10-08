<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// /leidinys/{slug} for a pharmacy with no current leaflet sends the visitor
// to its offers instead of a page about a leaflet that isn't there.
class LeafletHubRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacy_without_a_current_leaflet_redirects_to_its_offers(): void
    {
        $store = Store::factory()->create(['name' => 'Be Leidinio', 'slug' => 'be-leidinio', 'show_discounts_page' => true]);
        // An expired leaflet doesn't count.
        $this->createFlyer($store, now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        $this->get('/leidinys/be-leidinio')->assertRedirect('/be-leidinio')->assertStatus(301);
    }

    public function test_pharmacy_with_a_current_leaflet_keeps_its_hub(): void
    {
        $store = Store::factory()->create(['name' => 'Su Leidiniu', 'slug' => 'su-leidiniu', 'show_discounts_page' => true]);
        $this->createFlyer($store, now()->startOfMonth(), now()->endOfMonth());

        $this->get('/leidinys/su-leidiniu')->assertOk();
    }

    private function createFlyer(Store $store, $validFrom, $validTo): StoreFlyer
    {
        return StoreFlyer::create([
            'store_id' => $store->id,
            'slug' => 'menesio-leidinys-'.$validFrom->format('Ym'),
            'title' => 'Mėnesio leidinys',
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ]);
    }
}
