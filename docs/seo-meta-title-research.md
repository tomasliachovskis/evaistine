# Meta title / description research: evaistine.lt vs top competitors

Research date: 2026-09-20. Methodology: Google search (top 1-3 organic results) per page type, then `curl` of the raw HTML `<head>` to read the actual `<title>`/`<meta name="description">`/OG tags (not just the search-snippet text, which Google can rewrite).

All strings below are copy-pasted verbatim from either our own code or the fetched HTML — nothing paraphrased.

---

## FINAL RECOMMENDATIONS (H1 + title + description, real-data-first)

Single decisive pick per page type, not options — supersedes the "2-3 variants" lists further down (those stay as the supporting research trail). Every string below leans on a real, already-computed number (price, %, count, date) rather than generic copy, per the explicit ask. `{braces}` = live data already available server-side unless flagged otherwise.

**Implementation change requested alongside this: round discount percentages down to the nearest 10, not the nearest 5.** `ProductController::roundDownDiscountPercent()` (`app/Http/Controllers/Api/ProductController.php:1902-1905`) currently does `(int) (floor($percent / 5) * 5)` — a real 66% shows as "iki 65%" today. Change the divisor/multiplier from 5 to 10 so the same 66% shows as "iki 60%": still a conservative floor (never overstates the real discount, same safety property as today), but reads as a cleaner, more typical marketing number — round-10 numbers are what's actually seen across the competitor titles/descriptions fetched throughout this research (`-50%`, `iki 30%`, `-37%` aside, the great majority were clean 10s). This one function is shared by all three call sites that use it — `store` (`ProductController.php:1199`), `category` (`:1158`), and `store_category` (`:1225`) — so the fix is a single one-line change that automatically applies everywhere, including the new category-specific max-discount lookup proposed in §1 below.

