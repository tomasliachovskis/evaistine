<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--path= : Backup directory (default: DB_BACKUP_PATH or /var/www/backups)}
                            {--connection=mysql : Database connection name}
                            {--retention-days= : Delete backups older than N days}';

    protected $description = 'Dump the MySQL database, compress with gzip, and store under /var/www/backups';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("Connection \"{$connection}\" is not MySQL.");

            return 1;
        }

        if (! (new ExecutableFinder)->find('mysqldump')) {
            $this->error('mysqldump not found on PATH. Install mysql-client on the server.');

            return 1;
        }

        $backupPath = $this->option('path') ?: config('backup.path', '/var/www/backups');
        $retentionDays = (int) ($this->option('retention-days') ?: config('backup.retention_days', 14));

        if (! $this->ensureBackupDirectory($backupPath)) {
            return 1;
        }

        $database = $config['database'];
        $filename = sprintf('%s_%s.sql.gz', $database, now()->format('Y-m-d_His'));
        $destination = rtrim($backupPath, '/').'/'.$filename;

        $dumpArgs = $this->buildMysqldumpArgs($config);
        $shellCommand = sprintf(
            '%s | gzip > %s',
            implode(' ', array_map('escapeshellarg', $dumpArgs)),
            escapeshellarg($destination),
        );

        $this->info("Backing up \"{$database}\" to {$destination}...");

        $process = Process::fromShellCommandline($shellCommand);
        $process->setTimeout(null);
        $process->setEnv(array_filter([
            'MYSQL_PWD' => $config['password'] ?? '',
        ], fn ($value) => $value !== null && $value !== ''));

        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup failed.');
            $this->line(trim($process->getErrorOutput()));

            if (is_file($destination)) {
                @unlink($destination);
            }

            return 1;
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            $this->error('Backup file is missing or empty.');

            return 1;
        }

        chmod($destination, 0640);

        $size = $this->formatBytes(filesize($destination));
        $this->info("Backup completed: {$destination} ({$size})");

        $deleted = $this->purgeOldBackups($backupPath, $retentionDays, $database);
        if ($deleted > 0) {
            $this->info("Removed {$deleted} backup(s) older than {$retentionDays} day(s).");
        }

        return 0;
    }

    private function ensureBackupDirectory(string $backupPath): bool
    {
        if (is_dir($backupPath) && is_writable($backupPath)) {
            return true;
        }

        if (! is_dir($backupPath)) {
            try {
                File::makeDirectory($backupPath, 0750, true);
            } catch (\Throwable $e) {
                $this->error("Could not create backup directory \"{$backupPath}\": {$e->getMessage()}");

                return false;
            }
        }

        if (! is_writable($backupPath)) {
            $this->error("Backup directory is not writable: {$backupPath}");

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function buildMysqldumpArgs(array $config): array
    {
        $args = [
            'mysqldump',
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
        ];

        if (! empty($config['unix_socket'])) {
            $args[] = '--socket='.$config['unix_socket'];
        } else {
            $args[] = '--host='.$config['host'];

            if (! empty($config['port'])) {
                $args[] = '--port='.$config['port'];
            }
        }

        $args[] = $config['database'];

        return $args;
    }

    private function purgeOldBackups(string $backupPath, int $retentionDays, string $database): int
    {
        if ($retentionDays < 1) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;

        // Only this database's dumps: another app on the same server
        // (superakcijos.lt) may keep its backups in the same folder.
        foreach (glob(rtrim($backupPath, '/').'/'.$database.'_*.sql.gz') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $value, $units[$unit]);
    }
}
