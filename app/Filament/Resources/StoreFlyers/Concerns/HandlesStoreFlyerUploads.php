<?php

namespace App\Filament\Resources\StoreFlyers\Concerns;

use App\Jobs\ProcessStoreFlyerPagesJob;
use App\Models\Store;
use App\Models\StoreFlyer;
use App\Services\StoreFlyerSlugBuilder;
use App\Support\FlyerStorage;

trait HandlesStoreFlyerUploads
{
    protected bool $shouldProcessPages = false;

    protected function fillUploadFields(array $data): array
    {
        if (!empty($data['pdf_url'])) {
            $path = FlyerStorage::urlToStoragePath($data['pdf_url']);

            if ($path) {
                $data['pdf_upload'] = $path;
            }
        }

        return $data;
    }

    protected function processFlyerFormData(array $data, ?StoreFlyer $existing = null): array
    {
        $data['source'] = $data['source'] ?? 'manual';
        $store = Store::find($data['store_id'] ?? null);
        $pdfUpload = $data['pdf_upload'] ?? $this->form->getRawState()['pdf_upload'] ?? null;
        $hadPdfUpload = !empty($pdfUpload);

        if ($store && !empty($data['title']) && !empty($data['valid_from']) && !empty($data['valid_to'])) {
            $shouldRegenerateSlug = !$existing
                || $existing->title !== $data['title']
                || $existing->valid_from?->format('Y-m-d') !== $data['valid_from']
                || $existing->valid_to?->format('Y-m-d') !== $data['valid_to'];

            if (empty($data['slug']) || $shouldRegenerateSlug) {
                $data['slug'] = app(StoreFlyerSlugBuilder::class)->build(
                    $store,
                    $data['title'],
                    $data['valid_from'],
                    $data['valid_to'],
                    $existing?->id
                );
            }

            $data['view_url'] = "/leidinys/{$store->slug}/{$data['slug']}";
        }

        unset($data['pdf_upload']);

        if ($hadPdfUpload) {
            $data['processing_status'] = StoreFlyer::STATUS_PENDING;
            $this->shouldProcessPages = true;
        }

        return $data;
    }

    protected function syncPdfUploadFromForm(StoreFlyer $flyer): void
    {
        $formState = $this->form->getState();
        $rawState = $this->form->getRawState();
        $pdfUpload = $formState['pdf_upload'] ?? $rawState['pdf_upload'] ?? null;

        if (FlyerStorage::finalizeFlyerPdf($flyer, $pdfUpload)) {
            $this->shouldProcessPages = true;
        }
    }

    protected function dispatchPageProcessing(StoreFlyer $flyer): void
    {
        $flyer = $flyer->fresh();

        if (!$this->shouldProcessPages || !$flyer?->pdf_url) {
            return;
        }

        if (config('queue.default') === 'sync') {
            ProcessStoreFlyerPagesJob::dispatchSync($flyer->id);
        } else {
            ProcessStoreFlyerPagesJob::dispatch($flyer->id);
        }

        $this->shouldProcessPages = false;
    }

    protected function dispatchStoreFlyerPageProcessing(StoreFlyer $flyer): void
    {
        if (!$flyer->pdf_url) {
            return;
        }

        if (config('queue.default') === 'sync') {
            ProcessStoreFlyerPagesJob::dispatchSync($flyer->id);
        } else {
            ProcessStoreFlyerPagesJob::dispatch($flyer->id);
        }
    }
}
