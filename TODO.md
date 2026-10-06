# TODO

Running list for eVaistine.lt. Not a sprint board — check items off or
delete them once done, and add context inline rather than a bare title.
Project decisions are in `docs/evaistine.md`.

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
- [x] Test suite green (289 tests): the 125 failures were one data migration
      that used the deleted `EnergyDrinkCategory`.

## Next

- [ ] **Categories**: seed the 12 root categories from `docs/evaistine.md`
      with SVG icons, adapt the `categories:map-mappers` GPT prompt, and drop
      the grocery category ids hardcoded in `ProcessDiscounts::resolveCategoryId()`
      (pets `619`, the alcoholic/non-alcoholic split).
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
