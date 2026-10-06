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

**Redesigned 2026-09-22** — dropped date-fragile H1/title in favor of a plain evergreen shape, moved the real per-leaflet validity info into the description instead (where it can name more than one leaflet at once). Word order/wording iterated a few times same day; this reflects the final live version.

- **H1:** `Visi {Store} akcijų leidiniai` (`leidyniai` for Iki) — no validity date, no year, no "naujausi".
- **Title (`meta_title`):** `Naujausi {Store} akcijų {leidiniai/leidyniai}` — different word order from the H1 ("Naujausi" leads), same Iki-only plural spelling, still `leidiniai`-based (not "katalogai" — that word was tried and reverted same day). No date or issue number in the title at all.
- **Description (`meta_description`):** names the single newest currently-valid flyer (not the unreliable `is_active` flag — same "expired means `valid_to` < today" rule as `StoreFlyerTitleBuilder::toListingArray()`), by its own real `title` (falling back to `catalog_name`, then `Nr. {issue_number}`, then a generic "naujausias leidinys" only if none of those exist): `Dabar galioja „{flyer title}“. Peržiūrėkite katalogą ir kitus naujausius „{Store}“ akcijų {leidinius/leidynius}.` (accusative plural — a further stem-swap from the same singular nominative as the H1/title noun, "leidinys"→"leidinius"/"leidynys"→"leidynius").
- An all-caps flyer title ("NE MAISTO PREKIŲ PASIŪLYMAI") is sentence-cased in the description, keeping "Nr." (added 2026-10-01).
- Guarded: if a store has no currently-valid flyer at all, description falls back to the previous generic sentence (`Naujas {Store} {leidinys/leidynys} galioja nuo {validFrom} iki {validTo}.`, from `resolveStoreValidity()`). H1/title never fall back — they carry no per-flyer data to lose.

**Live examples (2026-09-22, local dev flyer data — some stores' flyers are already expired locally vs production, see Norfa below):**
- Rimi — H1: `Visi Rimi akcijų leidiniai` · Title: `Naujausi Rimi akcijų leidiniai` · Description: `Dabar galioja „Alkoholinių gėrimų asortimento katalogas Nr. 18“. Peržiūrėkite katalogą ir kitus naujausius „Rimi“ akcijų leidinius.`
- Maxima — H1: `Visi Maxima akcijų leidiniai` · Title: `Naujausi Maxima akcijų leidiniai` · Description: `Dabar galioja „Švaros mugė“. Peržiūrėkite katalogą ir kitus naujausius „Maxima“ akcijų leidinius.`
- Iki (Iki-only plural spelling) — H1: `Visi Iki akcijų leidyniai` · Title: `Naujausi Iki akcijų leidyniai` · Description: `Dabar galioja „IKI apdovanotų vynų ir šampanų kolekcija 2025“. Peržiūrėkite katalogą ir kitus naujausius „Iki“ akcijų leidynius.`
- Norfa (no currently-valid flyer in local dev data — both local flyers already expired vs today; production has a live Nr.519 valid until 2026.09.30) — H1: `Visi Norfa akcijų leidiniai` · Title: `Naujausi Norfa akcijų leidiniai` · Description (**local fallback**, generic): `Naujas Norfa leidinys galioja nuo 2026.09.03 iki 2026.09.16.` — on production with Nr.519 active, this instead reads `Dabar galioja „Nr. 519“. Peržiūrėkite katalogą ir kitus naujausius „Norfa“ akcijų leidinius.` (assuming that flyer has no better title/catalog_name than its issue number).

**Breadcrumbs (both this page and the single-flyer show page below), redesigned 2026-09-22:** this whole section now roots at its own "Leidiniai" crumb (→ `/leidiniai`) instead of the generic "Akcijos" home crumb every other page type uses — `/leidinys/{store}` isn't reached via `/akcijos` at all, so linking back to the discount listing there was the wrong "up" link. Built in `ProductController::generateBreadcrumbs()`'s `'store_leaflet'`/`'store_flyer_detail'` cases (`app/Http/Controllers/Api/ProductController.php`), which now fully replace the shared `$breadcrumbs` array instead of appending to it.
- Hub (`/leidinys/{store}`): `Leidiniai → {Store} leidiniai/leidyniai` (2 levels; previously `Akcijos → {Store} → Leidinys`).
- Single flyer (`/leidinys/{store}/{flyerSlug}`): `Leidiniai → {Store} leidiniai/leidyniai → {flyer's real title}` (3 levels; previously `Akcijos → {Store} → Leidinys → {flyer's real title}`, i.e. one redundant level dropped).
- Live example (Norfa, flyer `norfa-nr-17-517-...`): `Leidiniai → Norfa leidiniai → NORFA Nr. 17 (517)`.

---

## Leaflets index — `/leidiniai`

Source: `ProductController::generateSeoData()`'s `leaflets_index` case, real store count computed for free in `ProductController::getAllLeaflets()` (no new query — same data the page's own store-chip pill bar already dedupes). Own controller (`LeafletController::index()`) and view (`resources/views/leaflets/index.blade.php`).

