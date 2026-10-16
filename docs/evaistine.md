# eVaistine.lt: project decisions (2026-10-06)

## What it is

A price comparison site for Lithuanian pharmacies only: non-prescription
medicines, vitamins and supplements, cosmetics, hygiene, mother-and-baby and
medical goods. Domain: https://evaistine.lt/ (brand written `eVaistine.lt`,
without "ė").

It is a fork of superakcijos.lt (repo `/Users/tomas/www/nuolaidos`, cloned
2026-10-06 with full history; that repo is the git remote `upstream`). The
pipeline is unchanged: scrapers -> `POST /api/scrapers` -> `discount_temp`
-> `discounts:process` -> `products`/`discounts` -> Blade pages, leaflets,
price-watch emails, keyword pages.

## Stores

15 pharmacies (`config/stores.php`, with each one's website). The main
chains, in display order, are `config('stores.main_slugs')`, read through
`StoreListPriority::mainSlugs()` everywhere a "main stores first" order is
needed: Eurovaistinė, Gintarinė vaistinė, Camelia, Benu vaistinė, Apotheka.

| Pharmacy | Slug | Kind | Company (VVKT) |
|---|---|---|---|
| Eurovaistinė | `eurovaistine` | chain + e-shop | AZETA VAISTINĖ / Eurovaistinė |
| Gintarinė vaistinė | `gintarine-vaistine` | chain + e-shop | Gintarinė vaistinė (EUROAPOTHECA group) |
| Camelia | `camelia` | chain + e-shop | Nemuno vaistinė |
| Benu vaistinė | `benu-vaistine` | chain + e-shop | BENU Vaistinė Lietuva |
| Apotheka | `apotheka` | chain + e-shop | Apotheka Pharma Vaistinė |
| N vaistinė | `nvaistine` | chain (in Norfa) + e-shop | Norfos vaistinė |
| Ramunėlės vaistinė | `ramuneles-vaistine` | chain | Ramunėlės vaistinė |
| InternetineVaistine.lt | `internetine-vaistine` | online | Panpharmacy vaistinė |
| Mano vaistinė | `mano-vaistine` | online, ~37 independent pharmacies | Mano vaistinė |
| Piliulė | `piliule` | online | R. Losinskajos vaistinė |
| Universiteto vaistinė | `universiteto-vaistine` | online | Universiteto vaistinė |
| Ąžuolyno vaistinė | `azuolyno-vaistine` | online | Ąžuolyno vaistinė |
| Rx vaistinė | `rx-vaistine` | online | Rx vaistinė |
| LSMU vaistinė | `lsmu-vaistine` | online | Lietuvos sveikatos mokslų universitetas |
| 100 metų vaistinė | `100-metu-vaistine` | online | Ramunėlės vaistinė (second brand) |

Sources (checked 2026-10-07): VVKT's official list of pharmacies allowed to
sell medicines remotely
(vvkt.lrv.lt/lt/farmacine-licencijuojama-veikla_pagr_menu/nuotolinio-platinimo-vaistines/),
the largest pharmacy companies by revenue (infocloud.lt), manovaistine.lt's
member list. Left out on purpose: Parapharm (homeopathy) and Biofitus
(mostly supplements), niche; Esra and Rovifarma, physical only with no
website, so no prices; Mano vaistinė's members, whose prices are the shared
manovaistine.lt shop; Asfarma (site down) and Vaistinė plius ("coming
soon"). Recheck the VVKT list before launch.

The 7 chains have leaflet scrapers in `scrapers/flyers/`. Until a store has
offers, the `/vaistines` directory shows its card with a "Leidiniai" button.

## Leaflets (2026-10-08)

Monthly leaflets for 7 pharmacies, shown page by page; no offers are
extracted from them (`extract_discounts_from_flyer` is off for all, the
`/{slug}` price lists come from the e-shops).

| Pharmacy | Source | Dates |
|---|---|---|
| Gintarinė | own site | month in the title (`monthRangeFromText()`) |
| Benu | Issuu flipbook (`reader4.json`) | month in the Issuu docname |
| N vaistinė | nvaistine.lt/leidiniai (JS page, Puppeteer), PDF link | listing page |
| Ramunėlės | direct PDF on /akcijos/10 | upload folder month |
| Camelia | Yumpu direct download PDF | cover OCR |
| Eurovaistinė, Apotheka | Yumpu page images | Yumpu `validity`, else month in the title |

Yumpu's document JSON (page image list) is behind AWS WAF since 2026-10. A
plain fetch gets 202/403, plain headless Chrome 403, and stealth Chrome
opening the `yumpu.com/lt/embed/view/...` URL directly also 403. What works
(checked 2026-10-08, Eurovaistinė 40 pages, Apotheka 8): stealth Chrome opens
the pharmacy's own leaflet page (eurovaistine.lt/menesio-leidinys,
apotheka.lt/leidinys), and the viewer iframe's own `document/json` request
returns 200. `fetchYumpuDocumentViaPage()` in `scrapers/flyers/_shared.js`
catches that response; the page images themselves download with a plain
fetch.

Camelia doesn't need the JSON: its page links Yumpu's direct PDF download,
which isn't behind the WAF, and the title and dates come from cover OCR.
Until 2026-10-15 it still made a plain JSON fetch for a fallback title; that
fetch always got 403 and turned Camelia into WARN in every `flyers:scrape
--all` summary, so it (and `fetchYumpuDocument()`) was removed. The summary
counts *new* leaflets per pharmacy (`0 new flyer(s)`): the backend skips one
it already has, so 0 is normal for the rest of the month after the first run.

Without a leaflet: Mano vaistinė publishes a monthly leaflet, but only
aggregators (raskakcija.lt) show it, not manovaistine.lt, so we don't take it.
The online-only pharmacies (Rx, 100 metų, Piliulė, InternetineVaistine.lt,
Universiteto, Ąžuolyno, LSMU) publish none. `/leidinys/{slug}` for a pharmacy
with no current (active, ready, still valid) leaflet 301s to `/{slug}`
(`LeafletController::hub`), and such pharmacies are left out of the hub's
"Kitos vaistinės" list and the sitemap. The page comes back by itself when a
leaflet is ready.

Copy: leaflet titles come from `StoreFlyerTitleBuilder` (no "Naujas ...
nuolaidų leidinys -" prefix; all-caps words lowercased; a title that doesn't
name the pharmacy gets "{genitive} leidinys „...“"). Hub/index headings and
meta use the genitive (`PharmacyName::phrase`), and `/leidiniai` names only
chains with a current leaflet (`StoreListPriority::leafletChainsGenitiveText`).
The `store-leaflet` GPT prompt no longer claims we collect the leaflet's
products.

A new pharmacy needs: a migration (name, slug, `show_discounts_page`), its
entry in `config('stores.stores')` with the website, case forms in
`config('stores.name_forms')` if the name contains "vaistinė" (a unit test
fails otherwise), and its real logo at `public/assets/stores/{slug}.svg`.

Undated leaflets: a flyer without `valid_to` counts as valid forever
(`StoreFlyer::scopeCurrentlyValid`). `flyers:expire-undated` (ported
2026-10-13 from superakcijos commit `e9f4981`) sets `valid_to` = yesterday on
undated flyers created more than `--days` (21) days ago, bumps the `flyers`
cache, and runs daily at 03:50 in production, before `discounts:archive-expired`.
`--dry-run` lists them only. All 7 current leaflets have dates, so it found
nothing on 2026-10-13.

## E-shop scrapers (written 2026-10-06)

13 scrapers in `scrapers/`, registered in `config/scrapers.php`. They take
the full non-prescription catalog, use plain HTTP (no Puppeteer: every shop
has either an open JSON endpoint or server-rendered HTML) and share
`scrapers/lib/pharmacy.js`: the Monday–Sunday validity week (no pharmacy
publishes dates), GTIN check-digit validation, retries with a long pause on
429, the POST, and the env switches for test runs. Test without writing to
the DB:

    ./vendor/bin/sail bash -c 'SCRAPER_DRY_RUN=1 SCRAPER_MAX_PAGES=1 SCRAPER_ONLY=<category> node scrapers/<store>.js'

| Pharmacy | Source | EAN | Note |
|---|---|---|---|
| Eurovaistinė | JSON `api.eurovaistine.lt/eshop-api/luigi/search/api/taxon/render` | ~30 %, from `sku` | 12-digit `sku` is the EAN-13 without its check digit; 6–7 digits are internal codes |
| Gintarinė | HTML, first-level categories, 20 a page | product page (`itemprop="gtin"`), fetched by its own slow nightly run (`scrapers:fetch-product-pages`, see below) | rate-limits by User-Agent (429 for ~1 h after parallel requests) |
| Camelia | JSON `api.camelia.lt/api/v2/shop/search/products` | ~97 % | `prescriptionMedicine` flag, loyalty price = `card` |
| Benu | Luigi's Box `live.luigisbox.com/search`, tracker `232949-269214` | ~90 % | one main category per query (10 000-hit cap), `drugType` RX skipped |
| Apotheka | HTML (Magento, GraphQL off), 34 a page, `?cat=` subcategories | product page JSON-LD, cached | |
| InternetineVaistine.lt | HTML + JSON-LD `gtin13` | ~99 % | send `Accept: text/html`, or the shop answers with a JSON fragment |
| Mano vaistinė | HTML, `/{category}/{n}-psl`, 63 a page | product page, cached | medicines filtered with `?attrib[8]=2` (non-prescription) |
| 100 metų vaistinė | JSON `productsdata?cat=0`, paged with `Range: 0-199` | ~95 % | Ramunėlės' online shop |
| Rx vaistinė | HTML (PrestaShop), `?resultsPerPage=500` | in the product URL | |
| LSMU vaistinė | HTML + JSON-LD | ~35 % | only cosmetics, herbal teas, aromatherapy; mostly its own products |
| Piliulė, Universiteto vaistinė | HTML (Verskis, `scrapers/lib/verskis.js`) | none | small shops; sold-out products skipped (almost all of Piliulė's). Images: Piliulė's 384x384.g copies; Universiteto has none (404), so it sends the original file (`imageSize: null`, fixed 2026-10-08) |
| Ąžuolyno vaistinė | WooCommerce Store API | none | ~170 products |

The small shops (Piliulė, LSMU, Ąžuolyno) are shown like the big chains (owner's decision, 2026-10-08), even with few offers.

N vaistinė and Ramunėlės vaistinė have no e-shop: their "buy online" links
go to gintarine.lt and 100metu.lt.

Where the barcode is only on the product page, `loadEanCache()` keeps
product URL -> EAN in `storage/app/scrapers/ean-{store}.json` and fetches at
most `SCRAPER_EAN_LIMIT` (default 1500) new pages per run, one at a time, so
the first runs post some rows without an EAN and the cache fills over a few
nights. Product categories are sent as `Root/Subcategory` from each shop's
own tree, ready for `categories:map-mappers`.

## Scraping into production (2026-10-13)

Production gets its data from the owner's Mac (owner's decision; no token on
`/api/scrapers*`):
- Local `.env`: `SCRAPER_API_URL=https://evaistine.lt/api/scrapers`. The
  runners (`ScraperRunner`, `FlyerScraperRunner`, `HoursScraperRunner`) pass
  it to node, so `scrapers:run`, `flyers:scrape`, `hours:scrape` post to
  production, also when started by hand with `sail artisan`. A plain
  `node scrapers/x.js` inside the container doesn't see `.env` and still posts
  to localhost (use that, or `SCRAPER_DRY_RUN=1`, for tests).
- **The nightly scrape runs from launchd, not the scheduler** (2026-10-15,
  the same setup as superakcijos): `~/Library/LaunchAgents/lt.evaistine.nightly-scrape.plist`
  (copy of `scripts/launchd/`) runs `scripts/nightly-scrape.sh` at 00:00 under
  `caffeinate -s -i`: `scrapers:run --all` (about 4 hours, one pharmacy after
  another), then `flyers:scrape --all`, then `scrapers:run --failed` (one more
  try for each store whose latest scrape failed), and on Mondays
  `hours:scrape --all`. `lt.evaistine.evening-scrape` runs
  `scripts/evening-scrape.sh` at 18:30, also under caffeinate: the three
  `scrapers:fetch-product-pages` runs side by side (300 min), then
  `product-facts:batch-submit`; started after 23:00 (Mac asleep at 18:30) it
  skips the night. Both scripts first wait for the container clock
  (`scripts/wait-for-docker-clock.sh`): the Docker VM clock stops while the Mac
  sleeps, and a run started right after a wake saw the catch-up jump as
  elapsed time and timed out (2026-10-16, Eurovaistinė and the product pages).
  Why: the scheduler only runs while the Mac is awake; on 2026-10-15 the
  closed-lid Mac slept through 00:00 and the whole night was skipped. The Mac
  is woken by `pmset repeat wakeorpoweron MTWRFSU 00:55:00` (one repeat wake
  for both sites, set once with sudo); if it sleeps at 00:00, launchd starts
  the job at the next wake and caffeinate then keeps it awake. Mac on AC
  power, Docker Desktop started at login.
- **Logs**: `storage/logs/nightly/YYYY-MM-DD.log` (the night's full output,
  30 days, ends with an OK/WARN/FAILED/SKIPPED summary per command),
  `storage/logs/nightly/problems.log` (one block per night with only the
  problem lines, or "all OK"), `storage/logs/scraper-*.log` / `flyer-*.log`
  (runner start/success/warnings/failure), and `scraper_runs` in Filament
  (stderr tail kept on successful runs too, shown amber). A scraper is killed
  after 30 min without output or at its total timeout (or when its node process
  is killed); that store is marked failed and `--all` goes on with the next one.
  `scrapers:fetch-product-pages` (evening launchd job, 18:30) writes its
  own `storage/logs/nightly/YYYY-MM-DD-product-pages-{pharmacy}.log` and adds
  a WARN/FAILED block to `problems.log` when something went wrong (failed
  pages, failed POSTs, 429 stop).
- The Sail container still runs the scheduler for everything else:
  `[program:scheduler]` (`artisan schedule:work`) in
  `docker/8.2/supervisord.conf`, mounted by `docker-compose.yml` so it applies
  on `sail up -d` without a rebuild. Its `dev`-only jobs (only 18:00–08:00,
  the owner works on the Mac 08:00–18:00: `App\Support\QuietHours`,
  `SCRAPER_QUIET_HOURS`, long runs stop themselves at 08:00):
  only `product-facts:batch-collect` and `product-facts:push` (quick API
  calls; a sleeping Mac catches up at the next wake). The product pages,
  batch-submit and `scrapers:retry-failed` moved to launchd on 2026-10-16:
  started here they ran while the closed-lid Mac slept. The app, MySQL,
  Redis and Meilisearch containers have `restart: unless-stopped` in
  `docker-compose.yml` (2026-10-14), so they and the scheduler come back by
  themselves after a Mac or Docker restart. The other scheduled jobs also run
  locally against the local DB; harmless.

superakcijos scrapes from the same Mac at 01:00 (owner, 2026-10-14): launchd
(`~/Library/LaunchAgents/lt.superakcijos.nightly-scrape.plist`, copied from the
nuolaidos repo `scripts/launchd/`) runs nuolaidos `scripts/nightly-scrape.sh` (`scrapers:run --all`, then
`flyers:scrape --all` in the nuolaidos Sail stack, about 1.5 h; it overlaps
our run, which is fine; logs in that repo's `storage/logs/nightly/`). Not the
nuolaidos scheduler: its dev 04:00 `archive-expired` reindexes production
Meilisearch through an SSH tunnel from the local DB.

First night (2026-10-14), checked in the morning:
- **php-fpm could not write `storage`**: the server setup left
  `storage`/`bootstrap/cache` as `deploy:deploy`, php-fpm runs as www-data,
  so a request needing a log line or an uncompiled view was a fatal 500 (7 in
  the access log). Fixed with `chgrp -R www-data` (deploy is in that group, no
  sudo); `deploy.sh` now does it on every deploy. The supervisor queue logs stay
  root-owned, which is fine.
- All 13 e-shop scrapers ran 03:00–06:55 and posted to production (72 693
  rows); production processed them all (unprocessed 0, failed jobs 0).
  `scrapers:fetch-eans` grew Gintarinė's EAN cache to 1 567 (1 543 with EAN).
- **Puppeteer leaflet scrapers failed under the scheduler** (Eurovaistinė,
  Gintarinė, Apotheka, N vaistinė): supervisord runs as root and kept
  `HOME=/root` for `user=sail`, so Chrome could not start. `[program:scheduler]`
  now sets `HOME="/home/sail"`; rerun by hand, all leaflets `duplicate` (none
  new). The e-shop scrapers were fine.
- **2 320 duplicate offers**: same pharmacy, URL and week, but a second
  product. The row now carried an EAN (Apotheka, Mano vaistinė, Gintarinė
  caches fill a bit every night) or the pharmacy changed the product behind
  the URL (Eurovaistinė), so it matched another product, and `discountExists`
  only looks at product + week. `ProcessDiscounts` now moves this week's
  e-shop offer for that URL to the new product instead of creating a second
  one (`tests/Feature/ProcessDiscountsMovesOfferTest.php`). The existing
  pairs were deleted in production the same morning (2 320 rows, kept the
  newest id per store/URL/week, `store_flyer_id` null; 0 left), then reindexed both Meilisearch indexes,
  `cache:clear-discounts`, `keywords:map-products`, `keywords:refresh-counts`,
  `deal-pool:refresh`.

Gintarinė EANs: the barcode is only on each product page (listing pages,
sitemap and feeds have none), and fetching ~18 000 pages during the main
scrape got 429s that also cost listing pages. Now:
- `scrapers/gintarine-vaistine.js` reads the EAN cache only
  (`loadEanCache(slug, { limit: 0 })`) and saves the product URLs it saw to
  `storage/app/scrapers/urls-gintarine-vaistine.json` (full runs only).
- `scrapers/gintarine-eans.js` (`sail artisan scrapers:fetch-eans
  --minutes=140`, nightly 00:30, done before 03:00; since 2026-10-14
  `scrapers/product-pages.js` / `scrapers:fetch-product-pages`, which also
  takes the page text for the product facts, docs/product-facts.md) works
  through the URLs without a cached EAN: one page every 4–7 s, a 429 waits (Retry-After, else
  2/4/8 min), five in a row end the night. About 660 pages an hour, so the
  catalog (17 680 left on 2026-10-13) takes about 12 nights; then only new
  products. The first URL list was taken from that day's `discount_temp`.
- The EANs reach the site on the next main scrape: `ProcessDiscounts` matches
  the EAN product of another pharmacy first. The old Gintarinė-only product
  keeps its offer until the week ends, then has none; merging those leftovers
  is part of the "Multiple EANs per product" TODO.

## Second full scrape (2026-10-13)

Run before the production launch, into the local DB. Result: 72 622 current
offers (week 2026-10-12 – 10-18), 54 407 products, 52 128 with an offer,
9 759 priced at 2+ pharmacies (was 8 518), 0 offered products without a
category. Per pharmacy: Gintarinė 17 863, Benu 12 600, Eurovaistinė 10 922,
Camelia 10 550, Mano vaistinė 7 411, Apotheka 4 558, 100 metų 2 993, Rx 2 510,
InternetineVaistine.lt 2 365, Universiteto 573, Ąžuolyno 163, LSMU 97,
Piliulė 17. Leaflets: all 7 October leaflets were already in, nothing new.
Logs: `storage/app/scrapers/logs/*-2026-10-13*.log`.

What came up and how it was handled:
- **`scrapers:run --all` runs the stores one after another** (Oct 7 ran them
  in parallel), about 5 hours in total. Killing one store's `node` process
  aborts the whole `--all` loop (`ProcessSignaledException`) and leaves its
  `scraper_runs` row `running`, which blocks every later run
  (`isAnotherRunInProgress()`); mark it failed by hand.
- **Gintarinė 429s** again when fetching product pages for EANs; the 429s
  also made it skip listing pages ("Giving up on dekoratyvine-kosmetika-4
  page 11", 64 pages lost). Re-run with
  `sail bash -c 'SCRAPER_EAN_LIMIT=0 node scrapers/gintarine-vaistine.js'`:
  17 879 rows, no 429s, but only 198 with EAN. Gintarinė's EANs still need a
  slow nightly run (TODO).
- **Mano vaistinė moved two roots**: `/medicinos-prekes` ->
  `/medicinos-prekes-iranga`, `/arabatos-ir-vaistazoles` (typo in our key) ->
  `/arbatos-vaistazoles`; the old URLs 404, so these products had never been
  scraped. Both now have menu entries. +2 040 rows. 29 new category strings
  mapped by `categories:map-mappers` (teas -> "Sportas, svorio kontrolė,
  arbatos ir spec. maistas", supplements -> "Vitaminai ir maisto papildai").
  "Medicinos prekės/Akims" was shadowed by the coarse "Medicinos prekės ir
  prietaisai" mapper (prefix fallback), so it got its own mapper (-> "Akių
  priežiūra ir optika") and `products:recategorize --from="Medicinos
  prekės/Akims"` moved 47 products. Blank-category rows (mixed buckets) went
  through `categories:bulk-map "Mano vaistinė"`.
- **`unit_price` overflow**: "Oscillococcinum 0,01 ml/g N30" was read as a
  0.01 ml pack, the per-litre price overflowed `discounts.unit_price`
  (decimal(8,2)) and the insert failed. `ProcessDiscounts::resolveUnitPrice()`
  now leaves the unit price empty above 999 999.99.
- **Rows processed without an offer**: after the first `discounts:process`,
  2 374 of today's rows had no `Discount` (unmapped categories, the overflow).
  Reset to `processed = false` and processed again. ~255 rows stay without
  an offer on purpose: the same product under a second URL at the same
  pharmacy (variants/shades with one name, Benu's promo shelf).
- Afterwards: `discounts:archive-expired` (69 547 offers of the old week),
  `products:index-meilisearch`, `discounts:index-meilisearch`,
  `keywords:map-products`, `keywords:refresh-counts`, `deal-pool:refresh`,
  `cache:clear-discounts`.

## Mixed categories (fixed 2026-10-08)

About 1 700 products came from pharmacy categories that mix product types, so each landed in one approximate root:
- Benu "Specialūs pasiūlymai ir akcijos/Sezono svarbiausi" (~660, all filed as vitamins, ~360 of them sun creams).
- Mano vaistinė "Kosmetika ir higiena" (587, all Higiena), "Kitos prekės" (250) and "Kitos prekės/Kelionėms" (193), all filed as Medicinos prekės.

What changed:
- **Benu** (`scrapers/benu-vaistine.js`): the promo shelf sends `Sezono svarbiausi/{lvl3}/{productType}` from Luigi's Box (`main_category_lvl_3` + Benu's own `productType`), e.g. "Sezono svarbiausi/Apsauga nuo saulės/Kosmetika". The 12 strings were mapped by `categories:map-mappers`, and all 12 were reviewed. productType "Receptinis vaistas" is skipped like `drugType` RX. Luigi's Box returns ~10 % repeats across pages on this shelf (576 unique of 658), and the missed products keep their earlier offers.
- **Mano vaistinė** (`scrapers/mano-vaistine.js`): subcategories come from the header menu (`li.hassub` → `.nav--sub ul.links--two`), whatever their URL prefix, plus the old `/{root}/` links (the menu leaves out "Dekoratyvinė kosmetika"). The old rule alone found only "Dekoratyvinė kosmetika". Mixed buckets ("Kelionėms", "Dovanos", and the root listings of "Kosmetika ir higiena" and "Kitos prekės") are sent with an empty category, so `discounts:process --map-categories` classifies them by name. "Naujos prekės" (`/prekes`) is skipped.
- **Existing products**: `products:recategorize --store=… --from=… [--dry-run]` re-files products the store listed only under a mixed string. It uses, in order, the majority of other pharmacies' categories for the same product, the store's new category, or GPT by name. It prints a before→after table.
- **Shadowing**: new "Kosmetika ir higiena/…" strings would be caught by the existing "Kosmetika ir higiena/Dekoratyvinė kosmetika" mapper through `resolveCategoryId()`'s first-part prefix fallback (`str_starts_with`). So the new strings get mapper rows and are mapped before `discounts:process` runs, and the coarse mappers were deleted.

Results:
- **Benu**: 368 products changed (271 Vitaminai → Kūno priežiūra ir apsauga nuo saulės, 39 → Medicinos prekės, 36 → Nereceptiniai vaistai, …).
- **Mano vaistinė**: re-scraped "Kosmetika ir higiena" and "Kitos prekės" (2 720 rows, 15 new category strings mapped by `categories:map-mappers`, 91 root leftovers classified by GPT, 1 left unclassified). 762 products changed (333 → Veido priežiūra, 98 → spec. maistas, 56+48 → Higiena, 49+14 → Plaukų priežiūra, …).
- Caveat: "Kosmetika ir higiena/Dermatologinė kosmetika" maps to Veido priežiūra. It is mostly face care (~270 of ~420), but its body and hair products only get the right root when another pharmacy sells them (recategorize prefers their vote). Its own subcategories (veidui, kūnui, plaukams) would fix that if it matters.
- Afterwards ran `products:index-meilisearch`, `discounts:index-meilisearch`, `keywords:map-products`, `keywords:refresh-counts`, `deal-pool:refresh` and `cache:clear-discounts`.

## Pharmacy addresses and hours (2026-10-08)

`store_locations` feeds `/vaistines/{slug}` and `/vaistines/{slug}/{city}`. `scrapers/hours/scrape.js` collects it per pharmacy and POSTs to `/api/scrapers/store-locations`, which upserts by `external_id`, deactivates locations that disappeared, and stores the `source`. Run all with `sail artisan hours:scrape --all` (scheduled weekly in `Kernel.php`) or one with `sail artisan hours:scrape "N vaistinė"`. Use `SCRAPER_DRY_RUN=1 node scrapers/hours/scrape.js {slug}` inside the container to print without saving.

| Pharmacy | Source (`scrapers/hours/sources/`) | Locations (2026-10-08) |
|---|---|---|
| Eurovaistinė | `eurovaistine.js`: the pharmacy objects in eurovaistine.lt/vaistines' Next.js flight data (the HTML shows only a dozen); temporarily closed ones left out | 245 |
| Gintarinė vaistinė | `gintarine.js`: the `Destinations`/`Cities` JS object on gintarine.lt/vaistines; only entries named "Gintarinė…" (the list also has N vaistinė's "Norfos vaistinė" pharmacies) | 241 |
| Camelia | `camelia.js`: api.camelia.lt `/api/v2/shop/locations?itemsPerPage=5000` (3 600 pickup points); pharmacies = `mainTypeImage` set, `enabled`, with a street | 315 |
| Benu vaistinė | `benu.js`: the `pharmacies = {...}` JSON on benu.lt/vaistiniu-paieska; "00:00 - 00:00" every day = open 24h (3 duty pharmacies); town from `region`, or the address part after the street when `region` is a municipality | 82 |
| Apotheka | `apotheka.js`: the JSON-LD list on apotheka.lt/shops, then each pharmacy page's JSON-LD (hours, geo); 42 pages | 42 |
| N vaistinė | `nvaistine.js`: the `_markers_data_main` JSON on nvaistine.lt/vaistines/ | 125 |
| Mano vaistinė | `mano-vaistine.js`: the cards on manovaistine.lt/visos-vaistines, no coordinates | 48 |
| Ramunėlės vaistinė, 100 metų vaistinė | `ramuneles.js`: the `deliveryStores` JSON on 100metu.lt/vaistines/57 (the same company and pharmacies) | 22 each |
| Ąžuolyno, LSMU, Universiteto, Piliulė, Rx, InternetineVaistine.lt | `manual.js`: hand-checked `scrapers/hours/manual-locations.json` from their contact pages | 1, 1, 4, 1, 1, 1 |

Notes:
- Every source returns nuolaidos' `workTimes` notation (`"I-V 08:00-20:00"`, `"VII Nedirba"`), which `WorkingHoursParser` reads. `_shared.js` converts each site's own notation (`normalizeHours()`, `workTimesFromRows()`, `rowsFromLine()`). A lunch break is kept as `"09:00-13:00, 14:00-16:00"`, shown as text. A day left out is unknown (no hours shown), and "Nedirba" shows it closed.
- Towns: addresses that name only a municipality ("Šilalės r. sav.") get its centre town (`municipalityCentre()`). One N vaistinė address has no town at all and is mapped by id (`TOWN_BY_ID`, Palanga, checked against its coordinates).
- Piliulė and Rx publish no hours, only an address. Mano vaistinė and the manual pharmacies have no coordinates, so they get no map pins.
- The five big chains came from nuolaidos.lt (`nuolaidos.js`, kept as a fallback) until 2026-10-08, when they moved to their own sites. nuolaidos.lt kept closed pharmacies: Camelia's API marks "V. Krėvės pr. 97H", Miško g. 30 and Kareivių g. 11A as disabled, and spot-checked "only on nuolaidos.lt" addresses (Eurovaistinė Didlaukio, Liepkalnio; Benu Bartuvos, Šeškinės; Gintarinė Juozapavičiaus g. 13; Apotheka Stoties g. 28) are on none of the chains' lists. The switch deactivated the old rows (different `external_id`s) and created new ones: before → after 261→245, 230→241, 298→315, 85→82, 56→42; 1 151 active locations in all.
- The manual file needs a manual update when those pages change. Its `_comment` says when it was last checked.

## Product photo caching (checked 2026-10-08)

`products:cache-images` (production only, every 5 minutes) downloads each hotlinked `image_url` into `storage/app/public/products/` and rewrites it to `{APP_URL}/storage/products/...`. Nothing in it points at superakcijos. It keys off `APP_URL`, so run it only where `APP_URL` is the real site, never from a local checkout against the production DB.

A test of 3 images per host with the command's own HTTP client: every pharmacy CDN serves us (Gintarinė's Azure blob, benu.lt, api.eurovaistine.lt, images.camelia.lt, apotheka.lt, manovaistine.lt, armila.com, rx-vaistine.lt, internetinevaistine.lt, lsmu.lt, piliule.lt). The exception was Universiteto vaistinė, where every image returned 404 because the scraper forced a 384px size that shop doesn't have. Fixed in the scraper, and 538 stored links were rewritten to the original files.

## Wording: "vaistinė", not "parduotuvė"

Page copy rules and the GPT prompt rules ("vaistų kainos", SEO headings, no prescription mentions, YMYL) are in `docs/copy-and-prompts.md`.

Every URL and text says vaistinė: `/vaistines`, `/vaistines/{slug}/{city}`,
`POST /mano-vaistines`, "Visos vaistinės", "Kainos vaistinėse". The two
words decline the same way, so the rename kept every ending. Where a chain
name meets the noun, use `App\Support\PharmacyName::phrase($name, $case)`:
it gives "Camelia vaistinėje" but "Benu vaistinėje" and "Eurovaistinėje",
never "Benu vaistinė vaistinėje". Names that already say "vaistinė" are
inflected from `config('stores.name_forms')`; add a new chain there too.
Internal identifiers (`StoreController`, `MyStores`, the
`evaistine_parduotuves*` cookie and localStorage keys) were left as they are.

## URL structure (2026-10-07)

Chosen before launch, so it never has to change:

| Page | URL |
|---|---|
| Category | `/{category}` (e.g. `/vitaminai-ir-maisto-papildai`) |
| Pharmacy offers | `/{pharmacy}` (e.g. `/eurovaistine`) |
| Pharmacy x category | `/{pharmacy}/{category}` |
| Keyword page | `/{keyword}` (e.g. `/ibuprofenas`) |
| Product | `/p/{product}` |
| All offers | `/akcijos` |
| Search (noindex) | `/paieska/{query}` |
| Addresses, leaflets | `/vaistines/...`, `/leidiniai`, `/leidinys/...` (unchanged) |

- **Products carry no category in the URL.** On superakcijos a product's
  category was part of its URL, which produced ~500 "duplicate, Google chose
  a different canonical" pages whenever categories were re-mapped. Now a
  category can be renamed, merged or a product moved, and only the one
  category URL changes.
- **Listings are flat**, like Gintarinė and Benu. The route is the last one
  in `routes/web.php`, marked `->fallback()`, so every real route (and
  Livewire's hashed one) wins whatever the registration order.
- **Slugs can't collide.** `App\Rules\FreeTopLevelSlug` refuses a keyword
  page slug that equals a pharmacy, a category, another route's first
  segment or a `public/` entry. The Filament form and both keyword importers
  use it. Future ailment and brand pages go into the same space and must
  use it too.
- Build URLs with `App\Support\PageUrl` (`product()`, `listing()`,
  `search()`).
- Category slugs stayed as they were (long slugs don't hurt SEO; shortening
  would only be cosmetic).

## Categories (seeded 2026-10-07, no icons)

Researched on Eurovaistinė, Gintarinė, Camelia, Benu, Apotheka and
vaistai.lt. All of them agree on about eight product-type groups. Products
link to one root category only (same rule as superakcijos), so the roots are
product types:

1. Nereceptiniai vaistai
2. Vitaminai ir maisto papildai
3. Veido priežiūra
4. Kūno priežiūra ir apsauga nuo saulės
5. Plaukų priežiūra
6. Dekoratyvinė kosmetika ir kvepalai
7. Higiena
8. Mamai ir vaikui
9. Medicinos prekės ir prietaisai
10. Ortopedija ir kompresinės prekės
11. Akių priežiūra ir optika
12. Sportas, svorio kontrolė, arbatos ir spec. maistas

Seeded by `2026_10_07_100000_seed_pharmacy_root_categories`. Their short
names, genitive and dative forms, item examples, display order and popular
set are in `config/categories.php` (read by `ProductController`,
`DealPoolRefresher`, `HomeDealPoolService`, `HomePageSectionsService`,
`CategoryMappingService`). Categories are shown without icons: the owner
dropped them, so lists and cards are text only.

A second layer, by ailment (skausmas, peršalimas ir imunitetas, virškinimas,
sąnariai...), is planned as its own `product_ailments` table with
`/nuo/{ailment}` pages, because one product can serve several ailments.
Active substances (ibuprofenas, vitaminas D, magnis) use keyword pages.

Prescription medicines are not listed: Lithuanian law allows advertising only
non-prescription medicines.

## Look

- Palette (`resources/css/app.css`): navy buttons `--color-action #13306a`
  (white text 12.7:1), navy links and footer `--dark-green #0f234a`, mid-blue
  accent `--green #2b5ba8` for borders and soft tints, soft background
  `--green-soft #eef2f8`. The token names still say "green" from
  superakcijos. They hold the navy palette now.
- Discount badges use `--color-deal` (raspberry `#b5125e`, white text 6.5:1),
  through `bg-deal text-deal-foreground`. Never hardcode a badge color.
- Logo (`public/assets/logo.svg`, `logo-white.svg`), recolored 2026-10-08:
  mid-blue `#2b5ba8` rounded tile with a white cross, wordmark "eVaistine"
  navy `#0f234a` and ".lt" mid-blue, outlined from Inter 600. `logo-white.svg`
  (navy footer, email headers) is a white tile with a mid-blue cross, white
  wordmark and ".lt" `#9db8e3` (mid-blue is too dark on navy). The old
  pharmacy green `#58A618` was dropped because no other part of the site used
  it. Variants compared: navy mono, mid-blue tile (picked), raspberry ".lt", and
  a two-tone "e". Keep the cross white or blue. A red or raspberry cross on a
  light background is too close to the protected Red Cross emblem.
  `public/favicon.ico` (16/32/48) is the tile and cross alone. It was rendered
  with `rsvg-convert` and packed with PHP Imagick inside the Sail container.
- The site keeps superakcijos' older-reader rules (`docs/ui-older-readers.md`).

## Fresh-DB schema

The superakcijos production schema had drifted from its migrations (columns
changed by hand). `2026_10_06_130000_align_schema_with_production` makes a
fresh DB match it column for column: `discount_temp` keeps raw strings and a
store *name* (`store`, not `store_id`), and prices, dates and category ids are
nullable. Without it every real scraper row was rejected.

## Removed grocery features (2026-10-08)

Owner's call: keep `/pradzia-beta` (HomeBeta) and `/pigiausios-prekes`, drop
the rest of the superakcijos leftovers.
- Coupons (`/kuponai`): controller, views, models, Filament resources and
  sitemap entries removed; tables dropped by
  `2026_10_08_130000_drop_coupon_tables` (both were empty).
- `resources/views/welcome.blade.php` (unused Laravel stub) and the alcohol
  age-check modal (only for `alkoholiniai-gerimai`) removed.
- `FoodCategorySlugs` removed, with the food-only code around it: the
  "Maisto prekės" featured block and "Maisto prekėms / Chemijai / Namams"
  most-saved block in `ListingPageMetaService` (no view rendered them), the
  food filter of store top categories, the flyer page's "2 most common food
  categories" filter (now any 2 categories) and the food preference in
  `topFlyerDiscounts()`. `DealPoolRefresher` builds one `home_best` pool from
  every root category (max 4 per category); `home_food`/`home_non_food` are
  gone (`HomePageSectionsService` returns them empty for the unrouted old
  `home.blade.php`). `/akcijos` keeps its per-category carousels.
- Breadcrumb root is "Pradžia" → `/` everywhere (was "Akcijos", from
  superakcijos, where the home page was the offers). The product page cache
  key went to `product_with_similar_v15_` so cached product pages pick it up.

## Safety: nothing reaches superakcijos.lt

- Scrapers POST to `http://localhost/api/scrapers` unless `SCRAPER_API_URL`
  is set (for production it will be `https://evaistine.lt/api/scrapers`).
- `deploy.sh` takes its server only from `DEPLOY_SERVER` (user@host), with no
  default, and stops if the server's `.env` would put this app on
  superakcijos' Redis DBs 0/1 or bare Meilisearch indexes. (Until 2026-10-13
  it also exited on its first line; removed for the launch.)
- Meilisearch is this Sail stack's own container. The SSH tunnel to the
  superakcijos server was removed.
- Local Sail uses its own project name (`COMPOSE_PROJECT_NAME=vaistines`),
  ports (app 8081, MySQL 3307, Redis 6380, Mailhog 1026/8026, Vite 5175) and
  database (`vaistines`). A copied `.env` with `COMPOSE_PROJECT_NAME=nuolaidos`
  once recreated the superakcijos containers on this checkout, so check that
  line first in any new copy.
- No analytics: the GA4 (`G-WD8DH3ZRD3`) and Clarity (`v3dr99seco`) tags in the
  layout were superakcijos' accounts and were removed 2026-10-08 (owner: "kol
  kas be analitikos"). eVaistine's own GA4 (`G-B62M2HNEWJ`) added 2026-10-13,
  see "Analytics" under "Legal".

### Ties removed 2026-10-08 (audit for superakcijos links)

Code mentions of "superakcijos" are now comments only. What was removed or changed:

- **Old Next.js app** (owner: no longer used): the token API `POST /api/auth/{login,register,oauth,refresh}` and `GET /api/user` (`Api\AuthController` deleted). `/api/auth/oauth` accepted NextAuth JWTs signed with a secret shared with superakcijos. Also removed: `services.nextauth`, `NEXTAUTH_SECRET`, the direct `firebase/php-jwt` dependency (Socialite still pulls it in), `deploy-photos.sh` and `scripts/sync-product-photos-to-frontend.sh` (photo sync to the Next.js server), and the comments that explained code by the Next.js app. Site login (session + Google/Facebook through Socialite) is unchanged.
- **Superakcijos server IPs** (`84.247.186.143`, `195.181.245.125`) removed from `deploy.sh` and the nginx config comment. Supervisor configs renamed to `deploy/supervisor-evaistine-*.conf` (program names `evaistine-discounts`, `evaistine-flyers`, `evaistine-flyers-gemini`).
- **Footer "Sekite mus"** removed. Facebook `profile.php?id=61586857013836` was superakcijos' page, and `instagram.com/evaistine.lt` isn't ours. Add the block back when eVaistine has its own pages.
- **Grocery blog posts** (one titled "... - SuperAkcijos.lt") deleted by migration `2026_10_08_140000_delete_grocery_blog_posts`. `/naujienos` is empty now.
- Kept on purpose: `www.nuolaidos.lt` in `scrapers/hours/` is a third-party source of pharmacy addresses and hours, not superakcijos. "Ported from discount/src/..." comments only record where a file was copied from.
- **Old JSON API removed** (owner: "išimti"). The `/api/*` routes the Next.js frontend used (`/discount*`, `/search`, `/stores`, `/categories`, `/keywords`, `/leidiniai`, `/leidinys/*`, `/product/*/with-similar`, `/sitemap*`, `/favorite/*`, `/blog-posts*`, `/assistant/cart-comparison`) are gone, along with `Api\ProductWithSimilarController`, `Api\BlogPostController`, `Api\ProductAssistantController`, the unused favorite methods and the `laravel/sanctum` package. What's left in `routes/api.php`: `/api/scrapers*` (the Node scrapers) and `GET /api/store-locations/{slug}` (the store page's map loads it from `resources/js/app.js`). The `Api\ProductController` and `Api\KeywordPageController` methods stay: Blade controllers, Livewire and services call them as PHP. Tests that used the API URLs now call those methods or the Blade pages.
- **Unused tables dropped** (owner approved 2026-10-08, migration `2026_10_08_150000_drop_generic_products_and_tokens`):
  - `personal_access_tokens` (Sanctum).
  - `generic_products` with `products.generic_product_id`: grocery commodity groups, empty here. Their code went too: the `GenericProduct` model, the `generic-products:match` command, `DealFamilyKeyResolver`'s first branch, and the product page's "Radome panašų produktą su aktyvia nuolaida" mockup block, which only these groups fed.
  - Kept on the owner's choice: `cache` and `cache_locks`, unused because the cache is Redis. Every other table is written or read by live code.
- **Still shared, owner to replace:** local `.env` has the same `OPENAI_API_KEY`, `GEMINI_API_KEY` and `MEILISEARCH_KEY` as superakcijos (same billing and limits), plus a leftover `NEXTAUTH_SECRET` that nothing reads any more. Use eVaistine's own keys in production.

## Sharing the server with superakcijos (2026-10-08)

Production is planned on the same server as superakcijos.lt. As forked, eVaistine would have clashed with it in several places. What was changed, and what the production setup must keep:

| Shared thing | Risk as forked | Now |
|---|---|---|
| Code directory | `deploy.sh` rsynced into `/var/www/api` (superakcijos' directory) with `--delete` | `/var/www/evaistine` in `deploy.sh` (passed into the SSH script as `$REMOTE_DIR`), the nginx vhost and the supervisor configs |
| nginx | vhost installed as `sites-available/api`; Cloudflare `real_ip_header` at http level, which a second site makes a duplicate (`nginx -t` fails, checked in Docker with both files); `api-*.log`; an `:8080` `default_server` vhost | Install as `sites-available/evaistine`; the real-IP block sits inside the `server` block; logs `evaistine-*.log`; `deploy/nginx-8080-nossl.conf` deleted |
| Meilisearch | same index names `discounts`/`products`, so either app's reindex overwrites the other's | `MEILISEARCH_INDEX_PREFIX=evaistine_` in production (`services.meilisearch.index_prefix`, read by `MeilisearchService` and `ProductSearchIndex`; empty locally). Give eVaistine its own Meilisearch API key limited to `evaistine_*` |
| Redis | same DB numbers 0/1; `deploy.sh`'s `optimize:clear` runs `cache:clear`, which FLUSHDBs the whole cache DB | Production `.env`: `REDIS_DB=2`, `REDIS_CACHE_DB=3` (superakcijos keeps 0/1), plus the pinned `REDIS_PREFIX`/`CACHE_PREFIX` |
| php-fpm | `deploy.sh` restarted the shared pool (502s on both sites) | `systemctl reload` (graceful, fresh opcache) |
| Backups | the purge deleted every old `*.sql.gz` in the folder, superakcijos' too | purges only `{database}_*.sql.gz`; production `DB_BACKUP_PATH=/var/www/backups/evaistine` |
| Supervisor, cron, MySQL | | programs are `evaistine-*`; its own `* * * * * cd /var/www/evaistine && php artisan schedule:run` line; own database `vaistines` and its own MySQL user (don't reuse superakcijos' user) |

Production `.env` lines specific to sharing the server:
```
APP_ENV=production
REDIS_PREFIX=evaistinelt_database_
CACHE_PREFIX=evaistinelt_cache
REDIS_DB=2
REDIS_CACHE_DB=3
MEILISEARCH_INDEX_PREFIX=evaistine_
DB_BACKUP_PATH=/var/www/backups/evaistine
```

Also check the server has room for both: two sets of queue workers, both MySQL databases, and, if scrapers run on the server, two Chrome instances.

## Cache namespace (fixed 2026-10-08)

`/leidiniai` kept showing an old empty list although `CacheVersion::bump('flyers')`
ran. The cause: `REDIS_PREFIX` and `CACHE_PREFIX` defaulted from `APP_NAME`, and
the web process (`artisan serve --no-reload`, which reads `.env` only at start)
had started while `APP_NAME` was still `vaistines`. Web read and wrote
`vaistines_database_vaistines_cache*`, CLI (scrapers, commands, bumps)
`evaistinelt_database_evaistinelt_cache*`, so no CLI bump ever reached the site.
Both prefixes are now pinned in `.env`/`.env.example`
(`REDIS_PREFIX=evaistinelt_database_`, `CACHE_PREFIX=evaistinelt_cache`, the
values CLI and queues already used). The production `.env` needs the same two
lines. After changing `.env` locally, restart the app container
(`./vendor/bin/sail restart laravel.test`); in production `deploy.sh` runs
`config:cache`.

## Legal (2026-10-08)

Not legal advice; the texts should still go past a lawyer before launch.

How others do it: medizinfuchs.de (German medicine price comparison) says it is
"keine Apotheke, kein Hersteller oder Lieferant", that orders, delivery and
pharmaceutical advice are "ausschließlich" the pharmacy's, that it isn't liable
for the correctness or timeliness of outside data, and that its product info
"ersetzen nicht Packungsbeilage ... oder Beratung durch Arzt oder Apotheke".
It asks users to report wrong prices. Lithuanian comparison sites (pricer.lt,
kainos.lt) show medicines with no special notice. Lithuanian advertising rules
for non-prescription medicines shown to the public require "Prašome įdėmiai
perskaityti pakuotės lapelį ir vaistą vartoti kaip nurodyta. Netinkamai
vartojamas vaistas gali pakenkti Jūsų sveikatai". Directive 2001/83 art. 86(2)
does not count price lists without product claims as advertising, but our
keyword pages and generated copy could be read as advertising, so we show the
text anyway.

What the site shows:
- `<x-pharmacy-disclaimer>` in the footer on every page: a comparison site, not
  a pharmacy; the pharmacy is responsible for the product, price and delivery;
  prices may be out of date; no medical advice. It links to the terms.
- `<x-product-notice :category-slug>`: the medicine text for the
  `nereceptiniai-vaistai` root and a food-supplement text for
  `vitaminai-ir-maisto-papildai`. It appears on product pages (bottom of the
  hero card) and on category and keyword pages (keyword pages use their first
  `keyword_categories` slug). Store pages mix categories and get the footer only.
- Product offers line: "Pirksite pasirinktos vaistinės svetainėje, už prekę,
  kainą ir pristatymą atsako vaistinė."
- `/naudojimosi-taisykles` (new, `static/terms.blade.php`), rewritten
  `/privatumo-politika`. The operator is written as "eVaistine.lt valdytojas"
  (owner's choice; no company details). The privacy policy lists what is really
  stored: account (email, Google/Facebook login), favorites and chosen
  pharmacies, email subscriptions (consent), search log with IP (12 months,
  pruned daily by `model:prune` on `SearchResult`), and server logs (30 days, to
  be set up on the server). Only strictly necessary cookies are set, so the
  consent banner (`<x-cookie-consent>`) is off; put it back together with any
  analytics.
- Generated copy was checked for health claims (gydo, padeda nuo, malšina,
  saugus, ...) in keyword pages, category, store and store-category
  descriptions. The 44 hits were all about price comparison or real product
  names. Product descriptions are empty for now; check them the same way once
  generated.

## Launch checklist (2026-10-13)

Owner's decisions: launch an MVP with only the blocking work; product quality
(ailments, multi-EAN merge, Gintarinė EANs, name matching, €/vnt, meta title
cut) comes after. Scrapers keep running on the owner's machine (home IP, the
`dev` environment's schedule) and POST to production; **no token on
`/api/scrapers*`** (owner: "nereikia tokenų scraperiams"), same as superakcijos.

Done in code:
- `SCRAPER_API_URL` (`services.scrapers.api_url`) is passed by
  `ScraperRunner`, `FlyerScraperRunner` and `HoursScraperRunner` to node, so
  `scrapers:run`, `flyers:scrape` and `hours:scrape` post wherever `.env`
  says. `scrapers/flyers/_shared.js` used to hardcode
  `http://localhost/api/scrapers/...`; it now reads the same variable as
  `scrapers/lib/pharmacy.js` and `scrapers/hours/_shared.js`. With
  `config:cache` on, the value comes from the cached config, not `.env`.
- `deploy.sh`: first-line `exit 1` removed; new check in the remote script
  (Redis DBs not 0/1, `MEILISEARCH_INDEX_PREFIX` set) before `optimize:clear`.
- `deploy/env.production.example`: the production `.env` template.
- Logs: the `daily` and `flyer` channels keep `LOG_DAILY_DAYS` (default 30)
  days, as the privacy policy says; production uses `LOG_CHANNEL=daily`.
- Default `og:image` is `public/assets/og-default.png` (1200x630, built by
  `scripts/brand/render-og.cjs`); the SVG logo it used before isn't shown by
  Facebook or Messenger.
- Checked locally: 308 tests pass; `robots.txt` and `sitemap.xml` for host
  `evaistine.lt` list `https://evaistine.lt/...`; unknown URL gives 404.

Server (one time, on the superakcijos server):
1. DNS `evaistine.lt` + `www`, SSL certificate.
2. `/var/www/evaistine` owned by `deploy` (group `www-data`, setgid on
   `storage`, `bootstrap/cache`), nginx `sites-available/evaistine` from
   `deploy/nginx-public-domain.conf`, `nginx -t`, reload.
3. MySQL database `vaistines` with its own user.
4. `.env` from `deploy/env.production.example` (new `APP_KEY`).
5. Meilisearch key limited to `evaistine_*`.
6. Supervisor `deploy/supervisor-evaistine-*.conf`; cron
   `* * * * * cd /var/www/evaistine && php artisan schedule:run`.
7. Imagick + Ghostscript for leaflet pages (`flyers:process-pages` runs there).
8. nginx logrotate 30 days for `evaistine-*.log`.
9. Room for both apps (RAM, disk, two sets of queue workers).
10. `DEPLOY_SERVER=deploy@host ./deploy.sh`, then the first full scrape from
    the owner's machine with `SCRAPER_API_URL=https://evaistine.lt/api/scrapers`
    in its `.env`, then on the server `discounts:process`,
    `products:index-meilisearch`, `discounts:index-meilisearch`,
    `keywords:map-products`, `keywords:refresh-counts`, `cache:clear-discounts`.
11. Check superakcijos.lt still works and its Redis DBs 0/1 and indexes
    `discounts`/`products` are untouched.

Server setup done 2026-10-13 (server `deploy@84.247.186.143`, the superakcijos
box; Cloudflare SSL "Flexible", so nginx listens on :80 only):
- `deploy` needs a password for sudo. The one-time root part ran as a script
  the owner started (`sudo bash /tmp/evaistine-setup/evaistine-root-setup.sh`):
  `/var/www/evaistine` -> `deploy:www-data` 2775 (a 2025 placeholder
  `index.html` vhost `evaistine.lt` was there; its file is kept in
  `/root/evaistine-old/`), `/var/www/backups/evaistine`, MySQL database
  `vaistines` + user `evaistine@localhost` (ALL on `vaistines.*`, PROCESS for
  `mysqldump`), a Meilisearch key limited to `evaistine_*` (checked: 403 on
  superakcijos' `products`), nginx `sites-available/evaistine` from
  `deploy/nginx-public-domain.conf`, supervisor `evaistine-discounts`,
  `evaistine-flyers` (2), `evaistine-flyers-gemini`. The owner added
  `/etc/sudoers.d/evaistine-deploy` (`NOPASSWD: systemctl reload php8.4-fpm`)
  for `deploy.sh`.
- Production `.env` written from `deploy/env.production.example` with a new
  `APP_KEY`, DB password and `INDEXNOW_KEY`; `OPENAI_API_KEY` and
  `GEMINI_API_KEY` copied from superakcijos' `.env` (owner's choice, shared
  billing). Still empty: `MAIL_*`, Google/Facebook OAuth.
- Cron line `* * * * * cd /var/www/evaistine && php artisan schedule:run` added
  to `deploy`'s crontab (backup of the old one: `~/crontab-backup-2026-10-13.txt`).
- First `DEPLOY_SERVER=deploy@84.247.186.143 ./deploy.sh` (with
  `deploy_key` copied from the superakcijos repo into the repo root for the
  run, gitignored, removed afterwards): all migrations ran, site 200 on
  https://evaistine.lt. Checked after: superakcijos.lt 200, its Redis DBs 0/1
  not flushed (eVaistine in 2/3), its Meilisearch index untouched.

- First data (owner's choice, instead of a 5-hour re-scrape into prod): the
  local DB after the 2026-10-13 full scrape, `mysqldump` without the data of
  `discount_temp`, `search_results`, `scraper_runs`, `failed_jobs`, `cache*`
  (schema only; local `users` was empty anyway), imported as `evaistine`;
  `storage/app/public/` (leaflet pages, 172 MB) rsynced. Then on the server
  `products:index-meilisearch`, `discounts:index-meilisearch` (indexes
  `evaistine_products`, `evaistine_discounts`), `keywords:map-products`,
  `keywords:refresh-counts`, `deal-pool:refresh`, `cache:clear-discounts`,
  `CacheVersion::bump('flyers')`, `cache:warm --type=page-html`. Live:
  54 407 products, 72 622 offers, 7 leaflets, 2 072 locations, 50 keyword pages.
- From now on new data comes from the owner's machine: its `.env` needs
  `SCRAPER_API_URL=https://evaistine.lt/api/scrapers`, and the `dev`
  schedule (`scrapers:run --all` 03:00, `flyers:scrape --all` 05:00) must run
  there. The current offers end 2026-10-18.

Owner (no code): own OpenAI/Gemini/Meilisearch keys; Google and Facebook
OAuth apps for `https://evaistine.lt`; SMTP for `noreply@evaistine.lt`
(SPF/DKIM); lawyer review of the legal texts; recheck the VVKT list; after
launch GSC property, sitemap and `INDEXNOW_KEY`.

Noticed, not changed: `/naujienos` is in the sitemap but has no posts since
the grocery posts were deleted.

### Analytics (2026-10-13)

GA4 `G-B62M2HNEWJ` (owner's property) from `GA_MEASUREMENT_ID`
(`services.google_analytics.id`), printed by the layout only when
`APP_ENV=production`. Google Consent Mode: `analytics_storage` defaults to
denied, so GA sets no cookies and sends only cookieless pings (checked on the
live site: no `_ga*` cookies and `gcs=G100` before consent, `_ga` and
`_ga_B62M2HNEWJ` after "Leisti visus"). `<x-cookie-consent>` is back, shown only
when GA is on: "Leisti visus" grants, "Tik būtini" denies and deletes `_ga*`
cookies; the choice is the `cookie_consent` cookie (1 year), read in the
browser so cached page HTML is the same for everyone. Footer link "Slapukų
nustatymai" reopens it. The banner text no longer says that using the site
means consent. Privacy policy section 5 lists `cookie_consent` and the GA
cookies, section 4 names Google Analytics; dated 2026-10-13. No Clarity.

## Roadmap (estimate from 2026-10-06, days of work with Claude)

| # | Phase | Estimate | Status |
|---|---|---|---|
| 0 | Fork, own Sail stack, DB, ports; block prod paths | 0.5–1 | done |
| 1 | Remove grocery stores/scrapers/logic; main chains config | 1–2 | done |
| 2 | 12 root categories, mapper prompt (no icons); ailment layer | 1 + 1.5–2 | roots done, ailments next |
| 3 | E-shop scrapers (Eurovaistinė, Gintarinė, Camelia, Benu, Apotheka, N vaistinė), ~0.5–1 each | 4–6 | |
| 4 | Cross-pharmacy matching (EAN, strength/count/form normalization) | 1–2 | |
| 5 | Copy and SEO: GPT prompts, meta templates, schema, static pages, emails | 2–3 | |
| 6 | Design: palette, logo, favicon, OG images, hero | 1–2 | palette/logo done |
| 7 | Pharmacy keyword pages | 1–2 | |
| 8 | Legal: prescription rules, disclaimer, no treatment claims | 0.5 + lawyer | texts done 2026-10-08, lawyer review left |
| 9 | Full scrape, deploy, GSC, sitemap, IndexNow | 1 | |

Total about 15–22 working days; a usable MVP (categories, three main
scrapers, copy basics) about 5–7. Biggest risks: Cloudflare on a pharmacy
e-shop, weak matching without EAN, keeping two forks in sync (pull upstream
fixes with `git cherry-pick` from the `upstream` remote).

## Local test data

`storage/app/seed-test-products.php` (not in git) loads 31 real Eurovaistinė
products into three test categories, plus invented prices at Gintarinė,
Camelia and Benu for half of them so the price comparison shows. Run with:

```
./vendor/bin/sail artisan tinker --execute="require base_path('storage/app/seed-test-products.php');"
./vendor/bin/sail artisan discounts:process
./vendor/bin/sail artisan cache:clear-discounts
```
