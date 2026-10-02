<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\FlyerStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Big leaflet PDFs (Elimart's 54-65MB print masters) are sent by the
// scraper as their own URL instead of an upload; the backend downloads them.
class StoreFlyerSourceUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_URL = 'https://irp.cdn-website.com/75553b5e/files/uploaded/Elimart+Tau+Nr.10.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // Elimart's row already comes from the store migrations.
    private function elimart(): Store
    {
        return Store::where('slug', 'elimart')->first()
            ?? Store::factory()->create(['name' => 'Elimart', 'slug' => 'elimart']);
    }

    private function submit(array $fields)
    {
        return $this->postJson('/api/scrapers/store-flyer', $fields + [
            'store' => 'Elimart',
            'title' => 'Elimart Tau leidinys',
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addWeeks(4)->toDateString(),
        ]);
    }

    public function test_pdf_is_downloaded_from_the_source_url(): void
    {
        $store = $this->elimart();
        Http::fake([self::PDF_URL => Http::response("%PDF-1.7\nleaflet", 200)]);

        $this->submit(['pdf_source_url' => self::PDF_URL])->assertCreated();

        $flyer = StoreFlyer::where('store_id', $store->id)->sole();
        Storage::disk('public')->assertExists(FlyerStorage::pdfPathForFlyer($store, $flyer->slug));
        $this->assertSame("%PDF-1.7\nleaflet", Storage::disk('public')->get(FlyerStorage::pdfPathForFlyer($store, $flyer->slug)));
    }

    public function test_a_download_that_is_not_a_pdf_creates_nothing(): void
    {
        $this->elimart();
        Http::fake([self::PDF_URL => Http::response('<html>Not found</html>', 200)]);

        $this->submit(['pdf_source_url' => self::PDF_URL])->assertStatus(422);

        $this->assertSame(0, StoreFlyer::count());
    }

    public function test_private_and_non_https_urls_are_refused(): void
    {
        $this->elimart();
        Http::fake();

        $this->submit(['pdf_source_url' => 'https://127.0.0.1/leaflet.pdf'])->assertStatus(422);
        $this->submit(['pdf_source_url' => 'https://localhost/leaflet.pdf'])->assertStatus(422);
        $this->submit(['pdf_source_url' => 'http://irp.cdn-website.com/leaflet.pdf'])->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, StoreFlyer::count());
    }

    public function test_a_pdf_or_a_url_is_required(): void
    {
        $this->elimart();

        $this->submit([])->assertStatus(422)->assertJsonValidationErrors('pdf');
    }

    public function test_an_uploaded_pdf_still_works(): void
    {
        $store = $this->elimart();
        Http::fake();

        $this->submit(['pdf' => UploadedFile::fake()->createWithContent('leidinys.pdf', "%PDF-1.7\nleaflet")])->assertCreated();

        $flyer = StoreFlyer::where('store_id', $store->id)->sole();
        Storage::disk('public')->assertExists(FlyerStorage::pdfPathForFlyer($store, $flyer->slug));
        Http::assertNothingSent();
    }
}
