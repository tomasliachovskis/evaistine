<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFlyer;
use App\Support\FlyerStorage;
use App\Support\PharmacyName;
use Carbon\Carbon;

class StoreFlyerTitleBuilder
{
    public function build(StoreFlyer $flyer, Store $store): string
    {
        $issuePart = $flyer->issue_number ? " Nr.{$flyer->issue_number}" : '';

        if ($flyer->title) {
            $title = $this->tidyCase($flyer->title);

            // Don't append $issuePart when the scraped title already states
            // the same issue number itself (seen live: a scraped title of
            // "AČIŪ savaitinis leidinys Nr. 34" plus issue_number=34
            // produced "... Nr. 34 Nr.34" — the number twice, in two
            // different spacings). Case/spacing-insensitive: source titles
            // aren't consistently formatted ("Nr.34" vs "Nr. 34").
            if ($flyer->issue_number && preg_match('/\bNr\.?\s*'.preg_quote((string) $flyer->issue_number, '/').'\b/iu', $title)) {
                $issuePart = '';
            }

            // A title that already says whose leaflet it is ("Benu mėnesio
            // leidinys", or N vaistinė's own "Norfos vaistinės spalio
            // mėnesio leidinys") is shown as is. A themed campaign title
            // ("Kartu vienas dėl kito") gets the pharmacy in front, so a
            // visitor landing straight on the page knows which one it is.
            if ($this->mentionsStore($title, $store->name) || mb_stripos($title, 'vaistin') !== false) {
                return trim("{$title}{$issuePart}");
            }

            return trim(PharmacyName::phrase($store->name, 'genitive')." leidinys „{$title}“{$issuePart}");
        }

        $catalogName = $flyer->catalog_name ?: $this->defaultCatalogName($store);
        $datePart = $this->formatDateRange($flyer->valid_from, $flyer->valid_to);

        if ($store->slug === 'iki') {
            return trim("{$catalogName} akcijų ir nuolaidų leidinys{$issuePart} {$datePart}");
        }

        return trim("{$catalogName} akcijų leidinys{$issuePart} {$datePart}");
    }

    /**
     * Pharmacy leaflet titles come half in capitals ("GINTARINĖS vaistinės
     * spalio mėnesio leidinys", "KARTU VIENAS DĖL KITO"). Words of three or
     * more letters written all in capitals are lowercased (a title with no
     * lowercase letter at all is lowercased whole) and the title gets a
     * capital first letter; short all-caps words (SPF, D3) are kept.
     */
    private function tidyCase(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $title = preg_match('/\p{Ll}/u', $title)
            ? preg_replace_callback('/\b\p{Lu}{3,}\b/u', fn ($m) => mb_strtolower($m[0]), $title)
            : mb_strtolower($title);

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
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

        // "Benu vaistinė" is named "Benu" in its own titles: the chain part
        // alone counts too.
        $chain = trim(preg_replace('/\s*vaistin\S*/iu', '', $storeName));

        return collect([$storeName, $chain])
            ->filter(fn ($name) => mb_strlen($name) >= 3)
            ->contains(fn ($name) => str_contains($fold($title), $fold($name)));
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

        return PharmacyName::phrase($store->name, 'genitive');
    }

    private function formatDateRange(?Carbon $from, ?Carbon $to): string
    {
        if (!$from || !$to) {
            return '';
        }

        return $from->format('Y.m.d') . ' - ' . $to->format('Y.m.d');
    }
}
