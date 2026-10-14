<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

// Runs scrapers/gintarine-eans.js: Gintarinė's barcodes are fetched from its
// product pages slowly, on their own nightly run, because fetching them during
// the main scrape got 429s that also cost listing pages (2026-10-13). The main
// scrape reads the cache this fills. See docs/evaistine.md, "E-shop scrapers".
class FetchGintarineEans extends Command
{
    protected $signature = 'scrapers:fetch-eans {--minutes=140 : Time budget for tonight}';

    protected $description = "Fetch Gintarinė product barcodes slowly into the scraper's EAN cache";

    public function handle(): int
    {
        $process = new Process(
            ['node', 'scrapers/gintarine-eans.js'],
            base_path(),
            ['SCRAPER_EAN_MINUTES' => (string) (int) $this->option('minutes')]
        );
        // The budget plus room for one last 429 wait.
        $process->setTimeout(((int) $this->option('minutes') + 20) * 60);

        $process->run(function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('gintarine-eans.js failed: ' . trim($process->getErrorOutput()));

            return 1;
        }

        return 0;
    }
}
