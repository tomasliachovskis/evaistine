<?php

namespace App\Console\Commands;

use App\Services\ProductDuplicateMergeService;
use App\Services\ProductDuplicateQueryService;
use Illuminate\Console\Command;

class MergeDuplicateProducts extends Command
{
    protected $signature = 'products:merge-duplicates {--dry-run : Preview without DB changes}';

    protected $description = 'Find and merge duplicate products using SQL matching';

    public function handle(
        ProductDuplicateQueryService $queryService,
        ProductDuplicateMergeService $mergeService
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run mode — no database changes will be made.');
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
}
