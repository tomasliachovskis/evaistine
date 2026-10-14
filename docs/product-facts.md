# Product facts ("Apie prekę")

Started 2026-10-14. The owner wants every product page to carry useful facts
about the product itself, and only facts the page doesn't already show. The
plan: `/Users/tomas/.claude/plans/nifty-inventing-boole.md` (sources checked,
superakcijos and VVKT phases).

## Already on the product page (never repeat)

- price signal "Gera kaina! / Vidutinė / Brangiau nei įprasta" (30 days),
  `ProductPageMeta::priceDealSignal`;
- lowest price in 90 days and how often it is on offer, `historyFacts` (FAQ);
- cheapest pharmacy, price during offers, daily updates (`faqItems`);
- price history chart, every pharmacy's offer with unit price;
- the medicine / food supplement notice (`<x-product-notice>`).

So nothing in this block is about price. It is about what is in the product,
how much, who it is for, where it is made, and which other products have the
same active substance.

## How it works

Everything heavy runs on the owner's Mac, like the scrapers; production only
receives finished facts (owner, 2026-10-14: no collecting or model calls in
production).

1. **Pages (local)** – `scrapers/product-pages.js` (`sail artisan
   scrapers:fetch-product-pages {store}`), nightly 00:30 for Gintarinė,
   Apotheka and Mano vaistinė side by side, 140 min each. **The work list
   comes from production**: `GET /api/scrapers/product-facts/pending/{store}`
   returns the URLs of that store's active offers that were never tried for
   that store and whose product has no facts from any pharmacy, products with
   the most `discount_histories` rows first (owner: those first), plus counts
   (active offers, products with facts), which the run logs every night as
   the coverage check. If production can't be reached, the run falls back to
   the URLs the main scrape saved (`storage/app/scrapers/urls-{slug}.json`;
   Apotheka and Mano vaistinė now save theirs too).
   - **Each page is tried once** (owner, 2026-10-14): once a product has
     facts, or its page was tried and gave nothing (no text, 404), it is not
     fetched again. Empty pages are posted too, so production records the
     try; `product-facts:extract` closes them without a model call (`facts`
     = `{"schema":1}`, `extracted_with` = `none`). Only pages that failed to
     load (timeout, 5xx) are tried again another night. One page at a time (Gintarinė 4–7 s apart,
   the others 2–4 s), 429 backoff as before. It replaced
   `scrapers:fetch-eans` / `gintarine-eans.js`: the same page gives the EAN
   (into the EAN cache) and the text.
   - Extractors: `scrapers/lib/product-page.js` (one per pharmacy) return the
     description as plain text, labelled attributes ("Gamintojas",
     "Veiklioji medžiaga", "Stiprumas", "Kilmės šalis", ...), the page's
     product name (`<h1>`) and its breadcrumb as category. Gintarinė: the
     `ul.product-accordion` items; Apotheka: the `data-ssr-config` JSON
     (`fullText`, `items`); Mano vaistinė: `#info` and
     `.product-attribute-line`. A medicine's whole package leaflet is cut to
     its opening and section 6 ("Pakuotės turinys ir kita informacija").
   - Posted in batches of 25 to the **local** `POST /api/scrapers/product-facts`
     (`SCRAPER_LOCAL_API_URL`, default `http://localhost/api/scrapers`; never
     `SCRAPER_API_URL`). A page is only marked fetched
     (`storage/app/scrapers/pages-{slug}.json`) once the API took it.
2. **Store** – `product_facts` (the same table locally and in production):
   one row per store + URL (`url_hash`; the row also marks the page as
   tried), `product_id`, page name, breadcrumb,
   page text, attributes, `source_hash`, `facts` (JSON from the model),
   `extracted_with`, `fetched_at`, `extracted_at`, `pushed_at` (local). A
   changed page clears `facts`. `products.description` is not touched: the
   "Apie prekę" block is built from `product_facts.facts` when the page
   renders.
3. **Extract (local)** – `product-facts:extract` (dev, every 10 min, 300 pages
   a run) sends rows without facts to a language model
   (`App\Services\ProductFactExtractor`; model in `config/product_facts.php` /
   `PRODUCT_FACTS_MODEL`, empty = off; `gemini-*` goes to Gemini, anything
   else to OpenAI). The page's own name and breadcrumb are the context, so no
   product needs to exist in the local DB. The prompt only lets it restate
   the page: form, pack units, daily units, minimum age, amounts per dose
   with the page's own % RMV, active substances and strength (medicines),
   ingredients verbatim, key cosmetic ingredients, SPF, explicit "be ..." and
   "tinka ..." statements, allergens, origin, manufacturer. Tokens and the
   estimated cost are logged per run.
4. **Push (local -> production)** – `product-facts:push` (dev, every 10 min)
   posts extracted rows (with `facts`) to `SCRAPER_API_URL/product-facts`,
   100 a request, and stamps `pushed_at`; a row goes again after it is
   re-extracted. Production stores them as they come and links each to its
   product through the store's offer for that URL (`ProductFactLinker`:
   discounts, then discount_histories); `product-facts:link` (production,
   every 30 min) retries rows whose offer arrived later.
