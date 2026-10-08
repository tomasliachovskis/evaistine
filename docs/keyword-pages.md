# Keyword pages (first pharmacy wave, 2026-10-07)

Keyword pages are flat landing pages such as `/vitaminas-d`, `/magnis` and `/bioderma`, each built around one search phrase. Before this work the `keyword_pages` table was empty, the header nav already linked 15 slugs that didn't exist, and the whole pipeline (import files, category resolver, H1 and meta) still came from superakcijos.

## Which pages and why

Search volume wasn't available, so the owner chose two signals instead: **Google autocomplete plus our own product data**. A page is published only if:

1. Autocomplete (`suggestqueries.google.com`, `hl=lt&gl=lt`) returns the phrase with at least 5 variants. That shows people actually type it.
2. It has at least 10 active offers from at least 3 pharmacies. The importer enforces this (`MIN_ACTIVE_OFFERS`, `MIN_PHARMACIES` in `KeywordManualImporter`).
3. It is not a prescription medicine and not a disease or symptom page. The ailment layer is planned separately.

Only commercial and product variants are kept: doses, forms, "vaikams", "kaina", "akcija", brands and pharmacy names. These are dropped:

- informational variants ("nauda", "kas tai", "kaip vartoti", "ar ... ar"), because of YMYL;
- dosing variants ("pagal svorį", "skaičiuoklė");
- symptom variants ("nuo slogos", "nuo temperatūros");
- prescription brands (Nasonex, Dymista, tramadolis);
- off-topic variants (meat thermometers, dogs).

The kept variants are saved in `database/data/pharmacy-keyword-pages-2026-10.json` (fields `title`, `slug`, `category_slugs`, `keywords`), which is the default file for `keywords:import-manual`.

Candidates dropped before import:

- **Kūdikių mišinys**: only 2 autocomplete variants.
- **Prezervatyvai**: autocomplete only showed shop domains. Replaced by **Durex**, which has 10 variants.

Autocomplete gives no volume. The order of the suggestions reflects popularity, and `google:suggestsubtypes` are undocumented internal codes. For real volume we'd need Keyword Planner, or GSC once the site is live.

## How a page gets its products

`keyword_page_products` is the single source for:

- the page grid;
- offer counts (`matching_offers_count`);
- home and `/pigiausios-prekes` teasers;
- product-page keyword links;
- `DealFamilyKeyResolver`.

It is filled by `App\Services\KeywordPageProductMapper` (`sail artisan keywords:map-products [--page=slug]`):

1. Each `search_terms` entry is searched on its own in the Meilisearch `products` index (`App\Services\ProductSearchIndex`):
   - every word has to match (`matchingStrategy: all`);
   - typo tolerance applies only to words of 5+ letters, which catches inflections such as "magnis"/"magnio", and never to numbers;
   - results are filtered to the page's root category tree.
2. Words of up to 3 characters ("d", "b12", "k2") must stand alone in the product name. Meilisearch prefix-matches the last word, so without this check "vitaminas d" also matched "dengtos".
3. `brands` match `products.brand` exactly.
4. `exclude_terms` drop products whose name or brand contains the term. This happens here, so the table only holds products that belong to the page.

The page itself then runs one indexed query, joining `keyword_page_products` to active `discounts`, ordered by match score and then discount. It makes no search-engine call per request.

Earlier the grid searched the `discounts` index with every term glued into one query. It fell back to `LIKE` locally, and the product-page links came from a separate substring matcher. The three gave different results.

### Meilisearch in dev

This checkout now runs its own `meilisearch` container (`getmeili/meilisearch:v1.16`, host port 7701, `MEILISEARCH_HOST=http://meilisearch:7700`). Indexing is gated on `config('services.meilisearch.enabled')`, which is true whenever `MEILISEARCH_HOST` is set, not only in production.

`FinalizeScrapedStoresJob` runs these after every scrape batch:

1. `discounts:index-meilisearch`
2. `products:index-meilisearch`
3. `keywords:map-products`
4. `keywords:refresh-counts`

