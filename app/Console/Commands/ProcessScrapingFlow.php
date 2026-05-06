<?php

namespace App\Console\Commands;

use App\Models\DiscountTemp;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ProcessScrapingFlow extends Command
{
    protected $signature = 'scraping:process-all {--skip-deploy : Skip deployment step}';
    protected $description = 'Process complete product scraping flow';

    private array $expectedStores = ['Rimi', 'Lidl', 'Iki', 'Maxima', 'Norfa'];
    private array $scrapers = [
        'rimi.js',
        'lidl.js',
        'iki.js',
        'barbora.js',
        'norfa.js',
    ];

    public function handle()
    {
        $this->info('Starting complete product scraping flow...');
        $this->newLine();

        if (!$this->runScrapers()) {
            $this->error('Scraping failed. Aborting.');
            return 1;
        }

        if (!$this->validateStoresPresent()) {
            $this->error('Not all stores have data. Aborting.');
            return 1;
        }

        if (!$this->processDiscounts()) {
            $this->error('Processing discounts failed. Aborting.');
            return 1;
        }

        if (!$this->archiveExpiredDiscounts()) {
            $this->error('Archiving expired discounts failed. Aborting.');
            return 1;
        }

        if (!$this->reindexMeilisearch()) {
            $this->error('Reindexing Meilisearch failed. Aborting.');
            return 1;
        }

        if (!$this->generateDescriptions()) {
            $this->error('Generating descriptions failed. Aborting.');
            return 1;
        }

        if (!$this->option('skip-deploy')) {
            if (!$this->deploy()) {
                $this->warn('Deployment failed or skipped. Continuing...');
            }
        } else {
            $this->info('Skipping deployment step (--skip-deploy flag set).');
        }

        $this->newLine();
        $this->info('Complete product scraping flow finished successfully!');
        return 0;
    }

    private function runScrapers(): bool
    {
        $this->info('Step 1: Running node scrapers...');

        foreach ($this->scrapers as $scraper) {
            $this->info("Running scraper: {$scraper}");

            $process = new Process(['node', "scrapers/{$scraper}"], base_path());
            $process->setTimeout(10800);
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    $this->error($buffer);
                } else {
                    $this->line($buffer);
                }
            });

            if (!$process->isSuccessful()) {
                $this->error("Scraper {$scraper} failed with exit code: {$process->getExitCode()}");
                return false;
            }

            $this->info("✓ Scraper {$scraper} completed successfully");
        }

        $this->info('All scrapers completed successfully.');
        $this->newLine();
        return true;
    }

    private function validateStoresPresent(): bool
    {
        $this->info('Step 2: Validating all stores have data from last 2 hours...');

        $storesWithData = DiscountTemp::where('processed', false)
            ->where('created_at', '>=', now()->subHours(3))
            ->distinct()
            ->pluck('store')
            ->filter()
            ->map(function ($store) {
                return strtolower(trim($store));
            })
            ->toArray();

        $expectedStoresLower = array_map('strtolower', $this->expectedStores);
        $missingStores = array_diff($expectedStoresLower, $storesWithData);

        if (!empty($missingStores)) {
            $missingStoresFormatted = array_map('ucfirst', $missingStores);
            $this->error('Missing stores in discount_temp (last 2 hours): ' . implode(', ', $missingStoresFormatted));
            $foundStoresFormatted = array_map('ucfirst', $storesWithData);
            $this->info('Found stores: ' . implode(', ', $foundStoresFormatted));
            return false;
        }

        $this->info('✓ All expected stores have data from last 2 hours.');
        $this->newLine();
        return true;
    }

    private function processDiscounts(): bool
    {
        $this->info('Step 3: Processing discounts...');

        $exitCode = $this->call('discounts:process');

        if ($exitCode !== 0) {
            $this->error('discounts:process failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('✓ Discounts processed successfully.');
        $this->newLine();
        return true;
    }

    private function archiveExpiredDiscounts(): bool
    {
        $this->info('Step 4: Archiving expired discounts...');

        $exitCode = $this->call('discounts:archive-expired');

        if ($exitCode !== 0) {
            $this->error('discounts:archive-expired failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('✓ Expired discounts archived successfully.');
        $this->newLine();
        return true;
    }

    private function reindexMeilisearch(): bool
    {
        $this->info('Step 5: Reindexing Meilisearch...');

        $exitCode = $this->call('discounts:index-meilisearch', ['--with-ssh-tunnel' => true]);

        if ($exitCode !== 0) {
            $this->error('discounts:index-meilisearch failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('✓ Meilisearch reindexed successfully.');
        $this->newLine();
        return true;
    }

    private function generateDescriptions(): bool
    {
        $this->info('Step 6: Generating descriptions...');

        $this->info('Generating descriptions for stores...');
        $exitCode = $this->call('descriptions:generate', ['type' => 'store', '--all' => true]);

        if ($exitCode !== 0) {
            $this->error('descriptions:generate store --all failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Generating descriptions for categories...');
        $exitCode = $this->call('descriptions:generate', ['type' => 'category', '--all' => true]);

        if ($exitCode !== 0) {
            $this->error('descriptions:generate category --all failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Generating top products tables for store+category combinations...');
        $exitCode = $this->call('descriptions:generate', ['type' => 'store-category', '--all' => true]);

        if ($exitCode !== 0) {
            $this->error('descriptions:generate store-category --all failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('✓ Descriptions generated successfully.');
        $this->newLine();
        return true;
    }

    private function deploy(): bool
    {
        $this->info('Step 7: Deploying...');

        if (!file_exists(base_path('deploy.sh'))) {
            $this->error('deploy.sh not found.');
            return false;
        }

        $deployKeyPath = base_path('deploy_key');
        if (file_exists($deployKeyPath)) {
            chmod($deployKeyPath, 0600);
            $this->info('Using deploy_key for SSH authentication.');
        } else {
            $this->warn('deploy_key not found. Using default SSH authentication.');
        }

        if (env('APP_ENV') === 'local' || $this->isRunningInDocker()) {
            if (!file_exists($deployKeyPath)) {
                $this->warn('Running in Docker/local environment. Consider adding deploy_key for deployment.');
            }
        }

        $process = new Process(['bash', 'deploy.sh'], base_path());
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('deploy.sh failed with exit code: ' . $process->getExitCode());
            return false;
        }

        $this->info('✓ Deployment completed successfully.');
        $this->newLine();
        return true;
    }

    private function isRunningInDocker(): bool
    {
        return file_exists('/.dockerenv') ||
               file_exists('/proc/self/cgroup') && strpos(file_get_contents('/proc/self/cgroup'), 'docker') !== false;
    }
}

