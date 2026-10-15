<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

// Runs scrapers/product-pages.js for one pharmacy: its product pages, slowly,
// on their own nightly run. Each page gives the barcode (EAN cache for the
// main scrape; Gintarinė's main scrape fetches no product pages since the
// 429s of 2026-10-13) and the text the product facts are extracted from
// (docs/product-facts.md). Posts to this machine's own API, not to
// SCRAPER_API_URL: the facts are extracted here and pushed on by
// product-facts:push.
class FetchProductPages extends Command
{
    protected $signature = 'scrapers:fetch-product-pages
                            {store : gintarine-vaistine, apotheka or mano-vaistine}
                            {--minutes=140 : Time budget for tonight}';

    protected $description = "Fetch a pharmacy's product pages slowly for EANs and product facts";

    public function handle(): int
    {
        $store = $this->argument('store');
        $minutes = (int) $this->option('minutes');
        $process = new Process(
            ['node', 'scrapers/product-pages.js'],
            base_path(),
            array_filter([
                'SCRAPER_STORE' => $store,
                'SCRAPER_PAGES_MINUTES' => (string) $minutes,
                // Production is only asked which pages it still needs; the
                // pages themselves go to the local API.
                'SCRAPER_PROD_API_URL' => config('services.scrapers.api_url'),
            ])
        );
        // The budget plus room for one last 429 wait.
        $process->setTimeout(($minutes + 20) * 60);

        // Started by the scheduler, whose output goes nowhere: keep it next to
        // the nightly scrape logs (scripts/nightly-scrape.sh deletes them after
        // 30 days) and note problems in problems.log.
        $logDir = storage_path('logs/nightly');
        File::ensureDirectoryExists($logDir);
        $logFile = $logDir . '/' . now()->format('Y-m-d') . "-product-pages-{$store}.log";
        $log = fn (string $text) => File::append($logFile, $text);
        $log('=== ' . now()->format('Y-m-d H:i:s') . " start {$store}, {$minutes} min\n");

        $stderr = '';
        $aborted = null;

        try {
            $process->run(function (string $type, string $buffer) use (&$stderr, $log) {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                }
                $log($buffer);
                $this->output->write($buffer);
            });
        } catch (ProcessTimedOutException|ProcessSignaledException $e) {
            $aborted = $e->getMessage();
        }

        $problems = collect(preg_split('/\R/', $stderr))->map(fn ($line) => trim($line))->filter()->values();
        $failed = $aborted || !$process->isSuccessful();
        $status = $failed ? 'FAILED' : ($problems->isNotEmpty() ? 'WARN' : 'OK');
        $log('=== ' . now()->format('Y-m-d H:i:s') . " {$status}" . ($aborted ? ": {$aborted}" : '') . "\n");

        if ($status !== 'OK') {
            $lines = ["== " . now()->format('Y-m-d') . " (details in " . basename($logFile) . ")"];
            $lines[] = sprintf('%-8s product pages %s%s', $status, $store, $problems->isNotEmpty() ? "  {$problems->count()} problem line(s)" : '');
            foreach ($problems->take(3) as $line) {
                $lines[] = '         - ' . mb_substr($line, 0, 200);
            }
            if ($aborted) {
                $lines[] = '         - ' . $aborted;
            }
            File::append($logDir . '/problems.log', implode("\n", $lines) . "\n");
        }

        if ($failed) {
            $this->error('product-pages.js failed: ' . ($aborted ?? trim($process->getErrorOutput())));

            return 1;
        }

        return 0;
    }
}
