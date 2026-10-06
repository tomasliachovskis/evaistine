# "Mano parduotuvės" (2026-10-02)

A visitor picks the stores they shop at, and the offer listings that mix several stores show only those stores.

## Where the choice lives

- **Browser:** `localStorage['evaistine_parduotuves_v1']`, an array of store slugs. No account needed.
- **Account:** `users.preferred_store_slugs` (JSON), saved by `POST /mano-parduotuves` (`MyStoresController`). For a signed-in user the account copy wins on page load and is written back to the browser. If the account is empty but the browser has a choice, the browser's choice is pushed to the account.
- **Alpine store:** `$store.myStores` in `layouts/app.blade.php`. Other code uses `set()`, `toggle()`, `has()`, `active()`, `filterValue()` and `setShowAll()`.
- **"Rodyti visas":** sets `sessionStorage['evaistine_visos_parduotuves']`. Every store is shown until the browser tab is closed.

## Why it is applied in the browser

The production server caches each guest page by URL only (`App\Support\PageHtmlCache`) and never reads cookies. So the server can't tailor a clean URL to one visitor. The listing loads with every store, then the browser asks for the chosen stores:

- **Listings (`livewire/discount-filters.blade.php`):** `x-init` calls `$wire.applyStores()`. This sets the existing `?store=` filter, which already filters in SQL with correct totals and pagination. A URL with a query string skips the page cache.
- **Search (`akcijos/search.blade.php`):** redirects to `?store=a,b`. That page isn't Livewire.

Not applied when:
- the URL already has `?store=`;
- "Rodyti visas" was chosen;
- the page is a single store's page (`multiStore` is false for the `store` and `store_category` header types).

## Entry points

- Side menu: "Mano parduotuvės".
- On listings: the "Parduotuvės" button in the navigation bar (see below).
- Store pages (`/akcijos/{store}`, `/leidinys/{store}`): the "Mano parduotuvė" button (`<x-store-subscribe-button :slug>`). It used to be a "Sekti akcijas" link with nothing behind it.
## One navigation bar on every listing

`livewire/discount-filters.blade.php`, since 2026-10-02. The bar has three labelled buttons: "Parduotuvės", "Kategorija" and "Rikiuoti". Each says in words what is shown, e.g. "Mano: Maxima, Lidl" or "Duonos gaminiai", and opens its list.
- **Layout:** in the page above the list, not pinned (2026-10-02, it covered too much of the list). One row on desktop, stacked full-width buttons on phones.
- **Folding on phones:** a "Filtrai" header folds the stacked buttons (~346px) to one summary line (~84px), e.g. "Mano: Norfa · Mėsa ir žuvis · Populiariausi". Remembered in `localStorage['evaistine_filtrai_suskleisti']`. Desktop is always open (`<x-nav-bar>`).
- **It replaced:**
  - the green "Rodomos tik jūsų parduotuvės" bar;
  - the "Perkate tik keliose parduotuvėse?" line;
  - the separate pills;
  - the phone-only "Filtrai" sheet.
- **Where:** category, keyword and store pages, and the `/akcijos` hub.
- **Store list on multi-store pages (category, keyword, hub):** the same logo tiles as the "Mano parduotuvės" picker (`<x-store-pick-tile>`). Taps only tick (Alpine `picked`, no request); "Rodyti pažymėtas (N)" loads the list once (owner: reloading after every tap was slow). It has:
  - "Visos parduotuvės": all stores for this visit;
  - "Rodyti pažymėtas (N)" / "Rodyti visas parduotuves";
  - "Išsaugoti kaip mano parduotuves";
  - "Rodyti tik mano: …".
- **SEO:** tiles stay `<a href>` to the store+category page, so crawlers still follow them; the tap is intercepted.
- **Store and store+category pages:** the same tiles with the current store marked. A tap opens that store's page.
- **Search results (`akcijos/search.blade.php`):** the same bar and tiles, ticked locally; "Rodyti" loads `?store=` once (it isn't Livewire).
- **Loader:** while the list updates, a "Ieškome pasiūlymų…" card covers the list and the "Parduotuvės" button shows a spinner. It's set around each `$wire` call (`run()`), not with `wire:loading` (see `PageHtmlCache`). After "Rodyti" or a sort the bar scrolls into view, so the loader is visible on phones. Taps that load a new page (category links, search) show it too; `pageshow` clears it after "back".
- **`/leidiniai`:** the same bar ("Parduotuvė" + "Rikiuoti", not pinned) and a store tile sheet (`<x-leaflet-store-sheet>`), each tile with its leaflet count and opening that store's leaflets. `/leidinys/{store}` is unchanged on purpose (owner).
- **Shared markup:** `<x-nav-bar>` (the light green box) and `<x-nav-bar-button>` (label + white button, or a link with `href`) are used by listings, search and `/leidiniai`. Change the bar there, not per page.
- **Category links:** they keep the stores shown (`?store=`), so switching category doesn't drop them.

## Known limits

- **Header numbers:** the counts at the top of a listing (offers, stores, "kaina nuo") still describe every store. The green bar says the list is filtered.
- **Keyword pages:** they fetch up to 1000 Meilisearch hits. When a page has more than that, the store filter is sent to Meilisearch too (`KeywordPageService::buildListingResponse`), so matches from the chosen stores aren't cut off.
- **Not filtered yet:** the homepage teasers, `/pigiausios-prekes`, `/leidiniai` and the product page.

## Tests

`tests/Feature/MyStoresTest.php`: saving (only real slugs, empty clears), guests rejected, `applyStores` on a multi-store listing and on a single-store page, and the account copy handed to the page.
