<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPdfFlyerJob;
use App\Services\PdfFlyerIncomingProcessor;
use Illuminate\Console\Command;

class ProcessPdfFlyer extends Command
{
    protected $signature = 'flyers:process-pdf {--sync : Process immediately without queueing}';

    protected $description = 'Process incoming flyer PDFs from storage/app/flyers-incoming; filename must start with the store slug (e.g. aibe-1.pdf); deletes each file after successful processing';

    public function handle(PdfFlyerIncomingProcessor $processor): int
    {
        if ($this->option('sync')) {
            return $processor->processIncomingDirectory(function (string $level, string $message): void {
                if ($level === 'newline') {
                    $this->newLine();
                    return;
                }

                if ($level === 'line') {
                    $this->line($message);
                    return;
                }

                $this->{$level}($message);
            });
        }

        ProcessPdfFlyerJob::dispatch();
        $this->info('Flyer processing job queued on the flyers queue.');

        return 0;
    }
}
