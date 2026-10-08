# Page copy and GPT prompts (cleanup 2026-10-07)

Until this cleanup, the hand-written page texts and the GPT prompts still came from superakcijos. They named Maxima, Lidl, Iki, Rimi and Norfa, used grocery categories (vaisiai, daržovės, mėsa, buitinė chemija), said "prekybos tinklai", and linked dead slugs such as `/maxima` and `/vaisiai-ir-darzoves`. The `/akcijos` intro was one example. This note covers what was rewritten, the rules the copy follows now, and what is still left.

## Copy rules (owner's decisions)

- The site compares **"vaistų kainos"** ("vaistų kainų palyginimas"), not "vaistinių kainos" or "vaistinės prekių kainos". "Vaistinių" is used only for the pharmacies themselves ("visose vaistinėse", "vaistinių akcijos").
- **Don't mention prescription medicines at all**, not even to say they aren't listed. The copy describes only what is on the site.
- **Headings are SEO-oriented and plain**: built around what people search for ("Benu vaistinės leidinys", "Kur rasti naują … leidinį", "Vitaminų ir maisto papildų akcijos ir kainos vaistinėse"). No vague or clever headings.
- YMYL: no treatment advice, no dosing, no claims that a product helps with anything, no "stronger/better" comparisons. A product is described only by its type, brand, form and pack size.
- Use "vaistinė", never "parduotuvė", "prekybos tinklas", "prekybos centras" or "apsipirkti".
- Pharmacy names are declined with `PharmacyName::phrase()`, so they read "Benu vaistinės akcijos", not "Benu vaistinė akcijos" or "Visos Benu vaistinė …". Categories are declined with `ProductController::categoryGenitiveLabel()`.
- Pharmacy promotions don't run on a weekly supermarket cycle, so the copy never says "kiekvieną savaitę" or "nuo pirmadienio iki sekmadienio".

## Where chain names come from

`StoreListPriority::mainNames()` and `mainNamesText()` (`app/Support/StoreListPriority.php`) return the main chains from `config('stores.main_slugs')` and `config('stores.stores')`, giving "Eurovaistinė, Gintarinė vaistinė, Camelia, Benu vaistinė ir Apotheka". Every hand-written list of chains reads from these: the `/akcijos` intro and meta, `/leidiniai`, the home FAQ and meta, `/vaistines`, the layout's default meta, `/apie`, subscription settings, `/pigiausios-prekes`, and the keyword-page store priority.

Pharmacy counts come from `count(config('stores.stores'))`, not a hard-coded "40" or "20+".

## Hand-written templates that were rewritten

- **`ProductController::generateSeoData()`**
  - `all_discounts` (`/akcijos`): intro links to the 5 main pharmacies and to the `popular_slugs` categories via `PageUrl`.
  - `leaflets_index`: rewritten.
  - The product meta now says "palyginkite kainas vaistinėse".
- **`ListingPageMetaService`**
  - Category and pharmacy×category intros, and the stats summary.
  - Leaflet hub (`/leidinys/{pharmacy}`): the copy, the headings, and the FAQ, which now uses genitive names and no weekly or weekend assumptions.
  - FAQ category links now read "vitaminų ir maisto papildų akcijas Benu vaistinėje".
- **`HomePageMetaService`**: h1, intro, meta and FAQ.
- **`StoresPageMetaService`**: meta and FAQ.
- **`KeywordPageService`**: priority store names now come from `mainNames()`, and the stats sentence was rewritten.
- **Other PHP**: `ProductPageMeta` FAQ, and `HomeController`, `NewHomeController`, `CheapestProductsController` and `HomeBetaController`.
- **Blade**:
  - `layouts/app` (default meta and Organization JSON-LD)
  - `new-home` (hero, "+N kitų", pharmacy count)
  - `home`, `leaflets/index`, `static/about`
  - `pigiausios-prekes/show` (SEO block)
  - `subscriptions/settings`, `site-search` placeholder, `kuponai`

## Home hero (2026-10-08)

The hero in `new-home.blade.php` was a word-for-word copy of superakcijos'
("Kur pigiausia pirkti — palyginome už tave"). Rewritten so the H1 is the main
query and matches the meta title (owner picked variant A):
- H1: `HomePageMetaService` `h1` = "Vaistų kainų palyginimas", second line in
  the same `<h1>`: "{N} vaistinių vienoje vietoje" (`config('stores.stores')`).
- Shortened 2026-10-08 (owner: "per daug", half the height): the hero is
  now only the H1 (second line "{N} vaistinių kainos vienoje vietoje ·
  atnaujinama kasdien", the update note hidden on phones), the search box and
  the main chains' logos with "+N kitų" in one row (logo `alt` = pharmacy
  name). Removed: the eyebrow, the paragraph naming the chains, the old
  quick-link pills and the "67 000+ aktyvių akcijų" line. Desktop then got a
  bit more room back plus one light "Dažnai ieškoma: Vitaminas D · Ibuprofenas
  · Magnis · Nosies purškalas · Omega-3" line (owner picked it from three
  variants); phones keep the compact version without it. Height 612 → 395 px
  at 1440, 506 → 264 px at 390.
  Search placeholder: "Vitaminas D, ibuprofenas, kremas nuo saulės…".
