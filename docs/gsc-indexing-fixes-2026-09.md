# Search Console indexing fixes (2026-09-30)

Page indexing report (last update 2026-09-21): 55.4k indexed, 42.4k not indexed.

## What the "not indexed" reasons are

| Reason | Pages | Status |
|---|---|---|
| Page with redirect | 35 902 | Expected — old `/akcijos/{old-category}/{product}`, `gyvunu-prekesx/...`, `?page=1` URLs that 301 to the right place. |
| Blocked by robots.txt | 1 459 | Intended — `?store=` filters, `/akcijos/paieska`. |
| Excluded by noindex | 1 380 | Intended — `?page=N` pagination. |
| Alternative page with proper canonical / Duplicate without user-selected canonical | 298 + 84 | Old `?page=1` URLs, already 301'd by `RedirectFirstPageToCleanUrl`. |
| Indexed, though blocked by robots.txt | 1 449 (growing) | **Open.** `/akcijos/paieska/*` and `?store=` are noindex but robots-blocked, so Google never sees the noindex. Unblocking them (reverting `01ac734`) fixes it at the cost of more crawling — not done yet. |
| Not found (404) | 599 | Fixed, see below. Removed keyword pages (`/akcijos/saldytai-ris`) stay 404 on purpose. |
| Server error (5xx) | 3 | Fixed, see below. |
| Duplicate, Google chose different canonical | 345 | `ž`-slug URLs are already 301'd. `/parduotuves/{store}/{city}` → Google picked `/parduotuves/{store}`; addressed below. |
| Crawled / Discovered – currently not indexed | 1 813 + 479 | Product pages; Google's quality call. Not addressed. |

## Fixes

- **Expired flyers** (`/leidinys/{store}/{old-flyer}`) 301 to `/leidinys/{store}` instead of 404, if the store exists (`LeafletController::show`).
- **Removed products under a retired category slug** 301 to that category's successor (`AkcijosController::LEGACY_CATEGORY_SLUGS`, currently only `alkoholiniai-ir-nealkoholiniai-gerimai` → `nealkoholiniai-gerimai`). Add an entry there if another category is ever renamed/split.
- **`/auth/{provider}/callback` 500**: a request without `code` (bot, or the user cancelled consent with `?error=access_denied`), a stale state or a rejected code now go back to `/?login=1`. `/auth/` is disallowed in robots.txt. Tests: `tests/Feature/OAuthCallbackTest.php` (mocked Socialite, incl. the successful login path).
- **Store city pages** (`/parduotuves/{store}/{city}`), folded by Google into the chain page as duplicates because (a) each address printed 7 near-identical weekday rows, (b) the chain page's HTML already carried every city's addresses for its map/finder:
  - one-line hours per location (`App\Support\OpeningHours::summary`, handles every scraped spelling: `8-22`, `08:00–22:00`, `08:00 - 22:00`, `Nedirba`, blanks);
  - a "darbo laikas {city}" block that names the exceptions — which store opens earliest, closes earlier than the rest, is open 24h, is closed on Sunday (`OpeningHours::cityFacts`). Facts every location shares are left out; with no differences there's no block;
  - deals/leaflets links shown only when the store currently has them (same rule as the store toolbar);
  - the chain page's map and nearest-store finder load `/api/store-locations/{slug}` client-side (now with `city_slug`) instead of inlining every address;
  - city-page map is fixed-height + sticky on desktop (it used to stretch to the full address column and centre off-screen).

## After deploy

- Restart PHP-FPM (opcache doesn't revalidate).
- Try a real Google login once.
- In Search Console → Pages, "Validate fix" on Server error (5xx); 404 validation is already running. Re-inspect a city page such as `/parduotuves/norfa/vilnius` and request indexing.
