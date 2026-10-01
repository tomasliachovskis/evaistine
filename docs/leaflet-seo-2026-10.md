# Leaflet offers as SEO content (2026-10)

## Problem

Competitors (raskakcija.lt and similar) show leaflets as images only, under a URL that stays the same every week (`/norfa-akciju-leidinys.htm`), so it keeps building authority.

We extract every offer from a flyer (Gemini, `store_flyer_id` + `flyer_page` on `discounts`). Until now that text only appeared on the per-issue flyer page. That URL changes with every issue and 301s to the hub once the flyer expires. In GSC the traffic for "{store} leidinys" lands on the evergreen hub `/leidinys/{store}`, and the hub had no product text at all.

## What changed

- **Hub `/leidinys/{store}`**: a "Naujausio {Store} leidinio akcijos" section with the 24 best offers from the store's currently valid flyers, plus a data-driven intro (offer count, validity, top discounts) and a link to the full flyer. The meta description now carries the offer count, the dates and one example product with its price. There's also an `ItemList` schema with `priceValidUntil`. See `ProductController::currentFlyerOffersSummary()`.
- **Product page**: an offer that comes from a currently valid flyer shows "Leidinyje „{flyer}“, N psl." and links to `/leidinys/{store}/{flyer}#psl-N`. The flyer viewer opens on that page. See `AkcijosController` (`$flyerLinks`).
- **Flyer page**: a data-driven intro above the offer list. Each page image's `alt` lists the first 3 products printed on that page. The `ItemList` offers carry `priceValidUntil`.
- **Price comparison on leaflet cards** (`<x-deal-card :compare-stores="true">`, leaflet pages only): "Pigiausia iš N parduotuvių" or "{Store} pigiau – X €".
- **IndexNow**: `seo:indexnow` also submits the flyer page and hub of every currently valid flyer whose linked discounts changed since `--since`.

Deliberately not done:

- **Separate indexable URLs per flyer page**: they would be thin, near-duplicate and replaced every issue. `#psl-N` anchors cover the deep link.
- **Hub `<title>`**: unchanged, so the effect of the content change can be measured on its own.

## GSC baseline (2026-09-03 – 2026-09-30, before deploy)

Leaflet queries = queries matching `{store}.*(leidin|leidyn|katalog)`. Position is impression-weighted.

| Store | Queries | Clicks | Impr. | Avg pos | Hub clicks | Hub impr. | Hub pos | Top queries (impr., pos) |
|---|---|---|---|---|---|---|---|---|
| norfa | 17 | 1 | 246 | 26.9 | 1 | 400 | 25.2 | norfa leidinys (55, 23); norfa naujas leidinys (32, 32); norfa leidiniai (28, 31) |
| maxima | 28 | 3 | 302 | 21.0 | 7 | 1037 | 24.9 | maxima akcijos leidinys naujas (50, 8); maxima leidinys (48, 24); maxima akcijos naujas leidinys (40, 20) |
| rimi | 16 | 20 | 384 | 24.7 | 21 | 601 | 26.0 | rimi akcijos leidinys (73, 19); rimi leidinys (58, 29); rimi leidinys naujas (52, 28) |
| iki | 30 | 8 | 291 | 38.3 | 2 | 488 | 44.7 | iki leidinys (30, 32); iki akcijos leidiniai (26, 43); iki savaitėlė leidinys naujausias (26, 48) |
| lidl | 45 | 2 | 1737 | 12.2 | 4 | 2173 | 13.6 | lidl katalogas (452, 9); lidl naujas leidinys (280, 10); lidl leidinys (225, 15) |

## Follow-up

Re-pull the same table for 2026-10-02 – 2026-10-29 around 2026-10-29, together with the `gsc-ctr-quick-wins-2026-10.md` follow-up. Also check how many product pages get clicks through the flyer link (GA4 or the server logs).
