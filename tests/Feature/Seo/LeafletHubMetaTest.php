<?php

namespace Tests\Feature\Seo;

use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeafletHubMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_caps_flyer_title_is_sentence_cased_in_the_description(): void
    {
        $store = Store::factory()->create(['name' => 'Seo Tinklas', 'slug' => 'seo-tinklas']);
        StoreFlyer::create([
            'store_id' => $store->id,
            'title' => 'NE MAISTO PREKIŲ PASIŪLYMAI NR. 40',
            'slug' => 'ne-maisto',
            'image_url' => '/storage/flyers/pages/seo/page-1.webp',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addWeek(),
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ]);

        $this->get('/leidinys/seo-tinklas')
            ->assertOk()
            ->assertSee('content="Dabar galioja „Ne maisto prekių pasiūlymai Nr. 40“.', false);
    }

    public function test_mixed_case_flyer_title_is_left_alone(): void
    {
        $store = Store::factory()->create(['name' => 'Seo Tinklas', 'slug' => 'seo-tinklas']);
        StoreFlyer::create([
            'store_id' => $store->id,
            'title' => 'IKI apdovanotų vynų kolekcija',
            'slug' => 'vynai',
            'image_url' => '/storage/flyers/pages/seo/page-1.webp',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addWeek(),
            'is_active' => true,
            'processing_status' => StoreFlyer::STATUS_READY,
        ]);

        $this->get('/leidinys/seo-tinklas')
            ->assertOk()
            ->assertSee('Dabar galioja „IKI apdovanotų vynų kolekcija“.', false);
    }
}
