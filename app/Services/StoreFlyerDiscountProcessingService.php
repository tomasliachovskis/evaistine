<?php

namespace App\Services;

use App\Models\StoreFlyer;
use App\Support\FlyerStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StoreFlyerDiscountProcessingService
{
    private const MAX_RETRY_ATTEMPTS = 5;

    public function __construct(
        private PdfFlyerProcessingService $processingService
    ) {
    }

    public function process(int $storeFlyerId, ?callable $output = null): void
    {
        $flyer = StoreFlyer::with('store')->findOrFail($storeFlyerId);

        if ($flyer->discounts_processed_at !== null) {
            $this->emit($output, 'line', "Flyer #{$flyer->id} already has discounts processed, skipping.");

            return;
        }

        if (!$flyer->pdf_url || !$flyer->store) {
            $this->emit($output, 'warn', "Flyer #{$flyer->id} has no PDF or store, skipping.");

            return;
        }

        $relativePath = FlyerStorage::urlToStoragePath($flyer->pdf_url);
        $pdfPath = $relativePath ? Storage::disk('public')->path($relativePath) : null;

        if (!$pdfPath || !file_exists($pdfPath)) {
            $this->emit($output, 'error', "Flyer #{$flyer->id}: PDF file not found on disk ({$flyer->pdf_url}).");

            return;
        }

        $retryState = $flyer->discounts_retry_state ?? [];
        $targetPages = $retryState['failed_pages'] ?? null;
        $seedValidityDates = $retryState['validity_dates'] ?? null;
        $attempt = ($retryState['attempts'] ?? 0) + 1;

        // Same backoff shape as PdfFlyerIncomingProcessor's flyers-incoming/
        // sidecar-file retry state (2min after 1st failure, +2min per
        // attempt, capped at 15min) — kept identical so a stuck leaflet
        // isn't hammered every 5 minutes regardless of which of the two
        // paths it came through.
        $lastAttemptedAt = $retryState['last_attempted_at'] ?? null;
        if ($lastAttemptedAt !== null) {
            $delayMinutes = min(2 * ($attempt - 1), 15);
            $nextEligibleAt = \Illuminate\Support\Carbon::parse($lastAttemptedAt)->addMinutes($delayMinutes);

            if (now()->lt($nextEligibleAt)) {
                $this->emit($output, 'line', "Skipping flyer #{$flyer->id} — retry {$attempt} not due until {$nextEligibleAt->toDateTimeString()}.");

                return;
            }
        }

        $this->emit($output, 'info', $targetPages
            ? "Flyer #{$flyer->id} ({$flyer->store->name}): retrying, attempt {$attempt}, pages: " . implode(', ', $targetPages)
            : "Flyer #{$flyer->id} ({$flyer->store->name}): extracting discounts from {$pdfPath}");

        try {
            $result = $this->processingService->processPdf($pdfPath, $flyer->store, $targetPages, $seedValidityDates);

            if ($result['success']) {
                $this->emit($output, 'info', "OK — extracted: {$result['total_extracted']}, saved: {$result['count']}");

                if (!empty($result['partial'])) {
                    if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                        Log::channel('flyer')->error('Store flyer discounts still incomplete after many retries — needs manual attention', [
                            'store_flyer_id' => $flyer->id,
                            'attempts' => $attempt,
                            'failed_pages' => $result['failed_pages'],
                        ]);
                        $this->emit($output, 'error', "Flyer #{$flyer->id} STILL INCOMPLETE after {$attempt} attempts — needs manual attention.");
                    } else {
                        $this->emit($output, 'warn', "Flyer #{$flyer->id} INCOMPLETE, will retry (attempt {$attempt}).");
                    }

                    $flyer->update([
                        'discounts_retry_state' => [
                            'failed_pages' => array_keys($result['failed_pages']),
                            'validity_dates' => $result['validity_dates'] ?? null,
                            'attempts' => $attempt,
                            'last_attempted_at' => now()->toDateTimeString(),
                        ],
                    ]);
                } else {
                    $flyer->update([
                        'discounts_processed_at' => now(),
                        'discounts_retry_state' => null,
                    ]);
                    $this->emit($output, 'info', "Flyer #{$flyer->id}: fully processed.");
                }
            } else {
                $this->emit($output, 'error', "Flyer #{$flyer->id} failed: {$result['message']}");
                $flyer->update([
                    'discounts_retry_state' => [
                        'failed_pages' => array_keys($result['failed_pages'] ?? []) ?: $targetPages,
                        'validity_dates' => $result['validity_dates'] ?? $seedValidityDates,
                        'attempts' => $attempt,
                        'last_attempted_at' => now()->toDateTimeString(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            $this->emit($output, 'error', "Flyer #{$flyer->id} exception: {$e->getMessage()}");
            Log::channel('flyer')->error('flyers:process-discounts exception', [
                'store_flyer_id' => $flyer->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function emit(?callable $output, string $level, string $message): void
    {
        if ($output !== null) {
            $output($level, $message);
        }
    }
}