**Redesigned 2026-09-22** (again, alongside the hub/show redesign above) — H1 is now plain/static (no store count baked in), title moved the count into a "daugiau nei N" phrase, description names the 5 main stores in quotes instead of a generic "savaitės pasiūlymus PDF ir nuotraukose" line.

- **H1:** `Naujausi akcijų leidiniai iš visų parduotuvių` — static, no store count. `leaflets/index.blade.php`'s `<h1>` is now a plain literal instead of a conditional on `$seo['leaflet_store_count_label']` (that key no longer exists).
- **Title:** `Akcijų leidiniai iš daugiau nei {storeCount} parduotuvių`
- **Description:** `Peržiūrėkite naujausius „Maxima“, „Lidl“, „Iki“, „Rimi“, „Norfa“ ir kitų parduotuvių akcijų leidinius. Visi aktualūs katalogai vienoje vietoje.` (same for `seo_description`/`meta_description` — this store-name list is hardcoded text, same 5 names + order already used elsewhere on this page's own about blurb, not derived from `MAIN_STORE_SLUGS`'s different order).
- Guarded: `$storeCount === 0` drops the "daugiau nei N" clause entirely (`Akcijų leidiniai iš visų parduotuvių`) rather than ever rendering "0 parduotuvių". H1/description never depend on `$storeCount` at all.

**Live example (2026-09-22):**
- H1: `Naujausi akcijų leidiniai iš visų parduotuvių`
- Title: `Akcijų leidiniai iš daugiau nei 39 parduotuvių`
- Description: `Peržiūrėkite naujausius „Maxima“, „Lidl“, „Iki“, „Rimi“, „Norfa“ ir kitų parduotuvių akcijų leidinius. Visi aktualūs katalogai vienoje vietoje.`