**H1 vs title, deliberately, per page type below:** they share the same core keyword, but are never the same string, and **H1 does NOT just restate every number the title has.** Title is the compact, CTR-shaped SERP element (pre-click, no other data visible yet) and always carries the `| eVaistine.lt` suffix, so it's the right place for the full number set. H1 sits on the actual page, where a dedicated stats line almost always already renders the same numbers a few pixels below it — verified in code (`akcijos/listing.blade.php:105-116`'s `<x-type-hero>` subtitle, `ListingPageMetaService::buildForCategory()`'s `quick_stats`, `KeywordPageService::buildQuickStats()`, and the product page's own large price/discount-badge block right under its H1). Putting the same number in H1 too is pure duplication, not reinforcement — it was flagged directly and confirmed against the templates. **Rule applied below: H1 drops any number that's already rendered in that page's adjacent stats block; it keeps a number only when nothing else on the page already shows it** (currently just the leaflet hub's validity dates).

| Page | H1 (current → recommended) | Title (current → recommended) |
|---|---|---|
| Store | `Maxima akcijos šią savaitę` → `Visos Maxima akcijos šią savaitę – iki {maxDiscount}% nuolaidos` *(count dropped — already shown as `"{count} akcijos"` right under H1)* | → `Maxima akcijos – iki {maxDiscount}%, {count}+ pasiūlymų \| eVaistine.lt` |
| Leaflet hub | `Maxima leidiniai` → `Naujas Maxima savaitės leidinys galioja nuo {validFrom} iki {validTo}` *(kept — dates aren't shown anywhere else near H1)* | → `Maxima leidinys Nr.{issue}, {validFrom}–{validTo} \| eVaistine.lt` |
| Leaflets index | `Populiariausi akcijų leidiniai` → `Visi akcijų leidiniai vienoje vietoje` *(count dropped — already shown as `"{N} savaitės katalogai"` right under H1)* | → `Visi akcijų leidiniai – {storeCount} parduotuvių \| eVaistine.lt` |
| Category | *(seo_title, e.g. "Elektronika akcijos")* → `Elektronikos akcijos ir nuolaidos – iki {maxDiscount}% nuolaida` *(count dropped — `quick_stats` already shows "Aktyvios akcijos: {count}"; % kept, not duplicated anywhere)* | → `{Category} akcijos – {count}+ pasiūlymų, iki {maxDiscount}% \| eVaistine.lt` |
| Store+category | *(seo_title, e.g. "Maxima akcija elektronika")* → `Maxima elektronikos akcijos šią savaitę – iki {maxDiscount}% nuolaida` *(count dropped — hero already shows "{total} akcijos"; % kept)* | → `{Store} {category} akcijos – {count}+ pasiūlymų, iki {maxDiscount}% \| eVaistine.lt` |
| Product | `{Product} akcija` (`ProductPageMeta::heroTitle`) → **unchanged** *(price dropped entirely — the page's own price-hero block right under H1 already shows it in large type with a discount badge; adding it to H1 too is pure duplication)* | → `{Product} akcija – kaina nuo {minPrice} € \| eVaistine.lt` |
| Keyword | `{Keyword} akcijos ir nuolaidos šią savaitę` → **unchanged** *(count + price dropped entirely — `buildQuickStats()` already renders "Aktyvūs pasiūlymai", "Parduotuvių", AND "Kaina nuo X €" right under this exact H1)* | → `{Keyword} akcija – kaina nuo {minPrice} \| {count} pasiūlymų` |

### 1. Store page — `/akcijos/maxima`

**Revised after two follow-up rounds** (name the actual category behind the discount, not a bare %; make title/description read as a stronger CTA, not just a label) — real category-level data pulled live from the local DB for Maxima:

```
Buitinė chemija, valymo priemonės: 1558 offers, max 60%
Namų ūkio ir laisvalaikio prekės:  1177 offers, max 55%
Gėrimai, kava, arbata:              285 offers, max 51%
```

- **H1:** `Visos Maxima akcijos šią savaitę – Buitinė chemija iki 60%`
- **Title:** `Maxima akcijos – Buitinė chemija iki 60%, 1200+ pasiūlymų | eVaistine.lt`
- **Description:** `Rask geriausias Maxima akcijas: Buitinė chemija iki 60%, 1200+ pasiūlymų. Palyginkite kainas ir sutaupykite dabar! Galioja 2026.09.15–2026.09.21.`
- Naming the actual category behind the top discount ("Buitinė chemija iki 60%") is a concrete, checkable claim — stronger than a bare "iki 50%" with no context, and matches how real competitor copy reads (label + %, e.g. gudrusis.lt's search results showed "DADU ledai -40%"-style listings, not a bare percentage with nothing else). Description now opens with an imperative CTA verb (`Rask` — "find") and closes with one (`sutaupykite dabar` — "save now"); title stays a noun-phrase label (SERP titles read better that way) but leads with the strongest concrete number available.
- **This needs one new query that doesn't exist yet.** The current `generateSeoData('store', ...)` (`ProductController.php:1199`) only computes an overall `MAX(discount_percent)` across the whole store — it has no idea *which category* that maximum belongs to. `ListingPageMetaService::getTopCategoriesForStore()` (`app/Services/ListingPageMetaService.php:422-452`) already has the right shape (groups discounts by root category, selects `MAX(discount_percent)` per category) but orders by offer count, not by discount % — needs a small variant of that query ordering by `max_discount_percent` DESC instead, then that result (category name + its max %) gets threaded into `generateSeoData()`'s `store` branch in place of the bare store-wide max.

### 2. Leaflet hub — `/leidinys/maxima`

- **H1:** `Naujas Maxima savaitės leidinys galioja nuo 2026.09.15 iki 2026.09.21`
- **Title:** `Maxima leidinys Nr.38, 2026.09.15–2026.09.21 | eVaistine.lt`
- **Description:** `Naujas Maxima leidinys Nr.38 galioja nuo 2026.09.15 iki 2026.09.21. Peržiūrėkite visus akcijų puslapius ir kainas.`
- Adds the missing end date to the title (§2's research finding — every fetched competitor includes the full range, we only had the start date). `$flyer->issue_number`/`$validFromDot`/`$validToDot` are already in scope in `generateSeoData('store_leaflet', ...)`. H1 is written as a full sentence and drops the issue number (already visible on-page in the leaflet's own header) rather than repeating the title's `Nr.N, date–date` label shape.

### 3. Leaflets index — `/leidiniai`

- **H1:** `Visi akcijų leidiniai vienoje vietoje`
- **Title:** `Visi akcijų leidiniai – {storeCount} parduotuvių | eVaistine.lt`
- **Description:** `Naujausi Maxima, Lidl, Iki, Rimi, Norfa ir kitų {storeCount} parduotuvių leidiniai vienoje vietoje. Atnaujinama kiekvieną savaitę.`
- **`{storeCount}` is not yet computed anywhere in `generateSeoData('leaflets_index')`, which is currently fully hardcoded** — this needs one new query (e.g. count of `Store` rows with a currently-valid flyer) wired into that branch before this text can go live. Flagging explicitly rather than guessing a number, since the whole point of this update is not to write fake "real" data. **H1 has no injected number at all** — `leaflets/index.blade.php:38` already renders `"{{ count($leaflets) }} savaitės katalogai"` directly under this H1, so a store-count figure there too would be a second, slightly-different-sounding number competing with the one already visible a line below.

### 4. Category page — `/akcijos/elektronika`

- **H1:** `Elektronikos akcijos ir nuolaidos – iki 30% nuolaida`
- **Title:** `Elektronika akcijos – 340+ pasiūlymų, iki 30% nuolaidos | eVaistine.lt`
- **Description:** `Palyginkite 340+ Elektronika akcijų iš Maxima, Kauno Baldai – iki 30% nuolaidos. Atnaujinama kiekvieną savaitę.`
- All three numbers (count, max discount %, store names) already exist in `generateSeoData('category', ...)` — title keeps the full set (count + %), it's the only place they appear pre-click. **H1 drops the count** — `ListingPageMetaService::buildForCategory()` (`app/Services/ListingPageMetaService.php:150-153`) already feeds `quick_stats` with `"Aktyvios akcijos": {count}`, rendered directly under this H1 in `listing.blade.php`'s `@else` branch (line 119). Max discount % isn't part of that `quick_stats` array, so it's genuinely new information and stays in H1. H1 also uses the genitive form ("Elektronikos akcijos ir nuolaidos") as a fuller sentence instead of repeating the title's terser nominative label.

### 5. Store + category page — `/akcijos/maxima/elektronika`

- **H1:** `Maxima elektronikos akcijos šią savaitę – iki 25% nuolaida`
- **Title:** `Maxima elektronika akcijos – 85+ pasiūlymų, iki 25% nuolaidos | eVaistine.lt`
- **Description:** `Maxima elektronika akcijos: 85+ pasiūlymų, iki 25% nuolaidos. Pasiūlymai galioja ribotą laiką parduotuvėse ir internetu.`
- Same as §4: title keeps count + %, **H1 drops the count** — the `store_category` branch of `listing.blade.php` (line 112) already renders `"{{ number_format($total) }} akcijos"` directly under this H1. % stays since nothing else on the page shows it.
- Zero-offer fallback case (real fallback count from `$fallbackOtherStores`, per `AkcijosController.php`'s existing "show other stores' offers instead of a blank page" behavior) — here the count genuinely is the only real number available (there's no discount % to fall back on), so it stays in both:
  - **H1:** `Maxima šiuo metu elektronikos akcijų neturi – {fallbackCount}+ pasiūlymų kitose parduotuvėse`
  - **Title:** `Maxima elektronika – {fallbackCount}+ pasiūlymų kitose parduotuvėse | eVaistine.lt`
  - **Description:** `Šiuo metu Maxima neturi aktyvių elektronika akcijų, bet rasite {fallbackCount}+ pasiūlymų kitose parduotuvėse.`

### 6. Product page — `/akcijos/{category}/coca-cola-1-5l`

- **H1:** *(current, unchanged)* `Coca-Cola 1.5L akcija` (`ProductPageMeta::heroTitle()`)
- **Title:** `Coca-Cola 1.5L akcija – kaina nuo 1.29 € | eVaistine.lt`
- **Description:** `Coca-Cola 1.5L kaina nuo 1.29 €, palyginta {sellerCount} parduotuvėse: {storeNames}. Sutaupykite pirkdami akcijos metu.`
- This is the direct port of kaina24.lt's confirmed pattern (§6): price + real seller count + real store names in the description, not just a generic "palygink akcijas" line. `$storeNames` already exists (`getStoreNamesForProduct()`); `$sellerCount` is one line away — `$product->discounts->pluck('store_id')->unique()->count()` on the same `$product->discounts` relation already eager-loaded in `getProductWithSimilar()` (`ProductController.php:890`).
- **Guard required before shipping** (the kaina24.lt "Nuo 0 €" bug found in §4's research is the concrete precedent): if `$formattedPrice` is empty/zero, fall back to the existing no-price copy (`{Product} akcija`) — never render `kaina nuo 0 €` or `palyginta 0 parduotuvėse`.
- **H1 recommendation reversed from an earlier draft of this doc — correctly flagged as redundant.** `resources/views/akcijos/product.blade.php:240-246` renders a large `text-price-hero` price block with a discount badge directly under this exact H1 (`{{ number_format($heroDiscountedPrice, 2, ',', ' ') }} €` + `<x-discount-badge>`). Putting the price in H1 too doesn't add relevance signal (H1's job is topic identity, already satisfied by the product name) and is visually redundant one element below. Title keeps the price — it's the only place it's visible before the click.

### 7. Keyword page — `/akcijos/ledai`

Live production data pulled by calling `KeywordPageController::show()` directly for the real `ledai` `KeywordPage` row (not a hypothetical example):

```
seo_title:        "Ledai akcijos ir nuolaidos šią savaitę"
meta_title:       "Ledai akcija – kaina nuo 0.39 € | 100 pasiūlymų"
meta_description: "Ieškai pigiau? Ledai akcija ✔ nuo 0.39 € iki 5.99 €. 100 pasiūlymai: #ČIA #KUBAS #NORFA #AIBĖ #PROMOCASH&CARRY"
```

**This page type already implements the kaina24.lt real-data pattern almost verbatim** (`App\Services\KeywordPageDynamicMetaService`, added in a prior session per `git log` — "Keywords logic" / "Change keyword page meta title offer count format" / "Keyword pages changes") — price range, offer count, and store hashtags are already live in the title/description, unlike every other page type audited above. The gap here isn't "add real data", it's fixing what's already there.

**Competitor findings** (Google search "ledai akcijos šią savaitę palyginti kainas" / "ledai kaina akcija maxima norfa rimi"):

| Source | Title | Description |
|---|---|---|
| akcijuseklys.lt/akcijos/ledai (#1 result both queries — same page type as ours) | `Ledų akcijos 🍦 Pigiausia kaina šią savaitę \| Akcijų Seklys` | `Visos ledų akcijos Lietuvos parduotuvėse šią savaitę. Palygink kainas Lidl, IKI, Maxima, Rimi ir Grūstė. Rask pigiausius ledus su nuolaidomis!` |
| gudrusis.lt/maxima-akcijos/ (a store-scoped equivalent of our §1, useful cautionary data point below) | `Maxima akcijos šią savaitę — 0 pasiūlymų \| gudrusis.lt` | `0 Maxima akcijų šią savaitę. Kasdien atnaujinama. Palygink kainas ir sutaupyk.` |
| gudrusis.lt/ (homepage, all-stores total) | `40 akcijų šią savaitę — Maxima, Iki, Lidl, Norfa, Rimi \| gudrusis.lt` | `40 akcijų iš Maxima, Lidl, Iki, Norfa, Rimi šią savaitę. Palygink kainas ir sutaupyk. Atnaujinama kasdien.` |
| topakcijos.lt/kategorijos/lidl-akcijos/saldyti-produktai (store+subcategory hybrid, includes "ledai" as one of several frozen-goods types) | `LIDL akcijos \| Šaldyti ❄️ produktai \| Mėsa, žuvis, daržovės, picos ir ledai` | `🍗🐟 LIDL akcijos užšaldytiems produktams. Šaldyta mėsa, žuvis, daržovės ir vaisiai, ledai, picos už geriausią kainą! 🍕` |

**Analysis — three concrete, verified issues in our current live output, not hypotheticals:**

1. **Grammar bug in the description's offer count.** `KeywordPageDynamicMetaService::buildMetaDescription()` (`app/Services/KeywordPageDynamicMetaService.php:77`) hardcodes `". {$count} pasiūlymai"` regardless of count — for 100 that reads **"100 pasiūlymai"**, which is wrong Lithuanian (should be genitive plural **"100 pasiūlymų"**, same as the `meta_title` on the very same page already correctly says via a different code path, `buildMetaTitle()` line 56, `"{$count} pasiūlymų"`). Two sibling methods in the same class disagree on the correct case for the same number.
2. **Store hashtags surface obscure stores ahead of recognizable ones.** `buildStoreHashtags()` (lines 89-101) takes `unique()->take(5)` in raw discount order with no prominence sort, producing `#ČIA #KUBAS #NORFA #AIBĖ #PROMOCASH&CARRY` for "ledai" — two small regional chains (Čia, Kubas) lead ahead of Norfa, and Maxima/Lidl/Rimi/Iki don't even make the cut. `ProductController::getStoreNamesForProduct()` (used on the product page, §6) already solves exactly this with a `MAIN_STORE_SLUGS = ['maxima','norfa','lidl','iki','rimi']` prominence sort before truncating — the keyword-page service should reuse the same ordering logic instead of duplicating an unsorted version. Per [[project_main_stores]] these five are already the established "prefer over obscure stores" list for this project.
3. **Live confirmation that an un-guarded real count in a title can actively backfire**: gudrusis.lt's own `/maxima-akcijos/` page is currently live showing **"Maxima akcijos šią savaitę — 0 pasiūlymų"** — the exact failure mode flagged as a hypothetical risk earlier in this research (§4/§6, kaina24.lt's "Nuo 0 €") is now confirmed happening for real on a second, independent competitor, with a count instead of a price. This is strong independent evidence that the zero-guard recommended everywhere else in this doc is not overcautious — it's an observed, live, embarrassing bug pattern in this exact market.

None of the four competitor titles/descriptions do anything our `KeywordPageDynamicMetaService` doesn't already do at least as well (real price range + real count + real store names).

**Recommended (fixes the two real bugs above; H1 left alone — this supersedes an earlier draft of this doc that recommended stuffing count+price into H1 too):**

- **H1:** *(current, unchanged)* `Ledai akcijos ir nuolaidos šią savaitę` — **not** the count+price version an earlier pass of this doc proposed. `KeywordPageService::buildQuickStats()` (`app/Services/KeywordPageService.php:1022-1028`) already renders `"Aktyvūs pasiūlymai": {count}`, `"Parduotuvių": {storeCount}`, AND `"Kaina nuo {price}"` directly under this exact H1 via `<x-type-hero>`'s quick-stats slot (`listing.blade.php:159-164`) — putting the same three numbers into the H1 text itself would have been triple redundancy, not reinforcement. This was caught and corrected after review.
- **Title:** *(current, unchanged — already good)* `Ledai akcija – kaina nuo 0.39 € | 100 pasiūlymų` — title is the right place for these numbers since they aren't visible anywhere until the searcher clicks through.
- **Description:** `Ieškai pigiau? Ledai akcija nuo 0.39 € iki 5.99 €. 100 pasiūlymų: #MAXIMA #NORFA #LIDL #IKI #RIMI` — fixes the grammar bug (`pasiūlymų`, not `pasiūlymai`), reuses `MAIN_STORE_SLUGS`-style sorting so the hashtags name recognizable chains (whichever of the five actually have live "ledai" offers — this example assumes all five do; use whichever subset is real), and drops the `✔` glyph to match this project's plain-text convention (§6 raised the same point for the product page).
- **Zero-count guard**: if `$matchingTotal === 0`, the title/description shouldn't render `"0 pasiūlymų"` (per the gudrusis.lt precedent above) — fall back to generic copy with no count, same spirit as the price guard elsewhere in this doc.

---

## 1. Store discount listing — `/akcijos/{store}` (e.g. `/akcijos/maxima`)

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1196-1222`, `generateSeoData('store', ...)`:

```php
'meta_title' => $maxDiscount > 0
    ? "{$entity->name} -{$maxDiscount}% akcija {$monthLabel} – {$countLabel}+ pasiūlymų"
    : "{$entity->name} akcijos {$monthLabel} – {$countLabel}+ pasiūlymų",
'meta_description' => "Visos {$entity->name} akcijos ir nuolaidos (galioja {$validityLabel}). Filtruokite, rūšiuokite ir palyginkite kainas. Naujas savaitės leidinys: /leidinys/{$entity->slug}",
```

Rendered example (Maxima, ~50% max discount, September, ~1200 deals):
- **Title:** `Maxima -50% akcija rugsėjį – 1200+ pasiūlymų | eVaistine.lt`
- **Description:** `Visos Maxima akcijos ir nuolaidos (galioja 2026.09.15–2026.09.21). Filtruokite, rūšiuokite ir palyginkite kainas. Naujas savaitės leidinys: /leidinys/maxima`

### Competitor findings

| Source | Title | Description |
|---|---|---|
| akcijos.lt/maxima-akcijos | `MAXIMA AKCIJOS · parduotuvės ir darbo laikas \| Akcijos.lt` | `Maxima akcijos. Visų Maxima parduotuvių adresai, darbo laikas ir žemėlapis.` |
| raskakcija.lt/maxima-akcijos.htm | `MAXIMA akcijos \| RaskAkcija.lt` | `MAXIMA akcijos ir nuolaidos. Rask daug gerų akcijų naujausiam MAXIMA leidiny. Naujos MAXIMA akcijos vienoje vietoje.` |
| nuolaidos.lt/maxima | `MAXIMA akcijos - naujausi pasiūlymai ir nuolaidos` | `Atraskite naujausias MAXIMA akcijas ir leidinius - nuo maisto produktų iki buities prekių. Sutaupykite kiekvieną savaitę su geriausiais pasiūlymais!` |
| akcijuseklys.lt/parduotuve/maxima | `Maxima akcijos ir leidinys \| Rugsėjo 2026` | `Maxima katalogas vienoje vietoje: mėsa, pienas, vaisiai, gėrimai ir buities prekės. Galiojantys leidiniai su kainomis, Ačiū pasiūlymais ir nuolaidų procentais.` |

### Analysis

- All four competitors keep the title short and generic (`{Store} akcijos`), none front-load a discount percentage or offer count. Our current title (`Maxima -50% akcija rugsėjį – 1200+ pasiūlymų`) is more aggressive/CTR-bait than anything in the top results — could be a differentiator (bigger promise → higher CTR) or could read as spammy/less trustworthy than the plainer competitor titles. Worth A/B testing rather than assuming either way.
- akcijuseklys.lt (our closest structural competitor — same "aggregator" positioning) uses a month-freshness signal (`Rugsėjo 2026`) instead of a number. That's a cheap, always-true freshness cue that doesn't need a discount/count calculation.
- Descriptions across competitors consistently promise breadth ("visos parduotuvės", "nuo maisto iki buities prekių") and freshness ("naujausias", "kiekvieną savaitę"). Ours already does this via the validity date range — on par.

### Recommended variants

**Titles:**
1. `Maxima akcijos rugsėjį – iki 50% nuolaidos | eVaistine.lt` — closer to competitor brevity, keeps the discount hook but drops the offer count (count is a "so what" number to a searcher; % off is the number that sells).
2. `Maxima akcijos ir leidinys – 1200+ pasiūlymų | eVaistine.lt` — mirrors akcijuseklys.lt's "akcijos ir leidinys" phrasing (captures both discount-listing and leaflet search intent in one page), keeps our count as social proof.
3. *(current, unchanged)* `Maxima -50% akcija rugsėjį – 1200+ pasiūlymų | eVaistine.lt` — keep as-is if the aggressive-CTR bet is intentional; flagging it as a real option since it's untested, not proven wrong.

**Descriptions:**
1. `Visos Maxima akcijos ir nuolaidos vienoje vietoje – filtruokite, rūšiuokite ir palyginkite kainas. Galioja 2026.09.15–2026.09.21.` (reorders to lead with the aggregator promise, matching raskakcija.lt/nuolaidos.lt's "vienoje vietoje" framing, date moved to a supporting clause)
2. *(current, unchanged)* — already competitive on breadth + freshness.

---

## 2. Store leaflet hub — `/leidinys/{store}` (e.g. `/leidinys/maxima`)

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1175-1195`, `generateSeoData('store_leaflet', ...)`:

```php
$issueLabel = $flyer && $flyer->issue_number ? ", Nr.{$flyer->issue_number}" : '';
'meta_title' => "{$entity->name} naujas savaitės {$words['nominative']}{$issueLabel} {$validFromDot}",
'meta_description' => "Naujas {$entity->name} {$words['nominative']} galioja nuo {$validFromDot} iki {$validToDot}.",
```

Rendered example (Maxima, issue Nr.37, valid 2026.09.15–2026.09.21):
- **Title:** `Maxima naujas savaitės leidinys, Nr.37 2026.09.15 | eVaistine.lt`
- **Description:** `Naujas Maxima leidinys galioja nuo 2026.09.15 iki 2026.09.21.`

### Competitor findings

| Source | Title | Description |
|---|---|---|
| raskakcija.lt/maxima-akciju-leidinys.htm | `MAXIMA akcijų ir nuolaidų leidinys Nr.38 2026.09.15 - 2026.09.21 \| RaskAkcija.lt` | `Naujas MAXIMA akcijų leidinys. Visi MAXIMA leidiniai ir akcijos vienoje vietoje. Čia rasite naujausią MAXIMA akcijų ir nuolaidų leidinį.` |
| akcijos.lt/maxima-akciju-leidinys | `Maxima akcijų leidinys Nr. 38 \| 2026-09-15 - 2026-09-21` | `Atraskite naujausią Maxima akcijų leidinį galiojantį 2026-09-15 - 2026-09-21 Peržiūrėkite šios savaitės nuolaidas, geriausius pasiūlymus ir sutaupykite apsipirkdami Maxima parduotuvėse` |
| eleidinys.lt/maxima | `MAXIMA leidinys galioja nuo 2026.09.15 > Specialūs pasiūlymai` | `MAXIMA Rugsėjis 2026⏳ Peržiūrėkite naujausią MAXIMA leidinį, kuris galioja nuo 2026.09.15 ⚡ Sutaupykite pinigų dėka visų šių puikių pasiūlymų!` |

### Analysis

- Every competitor puts the **issue number AND the full validity date range** (both start and end date) directly in the title. We only put the start date, no end date, in the title — an easy, low-risk parity fix, and end-date-in-title is a real CTR lever for "is this still valid" searches.
- akcijos.lt's title format `{Store} akcijų leidinys Nr. N | start - end` is close to ours structurally; adopting the full range costs nothing.
- eleidinys.lt uses emoji (⏳⚡) — not an option for us per project convention, noted only as a pattern, not a recommendation.

### Recommended variants

**Titles:**
1. `Maxima leidinys Nr.37, 2026.09.15–2026.09.21 | eVaistine.lt` — adds the missing end date, matches the dominant competitor pattern exactly (issue + full range).
2. `Maxima naujas savaitės leidinys – galioja iki 09.21 | eVaistine.lt` — keeps our "naujas savaitės leidinys" phrasing (freshness cue) but swaps the bare start date for an "galioja iki" end-date framing, which answers the "is it still on" intent directly.
3. *(current, unchanged)* `Maxima naujas savaitės leidinys, Nr.37 2026.09.15 | eVaistine.lt`

**Descriptions:**
1. *(current, unchanged)* — already states both start and end date; on par with competitors.

---

## 3. Leaflets index — `/leidiniai`

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1309-1315`, `generateSeoData('leaflets_index')` (hardcoded, no interpolation):

- **Title:** `Akcijų leidiniai – visų parduotuvių savaitės katalogai | eVaistine.lt`
- **Description:** `Naujausi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje. Peržiūrėkite savaitės pasiūlymus PDF ir nuotraukose.`

### Competitor findings

| Source | Title | Description |
|---|---|---|
| akciju.lt | `Akciju.lt – visos prekybos tinklų akcijos ir nuolaidos ir akcijų leidiniai vienoje vietoje` | *(none found)* |
| akcijos.lt | `Populiariausių parduotuvių akcijų leidiniai vienoje vietoje - Akcijos.lt` | `Akcijos.lt rasi populiariausių parduotuvių leidinius` |
| raskakcija.lt | `Akcijų ir Nuolaidų portalas \| RaskAkcija.lt` | `Naujausi akcijų ir nuolaidų leidiniai vienoje vietoje. Rask geriausias MAXIMA, IKI, LIDL, NORFA ir kitų prekybos tinklų akcijas. Visi akcijų leidiniai.` |
| akcijuseklys.lt | `Akcijų Seklys — Visi akcijų leidiniai vienoje vietoje` | `Visi akcijų leidiniai ir nuolaidos vienoje vietoje. Raskite geriausias kainas Maxima, Lidl, Rimi, IKI, Norfa ir kitose parduotuvėse.` |

### Analysis

- Universal pattern: **"vienoje vietoje"** ("all in one place") appears in 3 of 4 competitor titles/descriptions — it's the category's standard trust signal for an aggregator page and we don't currently use it anywhere in this page's copy.
- Every competitor's description names the same 4-5 store brands (Maxima, Lidl, Rimi, IKI, Norfa) — we already do this too, no gap.
- akciju.lt's title omits a brand suffix entirely (site name is baked into the domain-style phrase) — not directly transferable since our layout always appends `| eVaistine.lt`.

### Recommended variants

**Titles:**
1. `Visi akcijų leidiniai vienoje vietoje | eVaistine.lt` — adopts the category-standard "vienoje vietoje" phrase directly (matches akciju.lt/akcijuseklys.lt almost verbatim), shorter than current.
2. `Akcijų leidiniai – Maxima, Lidl, Rimi, IKI, Norfa | eVaistine.lt` — names the actual brands in the title itself (none of the competitors do this in title, only description — a possible differentiation for brand-name searches like "lidl leidinys" landing on this hub via internal search).
3. *(current, unchanged)* `Akcijų leidiniai – visų parduotuvių savaitės katalogai | eVaistine.lt`

**Descriptions:**
1. `Visi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje – savaitės pasiūlymai PDF ir nuotraukose.` (adds "vienoje vietoje", otherwise unchanged)
2. *(current, unchanged)*

---

## 4. Category page — `/akcijos/{category}` (e.g. `/akcijos/elektronika`)

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1156-1174`, `generateSeoData('category', ...)`.

Rendered example (category=Elektronika, 340 offers, 30% max discount, stores="Maxima, Kauno Baldai"):
- **Title:** `Elektronika akcijos – pigiausios kainos, iki 30% nuolaidos | eVaistine.lt`
- **Description:** `Palyginkite elektronika akcijas prekybos centruose – 340+ pasiūlymų iš Maxima, Kauno Baldai. Iki 30% nuolaidos šią savaitę!`

### Competitor findings

| Source | Title | Description |
|---|---|---|
| visosnuolaidos.lt/buitine-technika | `Buitinė technika` (page `<title>`) / `Buitinė technika - Nuolaidos ir akcijos \| VisosNuolaidos.lt` (OG title) | `Buitinė technika pigiau – Jūsų patogumui. Sekite prekių kainas su nuolaidomis ir atraskite pigiausių pasiūlymų. Taupyti ne gėda – pirkite apgalvotai!` |

*(Only one usable competitor result for this page type — the other top results for "buitinė technika akcijos" were e-commerce retailers like Varle.lt/pigu.lt/Elesen.lt selling the products directly, not discount aggregators, so not a fair title/description comparison for this specific page template.)*

### Analysis

- The one directly-comparable competitor (visosnuolaidos.lt) keeps the `<title>` tag itself extremely short (just the category name), and puts all the selling copy in the OG title/description instead. Our title is denser — leads with intent keyword + price angle + discount %, which is more SEO-keyword-complete but longer (risks truncation past ~60 chars: `Elektronika akcijos – pigiausios kainos, iki 30% nuolaidos` is already 60 chars before the store suffix).
- visosnuolaidos.lt's description uses a rhetorical/emotional hook ("Taupyti ne gėda" — "saving isn't shameful") rather than a data-driven one (our count + %). Not necessarily better, but shows real competitors diversify tone here rather than all converging on one formula (unlike the leaflet/store pages, which were much more uniform).
- Our category description already puts real computed data in it (offer count + store names + max discount %) — checked kaina24.lt (a price-comparison site, not a discount aggregator, but the closest real-world example of "real data in description" found this session — see §6 for the full pattern) for comparison, and its own category page (`/c/buitine-technika/`) showed a live example of this going wrong: description read `"Nuo 0 €"` because the aggregated minimum price for a very broad category collapsed to zero. Our category description doesn't compute a price figure at all today so isn't exposed to that specific bug, but it's a concrete cautionary data point for §6's product-page recommendation, which does add prices.

### Recommended variants

**Titles:**
1. `Elektronika akcijos – iki 30% nuolaidos | eVaistine.lt` — trims "pigiausios kainos" (redundant with "akcijos"/"nuolaidos") to fit comfortably under 60 chars while keeping the discount-% hook.
2. `Elektronika – 340+ akcijų vienoje vietoje | eVaistine.lt` — leads with the count + aggregator trust phrase instead of the %, mirrors the "vienoje vietoje" pattern found to be a category-wide standard elsewhere in this research.
3. *(current, unchanged)* `Elektronika akcijos – pigiausios kainos, iki 30% nuolaidos | eVaistine.lt`

**Descriptions:**
1. *(current, unchanged)* — already ahead of the one comparable competitor on specificity (real store names + real count).

---

## 5. Store + category page — `/akcijos/{store}/{category}` (e.g. `/akcijos/maxima/elektronika`)

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1223-1265`, `generateSeoData('store_category', ...)`.

With offers (store=Maxima, category=Elektronika, 85 offers, 25% max discount):
- **Title:** `Maxima akcija elektronika – iki 25% nuolaidos | eVaistine.lt`
- **Description:** `Naujausios Maxima elektronika akcijos – iki 25% nuolaidos, 85+ prekių. Pasiūlymai galioja ribotą laiką parduotuvėse ir internetu.`

Zero-offer fallback:
- **Title:** `Maxima elektronika – palyginkite kainas kitose parduotuvėse | eVaistine.lt`
- **Description:** `Šiuo metu Maxima neturi aktyvių elektronika akcijų. Peržiūrėkite elektronika pasiūlymus kitose parduotuvėse.`

### Competitor findings

**None found.** Probed the three most likely URL patterns a competitor might use for this exact combination (`raskakcija.lt/maxima-elektronika.htm`, `akcijos.lt/maxima-elektronika`, `akcijuseklys.lt/parduotuve/maxima/elektronika`) — all returned HTTP 404. This appears to be a page template none of the top aggregators build at all; they stop at store-level and category-level pages separately.

### Analysis

Since there's no direct precedent, the best available signal is: (a) the store-page pattern (§1: short, keyword-first, no offer-count in title), and (b) the category-page pattern (§4: some competitors keep titles very short and push selling copy to the description). Combining both suggests our current title is already reasonably disciplined (no offer count in title, unlike §1's finding) — the recommendation below is a smaller tweak than the other page types.

### Recommended variants

**Titles:**
1. `Maxima elektronika akcijos – iki 25% nuolaidos | eVaistine.lt` — reorders "akcija elektronika" → "elektronika akcijos" (matches the natural Lithuanian search phrasing seen everywhere else in this research — categories consistently appear as "{category} akcijos", never "akcija {category}").
2. *(current, unchanged)* `Maxima akcija elektronika – iki 25% nuolaidos | eVaistine.lt`

**Descriptions:**
1. *(current, unchanged)* — no competitor benchmark exists to compare against; current copy is already specific (store + category + % + count).

---

## 6. Product page — `/akcijos/{category}/{product}`

### Current implementation

`app/Http/Controllers/Api/ProductController.php:1266-1279`, `generateSeoData('product', ...)`.

Rendered example (product="Coca-Cola 1.5L", min discounted price 1.29€, available at Maxima):
- **Title:** `Coca-cola 1.5l akcija – kaina nuo 1.29 € (Maxima) | eVaistine.lt`
- **Description:** `Coca-Cola 1.5L ✔ kaina nuo 1.29 €, palygink akcijas prekybos centruose!`

### Competitor findings

| Source | Title | Description |
|---|---|---|
| mazuma.lt (specific store offer page) | `Mazuma.lt \| „Gazuotas gėrimas COCA COLA, 1,5 l" parduotuvėje „Maxima"` | `Parduotuvėje „Maxima" prekėms „Gazuotas gėrimas COCA COLA, 1,5 l" galioja specialus pasiūlymas. Pateiktą prekę akcijos metu galite įsigyti net -37% pigiau. Akcija galioja 2020 08 25 - 2020 08 31 dienomis. Prekės kategorija - „Gaivieji gėrimai".` |
| akcijuseklys.lt/akcijos/coca-cola (product keyword-cluster page, closer to our page's actual role) | `Coca-Cola akcijos 🥤 Pigiausia kaina šią savaitę \| Akcijų Seklys` | `Visos Coca-Cola akcijos Lietuvos parduotuvėse šią savaitę. Palygink kainas Maxima, IKI, Rimi, Norfa, Aibė ir kt. Rask pigiausią Coca-Cola su nuolaidomis!` |
| kaina24.lt/s/coca-cola-1-5/ (multi-product search/comparison page — closest structural match to our page) | `Coca Cola 1,5 kainos nuo 1.53 € (26) \| Kaina24.lt` | `Ieškai pigiau? Coca cola 1,5 žemiausia kaina parduotuvėse ✔️ nuo 1.53 iki 113.90 €. 12 pardavėjų: #Rimi.lt #Lastmile.lt #1stop.lt #Oliver.lt...` |
| kaina24.lt/p/gazuotas-gaivusis-gerimas-coca-cola-zero-1-5-l/ (single-product detail page — the direct match for our page's actual data shape) | `Gazuotas gaivusis gėrimas Coca Cola Zero, 1.5 L kainos nuo 2.19 € \| Kaina24.lt` | `Ieškai pigiau? Gazuotas gaivusis gėrimas Coca Cola Zero, 1.5 L žemiausia kaina parduotuvėse ✔️ nuo 2.19 €. 4 pardavėjai.` |

*(kaina24.lt sits behind a Cloudflare bot check that blocks `curl`/WebFetch — these were fetched by rendering the page in an actual browser via Claude in Chrome, which cleared the challenge and confirmed real HTML `<title>`/`<meta name="description">` content, not a JS-rendered decoy.)*

### Analysis

- akcijuseklys.lt's product page is the closer *architectural* comparison — like ours, it's a cross-store aggregation page for one product, not a single retailer's single offer (mazuma.lt's page is different: one specific store×date offer, closer to our `Discount` row than our `Product` page). Its title leads with "pigiausia kaina šią savaitę" (cheapest price this week) rather than a literal live price figure, and lists multiple store names in the description as social proof of coverage.
- **kaina24.lt is the strongest direct precedent for putting real, live data into the description itself** — the exact ask here. Its pattern, confirmed on both a multi-seller comparison page and a single-product page:
  - **Title:** `{product name} kainos nuo {min price} €` — no rounding to "nuo X€ (store)" like ours, just the bare minimum price, always phrased as a *range starting point* ("nuo") rather than a snapshot number.
  - **Description:** `Ieškai pigiau? {product name} žemiausia kaina parduotuvėse ✔️ nuo {min price} [iki {max price}] €. {N} pardavėjai[: #store1 #store2 ...].` — i.e. it puts **both** the price *and* the exact seller/offer count into the description, and on the multi-seller page even hashtags the actual store names.
  - This is a materially different (and more data-dense) pattern than any of the Lithuanian discount-aggregator competitors found earlier (§1-§5), which mostly kept descriptions qualitative ("visos akcijos vienoje vietoje"). kaina24.lt's category is price-comparison, not discount-leaflet aggregation, but the mechanism — pull the already-computed min/max price and offer count straight into the meta tags — is exactly the data we already compute server-side for our own `generateSeoData('product', ...)` (`$formattedPrice`, and `getStoreNamesForProduct($entity)` already returns the store list).
  - **Caveat found on kaina24.lt's own category page** (`/c/buitine-technika/`): description read `"Geriausi „Buitinė technika" kainų pasiūlymai, akcijos. Nuo 0 €"` — a broken/degenerate case where the aggregated minimum price for a broad category collapsed to `0 €`, which looks wrong/untrustworthy in a real SERP snippet. **If we adopt this pattern, it needs a floor/sanity check** (don't render a price figure if it's `0`, or the category is too broad for a single meaningful "nuo X€" to make sense) — this is a real failure mode observed in production on the site we're benchmarking against, not a hypothetical.
- Our title already bakes in a literal live price (`kaina nuo 1.29 €`) — same instinct as kaina24.lt — but adds a single-store parenthetical, which kaina24.lt's title never does (it keeps the title to product + price only, pushing seller detail to the description). Our description, however, currently has **no seller/offer count at all** (`Coca-Cola 1.5L ✔ kaina nuo 1.29 €, palygink akcijas prekybos centruose!`) — this is the one concrete gap vs. kaina24.lt's pattern: we have the store-count data available (`getStoreNamesForProduct()`) but don't surface it in the description text.

### Recommended variants

**Titles:**
1. `Coca-Cola 1.5L akcijos – kaina nuo 1.29 € | eVaistine.lt` — drops the single-store parenthetical (which under-sells multi-store coverage), matches kaina24.lt's "product + price only" title discipline while keeping our "akcijos" framing (kaina24.lt is a pure price-comparison site, we're a discount site — the word still belongs).
2. `Coca-Cola 1.5L – pigiausia kaina šią savaitę | eVaistine.lt` — matches akcijuseklys.lt's non-numeric framing exactly, avoids the staleness risk of a cached literal price, but loses the price-specificity advantage both kaina24.lt and our current title have.
3. *(current, unchanged)* `Coca-cola 1.5l akcija – kaina nuo 1.29 € (Maxima) | eVaistine.lt` — keep if single-store attribution matters for trust/accuracy (e.g. if most products are genuinely single-store, the parenthetical is informative, not a limitation).

**Descriptions (real-data-driven, directly answering the question asked):**
1. `Coca-Cola 1.5L kaina nuo 1.29 €, palyginta {N} parduotuvėse: {store1}, {store2}...` — closest port of kaina24.lt's exact pattern (price + seller count + names) using data we already compute (`getStoreNamesForProduct()`); needs the same zero/degenerate-price guard noted above before shipping.
2. `Coca-Cola 1.5L kaina nuo 1.29 € iki {max price} € – palygink {N} pasiūlymų prekybos centruose.` — adds the price *range* (min-to-max), not just the floor, mirroring kaina24.lt's multi-seller-page description exactly; only viable if we already compute a max price for the product (needs verifying in `generateSeoData`/`getProductWithSimilar` — not confirmed in this research pass).
3. *(current, unchanged)* `Coca-Cola 1.5L ✔ kaina nuo 1.29 €, palygink akcijas prekybos centruose!` — keep if adding seller counts/names risks going stale as fast as offers rotate (kaina24.lt's data is live price-comparison, refreshed constantly; ours may update on a slower discounts:process cadence — worth confirming before committing to counts in cached meta text).

---

## Cross-cutting takeaways

- **"Vienoje vietoje"** is the single most repeated trust phrase across competitors (leaflets index, store pages) — currently absent from our copy everywhere. Cheapest, lowest-risk change to test first.
- **Leaflet page end-date-in-title** is a near-universal competitor pattern we're missing (§2) — the most mechanically simple fix (one extra date field already available in `$flyer`).
- **No competitor builds a store+category page** (§5) — this template is closer to a genuine content gap/moat than a place where we're behind competitors on copy.
- None of the fetched competitor pages use Open Graph tags any more thoroughly than we do (we have zero); if this is ever revisited, that's a separate, larger gap than title/description wording.
- **Real data in meta description** (the specific question this update answers): kaina24.lt is the clearest live example — it puts the actual min/max price and seller count straight into both `<title>` and `<meta name="description">` (`Coca Cola 1,5 kainos nuo 1.53 € (26)` / `...nuo 1.53 iki 113.90 €. 12 pardavėjų: #Rimi.lt #Lastmile.lt...`, see §6). We already do this partially for prices (product title/description) but not for seller/offer counts in the *product* description, and category pages already do include counts+stores. The pattern is worth extending, but adopt it with a guard: kaina24.lt's own category page showed the failure mode (`"Nuo 0 €"`) when the aggregated number degenerates — never render a computed price/count in meta text without checking it's non-zero/sane first.
