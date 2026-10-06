# nuolaidos.lt feature-gap analysis

Live audit of nuolaidos.lt (2026-09-11), focused on features they have that we don't and that could realistically bring additional organic traffic. Not a full redesign brief — only gaps with a plausible traffic upside, with an honest effort estimate for each.

## Site structure observed

Header nav: Pradžia, Naujausi, Top, Produktai, Parduotuvės, Kategorijos, Darbo laikai, Naujienos, Kuro kainos, Gauk App — plus a Premium button, login, and cart/favorites icons (counts "2" and "4", implying full accounts with multiple saved-list types beyond a single favorites list). Homepage sections, top to bottom: newest leaflets carousel → "Geriausias šios dienos krepšelis" (today's best basket, a curated daily highlight reel, distinct from a full price index) → category tiles → most-popular leaflets carousel → weekend-offers carousel → store directory (A–Z, count per store) → product taxonomy (A–Z, hundreds of entries, count per product) → news → store locations/hours → fuel prices. A running gambling ad banner (7bet.casino) sits above the header — a real monetization signal, not something to copy (clashes with brand/trust).

## Gaps ranked by traffic upside vs. effort

### 1. Fuel price comparison (`/kuro-kainos`) — biggest single gap
A complete separate vertical: 767 stations nationwide, sourced from LEA/ENA (the actual Lithuanian energy regulator feed — "Oficialūs duomenys iš LEA / ENA"), filterable by network/municipality/price/distance, with a "cheapest near me" and a map. This is a high-volume, evergreen search category ("degalų kainos", "pigiausias kuras {miestas}") we don't touch at all, and it doesn't compete with our core grocery-discount identity — it's additive.
- **Effort: large.** Needs a real data source (LEA publishes this — worth confirming their feed/API terms before building), a new `FuelStation` model + ingestion job, and a new page family (`/kuro-kainos`, `/kuro-kainos/{savivaldybe}`, per-station). Comparable in scope to the price-index feature already built for `/kainu-indeksas`.
- Recommend treating as its own project, not a quick add-on.

### 2. Massive auto-generated product taxonomy
Their `/produktai/{slug}` list runs into the hundreds — down to entries as specific as "Gouda sūris 45% riebumo" or "Kiaulienos karka", each with its own page, many with counts as low as 1. This reads as auto-generated from raw scraped product names, not hand-curated the way our `KeywordPage` system is (~275 pages, curated after real search-volume checks per `keyword-ideas.md`).
- **Effort: medium.** We already have the underlying data (`products.name`) and the matching/routing infrastructure (`KeywordPage`, `resolveCategoryId()`-style matching). The work is generating and filtering candidate product-name slugs at scale (dedupe near-duplicates, drop 1-result noise, decide a minimum-count threshold) rather than building new plumbing.
- **Caveat:** this is the opposite of our current approach, which deliberately checks real search volume before creating a `KeywordPage` (see `feedback_products_means_all_products` / keyword research this same repo did). A hybrid is probably right: keep hand-curated pages for anything with real volume, but auto-generate thin long-tail pages only if `noindex` + internal-linking value is enough to justify them — otherwise this is exactly the kind of low-value-page-bloat the address-page reversal earlier this session was trying to avoid. Worth a deliberate decision, not a default yes.

### 3. "Mažiausia stebėta kaina" (lowest-observed-price badge)
Shown directly on product cards: "Mažiausia stebėta per 30 d." or "Mažiausia stebėta: 0,99 €". A real trust/urgency signal — tells the shopper this isn't just a discount, it's an actual historic low, without needing a full price-history chart.
- **Effort: small.** We already snapshot pricing data for `/kainu-indeksas` (`PriceIndexSnapshot`/`PriceIndexEntry`). This needs: (a) a per-product rolling min-price lookup (30-day window) computed from `Discount`/`Product` history, (b) a small badge component (fits the existing `discount-badge`-style component pattern) shown when the current price is at or near that historic low. Good candidate for a quick, high-signal win.

### 4. Pervasive "Sekti" (follow) button
Appears next to every leaflet, store, and product card sitewide — not just a price-watch on a single product (our current `product_favorites` + `NotifyPriceWatchers` mechanic), but a general follow primitive across three different entity types.
- **Effort: medium.** Our favoriting infrastructure already exists for products (`<x-favorite-button>`, `product_favorites`). Extending "follow" to stores and leaflets means: new `store_favorites`/`flyer_favorites` tables (or a single polymorphic `favorites` table), UI buttons reusing the existing component pattern, and deciding what a "follow" on a store/leaflet actually triggers (a new-leaflet-published digest email, presumably — new notification command alongside `NotifyPriceWatchers`).
- Worth scoping only after deciding what the notification payload is; the button alone without a real follow-up email is cosmetic.

### 5. PWA "app" (`/idiegti-programa`, "Gauk App")
Not a native app — a plain "Add to Home Screen" PWA install prompt, with a benefits list (unified leaflets, hours, a "Top" curated section, a "most-viewed leaflets" social-proof feature, category filtering, favorite stores/leaflets). Cheaper to replicate than it looks.
- **Effort: small–medium.** A web app manifest + a minimal service worker (offline shell + icon set) gets us the install prompt and home-screen icon. Doesn't require any of the "Top"/social-proof features to exist first — the PWA wrapper is separable from what's inside it. Mostly a branding/retention play (repeat visits), not itself an SEO traffic driver — sequence it after higher-ROI items.

### 6. "Geriausias šios dienos krepšelis" (today's best basket)
A homepage widget of individually great-value items pulled from across all stores (e.g. "Kukurūzinis viščiukas -24% 3,79€", flagged bananas at "Mažiausia stebėta per 30 d."). Distinct from `/kainu-indeksas` (a full fixed-basket index) — this is a curated daily highlight reel, effectively depends on gap #3's price-history data to flag "lowest observed."
- **Effort: small**, once #3 exists. A homepage query selecting top-N discounts by (discount % OR "at historic low" flag) across stores, reusing existing `Discount`/`Product` queries — no new data model needed. Natural follow-on to #3, not a separate project.

### 7. Premium tier
A "Premium" button in header/footer implies a paid subscription ("save more"). Clicking it during this session didn't surface visible modal/page content in the automated check — likely gated behind login, or a client-side modal not captured by page-text extraction. Contents genuinely unverified.
- **Effort: unknown — needs real verification first**, ideally by creating a throwaway account and going through the actual upsell flow rather than guessing. Don't scope work off this section until that's done.
- Note: this is a monetization feature, not a traffic-acquisition one — lower priority for a "get more traffic" goal specifically, even once understood.

### Not a gap
- **Naujienos** — we already have an equivalent (`NewsArticleService`, `/naujienos`), no action needed here.
- **Darbo laikai / store hours nav item** — functionally the same territory as our `/parduotuves`, already covered by the city-page rebuild done earlier this session.

## Suggested task list, roughly in priority order

1. Verify what the Premium button actually offers (throwaway account, real click-through) — cheap, resolves an unknown before anything else gets prioritized around it.
2. Ship the "Mažiausia stebėta kaina" badge (#3) — small effort, reuses existing price-index snapshot data, real trust signal on every product card.
3. Ship "Geriausias šios dienos krepšelis" homepage widget (#6) — small effort once #3 exists, adds homepage engagement/freshness signal.
4. Decide, deliberately, whether to pursue the auto-generated long-tail product taxonomy (#2) or keep the curated-only approach — this is a strategy call (thin-page risk vs. long-tail traffic), not just an effort estimate; flag for explicit sign-off before building.
5. Scope "Sekti" as a general follow primitive across stores/leaflets (#4), contingent on defining the notification that follows from it.
6. Ship the PWA install prompt (#5) — retention/branding, sequence after the above.
7. Treat fuel-price comparison (#1) as its own separate project if pursued — largest effort, but also the single biggest genuinely new traffic vertical found in this audit; needs its own scoping pass (data source terms, page architecture) before estimating further.
