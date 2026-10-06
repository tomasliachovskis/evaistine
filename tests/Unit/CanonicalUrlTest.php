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
            'plain path' => ['/akcijos/iki', [], 'https://evaistine.lt/akcijos/iki'],
            'page 1 dropped' => ['/akcijos/iki', ['page' => '1'], 'https://evaistine.lt/akcijos/iki'],
            'page 2 kept' => ['/akcijos/iki', ['page' => '2'], 'https://evaistine.lt/akcijos/iki?page=2'],
            'default order dropped' => ['/akcijos/iki', ['order' => 'popular'], 'https://evaistine.lt/akcijos/iki'],
            'other order kept' => ['/akcijos/iki', ['order' => 'price_min'], 'https://evaistine.lt/akcijos/iki?order=price_min'],
            'filters and tracking stripped' => [
                '/akcijos/iki',
                ['store' => 'maxima', 'category' => 'pienas', 'card' => '1', 'plus' => '1', 'utm_source' => 'fb', 'gclid' => 'x'],
                'https://evaistine.lt/akcijos/iki',
            ],
            'page kept, filter stripped' => ['/akcijos/iki', ['page' => '3', 'store' => 'maxima'], 'https://evaistine.lt/akcijos/iki?page=3'],
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
            'plain page is indexed' => ['/akcijos/iki', [], 'index, follow'],
            'page 1 is indexed' => ['/akcijos/iki', ['page' => '1'], 'index, follow'],
            'page 2 is noindex but followed' => ['/akcijos/iki', ['page' => '2'], 'noindex, follow'],
            'filter param' => ['/akcijos/iki', ['store' => 'maxima'], $noindex],
            'tracking param' => ['/akcijos/iki', ['utm_source' => 'fb'], $noindex],
            'any order, even the default' => ['/akcijos/iki', ['order' => 'popular'], $noindex],
            'search results' => ['/akcijos/paieska/pienas', [], $noindex],
            'empty param ignored' => ['/akcijos/iki', ['store' => ''], 'index, follow'],
        ];
    }
}
