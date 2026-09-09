<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

// Downloads a product's hotlinked store-CDN image_url once, resizes/re-encodes
// it, and stores it under storage/app/public/products/ — same disk/path
// convention ProcessDiscounts::cropProductImage() already uses for
// flyer-cropped images. Run manually (not scheduled yet) with a small
// --limit and a --sleep between requests: this hits many different store
// domains one image at a time, and hammering any single one risks getting
// rate-limited/banned the same way the scrapers themselves have to be
// careful about.
class CacheProductImages extends Command
{
    protected $signature = 'products:cache-images
                            {--limit=20 : Max number of products to process this run}
                            {--sleep=500 : Milliseconds to sleep between downloads}
                            {--dry-run : List what would be cached without downloading anything}';

    protected $description = 'Download hotlinked product image_urls once and re-serve them from local storage instead';

    private const MAX_BYTES = 102400;
    private const MAX_DIMENSION = 800;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Query-level exclusion (image_url no longer external once cached)
        // already makes back-to-back runs safe, but two runs overlapping in
        // time could both grab the same not-yet-cached batch and double the
        // requests to the same store CDN — exactly what we're trying to
        // avoid. This isn't scheduled in Kernel.php yet (no
        // ->withoutOverlapping() to lean on), so guard it here instead.
        $lock = Cache::lock('products:cache-images', 3600);
        if (!$dryRun && !$lock->get()) {
            $this->warn('Another products:cache-images run is already in progress — skipping.');

            return 1;
        }

        try {
            return $this->cacheImages($dryRun);
        } finally {
            if (!$dryRun) {
                $lock->release();
            }
        }
    }

    private function cacheImages(bool $dryRun): int
    {
        $limit = (int) $this->option('limit');
        $sleepMs = (int) $this->option('sleep');

        $appUrl = rtrim(config('app.url'), '/');

        $products = Product::where('image_from_flyer', false)
            ->whereNotNull('image_url')
            ->where('image_url', 'like', 'http%')
            ->where('image_url', 'not like', "{$appUrl}/storage/%")
            ->where(function ($query) {
                // A failed download (dead link, timeout, store CDN blocking
                // us) would otherwise get re-selected — and re-fail — on
                // every run until fixed, wasting the batch's whole --limit
                // on the same handful of stuck products instead of making
                // progress on the rest. Back off for a day before retrying.
                $query->whereNull('image_cache_failed_at')
                    ->orWhere('image_cache_failed_at', '<', now()->subDay());
            })
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->info('Nothing to cache — no products with an external image_url in this batch.');

            return 0;
        }

        $this->info("Found {$products->count()} product(s) to cache" . ($dryRun ? ' (dry run)' : '') . '.');

        if (!Storage::disk('public')->exists('products')) {
            Storage::disk('public')->makeDirectory('products');
        }

        $manager = new ImageManager(new Driver());
        $cached = 0;
        $failed = 0;

        foreach ($products as $product) {
            if ($dryRun) {
                $this->line("Would cache #{$product->id} {$product->slug}: {$product->image_url}");
                continue;
            }

            try {
                $response = Http::timeout(15)->retry(2, 500)->get($product->image_url);

                if (!$response->successful() || empty($response->body())) {
                    throw new \RuntimeException("HTTP {$response->status()}");
                }

                $image = $manager->read($response->body());
                $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

                $binary = $this->encode($image);

                $filename = $product->slug . '-' . time() . '.jpg';
                $storagePath = 'products/' . $filename;
                Storage::disk('public')->put($storagePath, $binary);

                $product->image_url = Storage::disk('public')->url($storagePath);
                $product->save();

                $cached++;
                $this->line("Cached #{$product->id} {$product->slug} -> {$storagePath}");
            } catch (\Throwable $e) {
                $failed++;
                $product->update(['image_cache_failed_at' => now()]);
                Log::warning('CacheProductImages: failed to cache image', [
                    'product_id' => $product->id,
                    'image_url' => $product->image_url,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Failed #{$product->id} {$product->slug}: {$e->getMessage()} — leaving original hotlink in place, won't retry for 24h");
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if (!$dryRun) {
            $this->info("Done: {$cached} cached, {$failed} failed.");
        }

        return 0;
    }

    private function encode(\Intervention\Image\Interfaces\ImageInterface $image): string
    {
        $image->blendTransparency('ffffff');

        $quality = 84;
        while ($quality >= 58) {
            $binary = $image->toJpeg(quality: $quality)->toString();
            if (strlen($binary) <= self::MAX_BYTES) {
                return $binary;
            }
            $quality -= 8;
        }

        $image->scaleDown(480, 480);

        return $image->toJpeg(quality: 70)->toString();
    }
}
