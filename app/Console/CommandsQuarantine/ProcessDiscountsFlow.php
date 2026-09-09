<?php

namespace App\Console\CommandsQuarantine;

use App\Models\DiscountTemp;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ProcessDiscountsFlow extends Command
{
    private const PROCESS_SKIP_STORES = ['maxima', 'norfa', 'lidl', 'rimi', 'iki'];

    protected $signature = 'discounts:process-flow {--skip-photos : Skip photo sync to frontend}';

    protected $description = 'Process discounts, archive, reindex Meilisearch, and sync product photos to frontend';

    public function handle(): int
    {
        $this->info('Starting discounts processing flow...');
        $this->newLine();

        if (!$this->isDiscountTempReadyToProcess()) {
            return 0;
        }

        if (!$this->processDiscounts()) {
            $this->error('Processing discounts failed. Aborting.');
            return 1;
        }

        if (!$this->mergeDuplicateProducts()) {
            $this->error('Merging duplicate products failed. Aborting.');
            return 1;
        }

        if (!$this->removeDuplicateActiveDiscounts()) {
            $this->error('Removing duplicate active discounts failed. Aborting.');
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

        if (!$this->clearDiscountsCache()) {
            $this->error('Clearing discounts cache failed. Aborting.');
            return 1;
        }

        if (!$this->warmDiscountsCache()) {
            $this->error('Warming discounts cache failed. Aborting.');
            return 1;
        }

        if (!$this->option('skip-photos')) {
            if (!$this->syncPhotosToFrontend()) {
                $this->warn('Photo sync failed or skipped. Continuing...');
            }
        } else {
            $this->info('Skipping photo sync (--skip-photos flag set).');
        }

        $this->newLine();
        $this->info('Discounts processing flow finished successfully!');

        return 0;
    }

    private function isDiscountTempReadyToProcess(): bool
    {
        $this->info('Checking discount_temp for unprocessed records...');

        $unprocessedCount = DiscountTemp::query()
            ->where('processed', false)
            ->count();

        if ($unprocessedCount === 0) {
            $this->warn('No unprocessed records in discount_temp. Skipping flow.');
            return false;
        }

        $latest = DiscountTemp::query()
            ->where('processed', false)
            ->latest('created_at')
            ->first();

        if ($latest->created_at->gte(now()->subMinutes(30))) {
            $this->warn(
                'Found ' . $unprocessedCount . ' unprocessed record(s), but latest "' . $latest->name . '" was created at '
                . $latest->created_at->format('Y-m-d H:i:s')
                . ' Skipping flow.'
            );
            return false;
        }

        $this->info(
            'Found ' . $unprocessedCount . ' unprocessed record(s). Latest "' . $latest->name . '" (' . $latest->store . ') from '
            . $latest->created_at->format('Y-m-d H:i:s') . ' — ready to process.'
        );
        $this->newLine();

        return true;
    }

    private function processDiscounts(): bool
    {
        $this->info('Step 1: Processing discounts...');

        $exitCode = $this->call('discounts:process', [
            '--skip-stores' => implode(',', self::PROCESS_SKIP_STORES),
            '--map-categories' => 1,
        ]);

        if ($exitCode !== 0) {
            $this->error('discounts:process failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Discounts processed successfully.');
        $this->newLine();

        return true;
    }

    private function mergeDuplicateProducts(): bool
    {
        $this->info('Step 2: Merging duplicate products...');

        $exitCode = $this->call('products:merge-duplicates');

        if ($exitCode !== 0) {
            $this->error('products:merge-duplicates failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Duplicate products merged successfully.');
        $this->newLine();

        return true;
    }

    private function removeDuplicateActiveDiscounts(): bool
    {
        $this->info('Step 3: Removing duplicate active discounts...');

        $exitCode = $this->call('discounts:remove-duplicate-active');

        if ($exitCode !== 0) {
            $this->error('discounts:remove-duplicate-active failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Duplicate active discounts removed successfully.');
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

        $this->info('Expired discounts archived successfully.');
        $this->newLine();

        return true;
    }

    private function reindexMeilisearch(): bool
    {
        $this->info('Step 5: Reindexing Meilisearch...');

        $options = [];
        if (!app()->environment('production')) {
            $options['--with-ssh-tunnel'] = true;
        }

        $exitCode = $this->call('discounts:index-meilisearch', $options);

        if ($exitCode !== 0) {
            $this->error('discounts:index-meilisearch failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Meilisearch reindexed successfully.');
        $this->newLine();

        return true;
    }

    private function clearDiscountsCache(): bool
    {
        $this->info('Step 6: Clearing discounts cache...');

        $exitCode = $this->call('cache:clear-discounts');

        if ($exitCode !== 0) {
            $this->error('cache:clear-discounts failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Discounts cache cleared successfully.');
        $this->newLine();

        return true;
    }

    private function warmDiscountsCache(): bool
    {
        $this->info('Step 7: Warming discounts cache...');

        $exitCode = $this->call('cache:warm', ['--type' => 'all']);

        if ($exitCode !== 0) {
            $this->error('cache:warm failed with exit code: ' . $exitCode);
            return false;
        }

        $this->info('Discounts cache warmed successfully.');
        $this->newLine();

        return true;
    }

    private function syncPhotosToFrontend(): bool
    {
        $this->info('Step 8: Syncing product photos to frontend (deploy-photos.sh)...');

        $deployScript = base_path('deploy-photos.sh');
        if (!file_exists($deployScript)) {
            $this->error('deploy-photos.sh not found.');
            return false;
        }

        $deployKeyPath = base_path('deploy_key');
        if (file_exists($deployKeyPath)) {
            chmod($deployKeyPath, 0600);
            $this->info('Using deploy_key for SSH authentication.');
        }

        $process = Process::fromShellCommandline('DEPLOY_PHOTOS_ON_SERVER=1 bash deploy-photos.sh', base_path());
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('Photo sync failed with exit code: ' . $process->getExitCode());
            return false;
        }

        $this->info('Product photos synced to frontend successfully.');
        $this->newLine();

        return true;
    }
}
