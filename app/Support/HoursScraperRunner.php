<?php

namespace App\Support;

use App\Models\ScraperRun;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

// Mirrors FlyerScraperRunner, but for scrapers/hours/scrape.js — a single
// generic script parameterized by store name (unlike the per-store flyer
// scraper files), since the extraction logic is identical for every chain.
class HoursScraperRunner
{
    public function isAnotherRunInProgress(): bool
    {
        return ScraperRun::where('type', ScraperRun::TYPE_HOURS_SCRAPE)
            ->where('status', ScraperRun::STATUS_RUNNING)
            ->where('started_at', '>=', now()->subHours(2))
            ->exists();
    }

    public function run(string $store, callable $onLine = null): ScraperRun
    {
        $startedAt = now();

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_HOURS_SCRAPE,
            'store' => $store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        Log::channel('scraper')->info("HoursScraperRunner[{$store}]: starting node scrapers/hours/scrape.js \"{$store}\"");

        $process = new Process(['node', 'scrapers/hours/scrape.js', $store], base_path(), array_filter(['SCRAPER_API_URL' => config('services.scrapers.api_url')]));
        $process->setTimeout(1800);
        // A scraper stuck on one page (a hung page load, the Mac asleep) prints
        // nothing; kill it after 30 min of silence instead of waiting out the
        // whole timeout.
        $process->setIdleTimeout(1800);

        $stderr = '';
        $aborted = null;

        try {
            $process->run(function (string $type, string $buffer) use (&$stderr, $onLine, $run) {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                }

                $lastLine = trim(strrchr("\n" . trim($buffer), "\n"));
                if ($lastLine !== '') {
                    $run->update(['step' => substr($lastLine, 0, 255)]);
                }

                if ($onLine) {
                    $onLine($type, $buffer);
                }
            });
        } catch (ProcessTimedOutException|ProcessSignaledException $e) {
            // Timed out, or the node process was killed: mark this store failed
            // and let --all move on to the next one.
            $aborted = $e->getMessage();
        }

        if (!$aborted && $process->isSuccessful()) {
            $storeModel = Store::whereRaw('LOWER(name) = ?', [mb_strtolower($store)])->first();

            $itemsCount = $storeModel
                ? StoreLocation::where('store_id', $storeModel->id)->where('is_active', true)->count()
                : 0;

            // Problems the scraper recovered from (a skipped page, a failed
            // retry), printed to stderr; kept on the successful run too.
            $warnings = trim(substr($stderr, -4000)) ?: null;

            Log::channel('scraper')->info("HoursScraperRunner[{$store}]: success, {$itemsCount} active location(s)");
            if ($warnings) {
                Log::channel('scraper')->warning("HoursScraperRunner[{$store}] warnings:\n{$warnings}");
            }

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
                'step' => 'done',
                'error' => $warnings,
            ]);

            return $run->refresh();
        }

        $error = $aborted
            ?? (substr($stderr, -4000) ?: 'Scraper exited with code ' . $process->getExitCode());
        Log::channel('scraper')->error("HoursScraperRunner[{$store}] failed: {$error}");

        $run->update([
            'status' => ScraperRun::STATUS_FAILED,
            'finished_at' => now(),
            'error' => $error,
        ]);

        return $run->refresh();
    }
}
