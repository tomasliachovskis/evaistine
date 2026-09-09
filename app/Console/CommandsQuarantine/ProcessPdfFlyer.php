<?php

namespace App\Console\CommandsQuarantine;

use App\Services\PdfFlyerIncomingProcessor;
use Illuminate\Console\Command;

// QUARANTINED 2026-09-09: nothing writes into storage/app/flyers-incoming/
// anymore (scrapers/flyers/_shared.js's submitFlyer() used to be the only
// writer — it now pushes straight to storage/app/public/flyers/pdfs/ only).
// discount extraction happens via flyers:process-discounts, reading
// StoreFlyer.pdf_url directly. The queued-dispatch branch this command used
// to have (ProcessPdfFlyerJob::dispatch()) was removed along with that job
// class — this --sync path against PdfFlyerIncomingProcessor is kept only
// as a manual fallback if a PDF is ever dropped into that directory by hand.
class ProcessPdfFlyer extends Command
{
    protected $signature = 'flyers:process-pdf {--sync : Process immediately without queueing}';

    protected $description = 'Process incoming flyer PDFs from storage/app/flyers-incoming; filename must start with the store slug (e.g. aibe-1.pdf); deletes each file after successful processing';

    public function handle(PdfFlyerIncomingProcessor $processor): int
    {
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
}
