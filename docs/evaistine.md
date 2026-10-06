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

## Categories (planned)

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

## Local test data

`storage/app/seed-test-products.php` (not in git) loads 31 real Eurovaistinė
products into three test categories, plus invented prices at Gintarinė,
Camelia and Benu for half of them so the price comparison shows. Run with:

```
./vendor/bin/sail artisan tinker --execute="require base_path('storage/app/seed-test-products.php');"
./vendor/bin/sail artisan discounts:process
./vendor/bin/sail artisan cache:clear-discounts
```
