<?php

namespace Tests\Unit;

use App\Services\ProductDuplicateMergeService;
use PHPUnit\Framework\TestCase;

class ProductDuplicateMergeServiceTest extends TestCase
{
    public function test_build_clusters_groups_transitive_pairs(): void
    {
        $service = new ProductDuplicateMergeService();

        $pairs = collect([
            (object) ['id1' => 1, 'id2' => 2],
            (object) ['id1' => 2, 'id2' => 3],
        ]);

        $clusters = $service->buildClusters($pairs);

        $this->assertCount(1, $clusters);
        $this->assertEqualsCanonicalizing([1, 2, 3], $clusters[0]);
    }

    public function test_build_clusters_keeps_separate_groups(): void
    {
        $service = new ProductDuplicateMergeService();

        $pairs = collect([
            (object) ['id1' => 1, 'id2' => 2],
            (object) ['id1' => 10, 'id2' => 11],
        ]);

        $clusters = $service->buildClusters($pairs);

        $this->assertCount(2, $clusters);
    }

    public function test_cross_source_name_prefers_web_format(): void
    {
        $service = new ProductDuplicateMergeService();

        $this->assertSame(
            'Kefyras NAMINIS, 2,5% rieb., 0.9 kg',
            $service->pickCrossSourceName('Kefyras ROKIŠKIO NAMINIS, 0.9 kg', 'Kefyras NAMINIS, 2,5% rieb., 0.9 kg')
        );
    }

    public function test_cross_source_name_drops_variant_count(): void
    {
        $service = new ProductDuplicateMergeService();

        $this->assertSame(
            'Kavos pupelės HIMMEL, 1 kg',
            $service->pickCrossSourceName('Kavos pupelės HIMMEL, 1 kg', 'Kavos pupelės HIMMEL (3 rūšių), 1 kg')
        );
        $this->assertSame(
            'Kreminis jogurtas PIENO ROJUS, 150 g',
            $service->pickCrossSourceName('Kreminis jogurtas PIENO ROJUS, 150 g', 'Kreminis jogurtas PIENO ROJUS (2 rūš.), 150 g')
        );
        $this->assertSame(
            'Želė DR. OETKER, 72 g',
            $service->pickCrossSourceName('Želė DR. OETKER, 72 g', 'Želė DR. OETKER (įv. rūšių), 72 g')
        );
    }

    public function test_cross_source_name_falls_back_to_less_abbreviated_flyer_name(): void
    {
        $service = new ProductDuplicateMergeService();

        $this->assertSame(
            'Šaldytos bulvių lazdelės NATALI, 1 kg',
            $service->pickCrossSourceName('Šaldytos bulvių lazdelės NATALI, 1 kg', 'Šald.bulvių lazdelės NATALI (2 rūš.), 1 kg')
        );
        $this->assertSame(
            'ŠEIMOS vytinta dešra, 200 g',
            $service->pickCrossSourceName('ŠEIMOS vytinta dešra, 200 g', 'ŠEIMOS vytinta dešra, a. r., 200 g')
        );
        $this->assertSame(
            'Marinuoti šonkauliai BBQ medaus marinate, 1 kg',
            $service->pickCrossSourceName('Marinuoti šonkauliai BBQ medaus marinate, 1 kg', 'Marin. šonkauliai BBQ medaus marinate, 1 kg')
        );
    }

    public function test_strip_variant_count_moves_it_out_of_the_name(): void
    {
        $service = new ProductDuplicateMergeService();

        $this->assertSame(
            ['Pjaustyta lašišų filė VIČI, 100 g', '2 rūšių'],
            $service->stripVariantCount('Pjaustyta lašišų filė VIČI (2 rūšių), 100 g')
        );
        $this->assertSame(
            ['Kepti žuvies kukuliai EDEGA, 320 g', '2 rūš.'],
            $service->stripVariantCount('Kepti žuvies kukuliai EDEGA (2 rūš.), 320 g')
        );
        $this->assertSame(
            ['Kavos pupelės HIMMEL, 1 kg', null],
            $service->stripVariantCount('Kavos pupelės HIMMEL, 1 kg')
        );
    }
}
