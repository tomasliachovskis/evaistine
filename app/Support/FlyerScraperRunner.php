<?php

namespace App\Support;

use App\Models\ScraperRun;
use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

// Mirrors ScraperRunner, but for the flyer-PDF scrapers under scrapers/flyers/
// — these don't write to discount_temp, so "items" here means new StoreFlyer
// rows (source = 'scraper') created while the process ran.
class FlyerScraperRunner
{
    public function isAnotherRunInProgress(): bool
    {
        return ScraperRun::where('type', ScraperRun::TYPE_FLYER_SCRAPE)
            ->where('status', ScraperRun::STATUS_RUNNING)
            ->where('started_at', '>=', now()->subHours(4))
            ->exists();
    }

    public function run(string $store, callable $onLine = null): ScraperRun
    {
        $file = config('flyer_scrapers')[$store] ?? null;

        if (!$file) {
            throw new \InvalidArgumentException("Unknown flyer store \"{$store}\".");
        }

        $startedAt = now();

        $run = ScraperRun::create([
            'type' => ScraperRun::TYPE_FLYER_SCRAPE,
            'store' => $store,
            'status' => ScraperRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        Log::channel('flyer')->info("FlyerScraperRunner[{$store}]: starting node scrapers/flyers/{$file}");

        $process = new Process(['node', "scrapers/flyers/{$file}"], base_path());
        $process->setTimeout(10800);

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
                ? StoreFlyer::where('store_id', $storeModel->id)
                    ->where('source', 'scraper')
                    ->where('created_at', '>=', $startedAt)
                    ->count()
                : 0;

            Log::channel('flyer')->info("FlyerScraperRunner[{$store}]: success, {$itemsCount} flyer(s)");

            $run->update([
                'status' => ScraperRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'items_count' => $itemsCount,
                'step' => 'done',
            ]);

            return $run->refresh();
        }

        $error = substr($stderr, -4000) ?: 'Scraper exited with code ' . $process->getExitCode();
        Log::channel('flyer')->error("FlyerScraperRunner[{$store}] failed: {$error}");

        $run->update([
            'status' => ScraperRun::STATUS_FAILED,
            'finished_at' => now(),
            'error' => $error,
        ]);

        return $run->refresh();
    }
}
