<?php

namespace App\Support;

/**
 * Decides whether a StoreFlyer is worth running through the expensive
 * (Gemini vision, ~30s/page) discount-extraction pipeline at all — a store
 * often publishes several unrelated catalogs in parallel (beauty sale,
 * back-to-school, wine, cleaning-supplies fair) alongside its real grocery
 * flyer, and only food + household-chemistry ones matter for the price
 * index basket. Classifies by the flyer's own title — the only cheap signal
 * available before spending API calls on its pages.
 *
 * Deliberately keyword-based, not GPT: the input is a handful of short,
 * human-reviewable titles per store (not thousands of free-text product
 * names like CategoryMappingService deals with), so a transparent, free,
 * instant classifier is preferable to another API dependency for a decision
 * this small. Errs toward 'needs_review' rather than guessing when neither
 * list matches — silently including an irrelevant catalog wastes API calls,
 * silently excluding a relevant one loses real data, so an unclear title
 * should surface for a human decision instead of picking a side.
 */
class FlyerRelevanceClassifier
{
    public const INCLUDED = 'included';
    public const EXCLUDED = 'excluded';
    public const NEEDS_REVIEW = 'needs_review';

    /**
     * Confidently NOT food or household chemistry. Checked first — a title
     * matching both lists (unlikely but possible) is excluded, since
     * spending API calls on a wrongly-included irrelevant catalog is worse
     * than a false negative kept out of NEEDS_REVIEW.
     */
    private const EXCLUDE_KEYWORDS = [
        // Explicit negation seen live (Lidl "NE MAISTO PREKIŲ PASIŪLYMAI" =
        // "NON-food product offers") — must be checked before the plain
        // 'maisto' include keyword below matches inside it and wrongly
        // includes a title that is, by its own wording, the opposite.
        'ne maisto',
        // Single-location flyers ("for THIS store's customers", singular
        // "parduotuvės" — seen live: Iki "Specialūs pasiūlymai IKI Express
        // Jogailos parduotuvės klientams", "... IKI Pikas parduotuvės
        // klientams") rather than a chain-wide catalog. General rule, not
        // Iki-specific — applies to any store using this same convention.
        'parduotuvės klientams',
        // beauty / cosmetics
        'grožio', 'kosmetik', 'parfum', 'kvepal',
        // back-to-school / stationery (some stores title this in English —
        // seen live: Rimi "Back to School 2026")
        'mokyklos', 'mokyklai', 'raštinės', 'raštinei', 'back to school',
        // alcohol (its own category — not "food" for basket purposes, see
        // PriceIndexService::CANDIDATE_ITEM_SLUGS treating alus/degtinė as
        // distinct candidates already)
        'vyno', 'vynų', 'alkoholi', 'alaus katalogas', 'degtin',
        // electronics / appliances / non-food durable goods
        'kompiuter', 'telefon', 'elektronik', 'buitinė technika',
        'žaislų', 'žaislai', 'drabuži', 'avalyn', 'baldų', 'baldai',
        'sodo', 'sodui', 'automobili', 'dovanų kortel',
    ];

    /**
     * Confidently food or household chemistry/cleaning.
     */
    private const INCLUDE_KEYWORDS = [
        // food
        'maisto', 'maistas', 'skonio', 'skonių', 'skonis', 'ledų', 'grilio',
        'duonos', 'mėsos', 'žuvies', 'pieno', 'daržovių', 'vaisių',
        'gėrimų', 'sulčių', 'kavos', 'arbatos', 'saldumynų', 'konditerijos',
        'bakalėjos', 'šventinis stalas', 'šventinių', 'kalėdų stalas',
        // themed country/cuisine food-promotion months (seen live: Maxima
        // "ITALIJOS MĖNUO") — add other "<Country> mėnuo" titles here as
        // they're seen; not generalized to a bare 'mėnuo' keyword since an
        // unrelated non-food themed month is plausible for some store.
        'italijos mėnuo',
        // household chemistry / cleaning / hygiene
        'švaros', 'valymo', 'skalbimo', 'buitinė chemija', 'higienos',
        'indaplov',
        // known store-specific weekly-flyer brand names — not a generic
        // keyword, but confirmed live to always be that store's actual main
        // grocery flyer (Maxima's loyalty-program branding for it).
        'ačiū savaitinis',
        // A plain "<store> savaitinis leidinys" (weekly flyer) with no
        // other theme word is, by LT retail convention, always the store's
        // main grocery flyer (confirmed live: Rimi's "savaitinis leidinys
        // Nr. 36" is the real weekly grocery leaflet already verified
        // during this session's scraper testing). A themed weekly flyer
        // would carry its own theme keyword too (e.g. "Grožio šventė"),
        // which the exclude list above already catches first.
        'savaitinis leidinys',
        // More store-specific main-catalog naming conventions, each
        // personally verified this session (real grocery items with real
        // unit prices extracted and checked against the actual flyer page)
        // to be that store's genuine default grocery flyer, not a themed
        // catalog — their titles just don't happen to contain any of the
        // generic food keywords above.
        'norfa nr', // Norfa's only naming: "NORFA Nr. 17 (517)"
        'kainų leidinys', // Iki: "IKI Kainų leidinys Nr. 36"
        'mes - jūsų kaimynai', // Aibė's slogan-as-title
        'šilas leidinys', // Šilas: "Šilas leidinys Nr. 17"
        // Lidl marketing-slogan titles for what are, per user confirmation,
        // its own general/loyalty-program grocery promos (not a themed
        // non-food catalog) — same special-case treatment as the weekly-
        // flyer brand names above, not generic keywords.
        'pažadas jums', 'tapk lidliuku',
    ];

    public static function classify(string $title): string
    {
        $normalized = mb_strtolower(trim($title), 'UTF-8');

        if ($normalized === '') {
            return self::NEEDS_REVIEW;
        }

        foreach (self::EXCLUDE_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return self::EXCLUDED;
            }
        }

        foreach (self::INCLUDE_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return self::INCLUDED;
            }
        }

        return self::NEEDS_REVIEW;
    }
}
