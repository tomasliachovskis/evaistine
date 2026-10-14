<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        $minutes = (int) $this->option('minutes');
        $process = new Process(
            ['node', 'scrapers/product-pages.js'],
            base_path(),
            array_filter([
                'SCRAPER_STORE' => $this->argument('store'),
                'SCRAPER_PAGES_MINUTES' => (string) $minutes,
                // Production is only asked which pages it still needs; the
                // pages themselves go to the local API.
                'SCRAPER_PROD_API_URL' => config('services.scrapers.api_url'),
            ])
        );
        // The budget plus room for one last 429 wait.
        $process->setTimeout(($minutes + 20) * 60);

        $process->run(function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('product-pages.js failed: ' . trim($process->getErrorOutput()));

            return 1;
        }

        return 0;
    }
}
