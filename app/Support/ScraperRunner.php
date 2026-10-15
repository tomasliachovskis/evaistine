<?php

namespace App\Support;

use App\Models\DiscountTemp;
use App\Models\ScraperRun;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

// Scrapers only ever run locally (against the local app, per the scrapers'
// own POST to 127.0.0.1/api/scrapers) — there's no queue worker guarantee on
// a dev machine, so this runs synchronously from the command instead of
// going through a queued job. "Max one at a time" is enforced by checking
// scraper_runs for an in-progress row rather than a queue-level lock.
class ScraperRunner
{
    public function isAnotherRunInProgress(): bool
    {
        // A "running" row older than the process timeout (10800s = 3h) can
        // only mean the PHP process was killed before it could update the
        // row (e.g. SIGKILL doesn't reach the child node process/DB update) —
        // treat it as abandoned rather than let it block every future run.
        return ScraperRun::where('type', ScraperRun::TYPE_SCRAPE)
            ->where('status', ScraperRun::STATUS_RUNNING)
            ->where('started_at', '>=', now()->subHours(4))
            ->exists();
    }

    public function run(string $store, callable $onLine = null): ScraperRun
    {
        $file = config('scrapers')[$store] ?? null;

        if (!$file) {
            throw new \InvalidArgumentException("Unknown store \"{$store}\".");
        }

        $startedAt = now();

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_SCRAPE,
            'store' => $store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        Log::channel('scraper')->info("ScraperRunner[{$store}]: starting node scrapers/{$file}");

        $process = new Process(['node', "scrapers/{$file}"], base_path(), array_filter(['SCRAPER_API_URL' => config('services.scrapers.api_url')]));
        $process->setTimeout(10800);
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

                // Surface the scraper's own output as the run's current step, so
                // the dashboard shows live progress instead of just "running".
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
            // Rows posted to another app (production, SCRAPER_API_URL) never
            // land in this DB, so take the count from the scraper's own
            // summary line ("{store}: 168 rows, ...") instead.
            $itemsCount = config('services.scrapers.api_url')
                && preg_match('/: (\d+) rows,/', $process->getOutput(), $matches)
                ? (int) $matches[1]
                : DiscountTemp::whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
                    ->where('created_at', '>=', $startedAt)
                    ->count();

            // Problems the scraper recovered from (a skipped page, a failed
            // retry), printed to stderr; kept on the successful run too.
            $warnings = trim(substr($stderr, -4000)) ?: null;

            Log::channel('scraper')->info("ScraperRunner[{$store}]: success, {$itemsCount} row(s)");
            if ($warnings) {
                Log::channel('scraper')->warning("ScraperRunner[{$store}] warnings:\n{$warnings}");
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
        Log::channel('scraper')->error("ScraperRunner[{$store}] failed: {$error}");

        $run->update([
            'status' => ScraperRun::STATUS_FAILED,
            'finished_at' => now(),
            'error' => $error,
        ]);

        return $run->refresh();
    }
}
