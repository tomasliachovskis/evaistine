# Titles: "kaina" or "akcija"? (research 2026-10-08)

Owner's question: should page titles lead with **kaina** (price) or **akcija/akcijos** (promotion)? Short answer: **it depends on the page type.** Searches for a product or substance lead with "kaina". Searches for a pharmacy chain lead with "akcijos". Category and cosmetics-brand searches use both. A title should only promise "akcija" when the page really has discounts.

No code was changed. The proposed templates are at the end, to be picked by the owner.

## Sources

- **Google autocomplete** (`suggestqueries.google.com`, `hl=lt&gl=lt`), 2026-10-08, 75 terms:
  - all 50 keyword pages, 10 OTC products, 10 chains, 5 categories;
  - each with the suffixes `""`, `" k"`, `" a"`, `" kaina"`, `" akcij"`, plus the term without diacritics.
  - Autocomplete has no volumes: it shows which phrasings people type often enough to be suggested, and how high.
  - Raw output was kept in the session scratchpad. Rerun the script in this doc's history to refresh.
- **Ahrefs LT** "akcija" export from superakcijos (`docs/superakcijos-legacy/kw-akcija.csv`). It has "akcija" volumes only, no "kaina" ones, for example:
  - kolagenas sąnariams akcija 250; vitaminas d 4000 akcija 150;
  - vitaminas d / magnis / kalis magnis akcija 100 each;
  - eucerin kremas akcija 80, camelia akcija 80; omega 3 akcija 60; gintarinė vaistinė akcija 60;
  - eurovaistinė akcija 30, benu akcija 30.
- **SERPs** (WebSearch, US-based so only indicative for Lithuania), 2026-10-08: "vitaminas d kaina vaistinėje", "vitaminas d akcija", "magnis kaina", "magnis akcija vaistinėse", "nurofen kaina", "eurovaistinė akcijos".
- **Our own data** (local DB after the full scrape, 2026-10-08): the share of offers that carry a discount.

## Findings

### 1. Demand (autocomplete)

| Group | Terms | "{term} k…" suggests "kaina" | "{term} a…" suggests "akcij…" | "kaina" ranks higher | "akcija" ranks higher | Plain "{term}" suggests kaina / akcija |
|---|---|---|---|---|---|---|
| Keyword pages (substances, product types, brands) | 50 | 42 | 39 | **28** | 14 | 22 / 7 |
| OTC medicines (Ibumetin, Theraflu, Coldrex, Magne B6...) | 10 | 10 | 3 | **9** | 0 | 8 / 0 |
| Pharmacy chains | 10 | 0 | 7 | 0 | **7** | 1 / 7 |
| Categories | 5 | 2 | 2 | 2 | 1 | 1 / 1 |

- **Medicines and substances: "kaina".** Examples: "nurofen kaina", "ibumetin kaina", "paracetamolis kaina", "melatoninas kaina", "folio rūgštis kaina", "biotinas kaina".
- **Cosmetics brands and some supplements: both.** For Avene, Vichy, Eucerin, La Roche-Posay, Bioderma and CeraVe, "{brand} akcij…" returns 10 suggestions, the maximum (e.g. "avene akcija", "vichy akcijos"). The same goes for magnis, kolagenas, omega-3 and dantų pasta.
- **Chains: "akcijos" only.** For example "eurovaistine akcijos", "eurovaistine akcijos leidinys naujas", "camelia akcija", "benu akcijos". Nobody types "eurovaistinė kaina".

### 2. Who ranks (SERP)

- **"{product} kaina"**: price-comparison sites, kainos.lt and kaina24.lt, with titles in the form "**Magnis kaina nuo 2.09 € (297 pard.)**" and "Nurofen kainos nuo 2.29 € (49)". The chains' own category pages rank too (eurovaistine.lt "Vitaminas D (kaina)", gintarine.lt "Vitaminas D - Geriausia kaina"). This is exactly our format and our competition. Our edge: pharmacies only, with each pharmacy named.
- **"{product} akcija"**: mostly the chains' own category and promo pages (camelia.lt/akcijos/..., benu.lt "Mineralai iki -40%"), plus e-shops. There are few aggregators.
- **"{chain} akcijos"**: promo and leaflet aggregators, such as raskakcija.lt, kainos.lt/akcijos-nuolaidos, nuolaidos.lt, akcijos365.lt, akciju.lt and manoakcijos.lt, after the chain's own page.

### 3. What our pages actually have

- All offers: 24 373 of 67 517 discounted (36 %).
- By category:
  - Nereceptiniai vaistai 8 %, Ortopedija 11 %, Sportas 14 %, Medicinos prekės 18 %;
  - Veido priežiūra 53 %, Dekoratyvinė kosmetika 58 %.
- By keyword page:
  - Nurofen 0 %, paracetamolis 0 %, Durex 0 %, Voltaren 5 %;
  - cosmetics brands 52-69 %, vitamins about 50 %.
