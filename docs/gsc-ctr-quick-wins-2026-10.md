# Search Console CTR quick wins (2026-10-01)

## Data

Pulled from the Search Console API (`sc-domain:superakcijos.lt`, 28 days 2026-09-01–09-28 vs the 28 days before).

- Clicks/week went from ~1 500 (August) to ~3 100 (late September). 28-day total: 9 554 clicks / 336k impressions (before: 6 088 / 199k).

| Page type | Pages | Clicks | Impressions | CTR | Pos. |
|---|---|---|---|---|---|
| Product `/akcijos/{cat}/{product}` | 24 186 | 7 141 | 179k | 4.0% | 6.6 |
| Store city `/parduotuves/{store}/{city}` | 436 | 919 | 106k | 0.87% | 7.5 |
| Keyword/category `/akcijos/{x}` | 326 | 1 274 (was 409) | 30k | 4.2% | 10.2 (was 16) |
| Leaflet hub `/leidinys/{store}` | 23 | 85 | 7.8k | 1.1% | 20.6 |
| Store chain `/parduotuves/{store}` | 5 | 9 | 3k | 0.3% | 9.4 |

- Ignored: "daug akciju" (a competitor's brand), junk queries ("pagrindinis", "kokia kaina"). The `/akcijos/{store}` pages sit at position 25–45 for "maxima akcijos" and similar queries. That needs authority, not meta changes.

## Changes

1. **Store city and chain page meta** (`StoreController`): the query words ("{store} {city} darbo laikas") come first in the title. The description shows weekly hours (`OpeningHours::summary()`) instead of "šiandien …", fixes the "turi 1 parduotuvė" case, and has number agreement via `LithuanianPlural::storeWord()`. The details are in `docs/seo-current-formats.md`.
2. **Weekday hours bug** (`WorkingHoursParser`): nuolaidos.lt writes some ranges with an en dash (`I–V 8:00–18:00`). The regex only accepted `-`, so Senukai (54/58 locations) and Grustė (8/24) only had Saturday/Sunday hours stored. Senukai alone gets ~20k impressions/month on "senukai darbo laikas" / "senukai {city}". Čia (21/100) is different: its source only lists Sunday.
3. **Leaflet hub description**: an all-caps flyer title is sentence-cased.
4. **New keyword pages**: `database/data/gsc-keyword-pages-2026-10.json` (kavos pupelės, Hellmann's majonezas, Brite). These had demand in GSC and current offers locally.
   - Most other "missing" candidates already exist under a dative slug (`sauskelnems`, `varskei`, `krevetems`, `redbull`, `cola`, `dolce-gusto`). Google ranks old product pages above them instead.
   - Fairy, Borjomi, Akvilė, stiklainiai and distiliuotas vanduo had 0 current offers. The importer would leave them unpublished, and nothing publishes them later.
   - Not done: "katalogas" in the leaflet title. It was tried and reverted on 2026-09-22.

## After deploy

- Restart PHP-FPM.
- Re-scrape the hours so the parser fix reaches the data: `node scrapers/hours/scrape.js senukai` and `node scrapers/hours/scrape.js gruste` (they post to prod). Otherwise they wait for Monday's `hours:scrape --all`.
- Import on prod: `php artisan keywords:import-manual database/data/gsc-keyword-pages-2026-10.json --apply`.
- Search Console → URL Inspection → request indexing for a few city pages (e.g. `/parduotuves/senukai/klaipeda`, `/parduotuves/ermitazas/kaunas`).

## Re-checking

Service account key + `GOOGLE_APPLICATION_CREDENTIALS`, Python from the claude-seo venv (`~/Library/Application Support/claude-seo/.venv/bin/python`). Query `searchanalytics().query` with dimensions `page` and `query` for 28 days and compare:

- the `/parduotuves/{store}/{city}` CTR (baseline 0.87%);
- Senukai city pages (baseline ~0.2–0.5%).

Check again around 2026-10-29.
