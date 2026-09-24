<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessDiscounts;
use PHPUnit\Framework\TestCase;

class NormalizeEanTest extends TestCase
{
    /**
     * @dataProvider eanProvider
     */
    public function test_normalize_ean(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, ProcessDiscounts::normalizeEan($input), 'Input: ' . var_export($input, true));
    }

    public static function eanProvider(): array
    {
        return [
            'EAN-13' => ['4770495346292', '4770495346292'],
            'EAN-13 with whitespace' => [' 4770495346292 ', '4770495346292'],
            'EAN-8' => ['96385074', '96385074'],
            'UPC-A' => ['111201827980', '111201827980'],
            'GTIN-14' => ['14770495346299', '14770495346299'],
            'multipack suffix' => ['4779017040533BLK', null],
            'in-store EAN-13 (GS1 prefix 2)' => ['2000000051499', null],
            'too short' => ['12345', null],
            'all zeros' => ['0000000000000', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }
}
