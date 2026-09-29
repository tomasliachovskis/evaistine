# Merging flyer vs e-shop duplicates (`products:merge-duplicates --cross-source`)

How we find and merge products that exist twice for the same store: once created from the store's flyer (Gemini extraction) and once from its e-shop scraper. First full run on production: 2026-09-29, 159 pairs merged.

---

## The problem

The two sources word the same product differently, so `processTempDiscount()` (EAN → `product_mapping` → slug) never matches them, and the name-only `products:merge-duplicates` (`database/sql/find_product_duplicates.sql`) misses them too:

| Flyer | Web |
|---|---|
| `Šaldytos bulvių lazdelės NATALI, 1 kg` + info `2 rūšių` | `Šald.bulvių lazdelės NATALI (2 rūš.), 1 kg` |
| `Kiaulienos šonkauliai RIMI, 1 kg` + info `atšaldyti` | `Kiaulienos šonkauliai RIMI atšaldyti, 1 kg` |
| `Švieži viščiukų broilerių filė gabaliukai be odos RIMI, 400 g` | `Višč. br. filė gabaliukai RIMI, A kl., 400 g` |

The site then shows two cards for one product, one of them with an image cropped out of the leaflet.

## How it works

Code: `App\Services\CrossSourceDuplicateFinder` (finding) and `App\Services\ProductDuplicateMergeService::mergePair()` (merging).

### 1. Candidates: match on the offer, not the name

The strong signal is the offer itself. A pair is a candidate when:

- both discounts are for the **same store**,
- their periods **overlap**,
- `discounted_price` is **exactly equal**, and `original_price` too when both have one,
- one is a flyer offer and the other a web offer.

Flyer vs web: flyer discounts have `product_url IS NULL`, web ones have a URL. The Norfa scraper collects no product link (its cards have no `<a>`), so as a fallback the web side can also be a product with `image_from_flyer = 0` paired with one that has `image_from_flyer = 1`.

### 2. Confirmation: the name only has to roughly agree

- The flyer's `info` counts as part of its name, because text the flyer keeps in `info` often sits inline in the web name.
- Names are split into words. Abbreviations match their full word (`Šald.` → `šaldytos`, `br.` → `broilerių`).
- At least **60%** of the web name's words must be found on the flyer side. Web `info` isn't counted, because some stores copy promo text there. The threshold was picked on real data: 0.8 missed real pairs such as `Raudonieji` vs `Didieji raudonieji greipfrutai`, and 0.5 started merging generic flyer offers into one scent (`COCCOLINO` vs `COCCOLINO Sensitive Cotton Cloud`).
- The web name needs at least 2 words. `Grietinė, 360 g` can't confirm anything.

Hard rejects, each from a real false positive:

| Rule | Real example it stops |
|---|---|
| Pack size differs (`1 kg` = `1000 g`) | `NATALI, 1 kg` vs `NATALI, 750 g` |
| An all-caps brand word on either side missing on the other | `ESTRELLA` vs `LAYS`, `WOOLITE` vs `WOOLITE DARK` |
| The flyer offer covers several variants (`(3 rūšys)`, `X ar Y`, a comma list of brands) and the web *name* doesn't | `WOOLITE (3 rūšys)` vs `WOOLITE Color`; `COCA-COLA, FANTA, SPRITE` vs `FANTA APPLE-CHERRY` |
| Different flavor after "su" | `AMFORA su mangais` vs `su avietėmis` |
| `ar`/`arba` aren't counted as matching words | `Moterų arba vyrų terminės kojinės` vs `… maudymosi šlepetės` |
| **1:1 only**: a product matching more than one on the other side is skipped | `Skalbimo kapsulės ARIEL (2 rūšys)` vs `ARIEL COLOR` and `ARIEL EXTRA CLEAN` |

### 3. Merge

