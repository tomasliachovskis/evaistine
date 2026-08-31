<?php

namespace App\Support;

// Ported from discount/src/lib/product-page-meta.ts — only the pieces that
// drive visible product-page UI (hero copy, price-deal signal, FAQ, follower
// count). Deliberately NOT ported: popular-search-link generation, attribute
// chips, promo-banner copy, store-links-row — lower-value/SEO-only pieces of
// a 1700-line source file; the product/discount data plumbing already exists
// in AkcijosController/ProductController, this class is presentation-only.
class ProductPageMeta
{
    private const PRODUCT_FOLLOWER_COUNT_OPTIONS = [42, 58, 73, 91, 104, 118, 124, 136, 157, 183];

    private const VOLUME_PATTERN = '/\b(\d+(?:[.,]\d+)?\s*(?:ml|l|kg|g|vnt\.?|vnt))\b/iu';

    public static function heroTitle(string $productName, ?string $description): string
    {
        return self::stripVolumeSuffix($productName, $description) . ' akcija';
    }

    private static function stripVolumeSuffix(string $productName, ?string $description): string
    {
        $trimmed = trim($productName);
        $volume = self::parseVolume($productName, $description);

        if ($volume === null) {
            return $trimmed;
        }

        $parts = array_map('trim', explode(',', $trimmed));
        $last = end($parts);
        $volumeNorm = mb_strtolower(preg_replace('/\s+/', ' ', $volume));

        if ($last !== false && mb_strtolower(preg_replace('/\s+/', ' ', $last)) === $volumeNorm) {
            array_pop($parts);

            return trim(implode(', ', $parts)) ?: $trimmed;
        }

        $escaped = preg_quote($volume, '/');

        return trim(preg_replace('/,\s*$/', '', preg_replace("/\\s*,?\\s*{$escaped}\\s*$/iu", '', $trimmed)));
    }

