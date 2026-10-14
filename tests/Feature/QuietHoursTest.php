<?php

namespace Tests\Feature;

use App\Support\QuietHours;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Owner, 2026-10-14: no heavy jobs on the Mac 08:00-18:00.
class QuietHoursTest extends TestCase
{
    public function test_working_hours_on_the_local_machine_only(): void
    {
        config(['services.scrapers.quiet_hours' => '08:00-18:00']);
        $this->app['env'] = 'dev';

        $this->assertFalse(QuietHours::active(Carbon::parse('2026-10-15 07:59')));
        $this->assertTrue(QuietHours::active(Carbon::parse('2026-10-15 08:00')));
        $this->assertTrue(QuietHours::active(Carbon::parse('2026-10-15 17:59')));
        $this->assertFalse(QuietHours::active(Carbon::parse('2026-10-15 18:00')));
        $this->assertFalse(QuietHours::active(Carbon::parse('2026-10-15 03:00')));

        $this->app['env'] = 'production';
        $this->assertFalse(QuietHours::active(Carbon::parse('2026-10-15 12:00')));
    }

    public function test_scrapers_run_all_stops_in_quiet_hours(): void
    {
        config(['services.scrapers.quiet_hours' => '00:00-23:59']);
        $this->app['env'] = 'dev';

        $this->artisan('scrapers:run', ['--all' => true])
            ->expectsOutputToContain('Quiet hours')
            ->assertSuccessful();
    }
}
