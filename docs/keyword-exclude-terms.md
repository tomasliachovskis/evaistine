# Cleaning off-intent products from keyword pages (`exclude_terms`)

How we audit and fix keyword pages (`/{keyword-slug}`, `keyword_pages` table) that list products outside the page's search intent. The first full pass was on 2026-09-29. The scripts it used are in `docs/keyword-exclude-terms/`.

---

## The problem

Keyword pages were showing products that don't match their intent. Examples from production:

- `kava` listed bottled `STARBUCKS FRAPPUCCINO` drinks.
- `kavos-kapsules` listed 3in1 instant sachets.
- `sausainiai` listed 94 candy products.
- `kaciu-kraikas` listed cat food, toys and shampoo.
- `galviju-liezuviai` listed dog chews and no beef tongue at all.

Why it happens: `KeywordPageService` joins every `search_terms` entry into one Meilisearch query. Meilisearch's default matching strategy drops words until something matches, and it ignores diacritics and stems words. So one broad word such as `kava` or `kukurūz` pulls in anything that mentions it. After the search, the only filters are `exclude_terms` (a case-insensitive substring match on product name or brand) and `category_slugs`.

## The fix: data, not code

**Fix it in `exclude_terms` (and `search_terms`) per page. Don't make the matching code stricter.**

We tried the code route first and reverted it (2026-09-29). The attempt required that every product name contain one of the page's `search_terms`, stemmed. It emptied or halved about 200 of 240 pages. Many `search_terms` are written without diacritics (`sokoladas`, `dantu pasta`, `kaciu kraikas`), and many rely on Meilisearch's fuzziness to match inflected forms. A code-level rule can't tell a legitimate loose match from an off-intent one. A reviewed exclude list can.

## Process

### 1. Export what each page actually shows (prod, read-only)

Run the page's own listing path, so the export is exactly what users see. Local data is a separate, stale database (see CLAUDE.md), so always export from prod.

```bash
scp docs/keyword-exclude-terms/export-shown.php deploy@84.247.186.143:/tmp/kw_dump.php
ssh deploy@84.247.186.143 'cd /var/www/api && php artisan tinker /tmp/kw_dump.php < /dev/null'
scp deploy@84.247.186.143:/tmp/kw_dump.json storage/app/kw/dump.json
ssh deploy@84.247.186.143 'rm -f /tmp/kw_dump.php /tmp/kw_dump.json'
```

- **Close stdin with `< /dev/null`.** Without it, `tinker` finishes the script and then hangs waiting for input, and the SSH call never returns.
- The export takes about a minute for 242 pages. It calls `KeywordPageService::collectMatchingDiscountsCollection()` through reflection. That method is private, but it is the exact set the page lists.

### 2. GPT proposes excludes per page (local)

```bash
cp docs/keyword-exclude-terms/propose.php storage/app/kw/
sail artisan tinker storage/app/kw/propose.php < /dev/null
```

For each page, GPT (`config('services.openai.model')`, gpt-5-mini) receives the H1, the current excludes and the numbered list of shown products. It returns:
- `off_intent`: the numbers of the products that don't belong.
- `exclude_terms`: substrings that would remove those products.
- `notes`: one sentence on what was wrong.

The script runs 6 requests in parallel and writes `storage/app/kw/proposals.json` after every batch. Re-running it skips pages already done, so an interrupted run resumes where it stopped.

### 3. Simulate with a collateral check

```bash
python3 docs/keyword-exclude-terms/simulate.py
```

Before any review, the script applies the proposed terms to each page's real shown list, using the same substring rule as `passesKeywordFilters()`. It rejects a term automatically when:
- it would also remove a product GPT did *not* flag as off-intent,
- it matches nothing shown,
- it is shorter than 3 characters or on the blocklist (`akcija`, `iki`, `maxima`, etc.).

The output, `storage/app/kw/simulation.json`, holds before/after counts, the removed products, a sample of kept products and the rejected terms for every page. It is the input for the review and for the before/after report.

### 4. Human review, then overrides

GPT gets roughly 90% right, but its mistakes are systematic. Record every correction in `storage/app/kw/overrides.json` (`{"slug": {"drop": [...], "add": [...]}}`) and re-run step 3. The 2026-09-29 decisions are in `docs/keyword-exclude-terms/overrides-2026-09-29.json`.

Review rules learned on the first pass:

