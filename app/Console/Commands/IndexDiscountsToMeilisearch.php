<?php

namespace App\Console\Commands;

use App\Services\MeilisearchService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IndexDiscountsToMeilisearch extends Command
{
    protected $signature = 'discounts:index-meilisearch {--with-ssh-tunnel : Establish SSH tunnel before indexing}';
    protected $description = 'Index all active discounts to Meilisearch';

    protected $meilisearchService;

    public function __construct(MeilisearchService $meilisearchService)
    {
        parent::__construct();
        $this->meilisearchService = $meilisearchService;
    }

    public function handle()
    {
        if ($this->option('with-ssh-tunnel') && !$this->establishSshTunnel()) {
            return 1;
        }

        $this->info('Starting to index active discounts to Meilisearch...');

        try {
            $count = $this->meilisearchService->indexAllActiveDiscounts();
            $this->info("Successfully indexed {$count} active discounts to Meilisearch.");
            
            $stats = $this->meilisearchService->getIndexStats();
            if ($stats) {
                $this->info("Index now contains {$stats['numberOfDocuments']} documents.");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to index discounts: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function establishSshTunnel(): bool
    {
        $deployKeyPath = base_path('deploy_key');
        if (!file_exists($deployKeyPath)) {
            $this->error('deploy_key not found. Cannot establish SSH tunnel to Meilisearch.');
            return false;
        }

        chmod($deployKeyPath, 0600);

        $this->info('Establishing SSH tunnel to Meilisearch server...');
        $sshCommand = [
            'ssh',
            '-i', $deployKeyPath,
            '-L', '127.0.0.1:7700:127.0.0.1:7700',
            '-N',
            '-f',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'LogLevel=ERROR',
            'root@tnor.l.dedikuoti.lt'
        ];

        $sshProcess = new Process($sshCommand, base_path());
        $sshProcess->setTimeout(10);
        $sshProcess->run();

        if (!$sshProcess->isSuccessful()) {
            $this->warn('SSH tunnel command exited with code: ' . $sshProcess->getExitCode());
            $this->warn('Tunnel might already be established. Continuing...');
        } else {
            $this->info('SSH tunnel established.');
        }

        sleep(2);

        return true;
    }
}