Don't copy superakcijos' hero wording back when pulling upstream changes.

## Deleted (dead grocery code)

- `ListingPageMetaService::getPriorityStoreAbout/Format/Tips()` and `HAND_WRITTEN_HUB_COPY_SLUGS`: hand-written Maxima/Lidl/Iki copy that nothing reached.
- `getSeasonalModules()` and `config('listing.seasonal_modules')`: these were never rendered.
- `HomePageMetaService::buildCheapestBasket()` and `config('listing.home_basket_products')` ("Pigiausias krepšelis": pienas, duona…): no view used it. `config/listing.php` is gone.
- `app/Services/WeeklyReviewService.php`: nothing called it, and its image path stopped on a `dd()`.
- `KeywordGroupAnalyzer`'s coffee special case (`KAVA_PRIMARY_KEYWORDS`/`analyzeKava`).

## GPT prompts

### `DescriptionGenerationService`

Every system prompt (store, category, category FAQ, store FAQ, leaflet, store×category) ends with `pharmacyRules()`, the shared block of the rules above. The prompts also changed:

- They use pharmacy examples and say "pharmacy price comparison site".
- The leaflet headings are built from `store_name_forms`.
- The Iki "leidynys" and "AČIŪ" examples are gone.
- Cadence includes "kas mėnesį".

The data the prompts receive changed too:

- `store_name_forms`: case forms of the pharmacy name, from `PharmacyName`.
- `name_genitive` on store category links, and `category_name_genitive`.
- `everyday_products`: pharmacy staples, replacing grocery `essential_products`.
- `KNOWN_BRANDS`: pharmacy brands, used by `getDiverseTopDiscounts()`. The grocery-era `category_id != 535` filter was removed.

### Other prompts

- `KeywordPageGptService` (keyword pages): pharmacy few-shot examples, buying-only tips, no emoji (`emoji` is saved empty), and store variants from `mainNames()`. The importers offer the 12 roots from `config('categories.roots')` as the category choices.
- `NewsArticleService`: the prompt and search queries are about pharmacies and medicine prices, and health and treatment stories are out of scope. The cover image never shows anyone taking medicine.
- `PdfFlyerProcessingService`: says "pharmacy flyer" and uses pharmacy name examples.
- `.claude/skills/seo-content/SKILL.md`: points to `pharmacyRules()` and the new anchor convention, "Benu vaistinės ir kontaktai".

## Generation run (2026-10-07)

Benu (store 8) and Vitaminai (category 1) were tested first, then everything was generated:

```
sail artisan descriptions:generate store --all
sail artisan descriptions:generate category --all
sail artisan descriptions:generate faq --all
sail artisan descriptions:generate store-faq --all
sail artisan descriptions:generate store-leaflet --all     # only stores with flyers
sail artisan descriptions:generate store-category --all
sail artisan cache:clear-discounts
```

The first test caught three things, which the prompts and data now prevent:

- Categories in the nominative ("Vitaminai ir maisto papildai akcijos"). Fixed with `category_name_genitive`.
- "Apothekoje" instead of "Apotheka vaistinėje". Fixed with `store_name_forms` on the links.
- An efficacy hint ("stipresni papildai"). Fixed with the no-comparison rule.

A scan after the full run found two more problems, and both prompts were fixed:

- **Category links read "nereceptinių vaistų <a>Nereceptiniai vaistai</a>".** The link text is now `name_genitive` only, followed by "akcijos" outside the link.
- **"Parduotuvė" leaked in three places**: an "elektroninėje parduotuvėje", a category FAQ and a keyword tip. "Parduotuvė" is now banned in any form, tips included.

After the fixes, the store descriptions, all 5 leaflet descriptions, the category #5 FAQ and the `elektrolitai` keyword page were regenerated, and a re-scan found no grocery words, no "parduotuvė" and no duplicated category names.

Coverage after the run:

- **Pharmacies:** all 15 have a description. 13 have a FAQ; N vaistinė and Ramunėlės have no offers yet, so they have no FAQ.
- **Leaflets:** 5 leaflet descriptions, one per pharmacy with flyers.
- **Categories:** all 12 have a description and a FAQ.
- **Pharmacy × category:** 123 intros.

The model is still `gpt-5-mini` (`services.openai.model`), and it sometimes slips on grammar ("rasite platus asortimentas"). Moving the store and category descriptions to a stronger model would be a separate cost decision.

## Left for later

- Done 2026-10-08: `FoodCategorySlugs` removed (see `docs/evaistine.md`, "Removed grocery features"). The home "best deals" pool now draws from every root category; the food-only blocks were deleted, not regrouped, since no live page showed them.
- `HomeBetaController::EVERYDAY_SLUGS` (pienas, duona…) and the `alkoholiniai-gerimai` age gate.
- The Iki "leidynys" special cases (`getStoreLeafletWords`, `ProductPageMeta`, leaflet blades) are harmless but dead.
- The 2 grocery blog posts in `blog_posts`.
- The research JSON files (`storage/app/store_semantic_research.json`, `category_semantic_research.json`, `store_leaflet_semantic_research.json`) don't exist yet. With them, the copy would carry real per-pharmacy facts (founding year, footprint) instead of whatever can be read from discount data.
