<?php

namespace App\Support;

use App\Models\DiscountTemp;
use App\Models\ScraperRun;
use Illuminate\Support\Facades\Log;
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

        Log::info("ScraperRunner[{$store}]: starting node scrapers/{$file}");

        $process = new Process(['node', "scrapers/{$file}"], base_path());
        $process->setTimeout(10800);

        $stderr = '';
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

        if ($process->isSuccessful()) {
            $itemsCount = DiscountTemp::whereRaw('LOWER(store) = ?', [mb_strtolower($store)])
                ->where('created_at', '>=', $startedAt)
                ->count();

            Log::info("ScraperRunner[{$store}]: success, {$itemsCount} row(s)");

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
                'step' => 'done',
            ]);

            return $run->refresh();
        }

        $error = substr($stderr, -4000) ?: 'Scraper exited with code ' . $process->getExitCode();
        Log::error("ScraperRunner[{$store}] failed: {$error}");

        $run->update([
            'status' => ScraperRun::STATUS_FAILED,
            'finished_at' => now(),
            'error' => $error,
        ]);

        return $run->refresh();
    }
}
