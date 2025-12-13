<?php

namespace App\Console\Commands;

use App\Services\MeilisearchService;
use Illuminate\Console\Command;

class CheckMeilisearchStatus extends Command
{
    protected $signature = 'meilisearch:status';
    protected $description = 'Check Meilisearch index status and document count';

    protected $meilisearchService;

    public function __construct(MeilisearchService $meilisearchService)
    {
        parent::__construct();
        $this->meilisearchService = $meilisearchService;
    }

    public function handle()
    {
        $this->info('Checking Meilisearch status...');

        try {
            $stats = $this->meilisearchService->getIndexStats();
            
            if ($stats) {
                $this->info('Index Stats:');
                $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            } else {
                $this->error('Could not retrieve index stats');
            }

            $this->info("\nTesting search with empty query...");
            $results = $this->meilisearchService->search('', [], [], 1, 5);
            $this->info("Total documents: {$results['total']}");
            $this->info("Hits returned: " . count($results['hits']));

            if (count($results['hits']) > 0) {
                $this->info("\nSample document:");
                $this->line(json_encode($results['hits'][0], JSON_PRETTY_PRINT));
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
