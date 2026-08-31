<?php

namespace App\Services;

use App\Models\StoreFlyer;
use App\Models\StoreFlyerPage;
use App\Support\FlyerStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;

class StoreFlyerPageProcessingService
{
    public function process(int $storeFlyerId, ?callable $onProgress = null): void
    {
        $flyer = StoreFlyer::with('store')->findOrFail($storeFlyerId);

        if (!$flyer->pdf_url) {
            $this->markFailed($flyer, 'PDF failas nerastas');

            return;
        }

        $flyer->update([
            'processing_status' => StoreFlyer::STATUS_PROCESSING,
            'processing_error' => null,
        ]);

        try {
            $pdfPath = $this->resolvePdfPath($flyer);

            if (!$pdfPath || !file_exists($pdfPath)) {
                throw new \RuntimeException('PDF failas diske nerastas');
            }

            $this->reportProgress($onProgress, "Flyer #{$flyer->id}: pradedamas apdorojimas", [
                'flyer_id' => $flyer->id,
                'pdf_path' => $pdfPath,
            ]);

            $this->clearPages($flyer);

            $pages = $this->convertPdfToPageImages($pdfPath, $flyer->id, $onProgress);

            if ($pages === []) {
                throw new \RuntimeException('Nepavyko konvertuoti PDF puslapių');
            }

            foreach ($pages as $page) {
                StoreFlyerPage::create([
                    'store_flyer_id' => $flyer->id,
                    'page_number' => $page['page_number'],
                    'image_url' => $page['image_url'],
                    'sort_order' => $page['page_number'],
                ]);
            }

            $flyer->update([
                'image_url' => $pages[0]['image_url'],
                'processing_status' => StoreFlyer::STATUS_READY,
                'processing_error' => null,
            ]);

            $this->reportProgress($onProgress, "Flyer #{$flyer->id}: baigta, " . count($pages) . ' puslapiai', [
                'flyer_id' => $flyer->id,
                'pages' => count($pages),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($flyer, $e->getMessage());

            $this->reportProgress($onProgress, "Flyer #{$flyer->id}: klaida – {$e->getMessage()}", [
                'flyer_id' => $flyer->id,
                'error' => $e->getMessage(),
            ], 'error');

            throw $e;
        }
    }

    private function resolvePdfPath(StoreFlyer $flyer): ?string
    {
        $storagePath = FlyerStorage::urlToStoragePath($flyer->pdf_url);

        if (!$storagePath) {
            return null;
        }

        return Storage::disk('public')->path($storagePath);
    }

    private function clearPages(StoreFlyer $flyer): void
    {
        $directory = FlyerStorage::pagesDirectory($flyer->id);

        if (Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
        }

        $flyer->pages()->delete();
    }

    private function convertPdfToPageImages(string $pdfPath, int $flyerId, ?callable $onProgress = null): array
    {
        $tempDir = storage_path('app/temp/flyers');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdf = new Pdf($pdfPath);
        $numberOfPages = $pdf->getNumberOfPages();
        $pages = [];

        $this->reportProgress($onProgress, "Flyer #{$flyerId}: rasta {$numberOfPages} puslapių", [
            'flyer_id' => $flyerId,
            'total_pages' => $numberOfPages,
        ]);

        for ($pageNumber = 1; $pageNumber <= $numberOfPages; $pageNumber++) {
            $startedAt = microtime(true);

            $this->reportProgress($onProgress, "Flyer #{$flyerId}: apdorojamas puslapis {$pageNumber}/{$numberOfPages}", [
                'flyer_id' => $flyerId,
                'page' => $pageNumber,
                'total' => $numberOfPages,
            ]);

            // WebP, not JPEG/PNG — ~25-35% smaller than JPEG at equivalent
            // visual quality, universally supported by browsers/CDNs now.
            // getImageData() (not saveImage()) so we can resize + set
            // compression quality + strip EXIF/color-profile metadata
            // before writing — the library itself has no quality knob.
            $tempImagePath = $tempDir . '/flyer_' . $flyerId . '_page_' . $pageNumber . '.webp';

            // 150 DPI (was 200) since the resize below caps the real output
            // size anyway — lower DPI just means less work rendering pixels
            // that would be thrown away.
            if (method_exists($pdf, 'setResolution')) {
                $pdf->setPage($pageNumber)->setResolution(150);
            } else {
                $pdf->setPage($pageNumber);
            }

            $imagick = $pdf->getImageData($tempImagePath);

            // 200 DPI rendering was producing ~2000x3300px pages (500KB-1.7MB
            // each) — far past anything the flyer page viewer ever displays
            // at. Cap the long edge at 1400px: still sharp on any screen
            // (including pinch-zoom), a fraction of the file size.
            if ($imagick->getImageWidth() > 1400) {
                $imagick->resizeImage(1400, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(78);
            $imagick->stripImage();
            $imagick->writeImage($tempImagePath);
            $imagick->clear();
            $imagick->destroy();

            if (!file_exists($tempImagePath)) {
                $this->reportProgress($onProgress, "Flyer #{$flyerId}: puslapis {$pageNumber}/{$numberOfPages} praleistas (failas nesukurtas)", [
                    'flyer_id' => $flyerId,
                    'page' => $pageNumber,
                    'total' => $numberOfPages,
                ], 'warning');

                continue;
            }

            $storagePath = FlyerStorage::pagePath($flyerId, $pageNumber);
            Storage::disk('public')->makeDirectory(dirname($storagePath));
            Storage::disk('public')->put($storagePath, file_get_contents($tempImagePath));
            unlink($tempImagePath);

            $pages[] = [
                'page_number' => $pageNumber,
                'image_url' => FlyerStorage::publicUrl($storagePath),
            ];

            $this->reportProgress($onProgress, "Flyer #{$flyerId}: puslapis {$pageNumber}/{$numberOfPages} išsaugotas (" . round(microtime(true) - $startedAt, 1) . 's)', [
                'flyer_id' => $flyerId,
                'page' => $pageNumber,
                'total' => $numberOfPages,
                'seconds' => round(microtime(true) - $startedAt, 2),
            ]);
        }

        return $pages;
    }

    private function reportProgress(?callable $onProgress, string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('flyer')->{$level}($message, $context);

        if ($onProgress) {
            $onProgress($message, $context, $level);
        }
    }

    private function markFailed(StoreFlyer $flyer, string $message): void
    {
        $flyer->update([
            'processing_status' => StoreFlyer::STATUS_FAILED,
            'processing_error' => $message,
        ]);
    }
}
