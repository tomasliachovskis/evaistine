<?php

namespace Tests\Unit;

use App\Support\CanonicalUrl;
use PHPUnit\Framework\TestCase;

class CanonicalUrlTest extends TestCase
{
    /**
     * @dataProvider canonicalProvider
     */
    public function test_build(string $path, array $query, string $expected): void
    {
        $this->assertSame($expected, CanonicalUrl::build($path, $query));
    }

    public static function canonicalProvider(): array
    {
        return [
            'plain path' => ['/iki', [], 'https://evaistine.lt/iki'],
            'page 1 dropped' => ['/iki', ['page' => '1'], 'https://evaistine.lt/iki'],
            'page 2 kept' => ['/iki', ['page' => '2'], 'https://evaistine.lt/iki?page=2'],
            'default order dropped' => ['/iki', ['order' => 'popular'], 'https://evaistine.lt/iki'],
            'other order kept' => ['/iki', ['order' => 'price_min'], 'https://evaistine.lt/iki?order=price_min'],
            'filters and tracking stripped' => [
                '/iki',
                ['store' => 'maxima', 'category' => 'pienas', 'card' => '1', 'plus' => '1', 'utm_source' => 'fb', 'gclid' => 'x'],
                'https://evaistine.lt/iki',
            ],
            'page kept, filter stripped' => ['/iki', ['page' => '3', 'store' => 'maxima'], 'https://evaistine.lt/iki?page=3'],
        ];
    }

    /**
     * @dataProvider robotsProvider
     */
    public function test_robots_meta(string $path, array $query, string $expected): void
    {
        $this->assertSame($expected, CanonicalUrl::robotsMeta($path, $query));
    }

    public static function robotsProvider(): array
    {
        $noindex = 'noindex, nofollow, noarchive, nosnippet';

        return [
            'plain page is indexed' => ['/iki', [], 'index, follow'],
            'page 1 is indexed' => ['/iki', ['page' => '1'], 'index, follow'],
            'page 2 is noindex but followed' => ['/iki', ['page' => '2'], 'noindex, follow'],
            'filter param' => ['/iki', ['store' => 'maxima'], $noindex],
            'tracking param' => ['/iki', ['utm_source' => 'fb'], $noindex],
            'any order, even the default' => ['/iki', ['order' => 'popular'], $noindex],
            'search results' => ['/paieska/pienas', [], $noindex],
            'empty param ignored' => ['/iki', ['store' => ''], 'index, follow'],
        ];
    }
}