- **Check that the H1 matches the slug.** Some imported pages have a wrong H1: `mineralinis-vanduo` → "Akvile vanduo akcija", `saldainiai` → "Rūta saldainiai akcija", `kumpis` → "Serano kumpis akcija", `skalbimo-priemones` → "Skalbimo milteliai akcija". GPT follows the H1, so on these pages it removed the correct products, including Birutė and Vytautas mineral water, every candy brand, and ordinary pork ham. For such a page, keep only the obviously wrong excludes and fix the H1 separately.
- **No brand excludes for brands that also make on-intent products.** `rokiškio` or `žemaitijos` on `grietinele` would remove sour cream today, but also any Rokiškio cream that shows up next week. Brand excludes are acceptable only for a brand that never makes the page's product (`pedigree` on a cat page, and even that is usually covered by a word such as `šunų`).
- **Prefer a phrase over a single word when the single word hits something legitimate.** Use `spraginti kukurūzai`, not `spraginti`, which also hits the "Spragintiems kukurūzams" promo. Use `higieniniai paketai`, not `paketai`, which hits "Higieniniai įklotai ar paketai".
- **A variant of the same product is on-intent.** Grated Džiugas is still Džiugas cheese. Razor cartridges belong on `skustuvai`. Dishwasher capsules belong on `indaploviu-tabletes`. Sheba treats belong on `sheba`. Friskies dog food belongs on `friskies`.
- **A page going to 0 offers is fine** when everything it shows is wrong (`antis`, `cesnakai`, `galviju-liezuviai`, `akumuliatoriams`, `anglys`). The page then renders its empty state with links to nearby pages.
- **Some problems exclude can't fix.** On `grietinele` the leftover sour cream can only be removed by brand excludes (see above). The real fix there is narrower `search_terms`, which is a separate step, not covered here.

### 5. Before/after report

Publish `simulation.json` as a page for sign-off before anything is written. It shows per page the before → after counts, the added terms, the full list of removed products and a sample of kept products. 2026-09-29 report: https://claude.ai/artifact/FmSwsHap3Kdag2cTyfTBzX

### 6. Apply and verify

1. Append each page's accepted terms to `keyword_pages.exclude_terms` on prod. Append only, never replace. Save the old values first; the 2026-09-29 backup is `storage/app/keyword_exclude_backup_2026-09-29.json` on the prod server. Then call `KeywordPageService::refreshOfferCounts()` for each changed page.
2. `php artisan keywords:map-products`. It rebuilds the product-page cross-links and bumps the `keywords` cache version.
3. Clear the keyword pages' HTML cache. `PageHtmlCache` keys (24h TTL) are versioned only by `discounts`, not `keywords`, so bumping `keywords` doesn't refresh the rendered pages. Forget `PageHtmlCache::cacheKey('/akcijos/'.$slug)` for every keyword page instead. Don't run `cache:clear-discounts` for this.
4. Re-run step 1 and diff each changed page's new shown list against the old export. **Review every product that wasn't in the old export.** The listing fetches only about Meilisearch's hit count plus a small buffer, so removing off-intent items can surface products that were hidden before. On 2026-09-29 this happened twice:
   - On `antis`, two chicken products and salmon surfaced, all caught by Meilisearch on the word "pikantiškai".
   - On `kukuruzai`, "Kukurūzų traškučiams DORITOS" surfaced. The inflection (-iams) slipped past `traškučiai`, so the exclude became `traškuč`.
   Prefer a stem (`traškuč`) over a full word when the word inflects.

## 2026-09-29 run

- 242 published pages. 231 had offers; 11 were already empty.
- GPT proposals plus review: 118 pages got 437 new exclude terms. The simulation removed 1,682 of 8,112 shown products (about 21%).
- **Applied on prod 2026-09-29.** Stored offer counts on the changed pages went from 6,263 to 4,548. Every page matched the simulation except `antis`, which needed three more excludes (see step 6).
- 4 pages end at 0 offers (`akumuliatoriams`, `anglys`, `cesnakai`, `galviju-liezuviai`). `antis` keeps one real duck product.
- Fixed the 4 wrong stored H1s on prod (`Kumpis akcija`, `Mineralinis vanduo akcija`, `Saldainiai akcija`, `Skalbimo priemonės akcija`). Visitors never saw the stored `h1`: the rendered H1 is built from `title` and `grammar_genitive`. The wrong value did mislead internal tools, though, including this review's GPT pass. It came from the import, which takes the first `primary_keywords` entry (the highest-volume keyword) as `h1`.
- The offer count shown to visitors now comes from the filtered listing, not Meilisearch's raw hit total. Before, the chips, home links, the page's "N pasiūlymų" and its `<title>` overstated it on 111 pages (e.g. `kaciu-kraikas` said 362 while listing 98). `refreshOfferCounts()` now stores the listed count in both `matching_offers_count` and `displayed_offers_count`.
- Open follow-ups:
  - narrow `search_terms` on pages where exclude can't help (`grietinele`, `tirpi-kava` which shows only 7 products, and others): about 1,100 products GPT flagged are still shown;
  - `mineralinis-vanduo`'s intro text talks about micellar water;
  - 15 published pages have 0 offers: decide whether to unpublish the ones that will never have any (`galviju-liezuviai`, `akumuliatoriams`);
- Done the same day: `KeywordPageGptService`'s import prompt now encodes these review rules for new pages:
  - the main product phrase comes first;
  - no generic head word on a narrow page;
  - no store names, sizes, "kaina" or "receptai" in `search_terms`;
  - stemmed excludes for typical off-intent items;
  - no brand excludes for brands that make the product;
  - `brands` only for brands whose every product fits the page, because brands are merged into `search_terms`.
