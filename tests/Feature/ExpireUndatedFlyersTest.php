<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpireUndatedFlyersTest extends TestCase
{
    use RefreshDatabase;

    private StoreFlyer $oldUndated;

    private StoreFlyer $newUndated;

    private StoreFlyer $oldDated;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 12:00:00');

        $store = Store::firstOrCreate(['name' => 'Benu vaistinė'], ['slug' => 'benu-vaistine']);

        $this->oldUndated = $this->flyer($store, null, now()->subDays(30));
        $this->newUndated = $this->flyer($store, null, now()->subDays(10));
        $this->oldDated = $this->flyer($store, '2026-09-20', now()->subDays(30));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_closes_only_old_undated_flyers(): void
    {
        $this->artisan('flyers:expire-undated')->assertSuccessful();

        $this->assertSame('2026-10-11', $this->oldUndated->fresh()->valid_to->toDateString());
        $this->assertNull($this->newUndated->fresh()->valid_to);
        $this->assertSame('2026-09-20', $this->oldDated->fresh()->valid_to->toDateString());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('flyers:expire-undated --dry-run')->assertSuccessful();

        $this->assertNull($this->oldUndated->fresh()->valid_to);
    }

    private function flyer(Store $store, ?string $validTo, Carbon $createdAt): StoreFlyer
    {
        return StoreFlyer::forceCreate([
            'store_id' => $store->id,
            'slug' => Str::random(10),
            'valid_to' => $validTo,
            'processing_status' => StoreFlyer::STATUS_READY,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
