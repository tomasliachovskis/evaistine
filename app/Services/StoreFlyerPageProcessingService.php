<?php

namespace App\Services;

use App\Models\StoreFlyer;
use App\Models\StoreFlyerPage;
use App\Support\CacheVersion;
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

            $thumbnailUrl = $this->generateThumbnail($flyer->id, $pages[0]['image_url']);

            $flyer->update([
                'image_url' => $pages[0]['image_url'],
                'thumbnail_url' => $thumbnailUrl,
                'processing_status' => StoreFlyer::STATUS_READY,
                'processing_error' => null,
            ]);

            // /leidiniai and /leidinys/{store} payloads are cached for 1h —
            // without this a freshly processed flyer stays invisible until
            // that expires. Own group, not 'discounts': bumping that would
            // drop every discount/page-HTML cache entry site-wide.
            CacheVersion::bump('flyers');

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

    // Generates the small cover thumbnail (see FlyerStorage::thumbnailPath's
    // docblock) from the already-resized page-1 WebP — no need to re-decode
    // the original PDF/large image, page 1's file is already the source of
    // truth for the flyer's cover everywhere.
    public function generateThumbnail(int $flyerId, string $page1ImageUrl): ?string
    {
        $sourcePath = FlyerStorage::urlToStoragePath($page1ImageUrl);

        if (!$sourcePath || !Storage::disk('public')->exists($sourcePath)) {
            return null;
        }

        $tempDir = storage_path('app/temp/flyers');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempSource = $tempDir . '/thumb_src_' . $flyerId . '.webp';
        $tempOutput = $tempDir . '/thumb_out_' . $flyerId . '.webp';
        file_put_contents($tempSource, Storage::disk('public')->get($sourcePath));

        try {
            $imagick = new \Imagick($tempSource);

            if ($imagick->getImageWidth() > 400) {
                $imagick->resizeImage(400, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(75);
            $imagick->stripImage();
            $imagick->writeImage($tempOutput);
            $imagick->clear();
            $imagick->destroy();

            $thumbPath = FlyerStorage::thumbnailPath($flyerId);
            Storage::disk('public')->put($thumbPath, file_get_contents($tempOutput));

            return FlyerStorage::publicUrl($thumbPath);
        } finally {
            @unlink($tempSource);
            @unlink($tempOutput);
        }
    }

    // Backfill for pages processed before the resize+WebP pipeline existed
    // (flyers:regenerate-images) — resizes/reformats the EXISTING stored
    // image in place rather than re-rendering from the source PDF, so it
    // works even for flyers whose original PDF is no longer on disk.
    public function regeneratePageImage(int $flyerId, int $pageNumber, string $currentImageUrl, int $maxWidth = 1400, int $quality = 78): ?string
    {
        $sourcePath = FlyerStorage::urlToStoragePath($currentImageUrl);

        if (!$sourcePath || !Storage::disk('public')->exists($sourcePath)) {
            return null;
        }

        $tempDir = storage_path('app/temp/flyers');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempSource = $tempDir . '/regen_src_' . $flyerId . '_' . $pageNumber;
        $tempOutput = $tempDir . '/regen_out_' . $flyerId . '_' . $pageNumber . '.webp';
        file_put_contents($tempSource, Storage::disk('public')->get($sourcePath));

        try {
            $imagick = new \Imagick($tempSource);

            if ($imagick->getImageWidth() > $maxWidth) {
                $imagick->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality($quality);
            $imagick->stripImage();
            $imagick->writeImage($tempOutput);
            $imagick->clear();
            $imagick->destroy();

            $newPath = FlyerStorage::pagePath($flyerId, $pageNumber);
            Storage::disk('public')->put($newPath, file_get_contents($tempOutput));

            if ($sourcePath !== $newPath) {
                Storage::disk('public')->delete($sourcePath);
            }

            return FlyerStorage::publicUrl($newPath);
        } finally {
            @unlink($tempSource);
            @unlink($tempOutput);
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

            // Print-master PDFs (Elimart) render as CMYK, which WebP can't
            // hold. And spatie/pdf-to-image only knows jpg/png, so for a
            // .webp path getImageData() already did setFormat('jpg'), which
            // wins over setImageFormat() below: every page was written as
            // a JPEG named .webp (found 2026-10-02, ~2.5x the size).
            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }
            $imagick->setFormat('webp');
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
