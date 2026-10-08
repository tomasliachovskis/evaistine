# TODO

Running list for eVaistine.lt. Not a sprint board — check items off or
delete them once done, and add context inline rather than a bare title.
Project decisions are in `docs/evaistine.md`.

## Where we left off (2026-10-07, end of the third session)

**Nothing from this session is committed** (owner wasn't asked yet): the
13 e-shop scrapers + `scrapers/lib/`, flyer scraper fixes, two migrations
(`2026_10_07_120000_add_production_processing_indexes`,
`2026_10_07_130000_disable_flyer_discount_extraction`), `config/scrapers.php`,
`node-html-parser` in package.json, docs and this file. Ask before
committing. Nothing pushed or deployed ("į prodą nieko nekelk").

Local data (http://localhost:8081, on the LAN http://192.168.1.126:8081):
test products deleted; full e-shop scrape of all 13 pharmacies processed
(51 411 products, 67 548 offers, see "First full scrape" below). Logs in
`storage/app/scrapers/logs/`, EAN caches in `storage/app/scrapers/ean-*.json`.

**Leaflets (done 2026-10-08, `docs/evaistine.md` "Leaflets").** 7 pharmacies
have their October leaflet: Gintarinė, Benu, N vaistinė, Ramunėlės, Camelia,
Apotheka, Eurovaistinė. No Gemini offer extraction
(`extract_discounts_from_flyer` false for all 15). Eurovaistinė and Apotheka
go through the pharmacy's own page in stealth Chrome
(`fetchYumpuDocumentViaPage`), since Yumpu's JSON is behind AWS WAF.
Pharmacies without a current leaflet 301 from `/leidinys/{slug}` to `/{slug}`.
Rendering is slow (~1 min per page, Imagick re-reads the whole PDF per page);
**production deploy needs `storage:link`**.


Open questions put to the owner, not answered yet:
- Commit everything from this session?

How the owner works: writes in Lithuanian, wants answers in Lithuanian;
approves look by seeing it, so show screenshots or open the page before
asking. Bigger changes go through `/plan` first. Prefers the simple version
when a plan looks complex ("nebus per daug sudėtinga?").

Look decisions and how we got there (don't re-propose the rejected ones):
- Rejected: plain green (taken by Eurovaistinė/Benu/Camelia), teal ("labai
  nuobodžiai"), orange + navy/teal/plum/all-orange, N vaistinė-style full
  green or orange hero ("per ryškiai, bado akis"), white hero with soft
  blobs and light-green hero ("hujnia").
- Chosen: Benu-style palette (navy actions/links/footer, mid-blue tints,
  raspberry deal badges) with logo L1 (navy tile, green cross, navy
  "eVaistine" + green ".lt"); the logo green was then darkened to Benu's
  `#58A618`.
- **Open question**: the owner then said "grąžink mėlyną kuri buvo prieš tai"
  and interrupted before saying which blue. No blue had changed in that
  last step (only the logo green). Ask which one they mean before touching
  colors again.

Tools kept in the repo:
- `scripts/brand/make-logo.cjs` (logo SVGs, outlined Inter) and
  `scripts/brand/build-favicon.sh` (favicon from `scripts/brand/icon.svg`).
- `scripts/brand/screenshot-pages.cjs`: 390/1440 screenshots of any paths
  into `storage/app/screenshots/`, for the visual check before "done".
- `scripts/research/eurovaistine-category-json.py`: parses the product JSON
  in Eurovaistinė category pages (EAN, price, regular price, image, slug,
  name). Starting point for the Eurovaistinė e-shop scraper.
- `storage/app/seed-test-products.php` + `storage/app/test-products.json`
  (not in git): the local test data.
- `storage/app/logos/` (not in git): the scripts that pulled each pharmacy's
  header logo (`find.cjs`, `build.cjs`) and the logo sheet (`sheet.cjs`).

The phased roadmap with time estimates is in `docs/evaistine.md`
("Roadmap"); open items are under "Next" below.

## Done (2026-10-06 – 10-07)

- [x] Fork superakcijos.lt into `/Users/tomas/www/vaistines` with its own
      Sail stack, DB and ports; scrapers, deploy and Meilisearch can't reach
      superakcijos production.
- [x] Keep only the seven pharmacy chains; remove grocery scrapers, rules
      classes, generic grocery products and the energy-drink override; one
      `config('stores.main_slugs')` list for "main chains first".
- [x] Domain and brand `evaistine.lt` / `eVaistine.lt`; new logo, favicon,
      navy palette with raspberry discount badges.
- [x] Fresh-DB schema aligned with production (`discount_temp.store`,
      nullable prices and dates).
- [x] Local test data: 31 real Eurovaistinė products in three test
      categories, price comparison across four chains.
- [x] `/parduotuves` -> `/vaistines`, "parduotuvė" -> "vaistinė" in all UI,
      meta and GPT text; `PharmacyName::phrase()` for chain names.
- [x] Categories (2026-10-07): 12 roots seeded, `config/categories.php` for
      names/case forms/popular, GPT mapping prompts for pharmacies, grocery
      category ids dropped from `resolveCategoryId()`. **No category icons**
      (owner dropped them): icons and grocery category images removed, UI is
      text only.
- [x] URL structure (2026-10-07): products `/p/{slug}` without category,
      flat listings `/{slug}`, `/paieska/{q}`; slug collision rule. See
      `docs/evaistine.md`.
- [x] 15 pharmacies (2026-10-07): 8 online pharmacies from VVKT's
      remote-sale list added with real logos; grocery logos removed.
- [x] Test suite green (298 tests): the 125 earlier failures were one data
      migration that used the deleted `EnergyDrinkCategory`.

## Next

- [x] **First full scrape and processing (2026-10-07)**: 51 411 products,
      67 548 offers from 13 pharmacies, 22 610 products with EAN, 8 518
      priced at 2+ pharmacies. Fixed on the way: the production indexes
      superakcijos never had in its migrations
      (`2026_10_07_120000_add_production_processing_indexes`); without the
      `discount_temp.product_url` index, `discounts:process` locked the table
      for 10+ min and scrapers' inserts failed. **Never run
      `discounts:process` while a scraper is posting.**
- [ ] **Scraping follow-ups**: Gintarinė has no EANs yet (its EAN pages
      triggered 429s; ran with `SCRAPER_EAN_LIMIT=0`) - fetch them slower,
      e.g. a nightly `SCRAPER_EAN_LIMIT=300`. Mixed buckets mapped to one
      root by hand and need per-product classification: Benu "Specialūs
      pasiūlymai ir akcijos/Sezono svarbiausi" (657, use the hit's other
      categories), Mano vaistinė "Kosmetika ir higiena" (587, its
      subcategories live under /kosmetika-ir-higiena/) and "Kitos prekės"
      (443). Only 8 518 of 51 411 products match across pharmacies: name
      matching for products without EAN (see "Cross-pharmacy matching").
      Category sizes: every root is over ~2000 except Ortopedija (484) and
      Akių priežiūra (346), so all big ones need the keyword/ailment level.
- [x] **Small pharmacies** (owner, 2026-10-08: show them): Piliulė (17
      in-stock offers, almost everything sold out), LSMU (107, mostly its
      own products) and Ąžuolyno (162) stay visible like every other
      pharmacy, with their akcijos page on. No code change needed.
- [ ] **Ailment layer** (after the first full scrape): `product_ailments`
      table, mapping from each pharmacy's own ailment categories (Gintarinė,
      Benu, Apotheka) plus GPT for the rest. Pages go in the flat URL space
      (`/vaistai-nuo-skausmo`) and their slugs must pass
      `App\Rules\FreeTopLevelSlug`.
- [x] **Stores without data** (owner, 2026-10-08): keep them on
      `/vaistines`; a card with no offers says "Šiuo metu akcijų nėra"
      (`<x-store-card>` grid layout). Now N vaistinė and Ramunėlės.
- [ ] **Multiple EANs per product** (owner decided 2026-10-08): a product
      can carry several barcodes, and two products that share even one of
      them are the same product and get merged. Fixes the ~30 products split
      by an old/new barcode. Needs a `product_eans` table, EAN matching in
      `discounts:process` against all of a product's barcodes, and a one-off
      merge of existing duplicates (move discounts/history/favorites).
      Plan it with `/plan` before building.
- [ ] **Cross-pharmacy matching**: EAN first; name normalization for
      strength (`400 mg`), count (`N20`) and form (tabletės, sirupas).
- [ ] **Unit price for pharmacy goods**: €/l on 10 ml drops is meaningless;
      use €/vnt (per tablet/capsule) where the pack count is known.
- [x] **Copy and SEO** (2026-10-07, see `docs/copy-and-prompts.md`):
      hand-written templates and all GPT description prompts rewritten for
      pharmacies, grocery dead code removed, descriptions regenerated.
- [x] **Grocery leftovers removed** (2026-10-08, `docs/evaistine.md`
      "Removed grocery features"): coupons, welcome view, age check,
      `FoodCategorySlugs`; breadcrumb root "Pradžia". Kept: `/pradzia-beta`,
      `/pigiausios-prekes`.
- [ ] **Copy and SEO leftovers** (`docs/copy-and-prompts.md`, "Left for
      later"): research JSON files per pharmacy
      and category, domain strings hardcoded across services (centralize on
      `CanonicalUrl`).
- [x] **Keyword pages, first wave** (2026-10-07, `docs/keyword-pages.md`):
      50 pharmacy pages from autocomplete + product data, products mapped
      through a local Meilisearch `products` index into
      `keyword_page_products`, grocery import data removed.
- [ ] **Keyword pages, next**: second wave (more active substances, brands,
      `/nuo/{ailment}` after the ailment layer), real search volume once GSC
      has data.

- [ ] **Store brand colors** in `config('stores.brand_colors')`, from each
      chain's real logo.
- [x] **Legal texts** (2026-10-08, `docs/evaistine.md` "Legal"): footer
      disclaimer, medicine/supplement notices, `/naudojimosi-taisykles`, new
      privacy policy, superakcijos GA4/Clarity and the cookie banner removed.
- [ ] **Legal leftovers**: lawyer review of the texts; server log retention
      30 days (Laravel `daily` channel + nginx logrotate), as the privacy policy
      says; analytics with our own IDs + consent banner, if wanted. Footer
      social links removed 2026-10-08 (were superakcijos'); add ours later.
- [x] **Cache namespace bug** (2026-10-08): web and CLI used different cache
      prefixes; pinned `REDIS_PREFIX`/`CACHE_PREFIX` (see `docs/evaistine.md`).
      Production `.env` needs both lines.
- [ ] **Production**: server, nginx vhost, `deploy.sh` for the new server
      (`DEPLOY_SERVER=user@host`, remove the guard line), supervisor configs
      `deploy/supervisor-evaistine-*.conf`, Meilisearch index, GSC property,
      sitemap, IndexNow key. GitHub repo: `tomasliachovskis/evaistine`.
- [ ] **Own API keys**: OpenAI, Gemini and Meilisearch keys are still
      superakcijos' (same values in `.env`); create eVaistine's own and drop
      `NEXTAUTH_SECRET` from `.env`.

- [x] **Pharmacy addresses and hours** (2026-10-08, `docs/evaistine.md`
      "Pharmacy addresses and hours"): 1 157 locations for all 15 pharmacies
      via `hours:scrape --all`.
- [ ] **Addresses follow-ups**: read the five big chains from their own sites
      instead of nuolaidos.lt (a spot check found stale entries); coordinates
      for Mano vaistinė and the manual pharmacies (geocoding), so they get map
      pins; hours for Piliulė and Rx (not published).

## Ideas carried over from superakcijos

- [ ] **Image proxy for product photos**: cache/resize hero images locally
      instead of hotlinking pharmacy CDNs, for LCP and SEO.
- [ ] **Merged product-row comparison view**: one row per product with every
      pharmacy's price side by side, instead of one card per offer.
