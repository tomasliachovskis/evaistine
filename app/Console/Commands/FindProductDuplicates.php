<?php

namespace App\Console\Commands;

use App\Services\ProductDuplicateFilterService;
use Illuminate\Console\Command;

class FindProductDuplicates extends Command
{
    protected $signature = 'products:find-duplicates {--category= : Filter by category ID} {--min-chunk=20 : Minimum chunk size} {--max-chunk=30 : Maximum chunk size}';

    protected $description = 'Find potential product duplicates and group them for GPT analysis';

    private ProductDuplicateFilterService $filterService;

    public function __construct(ProductDuplicateFilterService $filterService)
    {
        parent::__construct();
        $this->filterService = $filterService;
    }

    public function handle()
    {
        $categoryId = $this->option('category') ? (int) $this->option('category') : null;
        $minChunk = (int) $this->option('min-chunk');
        $maxChunk = (int) $this->option('max-chunk');

        $this->filterService->setChunkSize($minChunk, $maxChunk);

        $this->info('Searching for potential product duplicates...');

        $potentialDuplicates = $this->filterService->findPotentialDuplicates($categoryId);

        $this->info("Found {$potentialDuplicates->count()} potential duplicate groups");

        $chunks = $this->filterService->groupForGptAnalysis($potentialDuplicates);

        $this->info("Grouped into {$chunks->count()} chunks for GPT analysis");

        $this->displayResults($chunks);
    }

    private function displayResults($chunks): void
    {
        $this->newLine();

        foreach ($chunks as $index => $chunk) {
            $this->info("Chunk " . ($index + 1) . " ({$chunk->count()} products):");
            
            $tableData = $chunk->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'N/A',
                    'brand' => $product->brand ?? 'N/A',
                ];
            })->toArray();

            $this->table(['ID', 'Name', 'Category', 'Brand'], $tableData);
            $this->newLine();
        }
    }
}
