<?php

namespace App\Services;

use App\Support\ProductPackSizeExtractor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Finds the same product created twice for one store: once from a flyer
// (Gemini extraction, discount has no product_url) and once from the store's
// e-shop scraper (discount has a product_url). The two sources word the name
// differently ("Šaldytos bulvių lazdelės NATALI, 1 kg" + info "(2 rūšių)" vs
// "Šald.bulvių lazdelės NATALI (2 rūš.), 1 kg"), so name-only matching
// (find_product_duplicates.sql) misses them. What they do share is the offer
// itself: same store, overlapping period, same price. Candidates come from
// that, and the name check below only has to confirm them.
class CrossSourceDuplicateFinder
{
    // Web-side tokens that must be covered by the flyer side. The web name is
    // the terse, specific one ("Višč. br. filė gabaliukai RIMI, A kl.") while
    // the flyer adds descriptive words ("Švieži ... be odos"), so coverage is
    // measured on the web side only. Kept loose on purpose: the exact price
    // match is the main signal, the name only confirms it. 0.6 was picked on
    // real data (2026-09-29): 0.8 missed "Raudonieji" vs "Didieji raudonieji
    // greipfrutai", 0.5 started merging generic flyer offers into one scent
    // ("COCCOLINO" vs "COCCOLINO Sensitive Cotton Cloud").
    private const MIN_WEB_COVERAGE = 0.6;

    private const MIN_MATCHED_TOKENS = 3;

    // A one-word web name ("Grietinė, 360 g") can't confirm anything.
    private const MIN_WEB_NAME_TOKENS = 2;

    private const STOPWORDS = ['su', 'ir', 'ar', 'be', 'a', 'r', 'kg', 'g', 'l', 'ml', 'vnt'];

    public function __construct(private ProductDuplicateMergeService $mergeService)
    {
    }

    /**
     * @return Collection<int, array{flyer_product_id:int, web_product_id:int, flyer_name:string, flyer_info:?string, web_name:string, web_info:?string, store:string, price:string, coverage:float}>
     */
    public function findPairs(): Collection
    {
        $accepted = collect($this->candidateRows())
            ->unique(fn ($row) => $row->flyer_product_id . ':' . $row->web_product_id)
            ->map(function ($row) {
                $coverage = $this->matchScore($row->flyer_name, $row->flyer_info, $row->web_name, $row->web_info);

                if ($coverage === null) {
                    return null;
                }

                return [
                    'flyer_product_id' => (int) $row->flyer_product_id,
                    'web_product_id' => (int) $row->web_product_id,
                    'flyer_name' => $row->flyer_name,
                    'flyer_info' => $row->flyer_info,
                    'web_name' => $row->web_name,
                    'web_info' => $row->web_info,
                    'store' => $row->store,
                    'price' => (string) $row->price,
                    'coverage' => $coverage,
                ];
            })
            ->filter()
            ->values();

        // 1:1 only. A flyer offer matching several web products is a
        // multi-variant offer ("Skalbimo kapsulės ARIEL (2 rūšys)" covers
        // ARIEL COLOR and ARIEL EXTRA CLEAN) — merging it into any single one
        // would be wrong, so every pair involving it is dropped.
        $flyerCounts = $accepted->countBy('flyer_product_id');
        $webCounts = $accepted->countBy('web_product_id');

        return $accepted
            ->filter(fn (array $pair) => $flyerCounts[$pair['flyer_product_id']] === 1
                && $webCounts[$pair['web_product_id']] === 1)
            ->values();
    }

