# Current SEO H1 / title / description formats (live)

Tracks what's actually shipped to production, per page type — updated after each change lands (not before). For the original competitor research and rationale behind these formats, see `docs/seo-meta-title-research.md`.

---

## Store page — `/akcijos/{store}`

Source: `ProductController::generateSeoData()`'s `store` case (`app/Http/Controllers/Api/ProductController.php`), H1 override in `AkcijosController::renderListingPayload()`.

- **H1:** `Visos {Store} akcijos ir nuolaidos šią savaitę`
- **Title:** `{Store} akcijos šiandien – nuolaidos iki {maxDiscount}%`
- **Description:** `{Category1} iki {pct1}%, {Category2} iki {pct2}%. Galioja iki {YYYY.MM.DD}. Palyginkite ir sutaupykite!` — real top-2 root categories by discount %, short category names.

**Live example (Maxima, 2026-09-21):**
- H1: `Visos Maxima akcijos ir nuolaidos šią savaitę`
- Title: `Maxima akcijos šiandien – nuolaidos iki 60%`
- Description: `Buitinė chemija iki 60%, Namų prekės iki 50%. Galioja iki 2026.09.14. Palyginkite ir sutaupykite!`

---

## Category page — `/akcijos/{category}`

Source: `ProductController::generateSeoData()`'s `category` case, H1 override in `AkcijosController::renderListingPayload()`.

