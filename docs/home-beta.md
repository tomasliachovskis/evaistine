# Beta homepage (/pradzia-beta), 2026-10

## Status

- Lives at `/pradzia-beta`, next to the live `/` (`NewHomeController`), which is untouched. `noindex, nofollow`, canonical to itself.
- **Local only.** The commits are on local `master` and not pushed (owner: "kol kas local nepushinam"). Check with `git log --oneline origin/master..HEAD`.
- One of those commits fixes a live bug and isn't specific to the beta: `3e23b84` gives `<x-signup-inline-card>` an Alpine scope (`x-data`). Without it, the "Užsiregistruoti nemokamai" button on `/` and on product pages does nothing in production. It can be cherry-picked and deployed on its own, if the owner agrees.
- Header and footer are the normal `<x-layouts.app>` ones (owner asked not to change them).

## Why

The current `/` has many blocks: comparison teasers, signup card, stores, categories, leaflets, stats. For the site's 50+ readers (`docs/ui-older-readers.md`) that is too much to take in.

The beta answers "where is it cheapest?" straight away, with large elements and few choices.

Mockup (Claude design canvas, private): https://claude.ai/artifact/EhLVNRhVj3NADCLHuJipgf

## What's on the page

1. **Hero.**
   - "Ką šiandien perkate?" (h1, 28/40px), left-aligned.
   - One sentence with today's real offer count ("Šiandien palyginome 17 366 pasiūlymus iš 40 parduotuvių…").
   - `<x-search-box size="lg">`.
   - "Arba pasirinkite parduotuvę" with tiles for the 5 main stores plus "+35 kitos" (→ `/parduotuves`). They sit beside the search from `lg`, 3×2 on phones.
2. **"Pigiausia šią savaitę".**
   - One card per keyword (`<x-keyword-deal-card>`): the cheapest current deal's photo, price, discount, store, and "N pasiūlymai →". The whole card links to the keyword page.
   - 8 cards shown, 8 more behind "Rodyti daugiau prekių (8)" (Alpine, same page).
   - "Visos akcijos" → `/akcijos`.
3. **"Pranešime, kai atpigs".** Dark-green band. For guests it opens the shared `authModal`; for signed-in readers it links to `/favorites`.
4. **"Naujausi akcijų leidiniai".** The same row as on `/` (`<x-home-latest-leaflets>`), added at the owner's request.

## Owner's design decisions (keep these)

- Few blocks. "Vyresniam žmogui daug nereikia, jis pasimeta", so only the killer features stay. Dropped from the mockup:
  - "kas naujo nuo paskutinio apsilankymo";
  - cheapest basket;
  - "gera kaina per 30 d.";
  - 3-step explainer;
  - weekly email;
  - "Kur jūs perkate?" store picker.
- Don't push leaflets hard on the page itself. The team is digitising them into offers, so the deals come first and leaflets get only the bottom row.
- One keyword = one card, showing that keyword's best deal. This was the owner's idea.
- Large elements for older readers. Phones show 2 cards per row, but still large (28px price, 48px bottom row).
- Not "AI-template" looking. No eyebrow pill with a dot, and no centred huge-headline hero. Headings follow the site's scale (h1 28/40, h2 24/28).

## Files

- `app/Http/Controllers/HomeBetaController.php`: the page's data.
- `resources/views/home-beta.blade.php`.
- `resources/views/components/keyword-deal-card.blade.php`.
- `resources/views/components/search-box.blade.php`: `size="lg"` added. The default size is unchanged.
- `resources/views/components/home-latest-leaflets.blade.php` and `app/Support/HomeLatestLeaflets.php`: leaflet row and its selection, extracted from `/`. Both homepages use them.
- `routes/web.php`: `Route::get('/pradzia-beta', ...)`.

## Data

**Keyword cards:**
- Source: `KeywordPageService::topCandidatesByCategoryGroup()['food']` (up to 20 published chip keywords, cached) and `buildHomeTeaser($page, 5)` per keyword (cached, reads `curated_deals` scope `keyword_teaser`).
- The card's deal is the cheapest of `leading_deals` with a price and a `to_date` that hasn't passed.
- Order: `HomeBetaController::EVERYDAY_SLUGS` first (pienas, duona, kiaušiniai, sviestas…), then the rest by offer count. Max 16.
- Only candidate keywords are used. Their teaser rows are kept fresh by `DealPoolRefresher`. Other keywords would fall back to stale `discount_histories`.
- Local DB ≠ prod. Locally the first cards are duona/sūris/dešra; in prod they'd be pienas, sviestas and so on.

**Other data:**
- Offer count: the `HomePageMetaService` stats, the same cache key as `/` (`new_home_meta_*`).
- Store tiles: the same store query and cache as `/`, with `StoreListPriority::sort()` taking 5. The link rule is the same as `<x-store-card>`.

## Known issues / next steps

1. **The cheapest deal isn't always the right product.** It's the cheapest product mapped to the keyword, and the keyword mapping is loose. Examples:
   - "Kiauliena" shows buckwheat porridge with pork;
   - "Sūris" in prod shows a curd snack bar;
   - "Kiaušiniai" shows quail eggs.
   
   On a page where it's the only answer, this hurts trust. Fix before going live: prefer an exact match, i.e. a product name starting with the keyword, a sensible pack size, or price per kg.
2. **SEO before swapping `/`.** The beta has fewer internal links and less text than `/`.
   - Check in Search Console which queries land on `/` (`reference_gsc_api_access` memory).
   - If `/` gets real search traffic, add a small text link block (categories, main-chain leaflets) under the cards.
3. **Measure.** Cards send GA `product_card_click` with `source=home_beta`. Compare that with `source=home_comparison` from `/`.
4. To go live: point `/` at `HomeBetaController`, set robots and canonical for `/`, and drop or redirect `/pradzia-beta`. Then deploy with `./deploy.sh` after `git push`.

## Checking it

```bash
sail npm run build
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/pradzia-beta   # 200
sail artisan test                                                         # 250 passed
```

- **Screenshots and audit.** Look at 390px and 1440px with a throwaway Puppeteer script run via `sail exec -T laravel.test node …`. Move the PNGs out of `storage/app` afterwards: `deploy.sh` rsyncs `storage/app/` to the server.
- **Last check.** No overflow, no JS errors, and text ≥16px. The only control under 44px is the search input; its 56px pill wrapper is the same known exception as on `/`.
