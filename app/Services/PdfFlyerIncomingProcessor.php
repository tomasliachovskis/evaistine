<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PdfFlyerIncomingProcessor
{
    public function __construct(
        private PdfFlyerProcessingService $processingService
    ) {
    }

    public function processIncomingDirectory(?callable $output = null): int
    {
        $incomingDir = storage_path('app/flyers-incoming');

        if (!is_dir($incomingDir)) {
            File::makeDirectory($incomingDir, 0755, true);
        }

        $files = File::glob($incomingDir . DIRECTORY_SEPARATOR . '*.pdf');
        if ($files === false || $files === []) {
            $this->emit($output, 'info', 'No PDF files in flyers-incoming.');
            return 0;
        }

        sort($files, SORT_STRING);

        $this->emit($output, 'info', 'Note: Flyer processing is logged to storage/logs/flyer-*.log (e.g. tail -f storage/logs/flyer-$(date +%F).log)');
        $this->emit($output, 'newline');

        $hadFailure = false;

        foreach ($files as $pdfPath) {
            $filename = basename($pdfPath);
            $storeSlug = $this->extractStoreSlugFromPdfFilename($filename);

            if ($storeSlug === '') {
                $this->emit($output, 'error', "Cannot parse store slug from filename: {$filename}");
                $hadFailure = true;
                continue;
            }

            $store = Store::where('slug', $storeSlug)->first();

            if (!$store) {
                $this->emit($output, 'error', "No store with slug \"{$storeSlug}\" for file: {$filename}");
                $this->emit($output, 'line', 'Known slugs: ' . Store::query()->orderBy('slug')->pluck('slug')->implode(', '));
                $hadFailure = true;
                continue;
            }

            $this->emit($output, 'info', "Processing {$filename} (slug \"{$store->slug}\" → {$store->name})");

            try {
                $result = $this->processingService->processPdf($pdfPath, $store, null);

                if ($result['success']) {
                    $this->emit($output, 'info', "OK — extracted: {$result['total_extracted']}, saved: {$result['count']}");
                    if (!@unlink($pdfPath)) {
                        Log::channel('flyer')->warning('Processed PDF could not be deleted', ['path' => $pdfPath]);
                        $this->emit($output, 'warn', "Processed but could not delete file: {$pdfPath}");
                    } else {
                        $this->emit($output, 'info', "Deleted: {$filename}");
                    }
                    $this->emit($output, 'newline');
                    $this->emit($output, 'info', 'Next step: Run "sail artisan discounts:process" to finalize the discounts.');
                } else {
                    $this->emit($output, 'error', "Failed {$filename}: {$result['message']}");
                    $hadFailure = true;
                }
            } catch (\Exception $e) {
                $this->emit($output, 'error', "Error processing {$filename}: {$e->getMessage()}");
                Log::channel('flyer')->error('flyers:process-pdf exception', [
                    'file' => $pdfPath,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $hadFailure = true;
            }

            $this->emit($output, 'newline');
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

    private function emit(?callable $output, string $level, string $message = ''): void
    {
        if ($output !== null) {
            $output($level, $message);
        }
    }
}
