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
}
