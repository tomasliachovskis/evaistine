<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Seo\Concerns\InspectsSeoHead;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use InspectsSeoHead;
    use RefreshDatabase;

    private const KEY = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function test_key_file_is_served_only_for_the_configured_key(): void
    {
        config(['services.indexnow.key' => self::KEY]);

        $this->get('/'.self::KEY.'.txt')->assertOk()->assertSeeText(self::KEY);
        $this->get('/ffffffffffffffffffffffffffffffff.txt')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertSeeText('User-agent');
    }

    public function test_key_file_is_404_without_a_key(): void
    {
        config(['services.indexnow.key' => null]);

        $this->get('/'.self::KEY.'.txt')->assertNotFound();
    }

    public function test_pings_changed_product_pages_in_production(): void
    {
        $seed = $this->seedListing();
        config(['services.indexnow.key' => self::KEY]);
        $this->app['env'] = 'production';
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);

        $this->artisan('seo:indexnow', ['--since' => now()->subHour()->toDateTimeString()])->assertSuccessful();

        Http::assertSent(function ($request) use ($seed) {
            return $request['key'] === self::KEY
                && $request['host'] === 'evaistine.lt'
                && $request['urlList'] === [self::ORIGIN."/akcijos/{$seed['category']->slug}/{$seed['product']->slug}"];
        });
    }

    public function test_does_nothing_outside_production(): void
    {
        $this->seedListing();
        config(['services.indexnow.key' => self::KEY]);
        Http::fake();

        $this->artisan('seo:indexnow')->assertSuccessful();

        Http::assertNothingSent();
    }
}