- **H1:** `Visos {category, genitive} akcijos ir nuolaidos šią savaitę` — same shape as the store page's H1, category in genitive since it's a common noun (a store's proper name doesn't need the case).
- **Title:** `{Category, genitive, capitalized} akcijos šiandien – nuolaidos iki {maxDiscount}%`
- **Description:** `Iki {maxDiscount}% nuolaidos {category, dative} iš {up to 4 prominent stores}. {count}+ pasiūlymų šią savaitę!` — store list now prominence-sorted + capped (`getStoreNamesForCategory()` fixed to mirror `getStoreNamesForProduct()`'s `MAIN_STORE_SLUGS` sort; previously unsorted and uncapped, confirmed live listing all 12 stores for one category).
- **Zero-discount fallback** (real priced products, no genuine `discount_percent` anywhere — full-catalog categories): title drops the "nuolaidos iki X%" clause; description reads `{Category, genitive, capitalized} akcijos šiandien | Palyginkite {category, genitive} pasiūlymus iš {stores}. {count}+ prekių šią savaitę!`

**Live example (Buitinė chemija, valymo priemonės → short "Buitinė chemija", 2026-09-21):**
- H1: `Visos buitinės chemijos akcijos ir nuolaidos šią savaitę`
- Title: `Buitinės chemijos akcijos šiandien – nuolaidos iki 60%`
- Description: `Iki 60% nuolaidos buitinei chemijai iš Maxima, Norfa, Lidl ir Iki. 2000+ pasiūlymų šią savaitę!`

**Live example (zero-discount fallback — Alkoholiniai gėrimai):**
- Title: `Alkoholio akcijos šiandien`
- Description: `Palyginkite alkoholio pasiūlymus iš Maxima, Lidl ir Rimi. 360+ prekių šią savaitę!`

---

## Store + category page — `/akcijos/{store}/{category}`

Source: `ProductController::generateSeoData()`'s `store_category` case, H1 override in `AkcijosController::renderListingPayload()`.

- **H1:** `{Store} akcijos {category, dative} šią savaitę` — e.g. "duonos gaminiams"
- **Title:** `{Store} {category, genitive} akcijos iki {month, genitive} {day} d.` — e.g. "duonos gaminių ... iki rugsėjo 30 d." (no % in title)
- **Description:** `{item examples, dative, "X, Y ir Z"} „{Store}“ [parduotuvėje] – iki {maxDiscount}% nuolaida. Patikrinkite pasiūlymus, galiojančius iki {month, genitive} {day} d.!` — item examples are a hand-written per-category list (`CATEGORY_ITEM_EXAMPLES`), not real product names. Store name uses a verified locative form only for Maxima (`STORE_LOCATIVE_LABELS`); every other store falls back to `„{Store}“ parduotuvėje`.
- **Zero-offer fallback** (unchanged since introduced): H1 uses the same dative phrase; title/description read `{Store} {category} – palyginkite kainas kitose parduotuvėse` / `Šiuo metu {Store} neturi aktyvių {category} akcijų. Peržiūrėkite {category} pasiūlymus kitose parduotuvėse.`

**Live example (Maxima + Duonos gaminiai, 2026-09-21):**
- H1: `Maxima akcijos duonos gaminiams šią savaitę`
- Title: `Maxima duonos gaminių akcijos iki rugsėjo 14 d.`
- Description: `Duonai, bandelėms ir kruasanams „Maximoje“ – iki 20% nuolaida. Patikrinkite pasiūlymus, galiojančius iki rugsėjo 14 d.!`

**Live example (Iki + Kosmetika, non-Maxima fallback):**
- Title: `Iki kosmetikos akcijos iki rugsėjo 13 d.`
- Description: `Šampūnams, kremams ir dantų pastoms „Iki“ parduotuvėje – iki 50% nuolaida. Patikrinkite pasiūlymus, galiojančius iki rugsėjo 13 d.!`

---

## Keyword page — `/akcijos/{keyword}`

Source: `App\Services\KeywordPageDynamicMetaService`. This page type already had the real-data pattern (price range + offer count + store hashtags, kaina24.lt-style) from a prior session — this pass **fixed two real bugs**, no redesign. H1 was already plain/number-free and stays that way (the on-page hero right under it already shows count/store-count/price via `KeywordPageService::buildQuickStats()`, so injecting them into H1 too would duplicate it).

- **H1** (unchanged): `{Keyword} akcijos ir nuolaidos šią savaitę`
- **Title:** `Akcija {Keyword, dative} – kaina nuo {minPrice} € | {count} {pasiūlymas/pasiūlymai/pasiūlymų}` — leads with "Akcija" then the keyword in dative case via `KeywordPage::grammar_dative` (e.g. "Akcija ledams" not "Ledai akcija"), falls back to nominative if a page has no dative authored yet. Offer-count word form now correctly declined by count via `App\Support\LithuanianPlural::offerWord()` (was hardcoded wrong before). Description's keyword stays nominative (unchanged).
- **Description:** `Ieškai pigiau? {Keyword} akcija nuo {minPrice} € iki {maxPrice} €. {count} {declined word}: #STORE1 #STORE2 ...` — store hashtags now sorted by a `PRIORITY_STORE_SLUGS` list (Maxima/Norfa/Lidl/Iki/Rimi first, mirroring `ProductController::MAIN_STORE_SLUGS`) before taking the top 5, instead of raw discount order. Dropped the `✔` glyph (project's no-decorative-glyph convention).
- **Zero-match pages don't reach this service at all** — `KeywordPageService::buildListingResponse()` `abort(404)`s before `min_active_offers` (default 1) is met, so there's no "0 pasiūlymų"-style live-bug risk here (verified architecturally, not just by luck).

**Schema.org (`ItemList`, shared with store/category/store_category pages via `AkcijosController::renderListingPayload()`):** fixed two gaps found by inspecting real live JSON-LD on `/akcijos/ledai` — `numberOfItems` was just `count($itemListDeals)` (the current page's ~20-24 items) instead of the real total match count, confirmed live as `"numberOfItems": 20` on a page with 100 real matches; and list items had no price data at all. `App\Support\ItemListSchema::build()` now accepts an optional `$totalCount` (passed from the paginator's real `$data['total']`) and each item can carry a `price`, which renders as a nested `Product`+`Offer` (price, EUR, availability) per `ListItem`. Verified live: `ledai` now reports `"numberOfItems": 100` with real per-product prices (e.g. `"price": "0.69"`). `StoreController`'s unrelated `ItemListSchema::build()` call (plain store list, no price/total) confirmed unaffected — still 200s.

**Live example (`ledai`, 2026-09-21):**
- Title: `Akcija ledams – kaina nuo 0.39 € | 100 pasiūlymų`
- Description: `Ieškai pigiau? Ledai akcija nuo 0.39 € iki 5.99 €. 100 pasiūlymų: #MAXIMA #NORFA #LIDL #IKI #RIMI`

**Declension spot-checks (real data, various counts):** `6→pasiūlymai`, `1→pasiūlymas`, `14→pasiūlymų`, `31→pasiūlymas`, `133→pasiūlymai`, `195→pasiūlymai`, `72→pasiūlymai`, `19→pasiūlymų` — all correct.

---

## Not yet implemented (research/recommendations only, not shipped)

These page types were researched in `docs/seo-meta-title-research.md` but no code changes were made — do not treat that doc's recommendations as live:

- Leaflet hub — `/leidinys/{store}`
- Leaflets index — `/leidiniai`
- Product page — `/akcijos/{category}/{product}`
