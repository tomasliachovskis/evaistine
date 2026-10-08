<?php

namespace Tests\Feature;

use App\Models\SearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchResultLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_log_stores_cloudflare_country_code(): void
    {
        $this->get('/paieska/pienas', ['CF-IPCountry' => 'lt'])->assertOk();

        $this->assertSame('LT', SearchResult::latest('id')->value('country_code'));
    }

    public function test_unknown_or_missing_country_is_stored_as_null(): void
    {
        $this->get('/paieska/pienas', ['CF-IPCountry' => 'XX'])->assertOk();
        $this->get('/paieska/kava')->assertOk();

        $this->assertSame([null, null], SearchResult::orderBy('id')->pluck('country_code')->all());
    }
}
