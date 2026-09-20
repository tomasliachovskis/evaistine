<?php

namespace App\Services;

use App\Models\StoreFlyer;
use App\Support\FlyerStorage;
use Carbon\Carbon;
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

        // Unlike StoreFlyerTitleBuilder::toListingArray()'s own status
        // check (where a null valid_to is treated as always-valid, since
        // that's just display text), a missing valid_to here means we
        // don't actually know this leaflet's validity window at all —
        // explicit choice: don't burn a Gemini call on it rather than
        // assume it's still current. lte(), not lt(): valid_to == today is
        // also skipped, by explicit request — only a valid_to strictly
        // after today is processed.
        if ($flyer->valid_to === null || $flyer->valid_to->lte(Carbon::today())) {
            $reason = $flyer->valid_to === null ? 'no valid_to set' : "expired or expires today, valid_to {$flyer->valid_to->toDateString()}";
            $this->emit($output, 'line', "Flyer #{$flyer->id} skipping discount extraction ({$reason}).");

            // discounts_processed_at stays null (nothing was actually
            // extracted) — the --pending query already excludes this row
            // going forward (valid_to filter added alongside this check),
            // so it won't loop being re-dispatched-then-skipped every 5
            // min. This marker exists only so a future "why is this still
            // pending" dig (see 2026-09-18's Oriflame queue investigation)
            // finds the reason here instead of having to re-derive it.
            $flyer->update([
                'discounts_retry_state' => [
                    'skipped_reason' => $reason,
                    'skipped_at' => now()->toDateTimeString(),
                ],
            ]);

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

                    $lastErrorMessage = reset($result['failed_pages']) ?: null;

                    $flyer->update([
                        'discounts_retry_state' => [
                            'failed_pages' => array_keys($result['failed_pages']),
                            // One representative reason string (e.g. "HTTP
                            // 429: Your project has exceeded its monthly
                            // spending cap...") — every failed page usually
                            // shares the same cause, so this alone is enough
                            // to tell what's wrong without grepping
                            // storage/logs/flyer-*.log by hand.
                            'last_error_message' => $lastErrorMessage,
                            'is_quota_exceeded' => PdfFlyerProcessingService::isQuotaExceededReason($lastErrorMessage),
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
                $lastErrorMessage = !empty($result['failed_pages']) ? reset($result['failed_pages']) : ($result['message'] ?? null);

                $flyer->update([
                    'discounts_retry_state' => [
                        'failed_pages' => array_keys($result['failed_pages'] ?? []) ?: $targetPages,
                        'last_error_message' => $lastErrorMessage,
                        'is_quota_exceeded' => PdfFlyerProcessingService::isQuotaExceededReason($lastErrorMessage),
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
