<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PdfFlyerIncomingProcessor
{
    private const MAX_RETRY_ATTEMPTS = 5;

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

            // A sidecar file (not the PDF itself — nothing else expects it to
            // exist) carrying state from a previous partial attempt: which
            // pages still need (re)processing and the validity dates already
            // found on an earlier (now-skipped) page. Its presence is what
            // lets a retry resume instead of re-extracting the whole PDF
            // (and re-paying for/duplicating already-succeeded pages) from
            // page 1 every time ProcessPdfFlyerJob's every-minute schedule
            // picks this file back up.
            $statePath = $pdfPath . '.retry.json';
            $retryState = $this->readRetryState($statePath);
            $targetPages = $retryState['failed_pages'] ?? null;
            $seedValidityDates = $retryState['validity_dates'] ?? null;
            $attempt = ($retryState['attempts'] ?? 0) + 1;

            // ProcessPdfFlyerJob runs every minute (Kernel.php) — without this,
            // a stuck leaflet gets hammered once a minute for the whole
            // MAX_RETRY_ATTEMPTS attempts, which is pointless against
            // anything but the shortest outages and burns a Gemini call per
            // page every time. Backs off 2 min after the 1st failure,
            // growing 2 min per attempt, capped at 15 min between attempts.
            $lastAttemptedAt = $retryState['last_attempted_at'] ?? null;
            if ($lastAttemptedAt !== null) {
                $delayMinutes = min(2 * ($attempt - 1), 15);
                $nextEligibleAt = \Illuminate\Support\Carbon::parse($lastAttemptedAt)->addMinutes($delayMinutes);

                if (now()->lt($nextEligibleAt)) {
                    $this->emit($output, 'line', "Skipping {$filename} — retry {$attempt} not due until {$nextEligibleAt->toDateTimeString()} (backing off {$delayMinutes}m after attempt " . ($attempt - 1) . ').');
                    $this->emit($output, 'newline');
                    continue;
                }
            }

            $this->emit($output, 'info', $targetPages
                ? "Retrying {$filename} (slug \"{$store->slug}\" → {$store->name}), attempt {$attempt}, pages: " . implode(', ', $targetPages)
                : "Processing {$filename} (slug \"{$store->slug}\" → {$store->name})");

            try {
                $result = $this->processingService->processPdf($pdfPath, $store, $targetPages, $seedValidityDates);

                if ($result['success']) {
                    $this->emit($output, 'info', "OK — extracted: {$result['total_extracted']}, saved: {$result['count']}");

                    // A partial result means some pages hit an unrecoverable
                    // API failure (see PdfFlyerProcessingService's
                    // 'INCOMPLETE FLYER' log) — deleting the PDF here would
                    // make that leaflet's missing pages effectively
                    // unrecoverable (only a fresh manual re-upload could get
                    // them back), for what's typically a transient outage.
                    // Keep the file (and record what's still missing) so a
                    // rerun of this command resumes from just those pages
                    // once the API recovers.
                    if (!empty($result['partial'])) {
                        $pageSummaries = [];
                        foreach ($result['failed_pages'] as $pageNum => $reason) {
                            $pageSummaries[] = "page {$pageNum} ({$reason})";
                        }
                        $failedPagesSummary = implode(', ', $pageSummaries);

                        // MAX_RETRY_ATTEMPTS is well past any transient
                        // outage — stop being quiet about it so a stuck
                        // leaflet gets human attention instead of retrying
                        // forever unnoticed.
                        if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                            $this->emit($output, 'error', "STILL INCOMPLETE after {$attempt} attempts: {$failedPagesSummary} — {$filename} needs manual attention.");
                            Log::channel('flyer')->error('Flyer PDF still incomplete after many retries — needs manual attention', [
                                'path' => $pdfPath,
                                'attempts' => $attempt,
                                'failed_pages' => $result['failed_pages'],
                            ]);
                        } else {
                            $this->emit($output, 'warn', "INCOMPLETE: {$failedPagesSummary} — keeping {$filename} for a retry (attempt {$attempt}).");
                            Log::channel('flyer')->warning('Keeping partially-processed PDF for retry', [
                                'path' => $pdfPath,
                                'attempt' => $attempt,
                                'failed_pages' => $result['failed_pages'],
                            ]);
                        }

                        $this->writeRetryState($statePath, array_keys($result['failed_pages']), $result['validity_dates'] ?? null, $attempt);
                        $hadFailure = true;
                    } else {
                        @unlink($statePath);

                        if (!@unlink($pdfPath)) {
                            Log::channel('flyer')->warning('Processed PDF could not be deleted', ['path' => $pdfPath]);
                            $this->emit($output, 'warn', "Processed but could not delete file: {$pdfPath}");
                        } else {
                            $this->emit($output, 'info', "Deleted: {$filename}");
                        }
                    }
                    $this->emit($output, 'newline');
                    $this->emit($output, 'info', 'Next step: Run "sail artisan discounts:process" to finalize the discounts.');
                } else {
                    $this->emit($output, 'error', "Failed {$filename}: {$result['message']}");
                    $this->writeRetryState($statePath, array_keys($result['failed_pages'] ?? []) ?: $targetPages, $result['validity_dates'] ?? $seedValidityDates, $attempt);
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

    /** @return array{failed_pages?: array<int>, validity_dates?: array, attempts?: int}|null */
    private function readRetryState(string $statePath): ?array
    {
        if (!file_exists($statePath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($statePath), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<int> $failedPages */
    private function writeRetryState(string $statePath, array $failedPages, ?array $validityDates, int $attempts): void
    {
        file_put_contents($statePath, json_encode([
            'failed_pages' => array_values($failedPages),
            'validity_dates' => $validityDates,
            'attempts' => $attempts,
            'last_attempted_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT));
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