    private function candidateRows(): array
    {
        return DB::select(<<<'SQL'
            SELECT
                f.product_id AS flyer_product_id,
                w.product_id AS web_product_id,
                pf.name AS flyer_name,
                f.info AS flyer_info,
                pw.name AS web_name,
                w.info AS web_info,
                s.name AS store,
                f.discounted_price AS price
            FROM discounts f
            JOIN discounts w
                ON w.store_id = f.store_id
                AND w.product_url IS NOT NULL
                AND w.product_id <> f.product_id
                AND w.discounted_price = f.discounted_price
                AND (f.original_price IS NULL OR w.original_price IS NULL OR f.original_price = w.original_price)
                AND (f.start_at IS NULL OR w.end_at IS NULL OR f.start_at <= w.end_at)
                AND (w.start_at IS NULL OR f.end_at IS NULL OR w.start_at <= f.end_at)
            JOIN products pf ON pf.id = f.product_id
            JOIN products pw ON pw.id = w.product_id
            JOIN stores s ON s.id = f.store_id
            WHERE f.product_url IS NULL
                AND f.discounted_price IS NOT NULL
        SQL);
    }

    /**
     * Web-side token coverage when the pair is the same product, null otherwise.
     */
    public function matchScore(string $flyerName, ?string $flyerInfo, string $webName, ?string $webInfo): ?float
    {
        $flyerInfo = $this->stripUnitPrice($flyerInfo);
        $webInfo = $this->stripUnitPrice($webInfo);

        if ($this->packSizesConflict($flyerName, $flyerInfo, $webName, $webInfo)) {
            return null;
        }

        $flyerTokens = $this->tokens($flyerName . ' ' . $flyerInfo);
        $webTokens = $this->tokens($webName . ' ' . $webInfo);
        // Coverage is measured on the web name only. Web info is shelf
        // noise, and some stores repeat the promo text there ("6 rūšių,
        // 1,28 Eur/l. Ir IKI EXPRESS"), which matched the flyer's copy of
        // the same text and pushed "COCA-COLA, FANTA, SPRITE" onto
        // "FANTA APPLE-CHERRY".
        $webNameTokens = $this->tokens($webName);

        if ($flyerTokens === [] || count($webNameTokens) < self::MIN_WEB_NAME_TOKENS) {
            return null;
        }

        // Every brand-looking (all-caps) word on either side must appear on
        // the other: "ESTRELLA" vs "LAYS", "WOOLITE DARK" vs "WOOLITE".
        if (!$this->brandTokensCovered($flyerName, $webTokens) || !$this->brandTokensCovered($webName, $flyerTokens)) {
            return null;
        }

        // "Skystas skalbiklis WOOLITE (3 rūšys)" is one offer over several
        // variants; only a web product that is itself the multi-variant
        // listing ("NATALI (2 rūš.)") is the same thing.
        // The web side is judged on its name only: some stores repeat the
        // flyer's promo text in web info ("6 rūšių, ... Ir IKI EXPRESS").
        if ($this->isMultiVariant($flyerName . ' ' . $flyerInfo) && !$this->isMultiVariant($webName)) {
            return null;
        }

        if ($this->flavorsConflict($flyerName . ' ' . $flyerInfo, $webName . ' ' . $webInfo)) {
            return null;
        }

        $matched = 0;
        foreach ($webNameTokens as $webToken) {
            foreach ($flyerTokens as $flyerToken) {
                if ($this->tokensMatch($webToken, $flyerToken)) {
                    $matched++;
                    break;
                }
            }
        }

        $coverage = $matched / count($webNameTokens);

        if ($matched < min(self::MIN_MATCHED_TOKENS, count($webNameTokens)) || $coverage < self::MIN_WEB_COVERAGE) {
            return null;
        }

        return round($coverage, 2);
    }

    private function stripUnitPrice(?string $info): ?string
    {
        if ($info === null) {
            return null;
        }

        // Scrapers put the shelf unit price in info ("14,45 €/kg",
        // "1,28 Eur/l").
        $info = preg_replace('/\d+(?:[.,]\d+)?\s*(?:€|eur)\s*\/\s*[\p{L}.]+/iu', ' ', $info);

        return trim($info) === '' ? null : trim($info);
    }

    private function packSizesConflict(string $flyerName, ?string $flyerInfo, string $webName, ?string $webInfo): bool
    {
        $flyerSize = $this->packSizeInBaseUnits(ProductPackSizeExtractor::extract($flyerName) ?? ProductPackSizeExtractor::extract($flyerInfo));
        $webSize = $this->packSizeInBaseUnits(ProductPackSizeExtractor::extract($webName) ?? ProductPackSizeExtractor::extract($webInfo));

        if ($flyerSize === null || $webSize === null) {
            return false;
        }

        return $flyerSize !== $webSize;
    }