The nightly 04:00 archive step also reindexes both indexes before the 04:15 mapping run.

## Never 404 (2026-10-08)

A keyword URL must keep answering, whatever happens to the products.
- A published page always renders 200 and stays indexable. With no matching
  offers it shows the empty state ("Šiuo metu {genitive} vaistinėse
  neradome..."), a link to its category ("Visos {category genitive} kainos")
  and close keyword pages that have offers now. It recovers by itself when
  products come back.
- Products show from the first offer: `min_active_offers` is 1 on every page
  (migration `2026_10_08_120000_keyword_pages_show_from_one_offer`; it was 10,
  which hid pages with 1-9 real offers). The importer's 10 offers / 3
  pharmacies rule (`KeywordManualImporter::MIN_OFFERS_TO_PUBLISH`,
  `MIN_PHARMACIES`) only decides whether a new page gets published.
- Nothing automatic unpublishes a page. The importers can publish a page but
  keep an already published one published; `keywords:audit` (not scheduled,
  only run at the end of `keywords:import-manual`) recommends "KEEP
  PUBLISHED" instead of "UNPUBLISH"; the scheduled `keywords:map-products` and
  `keywords:refresh-counts` never touch `is_published`. Unpublishing or
  deleting stays a manual Filament action.
- An unpublished page (taken offline in Filament) 301s to its first category,
  or `/akcijos` without one (`AkcijosController::show`). A deleted page's slug
  has no row left and would 404, so prefer unpublishing to deleting.
- Tests: `tests/Feature/KeywordPageAlwaysAvailableTest.php`.

## Other pipeline changes

- **`KeywordPageCategoryResolver`** accepts the 12 pharmacy roots (`config('categories.roots')`) instead of the grocery `FoodCategorySlugs`. Before, every imported page's category resolved to nothing.
- **H1, title and description** (`KeywordPageDynamicMetaService`):
  - H1: "Vitamino D kainos ir akcijos vaistinėse"
  - title: "Vitaminas D kaina nuo X € | N pasiūlymų vaistinėse"
  - description: "Palyginkite vitamino D kainas vaistinėse: nuo X iki Y €. …"
  - GPT returns grammatical forms lowercase, so the genitive gets the title's capitals back ("vitamino D", "Biodermos").
- **Home, `/pigiausios-prekes` and home-beta blocks** are split into "Vaistų ir papildų" and "Kosmetikos ir higienos prekių" (`config('categories.keyword_medicine_group')`), instead of food/non-food.
- **Importer** (`keywords:import-manual`):
  - reads `slug` and `keywords` (autocomplete variants become secondary keywords);
  - primary keywords are "{title} kaina / akcija / vaistinėje";
  - new pages get `is_chip = true` (footer, category pages, home);
  - counts use the same mapper as the saved page.
- **GPT prompt** (`KeywordPageGptService`):
  - uses pharmacy examples;
  - writes only about buying and prices, never health;
  - writes no "what we don't show" statements;
  - declines pharmacy names correctly;
  - never excludes the page's own variants ("vaikams" on `/vitaminas-d`).
- **Gift offers** at a token price (Benu "DOVANA …" at 0.01 €) are rejected in `DefaultRules::validate()` (`MIN_REAL_PRICE`), and the 31 existing ones were deleted. They had become the "nuo 0.01 €" price on every listing.

## Removed

- **Grocery import files**: `database/data/bulk-keyword-pages-2026.json`, `new-keyword-pages-2026-09.json`, `keyword-pages-2026-10-batch2.json`, `gsc-keyword-pages-2026-10.json`, `keyword-pages-2026-10-saslykai.json`.
- **`database/seeders/KeywordPageSeeder.php`**.
- **The coffee special case** in `KeywordGroupAnalyzer`.
- **What was kept**: old grocery migrations that only update keyword rows by slug, because they already ran and do nothing on an empty table, and `docs/superakcijos-legacy/` as an archive.

## Commands

```
sail artisan keywords:import-manual --dry-run                 # list, no GPT
sail artisan keywords:import-manual --apply --slug=magnis      # one page
sail artisan keywords:import-manual --apply --force           # all, overwrite
sail artisan keywords:map-products [--page=slug]
sail artisan keywords:refresh-counts [slug]
sail artisan keywords:audit
```

## Result

50 pages imported and 49 published. **CeraVe** is not published: it has 7 offers, and our scrape only finds it in 1–2 pharmacies.

After the import, these were fixed by hand:

- **magnis**: a transient GPT API error; it was re-imported.
- **nuo-uteliu**: lice products are spread across 4 root categories, so the page has no category filter. Also added "nuo utėlių", "utėlėms" and the lice-only brands Paranit, Nyda and Hedrin to its terms.
- **kalcis**: typo tolerance matched potassium ("kalis"), so "kalis", "kalio" and "kalį" were added to `exclude_terms`.

All 15 header nav slugs return 200. The keyword pages appear in the sitemap, the category pages and both home blocks.

Offer and pharmacy counts on 2026-10-07:

| Page | Active offers | Pharmacies | Published |
|---|---|---|---|
| `/dantu-pasta` | 694 | 9 | yes |
| `/vitaminas-d` | 567 | 11 | yes |
| `/magnis` | 497 | 11 | yes |
| `/vitaminas-c` | 447 | 12 | yes |
| `/omega-3` | 416 | 11 | yes |
| `/lupu-balzamas` | 402 | 9 | yes |
| `/ranku-kremas` | 333 | 11 | yes |
| `/avene` | 295 | 6 | yes |
| `/bioderma` | 289 | 7 | yes |
| `/dezodorantas` | 289 | 9 | yes |
| `/la-roche-posay` | 253 | 5 | yes |
| `/kolagenas` | 248 | 11 | yes |
| `/micelinis-vanduo` | 235 | 8 | yes |
| `/vichy` | 219 | 4 | yes |
| `/eucerin` | 217 | 8 | yes |
| `/retinolis` | 186 | 7 | yes |
| `/sampunas-nuo-pleiskanu` | 180 | 8 | yes |
| `/cinkas` | 175 | 11 | yes |
| `/elektrinis-dantu-sepetelis` | 174 | 6 | yes |
| `/nosies-purskalas` | 170 | 9 | yes |
| `/zuvu-taukai` | 167 | 11 | yes |
| `/sauskelnes` | 161 | 4 | yes |
| `/termometras` | 152 | 9 | yes |
| `/kraujospudzio-matuoklis` | 145 | 8 | yes |
| `/inhaliatorius` | 135 | 9 | yes |
| `/biotinas` | 115 | 11 | yes |
| `/gelezis` | 105 | 11 | yes |
| `/vitaminas-k2` | 102 | 11 | yes |
| `/folio-rugstis` | 97 | 11 | yes |
| `/melatoninas` | 95 | 10 | yes |
| `/ashwagandha` | 84 | 9 | yes |
| `/kremas-nuo-saules` | 83 | 7 | yes |
| `/vitaminas-b12` | 80 | 9 | yes |
| `/kalcis` | 68 | 9 | yes |
| `/durex` | 61 | 5 | yes |
| `/nuo-uteliu` | 49 | 7 | yes |
| `/voltaren` | 48 | 9 | yes |
| `/nurofen` | 47 | 8 | yes |
| `/paracetamolis` | 46 | 8 | yes |
| `/probiotikai` | 38 | 9 | yes |
| `/otrivin` | 37 | 9 | yes |
| `/strepsils` | 31 | 8 | yes |
| `/ibuprofenas` | 29 | 8 | yes |
| `/nestumo-testas` | 28 | 6 | yes |
| `/hialurono-rugstis` | 28 | 9 | yes |
| `/kreatinas` | 24 | 4 | yes |
| `/serumas-veidui` | 23 | 4 | yes |
| `/repelentas` | 15 | 5 | yes |
| `/elektrolitai` | 14 | 4 | yes |
| `/cerave` | 7 | 1 | no |

