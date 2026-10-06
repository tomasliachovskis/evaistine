<?php

namespace App\Support;

// Ported from discount/src/lib/product-page-meta.ts — only the pieces that
// drive visible product-page UI (hero copy, price-deal signal, FAQ).
// Deliberately NOT ported: a fabricated per-product "follower count" (same
// fake-social-proof pattern removed from StoreSocialProof — a deterministic
// number with no real feature behind it), popular-search-link generation, attribute
// chips, promo-banner copy, store-links-row — lower-value/SEO-only pieces of
// a 1700-line source file; the product/discount data plumbing already exists
// in AkcijosController/ProductController, this class is presentation-only.
class ProductPageMeta
{
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

    // Meta title/description casing for a product name. Lowercasing every
    // name wholesale lost brand casing in the SERP ("L'oréal paris",
    // "Vaza ilaja scandi") — keep the store's own casing, and only
    // normalize names a scraper sent in (mostly) ALL CAPS.
    public static function displayName(string $productName): string
    {
        $name = trim($productName);
        $letters = preg_replace('/[^\p{L}]/u', '', $name);
        $upper = preg_replace('/[^\p{Lu}]/u', '', $name);

        if (mb_strlen($letters) > 0 && mb_strlen($upper) / mb_strlen($letters) > 0.6) {
            $name = mb_strtolower($name);
        }

        return mb_ucfirst($name);
    }

    public static function shortName(string $productName): string
    {
        return trim(explode(',', $productName)[0] ?? $productName) ?: $productName;
    }

    /**
     * Real per-product price facts from discount_histories for the last
     * $days days: the lowest recorded price (store + date), the average,
     * and how many separate promotions ran. Gives each product page unique,
     * checkable text (FAQ, meta) instead of the same template sentences.
     * Null when there are fewer than 3 priced rows in the window — too thin
     * to state as a fact.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array{days: int, points: int, min_price: float, min_store: ?string, min_date: ?string, min_is_current: bool, avg_price: float, promo_count: int, last_promo_date: ?string}|null
     */
    public static function historyFacts(array $history, float $currentPrice, ?string $currentStoreName, int $days = 90): ?array
    {
        $cutoff = \Illuminate\Support\Carbon::today()->subDays($days);

        $rows = collect($history)
            ->filter(fn ($h) => (float) ($h['discounted_price'] ?? 0) > 0)
            ->filter(function ($h) use ($cutoff) {
                $date = $h['to_date'] ?? $h['from_date'] ?? null;

                return $date && \Illuminate\Support\Carbon::parse($date)->gte($cutoff);
            })
            ->values();

        if ($rows->count() < 3) {
            return null;
        }

        $lowest = $rows->sortBy(fn ($h) => (float) $h['discounted_price'])->first();
        $minPrice = (float) $lowest['discounted_price'];
        $minIsCurrent = $currentPrice > 0 && $currentPrice <= $minPrice;

        $prices = $rows->map(fn ($h) => (float) $h['discounted_price']);
        if ($currentPrice > 0) {
            $prices->push($currentPrice);
        }

        // A promotion = a genuinely discounted row (full-catalog stores also
        // archive plain shelf prices). One per store + start date, since the
        // same promo can be archived more than once.
        $promos = $rows
            ->filter(fn ($h) => (float) ($h['original_price'] ?? 0) > (float) $h['discounted_price'])
            ->unique(fn ($h) => ($h['store']['slug'] ?? '').'|'.($h['from_date'] ?? $h['to_date']));

        $lastPromoDate = $promos->map(fn ($h) => $h['to_date'] ?? $h['from_date'])->filter()->max();

        return [
            'days' => $days,
            'points' => $rows->count(),
            'min_price' => $minIsCurrent ? $currentPrice : $minPrice,
            'min_store' => $minIsCurrent ? $currentStoreName : ($lowest['store']['name'] ?? null),
            'min_date' => $minIsCurrent ? null : ($lowest['from_date'] ?? $lowest['to_date'] ?? null),
            'min_is_current' => $minIsCurrent,
            'avg_price' => round($prices->avg(), 2),
            'promo_count' => $promos->count(),
            'last_promo_date' => $lastPromoDate,
        ];
    }

