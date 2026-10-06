# eVaistine.lt

Price comparison for Lithuanian pharmacies: non-prescription medicines,
vitamins and supplements, cosmetics, hygiene, mother-and-baby and medical
goods across Eurovaistinė, Gintarinė vaistinė, Camelia, Benu, Apotheka,
N vaistinė and Ramunėlės vaistinė.

Laravel 11 + Blade/Alpine/Livewire, Filament admin, Node/Puppeteer scrapers.
Forked from superakcijos.lt (groceries); `docs/evaistine.md` covers what
changed and why.

## Local setup

The app runs in Sail with its own project name and ports, so it can run next
to superakcijos:

| Service | Port |
|---|---|
| App | http://localhost:8081 |
| MySQL (`vaistines`) | 3307 |
| Redis | 6380 |
| Mailhog | 1026 / http://localhost:8026 |
| Vite | 5175 |

```
cp .env.example .env            # already has COMPOSE_PROJECT_NAME=vaistines and these ports
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run build
```

Test data: `docs/evaistine.md`, "Local test data".

## Pipeline

1. Scrapers (`scrapers/*.js` e-shops, `scrapers/flyers/*.js` leaflets) POST
   rows to `/api/scrapers`, which stores them in `discount_temp`.
2. `artisan discounts:process` turns them into `products` and `discounts`,
   matching products by EAN first.
3. `artisan cache:clear-discounts` refreshes the listing caches.

## Docs

- `CLAUDE.md`: working rules and the store onboarding checklist.
- `docs/evaistine.md`: project decisions.
- `docs/ui-older-readers.md`: type scale, controls, colors.
- `docs/email-notifications.md`, `docs/my-stores.md`,
  `docs/cross-source-product-merge.md`: how those features work.
- `docs/superakcijos-legacy/`: the grocery site's SEO and keyword research.
- `TODO.md`: what's next.
