<?php

namespace App\Support;

use App\Models\ScraperRun;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Support\Facades\Log;
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

        Log::info("HoursScraperRunner[{$store}]: starting node scrapers/hours/scrape.js \"{$store}\"");

        $process = new Process(['node', 'scrapers/hours/scrape.js', $store], base_path());
        $process->setTimeout(1800);

        $stderr = '';
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

        if ($process->isSuccessful()) {
            $storeModel = Store::whereRaw('LOWER(name) = ?', [mb_strtolower($store)])->first();

            $itemsCount = $storeModel
                ? StoreLocation::where('store_id', $storeModel->id)->where('is_active', true)->count()
                : 0;

            Log::info("HoursScraperRunner[{$store}]: success, {$itemsCount} active location(s)");

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
                'step' => 'done',
            ]);

            return $run->refresh();
        }

        $error = substr($stderr, -4000) ?: 'Scraper exited with code ' . $process->getExitCode();
        Log::error("HoursScraperRunner[{$store}] failed: {$error}");

        $run->update([
            'status' => ScraperRun::STATUS_FAILED,
            'finished_at' => now(),
            'error' => $error,
        ]);

        return $run->refresh();
    }
}