    private static function historyFaqItems(array $facts, string $shortName): array
    {
        $days = $facts['days'];
        $where = $facts['min_store'] ? " ({$facts['min_store']})" : '';

        $minAnswer = $facts['min_is_current']
            ? "Dabartinė kaina – " . self::euro($facts['min_price']) . "{$where} – yra mažiausia per paskutines {$days} d."
            : "Mažiausia kaina per paskutines {$days} d. buvo " . self::euro($facts['min_price']) . $where
                . ($facts['min_date'] ? ', ' . \Illuminate\Support\Carbon::parse($facts['min_date'])->format('Y.m.d') : '') . '.';
        $minAnswer .= ' Vidutinė kaina per tą laikotarpį – ' . self::euro($facts['avg_price']) . '.';

        $items = [[
            'question' => "{$shortName}: kokia buvo mažiausia kaina?",
            'answer' => $minAnswer,
        ]];

        if ($facts['promo_count'] > 0) {
            $n = $facts['promo_count'];
            $teen = $n % 100 >= 10 && $n % 100 < 20;
            $times = match (true) {
                ! $teen && $n % 10 === 1 => 'kartą',
                ! $teen && $n % 10 >= 2 => 'kartus',
                default => 'kartų',
            };
            $items[] = [
                'question' => "Kaip dažnai {$shortName} būna akcijoje?",
                'answer' => "Per paskutines {$days} d. {$shortName} akcijoje buvo {$facts['promo_count']} {$times}"
                    . ($facts['last_promo_date'] ? ', paskutinį kartą iki ' . \Illuminate\Support\Carbon::parse($facts['last_promo_date'])->format('Y.m.d') : '') . '.',
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function faqItems(
        array $product,
        float $bestPrice,
        ?string $bestStoreName,
        int $offerCount,
        string $offersHeading = 'Kainos vaistinėse',
        ?array $historyFacts = null
    ): array {
        $shortName = self::shortName($product['name']);
        $genitive = self::toGenitivePlural($shortName);
        $category = $product['category']['name'] ?? 'prekės';

        $items = [
            [
                'question' => "Kur šiandien pigiausia pirkti {$genitive}?",
                'answer' => $bestPrice > 0 && $bestStoreName
                    ? "Šiuo metu geriausia {$genitive} kaina — " . self::euro($bestPrice) . ' ' . PharmacyName::phrase($bestStoreName, 'locative_plural') . ". Palyginkite visas {$offerCount} vaistinių kainas skyriuje „{$offersHeading}“."
                    : ($offerCount === 0
                        ? "Šiuo metu aktyvių {$genitive} akcijų nėra. Peržiūrėkite paskutinę akciją ir kainų istoriją šiame puslapyje."
                        : "Palyginkite {$genitive} kainas visose vaistinėse mūsų svetainėje — skyriuje „{$offersHeading}“."),
            ],
            [
                'question' => "Kiek kainuoja {$genitive} akcijų metu?",
                'answer' => $bestPrice > 0
                    ? "Akcijų metu {$genitive} kaina prasideda nuo " . self::euro($bestPrice) . '. Kainos skiriasi priklausomai nuo vaistinės ir akcijos sąlygų.'
                    : "{$shortName} kainos skiriasi priklausomai nuo vaistinės. Peržiūrėkite aktualius pasiūlymus šiame puslapyje.",
            ],
        ];

        // Real price-history facts replace the two template answers below,
        // which read the same on every product page.
        if ($historyFacts !== null) {
            return array_merge($items, self::historyFaqItems($historyFacts, $shortName));
        }

        $items = [
            ...$items,
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
        return 'Kainos vaistinėse';
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

    // Stores with an e-shop scraper (config('scrapers')) get "{Store}
    // vaistinėje internetu"; the rest are scraped from a printed/PDF leaflet, so
    // "{Store} kainų leidinys" instead.
    public static function offerOriginLabel(string $storeSlug, string $storeName): string
    {
        if (array_key_exists($storeName, config('scrapers', []))) {
            return PharmacyName::phrase($storeName, 'locative') . ' internetu';
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
        // Month in words ("iki spalio 5 d."), not "Iki 10.05": a bare
        // numeric pair reads as either day.month or month.day to an older
        // reader. The year is only spelled out when it isn't this year.
        $date = ($to->year !== $today->year ? $to->year . ' m. ' : '') . LithuanianDate::dayMonthGenitive($to);

        if ($to->lt($today)) {
            return $allowPast ? 'Galiojo iki ' . $date : null;
        }

        return 'Iki ' . $date;
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