- **Which product stays:** the **older** one (`pickBaseProduct()`, oldest `created_at`). Its discounts, history and favorites move over, and the other product is deleted.
- **Photo:** if the survivor has a flyer-cropped image (`image_from_flyer`), it takes the web product's photo, and `image_cache_failed_at` is cleared.
- **Final name,** in this order:
  1. The name without a promo variant count (`(2 rūšių)`, `(2 rūš.)`, `(įv. rūšių)`) wins: `PIEMENĖLIO raugintos pasukos (2 rūš.), 0.5 kg` → `PIEMENĖLIO raugintos pasukos, 0.5 kg`. If both names have one, it is stripped and moved to the offer's `info`.
  2. Otherwise the name with **fewer abbreviations** wins. `a. r.`, `Šald.`, `art.` and `/pak.` count. A short form right after a number is part of a value and doesn't count (`2,5% rieb.`, `3 sl.`). Example: `ŠEIMOS vytinta dešra, a. r., 200 g` → `ŠEIMOS vytinta dešra, 200 g`.
  3. On a tie, the **web** name wins, since it's the store's own format: `Kefyras ROKIŠKIO NAMINIS, 0.9 kg` + info vs `Kefyras NAMINIS, 2,5% rieb., 0.9 kg` → the web one.
- **`product_mapping`:** every name either product was known by points to the survivor: the flyer name, the web name and every mapping row that pointed to the deleted product. This uses `updateOrCreate`, so a stale row can't keep pointing at the deleted product. Without this, the next scrape of either source would recreate the duplicate.
- **Offers:** the survivor briefly has two offers from one store. The conflict step keeps the one with a `product_url`, and `DuplicateDiscountRemover` also prefers rows with a URL. An offer with empty `info` takes it from the removed same-store offer, so details like `3 rūšių` stay visible.

## Running it

It runs automatically after every scrape batch: `FinalizeScrapedStoresJob` and `ProcessScrapingFlow` call it right after the regular `products:merge-duplicates` and before `DuplicateDiscountRemover`.

Manual run:

```bash
# Preview, and write the pairs to CSV (store, price, both names/infos, coverage, kept id, final name)
php artisan products:merge-duplicates --cross-source --dry-run --export=/tmp/merge-candidates.csv

# Real run: back up first, then run the same follow-up steps the job does
php artisan db:backup
php artisan products:merge-duplicates --cross-source
php artisan tinker --execute 'app(App\Services\DuplicateDiscountRemover::class)->remove();'
php artisan discounts:index-meilisearch
php artisan cache:clear-discounts
php artisan cache:warm
```

## 2026-09-29 production run

- Reviewed the dry-run CSV pair by pair first. Six pairs were flagged as worth a second look, and the user approved merging all of them:
  - `Tamsi ruginė duona JONĖ` vs `Ruginei duonai be mielių JONĖ`
  - Iki broiler `atvėsintos` vs `užaugintos be antibiotikų`
  - `KĖDAINIŲ KONSERVAI` vs `BE KONSERVANTŲ`
  - PIEMENĖLIO `varškė` vs `biri`/`trinta varškė`
- Backup: `/var/www/backups/nuolaidos_2026-09-29_180218.sql.gz`.
- Results:

  | | Before | After |
  |---|---|---|
  | products | 59,433 | 59,274 (−159) |
  | discounts | 10,589 | 10,430 (−159: 155 same-period duplicates dropped during the merge, 4 by `DuplicateDiscountRemover`) |
  | `product_mapping` rows | 1,889 | 2,200 |

- By store: Norfa 124, Iki 24, Gulbelė 4, Lidl 4, Maxima 1, Rimi 1, Thomas Philipps 1.

## Known trade-offs

- **Norfa electronics lose the article number from the name.** `art.` counts as an abbreviation, so `Rankinis trintuvas TEFAL, art. HB1218, 1 vnt` → `Rankinis trintuvas TEFAL, 1 vnt`. The number stays in the offer's `info`.
- **The Norfa fallback relies on `image_from_flyer`.** A flyer product whose image was already replaced by a web photo (`image_from_flyer = 0`) can't be the flyer side of a Norfa pair. It could still be merged through the regular name-based merge.