- By pharmacy:
  - Rx, LSMU, Universiteto and Piliulė 0 %; Ąžuolyno 1 %;
  - Benu 15 %, Gintarinė 24 %, Camelia 42 %, Eurovaistinė 90 %.

### 4. Problems in today's titles

| Page | Today | Problem |
|---|---|---|
| Product `/p/nurofen-200-mg-…` | "NUROFEN 200 mg dengtos tabletės N12 **akcija** – kaina nuo 1.98 €", H1 "… akcija" | Says "akcija" on every product, even with no discount anywhere. That is a misleading promise, and "akcija" also uses the space where "kaina" should lead |
| Pharmacy `/rx-vaistine` | "Rx vaistinė **akcijos** šiandien" | 0 discounted offers |
| Category `/nereceptiniai-vaistai` | "Nereceptinių vaistų **akcijos** šiandien – nuolaidos iki 60%" | Only 8 % discounted, while people search medicines by "kaina" |
| Keyword `/durex` | title "kaina nuo …" (good), H1 "Durex kainos **ir akcijos**" | 0 % discounted |
| Prices | "2.09 €" | Lithuanian writes "2,09 €" (the competitors use a dot as well, so this is a minor point) |

Keyword pages' titles ("Vitaminas D kaina nuo 2.09 € | 567 pasiūlymai vaistinėse") already match what wins the SERP.

## Decided (owner, 2026-10-08)

- **Product H1**: the full product name, without "akcija". A trailing ", 1 vnt" is dropped (`ProductPageMeta::headingName()`); real pack sizes such as "N12", "50 ml" and "21 vnt." stay. Done.
- **Product meta title**: keeps both words ("{name} akcija – kaina nuo {min} €"), so the recommendation below to drop "akcija" from product titles is not taken.
- The other page types are not decided yet.

## Recommendation per page type

The rule: **lead with "kaina"; add "akcijos" only where at least about a third of the page's offers are discounted; lead with "akcijos" only on chain pages that have discounts.**

| Page | Template | Example (characters before " \| eVaistine.lt") |
|---|---|---|
| Product, with a discount | `{Name} kaina nuo {min} € (−{max} %)` | "5C CURE lakštinė veido kaukė kaina nuo 1,36 € (−40 %)" (53) |
| Product, no discount | `{Name} kaina nuo {min} €` (+ "– {N} vaistinės" if it fits) | "NUROFEN 200 mg dengtos tabletės N12 kaina nuo 1,98 €" (52) |
| Product H1 | `{Name}` (no "akcija") | |
| Keyword page, generic | keep: `{X} kaina nuo {min} € – {N} pasiūlymai vaistinėse` | "Vitaminas D kaina nuo 2,09 € – 567 pasiūlymai vaistinėse" (56) |
| Keyword page, brand with ≥ ~33 % discounted | `{X} kainos ir akcijos – nuo {min} €, {N} prekės` | "Avene kainos ir akcijos – nuo 4,99 €, 232 prekės" (48) |
| Keyword H1 | "kainos ir akcijos" only with discounts, else "{X} kainos vaistinėse" | |
| Category, ≥ ~33 % discounted | `{Category gen.} kainos ir akcijos – iki −{max} %` | "Veido priežiūros kainos ir akcijos – iki −70 %" (46) |
| Category, few discounts (OTC, ortopedija, medicinos prekės, sportas) | `{Category gen.} kainos – palyginkite {N} vaistinių` | "Nereceptinių vaistų kainos – palyginkite 15 vaistinių" (53) |
| Pharmacy with discounts | keep: `{Chain} akcijos šiandien – nuolaidos iki {max} %` | "Camelia akcijos šiandien – nuolaidos iki 70 %" (45) |
| Pharmacy without discounts | `{Chain}: {N} prekių kainos` | "Rx vaistinė: 2 525 prekių kainos" (32) |
| Pharmacy + category | keep "akcijos", add "ir kainos" | "Eurovaistinės vitaminų akcijos ir kainos iki spalio 31 d." (57) |
| `/akcijos`, home | keep, since they are the promo hub and the brand page | |

## Caveats

- Autocomplete shows the order of popularity, not volumes. Before tuning further, an Ahrefs export of "{term} kaina" vs "{term} akcija" volumes for the same terms would firm this up. The owner skipped it this time.
- The SERP checks ran from a US location, so the result order may differ from a Lithuanian Google. Which sites appear, and the title formats they use, is still telling.
- Once the site is live, Search Console impressions by query ("kaina" vs "akcij") per page type are the real test. Revisit about 4-6 weeks after launch.

## If the owner picks this

Templates live in `App\Support\ProductPageMeta` (product), `KeywordPageDynamicMetaService` (keyword pages), and `Api\ProductController::generateSeoData()` / `ListingPageMetaService` (category and pharmacy listings). The discount share needed for the "≥ ~33 %" rule is a count query per page, or `discounts_count` with a discounted subcount, cached with the rest of the page meta.
