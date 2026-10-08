<?php

namespace Tests\Unit;

use App\Support\ProductPageMeta;
use Tests\TestCase;

class ProductHeadingNameTest extends TestCase
{
    public function test_a_trailing_one_piece_count_is_dropped(): void
    {
        $this->assertSame('OMRON elektroninis termometras ECO-TEMP BASIC', ProductPageMeta::headingName('OMRON elektroninis termometras ECO-TEMP BASIC, 1 vnt'));
        $this->assertSame('Lakštinė kaukė', ProductPageMeta::headingName('Lakštinė kaukė 1 vnt.'));
    }

    public function test_real_pack_sizes_stay(): void
    {
        foreach ([
            'NUROFEN 200 mg dengtos tabletės N12',
            'Pleistrai 11 vnt',
            'PROSPAN 35 mg, 21 vnt., 5 ml',
            'MYPROTEIN sausainis, 1 vnt., 75 g',
        ] as $name) {
            $this->assertSame($name, ProductPageMeta::headingName($name));
        }
    }
}
