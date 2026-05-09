<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\PdfFlyerProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessPdfFlyer extends Command
{
    protected $signature = 'flyers:process-pdf';

    protected $description = 'Process incoming flyer PDFs from storage/app/flyers-incoming; filename must start with the store slug (e.g. aibe-1.pdf); deletes each file after successful processing';

    private PdfFlyerProcessingService $processingService;

    public function __construct(PdfFlyerProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    public function handle()
    {
        $incomingDir = storage_path('app/flyers-incoming');

        if (!is_dir($incomingDir)) {
            File::makeDirectory($incomingDir, 0755, true);
        }

        $files = File::glob($incomingDir . DIRECTORY_SEPARATOR . '*.pdf');
        if ($files === false || $files === []) {
            $this->info('No PDF files in flyers-incoming.');
            return 0;
        }

        sort($files, SORT_STRING);

        $this->info('Note: Flyer processing is logged to storage/logs/flyer-*.log (e.g. tail -f storage/logs/flyer-$(date +%F).log)');
        $this->newLine();

        $hadFailure = false;

        foreach ($files as $pdfPath) {
            $filename = basename($pdfPath);
            $storeSlug = $this->extractStoreSlugFromPdfFilename($filename);

            if ($storeSlug === '') {
                $this->error("Cannot parse store slug from filename: {$filename}");
                $hadFailure = true;
                continue;
            }

            $store = Store::where('slug', $storeSlug)->first();

            if (!$store) {
                $this->error("No store with slug \"{$storeSlug}\" for file: {$filename}");
                $this->line('Known slugs: ' . Store::query()->orderBy('slug')->pluck('slug')->implode(', '));
                $hadFailure = true;
                continue;
            }

            $this->info("Processing {$filename} (slug \"{$store->slug}\" → {$store->name})");

            try {
                $result = $this->processingService->processPdf($pdfPath, $store, null);

                if ($result['success']) {
                    $this->info("OK — extracted: {$result['total_extracted']}, saved: {$result['count']}");
                    if (!@unlink($pdfPath)) {
                        Log::channel('flyer')->warning('Processed PDF could not be deleted', ['path' => $pdfPath]);
                        $this->warn("Processed but could not delete file: {$pdfPath}");
                    } else {
                        $this->info("Deleted: {$filename}");
                    }
                    $this->newLine();
                    $this->info('Next step: Run "sail artisan discounts:process" to finalize the discounts.');
                } else {
                    $this->error("Failed {$filename}: {$result['message']}");
                    $hadFailure = true;
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$filename}: {$e->getMessage()}");
                Log::channel('flyer')->error('flyers:process-pdf exception', [
                    'file' => $pdfPath,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $hadFailure = true;
            }

            $this->newLine();
        }

        return $hadFailure ? 1 : 0;
    }

    private function extractStoreSlugFromPdfFilename(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/^(.+)-(\d+)$/u', $basename, $m)) {
            return strtolower($m[1]);
        }

        if (preg_match('/^([^-]+)-/u', $basename, $m)) {
            return strtolower($m[1]);
        }

        return strtolower($basename);
    }
}
