<?php

namespace App\Console\Commands;

use App\Services\CrossSourceDuplicateFinder;
use App\Services\ProductDuplicateMergeService;
use App\Services\ProductDuplicateQueryService;
use Illuminate\Console\Command;

class MergeDuplicateProducts extends Command
{
    protected $signature = 'products:merge-duplicates
        {--dry-run : Preview without DB changes}
        {--cross-source : Merge flyer vs e-shop copies of the same product (same store, period and price)}';

    protected $description = 'Find and merge duplicate products using SQL matching';

    public function handle(
        ProductDuplicateQueryService $queryService,
        ProductDuplicateMergeService $mergeService
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run mode — no database changes will be made.');
        }

        if ($this->option('cross-source')) {
            return $this->mergeCrossSource(app(CrossSourceDuplicateFinder::class), $mergeService, $dryRun);
        }

        $this->info('Searching for duplicate product pairs...');

        $pairs = $queryService->getDuplicatePairs();

        if ($pairs->isEmpty()) {
            $this->info('No duplicate pairs found.');
            return 0;
        }

        $this->info("Found {$pairs->count()} duplicate pairs.");

        if ($dryRun) {
            $this->table(
                ['ID 1', 'Name 1', 'ID 2', 'Name 2'],
                $pairs->map(fn ($pair) => [
                    $pair->id1,
                    $pair->name1,
                    $pair->id2,
                    $pair->name2,
                ])->all()
            );
        }

        $analyzedPairs = $mergeService->analyzePairs($pairs);
        $filteredPairs = $analyzedPairs
            ->filter(fn (array $result) => $result['keep'])
            ->map(fn (array $result) => $result['pair'])
            ->values();
        $skipped = $analyzedPairs->filter(fn (array $result) => !$result['keep'])->values();
        $skippedPairs = $skipped->count();

        if ($skippedPairs > 0) {
            $this->info("Skipped {$skippedPairs} pair(s) after filtering.");

            if ($dryRun) {
                $this->table(
                    ['ID 1', 'Name 1', 'ID 2', 'Name 2', 'Reason'],
                    $skipped->map(fn (array $result) => [
                        $result['pair']->id1,
                        $result['pair']->name1,
                        $result['pair']->id2,
                        $result['pair']->name2,
                        $result['reason'],
                    ])->all()
                );
            }
        }

        if ($filteredPairs->isEmpty()) {
            $this->info('No duplicate pairs left after filtering.');
            return 0;
        }

        $clusters = $mergeService->buildClusters($filteredPairs);
        $clusterCount = count(array_filter($clusters, fn (array $ids) => count($ids) >= 2));

        $this->info("Grouped into {$clusterCount} clusters.");

        $merged = $mergeService->mergeAllClusters($clusters, $dryRun);

        if (empty($merged)) {
            $this->info('No products to merge.');
            return 0;
        }

        $this->table(
            ['Duplicate ID', 'Duplicate name', 'Base ID', 'Base name'],
            array_map(fn (array $row) => [
                $row['duplicate_id'],
                $row['duplicate_name'],
                $row['base_id'],
                $row['base_name'],
            ], $merged)
        );

        $this->info('Merged ' . count($merged) . ' duplicate product(s) into base products.');

        if ($dryRun) {
            $this->warn('Dry run complete — nothing was written to the database.');
        }

        return 0;
    }

    private function mergeCrossSource(
        CrossSourceDuplicateFinder $finder,
        ProductDuplicateMergeService $mergeService,
        bool $dryRun
    ): int {
        $this->info('Searching for flyer vs e-shop duplicate pairs...');

        $pairs = $finder->findPairs();

        if ($pairs->isEmpty()) {
            $this->info('No cross-source duplicate pairs found.');
            return 0;
        }

        $merged = [];
        $rows = [];

        foreach ($pairs as $pair) {
            $result = $mergeService->mergePair($pair['flyer_product_id'], $pair['web_product_id'], $dryRun)[0];
            $merged[] = $result;

            $rows[] = [
                $pair['store'],
                $pair['price'],
                $pair['flyer_product_id'] . ' ' . $pair['flyer_name'] . ($pair['flyer_info'] ? " [{$pair['flyer_info']}]" : ''),
                $pair['web_product_id'] . ' ' . $pair['web_name'] . ($pair['web_info'] ? " [{$pair['web_info']}]" : ''),
                $pair['coverage'],
                $result['base_id'],
            ];
        }

        $this->table(['Store', 'Price', 'Flyer product', 'Web product', 'Coverage', 'Kept ID'], $rows);

        $this->info('Merged ' . count($merged) . ' cross-source duplicate product(s).');

        if ($dryRun) {
            $this->warn('Dry run complete — nothing was written to the database.');
        }

        return 0;
    }
}