    // "1 kg" and "1000 g" are the same size; anything unparseable is kept as
    // its normalized string so only identical strings compare equal.
    private function packSizeInBaseUnits(?string $size): ?string
    {
        if ($size === null) {
            return null;
        }

        if (!preg_match('/^(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l)$/u', mb_strtolower(trim($size)), $m)) {
            return mb_strtolower(trim($size));
        }

        $value = (float) str_replace(',', '.', $m[1]);
        $unit = $m[2];

        return match ($unit) {
            'kg' => round($value * 1000, 2) . 'g',
            'g' => round($value, 2) . 'g',
            'l' => round($value * 1000, 2) . 'ml',
            'ml' => round($value, 2) . 'ml',
        };
    }

    /**
     * @return string[]
     */
    private function tokens(string $text): array
    {
        $stripped = ProductPackSizeExtractor::extractAndStrip($text)['info'] ?? '';
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($stripped), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word) => !in_array($word, self::STOPWORDS, true)
        )));
    }

    private function brandTokensCovered(string $name, array $otherTokens): bool
    {
        preg_match_all('/(?<![\p{L}\'’])[\p{Lu}][\p{Lu}\'’]{2,}(?![\p{L}])/u', $name, $matches);
        $capsWords = $matches[0];

        preg_match_all('/\p{L}{3,}/u', $name, $allWords);

        // A name written entirely in caps ("VIRTAS LIEŽUVIO VYNIOTINIS") has
        // no brand signal in its casing.
        if ($capsWords === [] || count($capsWords) > count($allWords[0]) / 2) {
            return true;
        }

        foreach ($capsWords as $capsWord) {
            $capsTokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($capsWord), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($capsTokens as $token) {
                $found = false;
                foreach ($otherTokens as $otherToken) {
                    if ($this->tokensMatch($token, $otherToken)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return false;
                }
            }
        }

        return true;
    }

    // Flavor/filling variants differ by the word after "su" and nothing
    // else: "Varškė AMFORA su mangais" vs "su avietėmis", "Koldūnai VIČI
    // su vištiena" vs "su kiauliena". High coverage alone lets these through.
    private function flavorsConflict(string $flyerText, string $webText): bool
    {
        $flyerFlavor = $this->wordAfterSu($flyerText);
        $webFlavor = $this->wordAfterSu($webText);

        if ($flyerFlavor === null || $webFlavor === null) {
            return false;
        }

        return !$this->tokensMatch($flyerFlavor, $webFlavor);
    }

    private function wordAfterSu(string $text): ?string
    {
        if (!preg_match('/(?<![\p{L}])su\s+(\p{L}+)/iu', $text, $m)) {
            return null;
        }

        return mb_strtolower($m[1]);
    }

    private function isMultiVariant(string $text): bool
    {
        return (bool) preg_match('/(?<!\d)([2-9]|\d{2,})\s*rūš/iu', $text)
            || (bool) preg_match('/\s(ar|arba)\s/iu', $text)
            // A list of brands: "COCA-COLA, FANTA, SPRITE".
            || (bool) preg_match('/\p{Lu}{2,}[\p{Lu}\-]*\s*,\s*\p{Lu}{2,}/u', $text);
    }

    private function tokensMatch(string $a, string $b): bool
    {
        if ($this->mergeService->wordsFuzzyMatch($a, $b)) {
            return true;
        }

        // Catalog short-hand of 1-2 letters ("br." broilerių, "kl." klasė).
        // Unsafe on names alone (see KNOWN_SHORT_ABBREVIATIONS), acceptable
        // here because store, period, price and brand already agree.
        $shorter = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;

        return mb_strlen($shorter) <= 2
            && preg_match('/^\p{L}+$/u', $shorter)
            && mb_strlen($longer) > mb_strlen($shorter)
            && str_starts_with($longer, $shorter);
    }
}
