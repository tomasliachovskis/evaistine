<?php

namespace App\Console\Commands;

use App\Models\StoreFlyer;
use App\Services\StoreFlyerPageProcessingService;
use Illuminate\Console\Command;

class RegenerateFlyerImages extends Command
{
    protected $signature = 'flyers:regenerate-images
                            {id? : Specific store flyer ID (default: every ready flyer)}
                            {--force : Reprocess pages that are already .webp too}';

    protected $description = 'Backfill: resize + convert existing flyer page images (and cover thumbnail) to the smaller WebP pipeline';

    public function handle(StoreFlyerPageProcessingService $service): int
    {
        $flyers = $this->argument('id')
            ? StoreFlyer::where('id', $this->argument('id'))->get()
            : StoreFlyer::where('processing_status', StoreFlyer::STATUS_READY)->get();

        if ($flyers->isEmpty()) {
            $this->warn('No matching store flyers.');

            return 1;
        }

        $force = (bool) $this->option('force');
        $totalPagesDone = 0;
        $totalPagesSkipped = 0;

        foreach ($flyers as $flyer) {
            $pages = $flyer->pages()->orderBy('page_number')->get();

            if ($pages->isEmpty()) {
                continue;
            }

            $this->line("Flyer #{$flyer->id} ({$flyer->slug}): {$pages->count()} pages");

            foreach ($pages as $page) {
                if (!$force && str_ends_with($page->image_url, '.webp')) {
                    $totalPagesSkipped++;

                    continue;
                }

                $newUrl = $service->regeneratePageImage($flyer->id, $page->page_number, $page->image_url);

                if ($newUrl === null) {
                    $this->warn("  page {$page->page_number}: source file missing, skipped");

                    continue;
                }

                $page->update(['image_url' => $newUrl]);
                $totalPagesDone++;
            }

            $page1Url = $flyer->pages()->orderBy('page_number')->value('image_url');

            if ($page1Url) {
                $thumbnailUrl = $service->generateThumbnail($flyer->id, $page1Url);

                $flyer->update([
                    'image_url' => $page1Url,
                    'thumbnail_url' => $thumbnailUrl,
                ]);
            }
        }

        $this->info("Done. {$totalPagesDone} pages regenerated, {$totalPagesSkipped} already .webp (skipped).");

        return 0;
    }
}
