# TODO

Running list for eVaistine.lt. Not a sprint board — check items off or
delete them once done, and add context inline rather than a bare title.
Project decisions are in `docs/evaistine.md`.

## Where we left off (2026-10-06, end of the first session)

State: everything is local and committed in this repo; nothing was pushed or
deployed (the owner's rule: "į prodą nieko nekelk"). Sail stack `vaistines`
is up on http://localhost:8081 with 31 test products (see "Local test data"
in `docs/evaistine.md`). Full test suite passes (289).

How the owner works: writes in Lithuanian, wants answers in Lithuanian;
approves look by seeing it, so show screenshots or open the page before
asking. Plans get approved before code (`/plan`).

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

Suggested next step: the Eurovaistinė e-shop scraper (phase 3), or the
ailment layer (rest of phase 2). The phased roadmap with time estimates is in
`docs/evaistine.md` ("Roadmap"); open items are under "Next" below.

## Done (2026-10-06)

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
- [x] Test suite green (289 tests): the 125 failures were one data migration
      that used the deleted `EnergyDrinkCategory`.

## Next

- [ ] **Ailment layer**: `product_ailments` table, mapping from each
      pharmacy's own ailment categories (Gintarinė, Benu, Apotheka) plus GPT
      for the rest, `/nuo/{ailment}` pages on the shared listing template.
- [ ] **E-shop scrapers**, full catalog with EAN, skipping prescription items:
      Eurovaistinė (product JSON is in the category page HTML), Gintarinė,
      Camelia, Benu, Apotheka, N vaistinė. Watch for Cloudflare.
- [ ] **Cross-pharmacy matching**: EAN first; name normalization for
      strength (`400 mg`), count (`N20`) and form (tabletės, sirupas).
- [ ] **Unit price for pharmacy goods**: €/l on 10 ml drops is meaningless;
      use €/vnt (per tablet/capsule) where the pack count is known.
- [ ] **Copy and SEO**: rewrite the home hero (still lists Maxima, Lidl...
      and grocery search examples), listing titles ("Visos vitaminai ir
      maisto papildai akcijos" grammar), GPT prompts in
      `DescriptionGenerationService`, `ListingPageMetaService`,
      `HomePageMetaService` and `KeywordPage*`, hand-written chain copy in
      `ListingPageMetaService::getPriorityStore*()`, and the domain strings
      hardcoded across services (centralize on `CanonicalUrl`). No treatment
      advice or health claims (YMYL).
- [ ] **Keyword pages**: pharmacy keyword list (active substances, product
      types, brands), import with `ImportManualKeywordPagesCommand`. The
      header nav (`config/header_nav.php`) already links slugs that don't
      exist yet.
- [ ] **Offer origin label**: product pages say "Eurovaistinė kainų
      leidinys" for e-shop prices until the chain is in `config/scrapers.php`.
- [ ] **Store brand colors** in `config('stores.brand_colors')`, from each
      chain's real logo.
- [ ] **Legal**: disclaimer text, check what may be shown for medicines.
- [ ] **Production**: GitHub repo, server, nginx vhost, `deploy.sh` for the
      new server (remove the guard lines), Meilisearch index, GSC property,
      sitemap, IndexNow key.

## Ideas carried over from superakcijos

- [ ] **Image proxy for product photos**: cache/resize hero images locally
      instead of hotlinking pharmacy CDNs, for LCP and SEO.
- [ ] **Merged product-row comparison view**: one row per product with every
      pharmacy's price side by side, instead of one card per offer.
