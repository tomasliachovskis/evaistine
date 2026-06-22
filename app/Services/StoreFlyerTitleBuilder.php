<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFlyer;
use Carbon\Carbon;

class StoreFlyerTitleBuilder
{
    public function build(StoreFlyer $flyer, Store $store): string
    {
        if ($flyer->title) {
            return $flyer->title;
        }

        $catalogName = $flyer->catalog_name ?: $this->defaultCatalogName($store);
        $issuePart = $flyer->issue_number ? " Nr.{$flyer->issue_number}" : '';
        $datePart = $this->formatDateRange($flyer->valid_from, $flyer->valid_to);

        if ($store->slug === 'iki') {
            return trim("{$catalogName} akcijų ir nuolaidų leidinys{$issuePart} {$datePart}");
        }

        return trim("{$catalogName} akcijų leidinys{$issuePart} {$datePart}");
    }

    public function toListingArray(StoreFlyer $flyer, Store $store): array
    {
        $pdfUrl = $flyer->pdf_url;
        $hasPdf = $pdfUrl && $pdfUrl !== '#';
        $slug = $flyer->slug ?? '';

        return [
            'title' => $this->build($flyer, $store),
            'slug' => $slug,
            'image_url' => $flyer->image_url ?? '',
            'view_url' => $slug
                ? "/leidinys/{$store->slug}/{$slug}"
                : ($flyer->view_url ?: "/leidinys/{$store->slug}"),
            'pdf_url' => $hasPdf ? $pdfUrl : null,
            'valid_from' => $flyer->valid_from ? $flyer->valid_from->format('Y-m-d') : '',
            'valid_to' => $flyer->valid_to ? $flyer->valid_to->format('Y-m-d') : '',
            'pages_count' => (int) ($flyer->pages_count ?? $flyer->pages()->count()),
            'processing_status' => $flyer->processing_status ?? StoreFlyer::STATUS_PENDING,
        ];
    }

    private function defaultCatalogName(Store $store): string
    {
        if ($store->slug === 'iki') {
            return 'IKI SAVAITĖLĖ';
        }

        return mb_strtoupper($store->name);
    }

    private function formatDateRange(?Carbon $from, ?Carbon $to): string
    {
        if (!$from || !$to) {
            return '';
        }

        return $from->format('Y.m.d') . ' - ' . $to->format('Y.m.d');
    }
}