5. **Show (production)** – `App\Support\ProductFacts::forProduct()` merges
   the rows of all pharmacies (fullest wins per field), tidies the model's
   free-text lists (only "be <ingredient>", no "tinka" twice, no ages under
   "tinka", no address or country as manufacturer) and builds:
   - "Verta žinoti": active substance and strength (medicines), form and pack
     size, how many days a pack lasts at the stated daily dose (not for
     medicines), minimum age (not for medicines), SPF, key ingredients,
     "tinka ...", "be ...", allergens, origin and manufacturer;
   - "Kiekis (1 kapsulėje)" table with the page's % RMV (we don't compute RMV
     ourselves yet);
   - "Sudėtis" (ingredients as written);
   - "Ta pati veiklioji medžiaga ir stiprumas": other medicines with an offer
     and the same active substances + strengths.
   `<x-product-facts>` renders it in the "Apie prekę" section (tab "Apie").
   No dosing advice or health claims; the free description text itself is
   never shown (it's the pharmacy's copy, and the same on every site).

## Choosing the model (owner: test cost first, OpenAI and Gemini)

`sail artisan product-facts:benchmark --limit=72 --models=...` runs the same
pages through each model, prints tokens, time and cost, and writes each
model's answers to `storage/app/product-facts-benchmark/{model}.json`.

Sample: 72 pages (24 per pharmacy, 2 per category from the 12 roots),
2026-10-14. Owner: judge correctness, not only price. Two checks
(scripts kept in the session scratchpad, logic described here):
- **Hand-checked answers**: 18 of the pages read by hand, correct values
  written down for kind, pack units, daily units, minimum age, origin,
  amounts per dose and active substance (several values accepted where the
  page is ambiguous), 95–96 checks per model.
- **Grounding**: every value a model returned (numbers, amounts, substances,
  origin, manufacturer, lists, ingredients) must be findable in the page text,
  attributes or name; the misses were then read one by one.

| Model | Hand-checked | Values not on the page (real ones after reading) | Time/page | USD/page | ~30 000 pages now |
|---|---|---|---|---|---|
| gemini-3.5-flash-lite | 93/95 (98 %) | 5 of 470, about 2 real (age 18 from "suaugusiems") | 1.1 s | 0.00136 | ~$41 |
| gemini-2.5-flash | 95/95 (100 %) | 12 of 491, about 4 real (invented "tinka", daily 15) | 1.7 s | 0.00135 | ~$41 |
| gpt-5-mini | 95/96 (99 %) | 20 of 544, about 12 real (allergens on cosmetics, daily 1) | 2.8 s | 0.00110 | ~$33 |
| gpt-5-nano | 80/96 (83 %) | many: ml/g taken as pack units, nappies as supplements | 3.6 s | 0.00021 | ~$6 |

gemini-3-flash-preview: 2 of 72 failed and ~$0.0021/page, dropped.
gemini-2.5-flash-lite: no longer offered to new API users. Prices are list
prices (Gemini 3.5 from price trackers); the Batch APIs of both would halve
them (not built). Recommendation: gemini-3.5-flash-lite (fewest invented
values, fastest); after the first pass only new and changed pages cost
anything. Allergens are shown for food and supplements only (the models
listed food allergens for cosmetics).

**Chosen (owner, 2026-10-14): gemini-3.5-flash-lite**, `PRODUCT_FACTS_MODEL`
in the local `.env` (production needs none). Caching checked the same day:
Gemini's implicit cache did not kick in (no `cachedContentTokenCount`; the
instructions are ~1 000 tokens, under the cache minimum), and about 60 % of
the cost is output tokens, which no cache covers, so it would save under
20 % anyway. The real halving is the Gemini Batch API (-50 %, results within
24 h), a fit for the nightly run but not built yet.

## Files

- `scrapers/product-pages.js`, `scrapers/lib/product-page.js`
- `app/Console/Commands/FetchProductPages.php`, `ExtractProductFacts.php`,
  `PushProductFacts.php`, `LinkProductFacts.php`, `BenchmarkProductFacts.php`
- `app/Services/ProductFactExtractor.php`, `ProductFactLinker.php`,
  `app/Support/ProductFacts.php`, `app/Models/ProductFact.php`
- `resources/views/components/product-facts.blade.php`,
  `resources/views/akcijos/product.blade.php` ("Apie prekę" section)
- `config/product_facts.php`, migration `2026_10_14_120000_create_product_facts_table`
- `tests/Feature/ProductFactsTest.php`

## Next

- Eurovaistinė (its page has PIM attributes: `pim_consist`, `pim_diabetic`,
  `pim_alergic`, ...) and Camelia: extractors + the same nightly run.
- superakcijos: Rimi product pages (nutrition per 100 g), LastMile for IKI and
  PROMO (`__NEXT_DATA__`), same table and block.
- VVKT register (data.gov.lt #2853, #3802) for medicines: official leaflet link.
- RMV computed ourselves when a page gives the amount but no %.
