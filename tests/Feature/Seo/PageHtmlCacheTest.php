<?php

namespace Tests\Feature\Seo;

use App\Support\PageHtmlCache;
use Tests\TestCase;

class PageHtmlCacheTest extends TestCase
{
    // Found live 2026-09-28: every cached listing page served a root-relative
    // canonical, because entries are stored with meta URLs stripped to paths
    // and the response hydrate step never put the origin back.
    public function test_cached_html_is_served_with_absolute_canonical_and_og_url(): void
    {
        $html = '<head>'
            .'<link rel="canonical" href="https://superakcijos.lt/akcijos/kava">'
            .'<meta property="og:url" content="https://superakcijos.lt/akcijos/kava">'
            .'</head>';

        $stored = PageHtmlCache::neutralizeForStorage($html);
        $this->assertStringContainsString('<link rel="canonical" href="/akcijos/kava">', $stored);

        $served = PageHtmlCache::hydrateForResponse($stored);
        $this->assertStringContainsString('<link rel="canonical" href="https://superakcijos.lt/akcijos/kava">', $served);
        $this->assertStringContainsString('<meta property="og:url" content="https://superakcijos.lt/akcijos/kava">', $served);
    }

    public function test_homepage_canonical_round_trips_to_the_bare_origin_with_slash(): void
    {
        $stored = PageHtmlCache::neutralizeForStorage('<link rel="canonical" href="https://superakcijos.lt">');

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://superakcijos.lt/">',
            PageHtmlCache::hydrateForResponse($stored),
        );
    }
}
