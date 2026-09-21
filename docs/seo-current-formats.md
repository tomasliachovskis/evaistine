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

## Leaflet hub — `/leidinys/{store}`

Source: `ProductController::generateSeoData()`'s `store_leaflet` case (`app/Http/Controllers/Api/ProductController.php`). This page has its own controller (`App\Http\Controllers\LeafletController::hub()`) and its own view (`resources/views/leaflets/hub.blade.php`) — H1 is computed inline in the blade file, not via `AkcijosController`.

- **H1:** `{Store} naujausi leidiniai – galioja iki {validTo}` (`leidyniai` for Iki) — plural noun kept (this page is an archive of current+expired leaflets, not just one issue), real end date appended since nothing else near the H1 shows it (`<x-leaflet-quick-links>` already shows the real offer count, so that's not repeated).
- **Title:** `{Store} naujas savaitės {leidinys/leidynys}{, Nr.N} {validFrom}–{validTo}` — added the missing end date (previously only had the start date; every competitor checked in the original research included the full range).
- **Description:** unchanged (already had the full real date range).
- Guarded: if a store has no resolvable validity, H1 falls back to the plain `{Store} naujausi leidiniai` with no date clause.

**Live example (Maxima, 2026-09-21 — note: local dev flyer data is stale, shows an already-past date range):**
- H1: `Maxima naujausi leidiniai – galioja iki 2026.09.14`
- Title: `Maxima naujas savaitės leidinys, Nr.37 2026.09.08–2026.09.14`

---

## Leaflets index — `/leidiniai`

Source: `ProductController::generateSeoData()`'s `leaflets_index` case, real store count computed for free in `ProductController::getAllLeaflets()` (no new query — same data the page's own store-chip pill bar already dedupes). Own controller (`LeafletController::index()`) and view (`resources/views/leaflets/index.blade.php`) — H1 was previously a hardcoded literal string.

- **H1:** `Visi akcijų leidiniai – {storeCount}+ parduotuvių`
- **Title:** `Akcijų leidiniai – {storeCount}+ parduotuvių savaitės katalogai`
- **Description:** `Naujausi Maxima, Lidl, Iki, Rimi, Norfa ir kitų {storeCount}+ parduotuvių akcijų leidiniai vienoje vietoje. Peržiūrėkite savaitės pasiūlymus PDF ir nuotraukose.`
- Guarded: `$storeCount === 0` falls back to the original hardcoded generic copy (verified via direct `generateSeoData()` call) — never renders "0+ parduotuvių".

**Live example (2026-09-21):**
- H1: `Visi akcijų leidiniai – 39+ parduotuvių`
- Title: `Akcijų leidiniai – 39+ parduotuvių savaitės katalogai`

**Schema.org — leaflet pages (hub, index, single-flyer show):** previously only had `BreadcrumbList`, nothing else, despite real leaflet cover images being available everywhere. Added:
- **Hub + index:** `ItemList` with each leaflet's real name/URL/cover image, reusing `App\Support\ItemListSchema::build()` (same class used for the `akcijos/*` pages). Hub scopes to non-expired leaflets only (matches the page's own "Galiojantys"/"Pasibaigę" split); index already only ever contains active leaflets at the source query. Verified live: index reports `"numberOfItems": 91` with real images.
- **Single-flyer show page:** a plain `ImageObject` (`contentUrl` = the flyer's real cover image, `representativeOfPage: true`) — the page's first-ever structured image data.

---

## Single flyer show page — `/leidinys/{store}/{flyerSlug}`

Source: `ProductController::getStoreLeaflet()` (`app/Http/Controllers/Api/ProductController.php`), title composition shared with breadcrumbs/hub/index cards via `App\Services\StoreFlyerTitleBuilder::build()`.

**Real bug fixed**: `build()` unconditionally appended `" Nr.{issue_number}"` even when the flyer's own scraped title already stated that same issue number, producing a live double-number title (`"...Nr. 34 Nr.34"`) — confirmed systemic (every issue-numbered flyer whose scraped title states its own "Nr. X" was affected, across multiple stores). Fixed by skipping the append when the scraped title already contains `Nr.`/`Nr. ` + that number (case/spacing-insensitive). Since `build()` is shared, this also fixed the same duplication on the leaflet hub's and leaflets index's card titles, and resolved an inconsistency where this page's breadcrumb (a different code path, never affected) showed the correct un-duplicated title while the H1 right below it showed the broken one.

- **H1:** the shared flyer title (bug-fixed), e.g. `Naujas {Store} nuolaidų leidinys - {scraped title}` — wording changed from `"Naujas {Store} leidinys - ..."` to `"Naujas {Store} nuolaidų leidinys - ..."`. No injected date — the page already shows the real validity range as body text directly under the H1.
- **Title:** same as H1 + real date range appended — `{H1 text} – {validFrom}–{validTo}` (previously had no date at all when the flyer had its own scraped title, which is the common case).
- **Description:** leads with **"Naujausias"** instead of "Naujas" (description-only wording), adds the real per-flyer page count and date range — `Naujausias {Store} nuolaidų leidinys - {scraped title} – {Store} leidinys, {N} psl., galioja {validFrom}–{validTo}. Peržiūrėkite visus akcijų puslapius.`
- Guarded: a flyer with no resolvable validity dates gets no date clause in title/description (rather than a broken empty range).

**Live example (Maxima, flyer Nr.34, 2026-09-21 — note: local dev flyer data is stale):**
- H1: `Naujas Maxima nuolaidų leidinys - AČIŪ savaitinis leidinys Nr. 34`
- Title: `Naujas Maxima nuolaidų leidinys - AČIŪ savaitinis leidinys Nr. 34 – 2026.08.18–2026.08.24`
- Description: `Naujausias Maxima nuolaidų leidinys - AČIŪ savaitinis leidinys Nr. 34 – Maxima leidinys, 48 psl., galioja 2026.08.18–2026.08.24. Peržiūrėkite visus akcijų puslapius.`

---

## Not yet implemented (research/recommendations only, not shipped)

These page types were researched in `docs/seo-meta-title-research.md` but no code changes were made — do not treat that doc's recommendations as live:

- Product page — `/akcijos/{category}/{product}`