    private static function parseVolume(string $productName, ?string $description): ?string
    {
        if (preg_match(self::VOLUME_PATTERN, $productName, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        if ($description && preg_match(self::VOLUME_PATTERN, $description, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        $parts = array_map('trim', explode(',', $productName));
        $last = end($parts);

        if ($last !== false && preg_match(self::VOLUME_PATTERN, $last)) {
            return $last;
        }

        return null;
    }

    public static function followerCount(int $productId): int
    {
        $index = abs($productId) % count(self::PRODUCT_FOLLOWER_COUNT_OPTIONS);

        return self::PRODUCT_FOLLOWER_COUNT_OPTIONS[$index];
    }

    public static function followerLabel(int $productId): string
    {
        $count = self::followerCount($productId);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        $noun = 'žmonių';
        if ($mod10 === 1 && $mod100 !== 11) {
            $noun = 'žmogus';
        } elseif ($mod10 >= 2 && $mod10 <= 9 && ($mod100 < 11 || $mod100 > 19)) {
            $noun = 'žmonės';
        }

        return "{$count} {$noun} jau seka šią prekę";
    }

    /**
     * @param  array<int, array{discounted_price: float}>  $history
     */
    private static function priceStats(array $history, float $currentPrice): ?array
    {
        $prices = array_values(array_filter(
            array_map(fn ($h) => (float) ($h['discounted_price'] ?? 0), $history),
            fn ($p) => $p > 0
        ));

        if ($currentPrice > 0) {
            $prices[] = $currentPrice;
        }

        if ($prices === []) {
            return null;
        }

        return [
            'min' => min($prices),
            'max' => max($prices),
            'avg' => array_sum($prices) / count($prices),
        ];
    }

    /**
     * "Gera kaina!" / "Vidutinė kaina." / "Brangiau nei įprasta." indicator,
     * matching resolvePriceDealSignal's thresholds exactly (±10% of the
     * 30-day average, "among lowest" = within 2% of the historical min).
     *
     * @param  array<int, array{discounted_price: float}>  $history
     */
    public static function priceDealSignal(array $history, float $currentPrice, int $periodDays = 30): ?array
    {
        $stats = self::priceStats($history, $currentPrice);

        if ($stats === null || $currentPrice <= 0 || $stats['avg'] <= 0) {
            return null;
        }

        $isAmongLowest = $currentPrice <= $stats['min'] * 1.02;

        if ($isAmongLowest) {
            return [
                'tone' => 'good',
                'label' => 'Gera kaina!',
                'description' => "Viena mažiausių kainų per paskutines {$periodDays} d.",
            ];
        }

        $cheaperPercent = $stats['avg'] > $currentPrice ? round((1 - $currentPrice / $stats['avg']) * 100) : null;
        $isNearHistoricalLow = $currentPrice <= $stats['min'] * 1.1;

        if ($isNearHistoricalLow && $cheaperPercent !== null && $cheaperPercent > 0) {
            return [
                'tone' => 'good',
                'label' => 'Gera kaina!',
                'description' => "Pigiau nei įprastai (-{$cheaperPercent}%).",
            ];
        }

        if ($currentPrice > $stats['avg'] * 1.1) {
            return [
                'tone' => 'bad',
                'label' => 'Brangiau nei įprasta.',
                'description' => 'Dažniausiai apie ' . self::euro($stats['avg']) . ' — galbūt verta palaukti.',
            ];
        }

        return [
            'tone' => 'neutral',
            'label' => 'Vidutinė kaina.',
            'description' => 'Dažniausiai apie ' . self::euro($stats['avg']) . " per pastarąsias {$periodDays} d.",
        ];
    }

    public static function toGenitivePlural(string $word): string
    {
        $w = trim($word);
        $overrides = [
            'vaisiai' => 'vaisių',
            'daržovės' => 'daržovių',
            'vaisiai ir daržovės' => 'vaisių ir daržovių',
        ];

        if (isset($overrides[mb_strtolower($w)])) {
            return $overrides[mb_strtolower($w)];
        }

        if (str_ends_with($w, 'ės')) {
            return mb_substr($w, 0, -2) . 'ių';
        }
        if (str_ends_with($w, 'ai')) {
            return mb_substr($w, 0, -2) . 'ų';
        }

        return mb_strtolower($w);
    }

    public static function shortName(string $productName): string
    {
        return trim(explode(',', $productName)[0] ?? $productName) ?: $productName;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function faqItems(
        array $product,
        float $bestPrice,
        ?string $bestStoreName,
        int $offerCount,
        string $offersHeading = 'Kainos parduotuvėse'
    ): array {
        $shortName = self::shortName($product['name']);
        $genitive = self::toGenitivePlural($shortName);
        $category = $product['category']['name'] ?? 'prekės';

        $items = [
            [
                'question' => "Kur šiandien pigiausia pirkti {$genitive}?",
                'answer' => $bestPrice > 0 && $bestStoreName
                    ? "Šiuo metu geriausia {$genitive} kaina — " . self::euro($bestPrice) . " {$bestStoreName} parduotuvėse. Palyginkite visas {$offerCount} parduotuvių kainas skyriuje „{$offersHeading}“."
                    : ($offerCount === 0
                        ? "Šiuo metu aktyvių {$genitive} akcijų nėra. Peržiūrėkite paskutinę akciją ir kainų istoriją šiame puslapyje."
                        : "Palyginkite {$genitive} kainas visose parduotuvėse mūsų svetainėje — skyriuje „{$offersHeading}“."),
            ],
            [
                'question' => "Kiek kainuoja {$genitive} akcijų metu?",
                'answer' => $bestPrice > 0
                    ? "Akcijų metu {$genitive} kaina prasideda nuo " . self::euro($bestPrice) . '. Kainos skiriasi priklausomai nuo parduotuvės ir akcijos sąlygų.'
                    : "{$shortName} kainos skiriasi priklausomai nuo parduotuvės. Peržiūrėkite aktualius pasiūlymus šiame puslapyje.",
            ],
            [
                'question' => 'Kokios ' . mb_strtolower($category) . ' akcijos galioja šią savaitę?',
                'answer' => 'Akcijų galiojimo datos nurodytos prie kiekvieno pasiūlymo. Filtruokite ' . mb_strtolower($category) . ' akcijas kategorijos puslapyje arba palyginkite kainas čia, produkto puslapyje.',
            ],
            [
                'question' => "Ar {$genitive} kainos atnaujinamos kasdien?",
                'answer' => 'Taip — kainos ir akcijos atnaujinamos reguliariai, kai pasikeičia prekybos tinklų leidiniai. Rekomenduojame bookmarkinti puslapį ir tikrinti prieš apsipirkimą.',
            ],
        ];

        return $items;
    }

    public static function offersHeading(): string
    {
        return 'Kainos parduotuvėse';
    }

    public static function similarHeading(array $product): string
    {
        $category = $product['category']['name'] ?? null;

        return $category ? 'Kitos ' . self::toGenitivePlural($category) . ' akcijos šiandien' : 'Kitos akcijos šiandien';
    }

    public static function historyTitle(string $productName): string
    {
        $genitive = ucfirst(self::toGenitivePlural(self::shortName($productName)));

        return "{$genitive} kainų istorija";
    }

    public static function otherStoreOffersCta(string $storeName): string
    {
        return "Kitos {$storeName} akcijos";
    }

    // Stores selling directly online get "{Store} parduotuvė"; the rest are
    // scraped from a printed/PDF leaflet, so "{Store} kainų leidinys" instead —
    // resolveOfferOriginLabel in product-page-meta.ts.
    private const ONLINE_PRICE_SOURCE_STORES = ['maxima', 'norfa', 'rimi', 'lidl', 'iki'];

    public static function offerOriginLabel(string $storeSlug, string $storeName): string
    {
        if (in_array($storeSlug, self::ONLINE_PRICE_SOURCE_STORES, true)) {
            return "{$storeName} parduotuvė";
        }

        $noun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';

        return "{$storeName} kainų {$noun}";
    }

    // MIN_PROMOTION_BADGE_PERCENT in promotion-percent-badge.tsx.
    public static function promotionBadgePercent(?float $percent): ?int
    {
        return $percent !== null && $percent >= 20 ? (int) round($percent) : null;
    }

    public static function offerIsActive(?string $toDate): bool
    {
        if (empty($toDate)) {
            return true;
        }

        return \Illuminate\Support\Carbon::parse($toDate)->endOfDay()->gte(now());
    }

    // Ported from formatProductValidDateRange in product-page-meta.ts. Offer
    // rows only ever have a from/to pair where "from" is already in the past
    // (it's an active discount), so this only implements that branch — not
    // the future-start/both-past/single-date cases the source also handles
    // for other contexts (price history, etc).
    //
    // $allowPast: product-store-offer-card.blade.php also renders the
    // "Paskutinė žinoma kaina" (no active promotion) history rows through
    // this same label — those to_dates are always in the past by
    // definition (the offer already expired), so without this the date
    // was always suppressed there. Past tense ("Galiojo iki X") instead of
    // "Iki X" ("valid until X") since the offer is no longer active.
    public static function validUntilLabel(?string $toDate, bool $allowPast = false): ?string
    {
        if (empty($toDate)) {
            return null;
        }

        $to = \Illuminate\Support\Carbon::parse($toDate)->startOfDay();
        $today = \Illuminate\Support\Carbon::today();
        $format = $to->year < $today->year ? 'Y.m.d' : 'm.d';

        if ($to->lt($today)) {
            return $allowPast ? 'Galiojo iki ' . $to->format($format) : null;
        }

        return 'Iki ' . $to->format($format);
    }

    private static function euro(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' €';
    }

    /**
     * Ported from buildProductNoOffersDisplay in product-page-meta.ts — dated
     * history rows for products with no active offer, shaped like live offer
     * cards so the UI can reuse the same component.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array{mode: 'none'|'priced', history: array<int, array<string, mixed>>, best_offer: array<string, mixed>|null}
     */
    public static function buildNoOffersDisplay(array $history): array
    {
        $normalized = collect($history)
            ->filter(fn ($offer) => (float) ($offer['discounted_price'] ?? 0) > 0
                && ! empty($offer['store']['slug'])
                && ! empty($offer['id']))
            ->values();

        if ($normalized->isEmpty()) {
            return ['mode' => 'none', 'history' => [], 'best_offer' => null];
        }

        $seen = [];
        $deduped = $normalized
            ->filter(function ($offer) use (&$seen) {
                $key = ($offer['store']['slug'] ?? '') . '|' . ($offer['discounted_price'] ?? '') . '|' . ($offer['valid_date'] ?? '');
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;

                return true;
            })
            ->sortByDesc('id')
            ->values();

        $minPrice = (float) $deduped->min('discounted_price');
        $bestOffer = $deduped->first(fn ($offer) => (float) $offer['discounted_price'] === $minPrice);

        return [
            'mode' => 'priced',
            'history' => $deduped->all(),
            'best_offer' => $bestOffer,
        ];
    }
}
