<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\FlyerStorage;
use Carbon\Carbon;

class StoreFlyerTitleBuilder
{
    public function build(StoreFlyer $flyer, Store $store): string
    {
        $issuePart = $flyer->issue_number ? " Nr.{$flyer->issue_number}" : '';

        if ($flyer->title) {
            // A themed campaign title (e.g. Aibė's "Mes - Jūsų kaimynai!")
            // carries no store context or issue number at all on its own —
            // shown bare, a visitor landing straight on the page (not via
            // the store's own hub, where the logo/breadcrumb already say
            // which store) has no way to tell which store it's even for.
            // Only titles that already name the store are left untouched.
            if ($this->mentionsStore($flyer->title, $store->name)) {
                return $flyer->title;
            }

            return trim("Naujas {$store->name} leidinys - {$flyer->title}{$issuePart}");
        }

        $catalogName = $flyer->catalog_name ?: $this->defaultCatalogName($store);
        $datePart = $this->formatDateRange($flyer->valid_from, $flyer->valid_to);

        if ($store->slug === 'iki') {
            return trim("{$catalogName} akcijų ir nuolaidų leidinys{$issuePart} {$datePart}");
        }

        return trim("{$catalogName} akcijų leidinys{$issuePart} {$datePart}");
    }

    /**
     * Diacritic-insensitive substring check — a scraped title can spell the
     * store's own name with different diacritics than the stores.name row
     * does (seen live: "GRŪSTĖ" in a flyer title vs the store record's
     * "Grustė", a macron-ū vs plain-u difference that a plain case-
     * insensitive str_contains wouldn't catch).
     */
    private function mentionsStore(string $title, string $storeName): bool
    {
        $fold = fn (string $s) => strtr(mb_strtolower($s), [
            'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
            'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
        ]);

        return mb_strlen($storeName) > 0 && str_contains($fold($title), $fold($storeName));
    }

    public function toListingArray(StoreFlyer $flyer, Store $store): array
    {
        $pdfUrl = $flyer->pdf_url;
        $hasPdf = $pdfUrl && $pdfUrl !== '#';
        $slug = $flyer->slug ?? '';

        // is_active is a manually-set flag that isn't kept in sync with real
        // validity dates (seen stale true on flyers over a month expired), so
        // current/expired status is derived from valid_to instead, not it.
        $validTo = $flyer->valid_to;
        $isExpired = $validTo !== null && $validTo->lt(Carbon::today());
        // +1 because diffInDays is exclusive — on the flyer's actual last
        // valid day (valid_to == today) it returns 0, which reads as
        // "already over" even though today is still fully valid.
        $daysRemaining = $isExpired || $validTo === null
            ? null
            : (int) Carbon::today()->diffInDays($validTo) + 1;

        return [
            'title' => $this->build($flyer, $store),
            'slug' => $slug,
            'image_url' => FlyerStorage::normalizePublicUrl($flyer->image_url ?? '') ?? '',
            // Falls back to the full-size image_url for flyers processed
            // before thumbnail_url existed — cards render fine either way,
            // just heavier until flyers:regenerate-images backfills it.
            'thumbnail_url' => FlyerStorage::normalizePublicUrl($flyer->thumbnail_url ?? $flyer->image_url ?? '') ?? '',
            'view_url' => $slug
                ? "/leidinys/{$store->slug}/{$slug}"
                : ($flyer->view_url ?: "/leidinys/{$store->slug}"),
            'pdf_url' => $hasPdf ? FlyerStorage::normalizePublicUrl($pdfUrl) : null,
            'valid_from' => $flyer->valid_from ? $flyer->valid_from->format('Y-m-d') : '',
            'valid_to' => $flyer->valid_to ? $flyer->valid_to->format('Y-m-d') : '',
            'pages_count' => (int) ($flyer->pages_count ?? $flyer->pages()->count()),
            'processing_status' => $flyer->processing_status ?? StoreFlyer::STATUS_PENDING,
            'status' => $isExpired ? 'expired' : 'active',
            'days_remaining' => $daysRemaining,
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
