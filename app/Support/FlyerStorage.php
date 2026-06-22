<?php

namespace App\Support;

use App\Models\Store;
use App\Models\StoreFlyer;
use Illuminate\Support\Facades\Storage;

class FlyerStorage
{
    public static function publicUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public static function urlToStoragePath(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $storageBase = rtrim(Storage::disk('public')->url(''), '/');

        if (str_starts_with($url, $storageBase)) {
            return ltrim(substr($url, strlen($storageBase)), '/');
        }

        if (preg_match('#/storage/(.+)$#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function pdfPathForFlyer(Store $store, string $flyerSlug): string
    {
        return "flyers/pdfs/{$store->slug}-{$flyerSlug}.pdf";
    }

    public static function pagePath(int $flyerId, int $pageNumber): string
    {
        return "flyers/pages/{$flyerId}/page-{$pageNumber}.png";
    }

    public static function pagesDirectory(int $flyerId): string
    {
        return "flyers/pages/{$flyerId}";
    }

    public static function finalizeFlyerPdf(StoreFlyer $flyer, mixed $pdfUpload = null): bool
    {
        $store = $flyer->store ?? Store::find($flyer->store_id);

        if (!$store || !$flyer->slug) {
            return false;
        }

        if ($pdfUpload) {
            $processed = self::processUploads([
                'store_id' => $flyer->store_id,
                'slug' => $flyer->slug,
                'pdf_url' => $flyer->pdf_url,
                'view_url' => $flyer->view_url,
            ], ['pdf_upload' => $pdfUpload], $store, $flyer->slug);

            if (!empty($processed['pdf_url']) && $processed['pdf_url'] !== $flyer->pdf_url) {
                $flyer->update([
                    'pdf_url' => $processed['pdf_url'],
                    'view_url' => $processed['view_url'] ?? $flyer->view_url,
                    'processing_status' => StoreFlyer::STATUS_PENDING,
                ]);

                return true;
            }
        }

        if ($flyer->pdf_url) {
            return false;
        }

        $path = self::pdfPathForFlyer($store, $flyer->slug);

        if (!Storage::disk('public')->exists($path)) {
            return false;
        }

        $flyer->update([
            'pdf_url' => self::publicUrl($path),
            'processing_status' => StoreFlyer::STATUS_PENDING,
        ]);

        return true;
    }

    public static function processUploads(array $data, array $rawState, ?Store $store = null, ?string $flyerSlug = null): array
    {
        $store = $store ?: Store::find($data['store_id'] ?? null);

        if (!$store) {
            unset($data['pdf_upload']);

            return $data;
        }

        if (!empty($rawState['pdf_upload']) && $flyerSlug) {
            $path = self::normalizeUploadPath($rawState['pdf_upload']);

            if ($path && Storage::disk('public')->exists($path)) {
                $target = self::pdfPathForFlyer($store, $flyerSlug);
                $path = self::moveToTarget($path, $target);
                $data['pdf_url'] = self::publicUrl($path);
            }
        }

        if (!empty($data['slug'])) {
            $data['view_url'] = "/leidinys/{$store->slug}/{$data['slug']}";
        }

        unset($data['pdf_upload']);

        return $data;
    }

    private static function normalizeUploadPath(mixed $upload): ?string
    {
        if (is_array($upload)) {
            $upload = $upload[array_key_first($upload)] ?? null;
        }

        if (!is_string($upload) || $upload === '') {
            return null;
        }

        if (str_contains($upload, '/storage/') || str_starts_with($upload, 'http')) {
            return self::urlToStoragePath($upload) ?? $upload;
        }

        return ltrim($upload, '/');
    }

    private static function moveToTarget(string $path, string $target): string
    {
        if ($path === $target) {
            return $path;
        }

        Storage::disk('public')->makeDirectory(dirname($target));
        Storage::disk('public')->move($path, $target);

        return $target;
    }
}
