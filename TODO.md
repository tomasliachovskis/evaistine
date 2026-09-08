# TODO

Running list of planned work and ideas. Not a sprint board — just a place to
write things down before they're lost. Check items off or delete them once
done; add context/rationale inline rather than just a bare title.

## SEO / content

- [x] **Scraper unit pricing** (done 2026-09-08): `unit_price`/`unit_price_basis`/
      `unit_price_estimated` columns added to `discount_temp`/`discounts`.
      Real store-published values now captured for Rimi, Lidl, Iki, Norfa,
      Gulbelė, Vynoteka, Barbora/Maxima, plus flyer extraction (Rimi, Lidl,
      Iki, Norfa, Gulbelė, Aibė, Šilas, Grustė, Promo Cash&Carry — all
      verified against real pages). Thomas Philipps has no real source
      anywhere (checked live site + flyer) — pack-size-derived fallback
      (`ProcessDiscounts::resolveUnitPrice()`) covers it and any other row
      missing a real value. Not yet checked: Čia, Express Market, Koops,
      Kubas (flyer-only stores, never tested). `PriceIndexService` now
      wired (`resolveUnitPrice()`) to prefer these columns, falling back
      to its own name-regex `parseUnitPrice()` only when a row has
      neither.
- [ ] **Image proxy for product photos**: product hero images hotlink the
      scraped store's own CDN (`DiscountResponseFormatter::resolveProductImageUrl()`)
      — no resize/WebP/local caching. Found via Clarity: LCP 3.5–7.8s on product
      pages. Plan: queue job to download+convert to WebP via `intervention/image`
      (already used for flyer PDFs), store under `storage/app/public/products/`,
      reuse the existing `image_from_flyer` symlink convention. Partial mitigation
      already shipped (`fetchpriority="high"`, `loading="eager"`, `aspect-square`
      on the hero `<img>`) — this would fix the actual root cause.
- [ ] **Merged product-row comparison view**: taupulis.lt shows one row per
      product with all stores' current prices side by side (e.g. "Persikai
      PARAGUAYO: €1.99 / €3.49 / €3.49"), vs. our current one-card-per-store+product
      layout on `/akcijos/{category}`. Worth prototyping for category pages —
      genuinely better UX for "which store has this cheapest right now", and it's
      the one thing they do better than us (they otherwise only cover 3 stores
      vs our 47, and are a pure client-rendered SPA with zero crawlable links).

## Price index (`/kainu-indeksas`)

- [ ] Schedule `price-index:snapshot` in `Kernel.php` (weekly, e.g. Monday
      morning) — currently manual-only.
- [ ] Decide a review/publish gate before this goes live in production nav —
      currently built and tested locally only, not deployed, not linked from
      anywhere on the site.
- [x] **Expand candidate basket** (done 2026-09-08): `CANDIDATE_ITEM_SLUGS`
      grown from 16 to 34 items, modeled on pricer.lt's own monthly
      "pigiausias krepšelis" — see conversation for the cross-check against
      `generic_products`. Also added real per-item payable price (not just
      the normalized €/kg/l/10vnt figure) to the page's item table, so a
      reader can sanity-check e.g. a suspiciously cheap normalized price
      against the real product/pack.
- [ ] **Maxima/Lidl/Rimi flyer backlog blocks real basket coverage**: checked
      2026-09-08 — these 3 stores have 211/213/150 flyer pages respectively
      already downloaded and page-image-converted (`StoreFlyer`/
      `StoreFlyerPage`, `processing_status=ready`), but only a handful were
      ever run through `PdfFlyerProcessingService::processPdf()`'s actual
      Gemini discount extraction (Lidl 9, Rimi 16 — from manual testing
      this session; Maxima 0). `ProcessPdfFlyerJob` (runs every minute) only
      picks up *newly arriving* PDFs from `flyers-incoming/` — it does not
      retroactively process this already-downloaded backlog, so it won't
      fix itself. Confirmed live: of Maxima's 2918 active discounts matched
      to a generic_product, only ~20 are food — the rest are non-food
      (notebooks, shampoo, deodorant, cat food, diapers, sunscreen — a back-
      to-school/hygiene sale wave), which is why Maxima/Lidl/Rimi basket
      coverage is currently only 2/34, 3/34, 4/34 vs. Iki 23/34, Norfa
      19/34. Running the 574-page backlog through real extraction (real
      Gemini API cost/time) should substantially fill this gap — deferred,
      not started.

## News articles (`/naujienos`)

- [ ] Build Type A: a weekly leaflet/deals roundup sourced from our own DB
      data (no external search needed) — the other half of the original
      pricer.lt-inspired plan; Type B (external Bing-News-grounded articles)
      is built, Type A was deprioritized this session.
- [ ] Decide a review/publish workflow for `NewsArticleService` drafts —
      currently everything lands as `status=draft`, nothing auto-publishes.
- [ ] Once there's a steady flow of fresh drafts, tighten `news:generate`'s
      `--max-age-days` back down from 7 to 3 (widened for initial seeding).
- [ ] Schedule `news:generate` in `Kernel.php` — currently manual-only.