**Schema.org — leaflet pages (hub, index, single-flyer show):** previously only had `BreadcrumbList`, nothing else, despite real leaflet cover images being available everywhere. Added:
- **Hub + index:** `ItemList` with each leaflet's real name/URL/cover image, reusing `App\Support\ItemListSchema::build()` (same class used for the `akcijos/*` pages). Hub scopes to non-expired leaflets only (matches the page's own "Galiojantys"/"Pasibaigę" split); index already only ever contains active leaflets at the source query. Verified live: index reports `"numberOfItems": 91` with real images.
- **Single-flyer show page:** a plain `ImageObject` (`contentUrl` = the flyer's real cover image, `representativeOfPage: true`) — the page's first-ever structured image data.

---

## Single flyer show page — `/leidinys/{store}/{flyerSlug}`

Source: `ProductController::getStoreLeaflet()` (`app/Http/Controllers/Api/ProductController.php`), title composition shared with breadcrumbs/hub/index cards via `App\Services\StoreFlyerTitleBuilder::build()`.

**Real bug fixed**: `build()` unconditionally appended `" Nr.{issue_number}"` even when the flyer's own scraped title already stated that same issue number, producing a live double-number title (`"...Nr. 34 Nr.34"`) — confirmed systemic (every issue-numbered flyer whose scraped title states its own "Nr. X" was affected, across multiple stores). Fixed by skipping the append when the scraped title already contains `Nr.`/`Nr. ` + that number (case/spacing-insensitive). Since `build()` is shared, this also fixed the same duplication on the leaflet hub's and leaflets index's card titles, and resolved an inconsistency where this page's breadcrumb (a different code path, never affected) showed the correct un-duplicated title while the H1 right below it showed the broken one.

- **H1:** the shared flyer title (bug-fixed), e.g. `Naujas {Store} nuolaidų leidinys - {scraped title}` — wording changed from `"Naujas {Store} leidinys - ..."` to `"Naujas {Store} nuolaidų leidinys - ..."`. No injected date — the page already shows the real validity range as body text directly under the H1.
- **Title (`meta_title`, `<title>` tag):** same as H1 with **"nuolaidų leidinys" swapped to "nuolaidų katalogas"**, plus real date range appended — `{H1 text, "leidinys"→"katalogas"} – {validFrom}–{validTo}` (previously had no date at all when the flyer had its own scraped title, which is the common case). This swap is `meta_title`-only (a plain `str_replace` in `ProductController::getStoreLeaflet()`) — `seo_title` (used for the H1 and breadcrumbs/shared card titles via `StoreFlyerTitleBuilder`) still says "leidinys".
- **Description:** unchanged wording, still says "leidinys" throughout — leads with **"Naujausias"** instead of "Naujas" (description-only wording), adds the real per-flyer page count and date range — `Naujausias {Store} nuolaidų leidinys - {scraped title} – {Store} leidinys, {N} psl., galioja {validFrom}–{validTo}. Peržiūrėkite visus akcijų puslapius.`
- Guarded: a flyer with no resolvable validity dates gets no date clause in title/description (rather than a broken empty range).

**Live example (Maxima, flyer Nr.34, 2026-09-21 — note: local dev flyer data is stale):**
- H1: `Naujas Maxima nuolaidų leidinys - AČIŪ savaitinis leidinys Nr. 34`
- Title: `Naujas Maxima nuolaidų katalogas - AČIŪ savaitinis leidinys Nr. 34 – 2026.08.18–2026.08.24`
- Description: `Naujausias Maxima nuolaidų leidinys - AČIŪ savaitinis leidinys Nr. 34 – Maxima leidinys, 48 psl., galioja 2026.08.18–2026.08.24. Peržiūrėkite visus akcijų puslapius.`

---

## Product page — `/akcijos/{category}/{product}`

Source: `ProductController::generateSeoData()`'s `product` case, JSON-LD in `App\Support\ProductSchema`. Changed 2026-09-29. Before that change, meta was lowercased wholesale and the schema reused the meta title.

- **Title:** `{Name} akcija – kaina nuo {minPrice} € ({Stores})`, capped at about 65 chars. The name is trimmed at a word boundary, and a trailing bare number left by the cut ("…, 40") is dropped. `(Stores)` is added only when it fits.
- **Name casing:** `ProductPageMeta::displayName()` keeps the store's own casing (brands like `L'Oréal`, `ILAJA`). It lowercases only when more than 60% of the name's letters are uppercase (`DUŠO ŽELĖ` → `Dušo želė`).
- **Description:** `{Name} akcija – kaina nuo {minPrice} € ({Stores}). Palyginkite kainas prekybos centruose!`. Without a price: `{Name} – palyginkite kainas prekybos centruose.`. No `✔` glyph.
- **Product JSON-LD:**
  - `name` is the plain product name (it used to be the meta title with price and store).
  - `description` is the product's own description, or `{Name} kainos ir akcijos parduotuvėse`.
  - When there are 2+ offers, `offers` is an `AggregateOffer` (`lowPrice`/`highPrice`/`offerCount`) wrapping the per-store `Offer`s. With 1 offer it is a plain `Offer`.
  - A genuine discount adds `priceSpecification` with `StrikethroughPrice` = `original_price`.
  - `gtin` comes from `products.ean` when present.

**Example (Knoppers, 2 stores):**
- Title: `Vaflinis batonėlis KNOPPERS NUTBAR akcija – kaina nuo 0.82 €`
- Description: `Vaflinis batonėlis KNOPPERS NUTBAR, 40 g akcija – kaina nuo 0.82 € (Gulbelė, Ermitažas). Palyginkite kainas prekybos centruose!`

---

### Price-history facts (added 2026-09-29)

`ProductPageMeta::historyFacts()` reads the product's `discount_histories` for the last 90 days. It needs at least 3 priced rows. It returns the lowest price (store + date), the average (the current price counts as one point), and how many separate promotions ran (rows with `original_price > discounted_price`, one per store + start date).

- **FAQ:** with facts, the two template Q&As ("Kokios … akcijos galioja šią savaitę?", "Ar … kainos atnaujinamos kasdien?") are replaced by:
  - `{Name}: kokia buvo mažiausia kaina?` → `Mažiausia kaina per paskutines 90 d. buvo 1,19 € (Store), 2026.08.14. Vidutinė kaina per tą laikotarpį – 1,44 €.` (or `Dabartinė kaina – X € (Store) – yra mažiausia per paskutines 90 d.`)
  - `Kaip dažnai {Name} būna akcijoje?` → `Per paskutines 90 d. {Name} akcijoje buvo 3 kartus, paskutinį kartą iki 2026.08.10.` (1 kartą / 2–9 kartus / 10–20 kartų)
  - Without facts, the template Q&As stay. `FaqSchema` renders both cases.
- **Meta description:** when the current lowest price is at or below the 90-day low (same 3-row minimum), `Mažiausia kaina per 90 d.` goes before `Palyginkite kainas prekybos centruose!`.

### Unit price (added 2026-09-29)

- The offer card on the product page shows `X €/kg` / `€/l` next to the price (`App\Support\UnitPrice::label()`, shared with `<x-deal-card>`).
- Product JSON-LD: each `Offer.priceSpecification` gets a `UnitPriceSpecification` with `referenceQuantity` `{value: 1, unitCode: KGM|LTR}`. Only for kg/l and only when `unit_price_estimated` is false. With a strikethrough price too, `priceSpecification` becomes an array.

---

## Single flyer page: offer list (added 2026-09-29)

The flyer page was images only. `discounts.store_flyer_id` (and `discount_temp.store_flyer_id`) now records which flyer a Gemini-extracted offer came from. `StoreFlyerDiscountProcessingService` → `PdfFlyerProcessingService::processPdf(..., $storeFlyerId)` → `discount_temp` → `ProcessDiscounts`. A store + date match was tried and rejected: Iki had 5 flyers and Maxima had 3 with overlapping dates.

- Below the viewer: the section `Šio leidinio akcijos ({N})`, up to 120 `<x-deal-card>` cards. Priced offers come first, then the highest %. Cards use flex-wrap.
- `ItemList` JSON-LD with each item's name, URL, image and price. `numberOfItems` is the real total.
- The meta description gets the count: `… leidinys, 24 psl., 639 nuolaidos, galioja …`.
- Existing rows have no `store_flyer_id` and cannot be backfilled, because the page image path has no flyer id. The list appears as flyers are processed again, which happens weekly.

## Store chain page — `/parduotuves/{store}` (changed 2026-10-01)

Source: `StoreController::show()`.

- **Title:** `{Store} darbo laikas ir parduotuvių adresai`.
- **Description:** `{Store} Lietuvoje – {N} {parduotuvė/-ės/-ių}: {top 3 cities with counts} ir kiti miestai. Dažniausias darbo laikas: {summary}. Adresai, darbo laikas ir kontaktai pagal miestą.` The hours sentence appears only when more than half of the locations share one `OpeningHours::summary()`.

## Store city page — `/parduotuves/{store}/{city}` (changed 2026-10-01)

Source: `StoreController::showCity()`. GSC (Sept 2026): ~106k impressions in 28 days at 0.87% CTR, mostly "{store} darbo laikas" / "{store} {city}" queries.

- **Title:** query words first. One location: `{Store} {City} darbo laikas – {address}`. Several: `{Store} {City} darbo laikas – {N} parduotuvės`.
- **Description:** weekly hours from `OpeningHours::summary()`, never today's row (Google keeps a snippet for days, so "šiandien 08:00–21:00" was often wrong).
  - One location: `{Store} {City}, {address} ({hours}). Adresas, kontaktai ir vieta žemėlapyje.`
  - All locations share hours: `{Store} {City}: {addr1}, {addr2} ir kt. Darbo laikas: {hours}. Visi adresai ir kontaktai.`
  - Hours differ: two addresses with their hours if that fits the layout's 158-char cut, otherwise one, then ` Visi adresai, darbo laikas ir kontaktai.`
- Number agreement everywhere on these pages goes through `LithuanianPlural::storeWord()`.

## Error pages

404/500 pass `:canonical="false"`, so no canonical tag is rendered (it used to point at the homepage).

## IndexNow

`sail artisan seo:indexnow [--since=] [--dry-run]` submits product URLs whose discounts changed to api.indexnow.org (Bing, Yandex, etc.; Google ignores it). It runs from `FinalizeScrapedStoresJob` after every batch. It is a no-op outside production or without `INDEXNOW_KEY` in `.env`. The key is served at `/{key}.txt`.
