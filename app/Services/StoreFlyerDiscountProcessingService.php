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

        // Themed/campaign catalogs (e.g. Rimi's "Grožio prekių katalogas")
        // never carry a date on the store's own listing page at all (see
        // scrapers/flyers/rimi.js's parseDateRange() comment) — they're
        // submitted with valid_to null. We still always want the date if
        // it's printed on the leaflet itself, so a cheap page-1-only peek
        // (no discounts saved) discovers it before deciding whether the
        // full (all-pages, per-page Gemini calls) discount extraction is
        // actually worth running — two separate questions: "what's the
        // date" (always attempted) vs. "should we extract discounts"
        // (only once a real, non-expired valid_to is known).
        if ($flyer->valid_to === null) {
            $discovered = $this->processingService->peekValidityDates($pdfPath, $flyer->store);

            if (!empty($discovered['start_at']) && !empty($discovered['end_at'])) {
                $flyer->update([
                    'valid_from' => $discovered['start_at'],
                    'valid_to' => $discovered['end_at'],
                ]);
                $flyer->refresh();
                $this->emit($output, 'info', "Flyer #{$flyer->id}: discovered validity dates from page 1 ({$discovered['start_at']} to {$discovered['end_at']}).");
            } else {
                $this->emit($output, 'line', "Flyer #{$flyer->id}: page 1 peek found no validity dates.");
            }
        }

        // A KNOWN valid_to in the past (or today — lte(), not lt(), by
        // explicit request) means we're certain this leaflet is stale (or,
        // after the peek above, still genuinely unknown) — skip the full
        // extraction rather than burn a Gemini call per page on something
        // that isn't going to produce live discounts anyway.
        if ($flyer->valid_to === null || $flyer->valid_to->lte(Carbon::today())) {
            $reason = $flyer->valid_to === null
                ? 'no valid_to set (page 1 peek found no date either)'
                : "expired or expires today, valid_to {$flyer->valid_to->toDateString()}";
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

        $retryState = $flyer->discounts_retry_state ?? [];
        $targetPages = $retryState['failed_pages'] ?? null;
        $seedValidityDates = $retryState['validity_dates'] ?? null;
        $attempt = ($retryState['attempts'] ?? 0) + 1;

        // 2min after 1st failure, +2min per attempt, capped at 15min —
        // backs off instead of retrying a stuck leaflet every 5 minutes.
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
