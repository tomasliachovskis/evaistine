# Curated deal carousels (`curated_deals`)

The category carousels on `/akcijos` and `/akcijos/{store}`, each store's "Geriausi pasiūlymai" strip, and the home "best deals" pool are stored rows in `curated_deals`. `App\Services\DealPoolRefresher` writes them after every `discounts:process` batch, and you can rebuild them with `sail artisan deal-pool:refresh`. The picking logic is in `App\Services\HomeDealPoolService`.

## Product lines: different products before repeats (2026-10-08)

Problem (owner's report): the "Plaukų priežiūra" carousel showed five CRESCINA TRANSDERMIC HFSC kits, then three NIOXIN serums, although the category has about 5,100 discounts. There were three causes:

- `deal_score` weights absolute savings ×3, so one line of expensive kits takes every top place.
- Only 32 candidates were considered.
- The only diversity rule was `DealFamilyKeyResolver`. For pharmacy products it falls back to `category:{slug}`, because there is no `generic_product_id` and usually no keyword page match. Every card got the same key, so the rule allowed one card and the last-resort tier refilled the carousel in plain score order.

Rule now: **one card per product line in a carousel, while other lines exist.** `App\Support\ProductLineKey::for($brand, $name)` gives two keys:

- `brand:{brand}`, lowercased, when the brand is set.
- `name:{first two words}`, lowercased with punctuation stripped. Examples: "crescina transdermic", "dermatologinis kosmetinis".

A card whose brand **or** name prefix is already in the carousel counts as a repeat.

Order of relaxation in `bestForCategory()`. Each step runs only if the carousel is still short:

1. Scored candidates (150 now, previously 32), with the family, line and per-store caps. On global carousels the store cap is half the limit.
2. Same, without the family cap.
3. Same, without the store cap. The line rule still applies.
4. Fill-up from the rest of the category (`fillCategory()`: other discounts by biggest %, then products without a discount by lowest price), new lines only.
5. Scored candidates without the line rule.
6. Fill-up without the line rule.

So a different product without a discount beats a second CRESCINA kit. `pickDiversePool()` (store top offers) checks the line key next to the family key, and relaxes it last. `DealPoolRefresher::pickFromCategoryRows()` (the home pool) checks lines across categories, because one line can top two categories. It allows repeats only in a second pass. The priceless cap ("Sutaupyk iki X%" cards) is never relaxed.

The 150 candidates load only `product.category` and `store`, not the offer and history relations, since `DealPoolRefresher` keeps only the ids.

Results on the local DB after `deal-pool:refresh`:

- Every `global_category`, `store_top_offers` and `home_best` bucket has no repeated brand or two-word name prefix.
- 18 of 123 `store_category` buckets still repeat a line. Each of them has fewer distinct lines in that store and category than there are cards to show, for example a store with 25 offers all from one brand.
- "Plaukų priežiūra" now shows 8 brands: Crescina, Dermedic, NIOXIN, PHYTO, DUCRAY, RENE FURTERER, FORCAPIL and SENDO.
- A full `deal-pool:refresh` takes about 40 s locally, compared with 29 s before. It runs on the write path only.

Test: `tests/Feature/DealPoolFillTest.php`, `test_carousel_shows_different_product_lines_before_repeating_one`.

Possible follow-up: the name key is crude, using only the first two words. If it merges unrelated products often (two different brands' "Vitaminas D3"), that is acceptable in a carousel, since it only pushes variety further. If it misses a line, the brand usually catches it.
