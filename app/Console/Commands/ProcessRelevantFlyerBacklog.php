<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Services\PdfFlyerProcessingService;
use App\Support\FlyerRelevanceClassifier;
use App\Support\FlyerStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessRelevantFlyerBacklog extends Command
{
    protected $signature = 'flyers:process-relevant-backlog
                            {--stores= : Comma-separated store names (default: Maxima,Lidl,Rimi,Norfa,Iki,Aibė,Šilas)}
                            {--min-days-left=3 : Skip flyers already expired or expiring within this many days. A flyer with no valid_to at all is kept — we cannot tell it is expired.}
                            {--dry-run : List what would be processed without calling Gemini}';

    protected $description = 'Process the already-downloaded flyer backlog for the given stores, skipping non-food/chemistry catalogs (FlyerRelevanceClassifier) and expired/soon-expiring ones';

    public function handle(PdfFlyerProcessingService $service): int
    {
        $storeNames = $this->option('stores')
            ? array_map('trim', explode(',', $this->option('stores')))
            : ['Maxima', 'Lidl', 'Rimi', 'Norfa', 'Iki', 'Aibė', 'Šilas'];

        $minDaysLeft = (int) $this->option('min-days-left');
        $dryRun = (bool) $this->option('dry-run');

        $toProcess = [];

        foreach ($storeNames as $storeName) {
            $store = Store::where('name', $storeName)->first();
            if (!$store) {
                $this->warn("Store not found: {$storeName}");
                continue;
            }

            $flyers = StoreFlyer::where('store_id', $store->id)->get();

            foreach ($flyers as $flyer) {
                $classification = FlyerRelevanceClassifier::classify($flyer->title);
                if ($classification !== FlyerRelevanceClassifier::INCLUDED) {
                    $this->line("SKIP [{$classification}] {$storeName}: {$flyer->title}");
                    continue;
                }

                if ($flyer->valid_to) {
                    $daysLeft = now()->diffInDays($flyer->valid_to, false);
                    if ($daysLeft < $minDaysLeft) {
                        $this->line("SKIP [expired/soon, {$daysLeft}d left] {$storeName}: {$flyer->title}");
                        continue;
                    }
                }

                $pdfStoragePath = FlyerStorage::urlToStoragePath($flyer->pdf_url);
                if (!$pdfStoragePath || !Storage::disk('public')->exists($pdfStoragePath)) {
                    $this->error("NO PDF FILE: {$storeName}: {$flyer->title} (pdf_url={$flyer->pdf_url})");
                    continue;
                }

                $toProcess[] = [
                    'store' => $store,
                    'flyer' => $flyer,
                    'pdf_path' => Storage::disk('public')->path($pdfStoragePath),
                ];
            }
        }

        $this->info('');
        $this->info('=== To process: ' . count($toProcess) . ' flyer(s) ===');
        foreach ($toProcess as $item) {
            $this->line("  [{$item['store']->name}] {$item['flyer']->title} (flyer id {$item['flyer']->id})");
        }
        $this->info('');

        if ($dryRun) {
            $this->info('Dry run — not calling Gemini.');
            return 0;
        }

        $touchedStores = [];

        foreach ($toProcess as $item) {
            $this->info("Processing [{$item['store']->name}] {$item['flyer']->title}...");

            try {
                $result = $service->processPdf($item['pdf_path'], $item['store']);
                $this->info('  -> extracted=' . ($result['total_extracted'] ?? 0) . ' saved=' . ($result['count'] ?? 0) . ' success=' . (($result['success'] ?? false) ? 'yes' : 'no'));
                $touchedStores[$item['store']->name] = true;
            } catch (\Throwable $e) {
                $this->error('  -> FAILED: ' . $e->getMessage());
            }
        }

        $this->info('');
        $this->info('Done. Touched stores: ' . implode(', ', array_keys($touchedStores)));
        $this->info('Run `discounts:process --only-store=<name> --map-categories` for each to finalize into real Discount rows.');

        return 0;
    }
}
