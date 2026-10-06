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

Seven pharmacy chains (`config/stores.php`). The main chains, in display
order, are `config('stores.main_slugs')`, read through
`StoreListPriority::mainSlugs()` everywhere a "main stores first" order is
needed:

1. Eurovaistinė
2. Gintarinė vaistinė
3. Camelia
4. Benu vaistinė
5. Apotheka

Plus N vaistinė and Ramunėlės vaistinė. Each has a leaflet scraper in
`scrapers/flyers/`. E-shop scrapers don't exist yet (`config/scrapers.php` is
empty).

Eurovaistinė's category pages carry the full product JSON in the HTML (name,
EAN in `sku`, `price`/`regularPrice` in cents, `ev_large` image, `slug`).
That's the starting point for its e-shop scraper.

## Wording: "vaistinė", not "parduotuvė"

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
- Logo (`public/assets/logo.svg`, `logo-white.svg`): navy rounded tile with a
  green pharmacy cross (`#58A618`), wordmark "eVaistine" navy and ".lt" green,
  outlined from Inter 600. Green appears only in the logo and favicon.
- The site keeps superakcijos' older-reader rules (`docs/ui-older-readers.md`).

## Fresh-DB schema

The superakcijos production schema had drifted from its migrations (columns
changed by hand). `2026_10_06_130000_align_schema_with_production` makes a
fresh DB match it column for column: `discount_temp` keeps raw strings and a
store *name* (`store`, not `store_id`), and prices, dates and category ids are
nullable. Without it every real scraper row was rejected.

## Safety: nothing reaches superakcijos.lt

- Scrapers POST to `http://localhost/api/scrapers`.
- `deploy.sh`, `deploy-photos.sh` and `scripts/sync-product-photos-to-frontend.sh`
  exit immediately until an eVaistine server exists.
- Meilisearch indexing runs only in production, and the SSH tunnel to the
  superakcijos server was removed.
- Local Sail uses its own project name (`COMPOSE_PROJECT_NAME=vaistines`),
  ports (app 8081, MySQL 3307, Redis 6380, Mailhog 1026/8026, Vite 5175) and
  database (`vaistines`). A copied `.env` with `COMPOSE_PROJECT_NAME=nuolaidos`
  once recreated the superakcijos containers on this checkout, so check that
  line first in any new copy.

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
| 8 | Legal: prescription rules, disclaimer, no treatment claims | 0.5 + lawyer | |
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
