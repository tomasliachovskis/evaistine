<?php

use App\Models\Category;
use App\Models\CategoryMapper;
use App\Models\KeywordPage;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Splits the single "Alkoholiniai ir nealkoholiniai gėrimai" root category
 * (id 401) into two roots: the old id is renamed to "Alkoholiniai gėrimai"
 * (its 12 existing subcategories — alus/sidras/vynas/degtinė/etc — were
 * already alcohol-only, so they stay put), and the target for non-alcoholic
 * products is the pre-existing "Nealkoholiniai gėrimai" category (id 397)
 * — it already had 3 sensible subcategories (nealkoholinis alus/vynas/
 * sidras-ir-kokteiliai) but was sitting as a subcategory under "Gėrimai,
 * kava, arbata" (id 380, unused — 0 products) rather than a root, which
 * violates the "category_id must point to a root" rule, so it's promoted
 * to root here rather than creating a duplicate category. The 646 products
 * sitting directly on the old alcoholic root (sodas, energy drinks, non-
 * alc beer/wine/cider) are reclassified by a name-keyword heuristic,
 * manually verified against the full product list before writing this
 * migration (486 matched as non-alcoholic, 160 stayed alcoholic —
 * spot-checked both buckets for false positives/negatives; 2 further false
 * positives — "Gazuotas... vyno kokteilis", "...gazuotas alaus kokteilis",
 * both genuinely alcoholic despite the "gazuotas" wording — were caught by
 * a live visual check after this migration first ran and are corrected
 * here via the $alcoholicOverride guard, mirrored in
 * ProcessDiscounts::resolveCategoryId() for future scrapes).
 *
 * Also fixes 6 pre-existing KeywordPage rows whose category_slugs pointed
 * at the old combined slug (now dead) — 5 get both new slugs (replicating
 * their prior combined-category scope, still narrowed by their own
 * search_terms/exclude_terms); the 6th, id 272 "nealkoholiniai-gerimai",
 * duplicated this migration's new category page 1:1 at the same URL and
 * was shadowing it (KeywordPage routes are checked before Category ones),
 * so it's deleted instead per explicit instruction. ("misinukai" keeps
 * 404ing after this — confirmed pre-existing/unrelated: 0 products in the
 * whole catalog ever matched its search terms, not caused by this split.)
 *
 * Two other hardcoded references to the old slug live outside this
 * migration's reach and were fixed alongside it in the same commit:
 * App\Support\FoodCategorySlugs (category-tree/breadcrumb resolution) and
 * SeedGenericProducts.php (degtinė/konjakas/viskis seed rows).
 */
return new class extends Migration
{
    private const NON_ALC_KEYWORDS = [
        'nealk', 'neal.', 'pepsi', 'cola', 'fanta', 'sprite', 'rc cola',
        'red bull', 'dynami', 'natakhtari', 'mountain dew', 'schweppes',
        'mirinda', '7up', 'krynka', 'gir', 'energ', 'gaiv',
        'mineralinis vanduo', 'sultys', 'limonad', 'tonik', 'laimon',
        'sodos vanduo', 'soda', 'izotonin', 'funkcinis ger', 'funkcinis gėr',
        'gazuot', 'gaz.', 'sodavand', 'vitafruit', 'zandukeli', 'arbata',
    ];

    private const OLD_SLUG = 'alkoholiniai-ir-nealkoholiniai-gerimai';

    public function up(): void
    {
        $alcoholic = Category::find(401);
        if (!$alcoholic) {
            return;
        }

        $nonAlcoholic = Category::where('slug', 'nealkoholiniai-gerimai')->first();
        if ($nonAlcoholic) {
            $nonAlcoholic->update([
                'parent_id' => null,
                'description' => $this->nonAlcoholicDescription(),
                'faq' => $this->nonAlcoholicFaq(),
            ]);
        } else {
            $nonAlcoholic = Category::create([
                'name' => 'Nealkoholiniai gėrimai',
                'slug' => 'nealkoholiniai-gerimai',
                'parent_id' => null,
                'description' => $this->nonAlcoholicDescription(),
                'faq' => $this->nonAlcoholicFaq(),
            ]);
        }

        $alcoholic->update([
            'name' => 'Alkoholiniai gėrimai',
            'slug' => 'alkoholiniai-gerimai',
            'description' => $this->alcoholicDescription(),
            'faq' => $this->alcoholicFaq(),
        ]);

        $products = Product::where('category_id', 401)->get(['id', 'name']);
        $movedIds = [];
        foreach ($products as $product) {
            $normalized = mb_strtolower($product->name, 'UTF-8');
            $isExplicitlyNonAlc = mb_strpos($normalized, 'nealk') !== false || mb_strpos($normalized, 'neal.') !== false;
            $alcoholicOverride = !$isExplicitlyNonAlc && (
                mb_strpos($normalized, 'kokteilis') !== false
                || mb_strpos($normalized, 'vyno gėrimas') !== false
                || mb_strpos($normalized, 'alkoholin') !== false
            );
            if ($alcoholicOverride) {
                continue;
            }

            foreach (self::NON_ALC_KEYWORDS as $keyword) {
                if (mb_strpos($normalized, $keyword) !== false) {
                    $movedIds[] = $product->id;
                    break;
                }
            }
        }
        if (!empty($movedIds)) {
            Product::whereIn('id', $movedIds)->update(['category_id' => $nonAlcoholic->id]);
        }

        // Rimi's already-granular "Nealkoholinis alus/kokteilis/sidras/vynas"
        // mapper strings (store id 25) point at the correct concept now —
        // move them off the (now alcohol-only) old root.
        CategoryMapper::where('store', 25)
            ->where('store_category', 'like', '%Nealkoholiniai gerimai%')
            ->update(['category_id' => $nonAlcoholic->id]);

        // KeywordPageCategoryResolver::resolveListingCategorySlugs only
        // resolves the FIRST recognized slug in category_slugs (its
        // "primary" concept — used to build both the Meilisearch filter and
        // the fallback query's category scope), so order matters here: most
        // of these pages' real content is alcoholic (alus/šampanas/sidras
        // are still mostly in the alcoholic root even after the split), but
        // "gazuoti-gerimai" (carbonated drinks) is predominantly the
        // non-alcoholic side — confirmed live (0 matches with alcoholic
        // first, 7 with non-alcoholic first).
        $defaultOrder = ['alkoholiniai-gerimai', 'nealkoholiniai-gerimai'];
        $nonAlcFirstOrder = ['nealkoholiniai-gerimai', 'alkoholiniai-gerimai'];
        KeywordPage::where('category_slugs', 'like', '%'.self::OLD_SLUG.'%')
            ->get(['id', 'slug', 'category_slugs'])
            ->each(function (KeywordPage $page) use ($defaultOrder, $nonAlcFirstOrder) {
                if ($page->slug === 'nealkoholiniai-gerimai') {
                    // Duplicates this migration's new category page at the
                    // exact same URL and would otherwise shadow it (keyword
                    // pages route before category pages).
                    $page->delete();

                    return;
                }

                $newSlugs = $page->slug === 'gazuoti-gerimai' ? $nonAlcFirstOrder : $defaultOrder;
                $slugs = array_values(array_filter(
                    (array) $page->category_slugs,
                    fn ($s) => $s !== self::OLD_SLUG
                ));
                $page->update(['category_slugs' => array_values(array_unique([...$slugs, ...$newSlugs]))]);
            });
    }

    public function down(): void
    {
        $alcoholic = Category::where('slug', 'alkoholiniai-gerimai')->first();
        $nonAlcoholic = Category::where('slug', 'nealkoholiniai-gerimai')->first();

        if ($nonAlcoholic) {
            Product::where('category_id', $nonAlcoholic->id)->update(['category_id' => 401]);
            CategoryMapper::where('category_id', $nonAlcoholic->id)->update(['category_id' => 401]);
            $nonAlcoholic->update(['parent_id' => 380]);
        }

        if ($alcoholic) {
            $alcoholic->update([
                'name' => 'Alkoholiniai ir nealkoholiniai gėrimai',
                'slug' => self::OLD_SLUG,
            ]);
        }

        // Not reverting the KeywordPage changes (5 pages' category_slugs,
        // deletion of id 272) — those pointed at a slug this down() just
        // brought back, so leaving them on the new slugs would silently
        // break them again; simplest safe choice is to leave the
        // migration's keyword-page fix in place even on rollback.
    }

    private function alcoholicDescription(): string
    {
        return <<<'HTML'
<div class="category-description-block p-0 lg:p-4">
  <h2 class="text-3xl font-bold mb-6 leading-tight">Alkoholinių gėrimų akcijos: atraskite naujausius pasiūlymus</h2>

  <p class="mb-4 text-gray-700">Ruošiate vakarėlį, planuojate barą ar tiesiog norite papildyti namų baro asortimentą? Kategorijoje Alkoholiniai gėrimai rasite alaus, sidro, vyno ir stipriųjų alkoholinių gėrimų (degtinės, viskio, konjako, brendžio, romo, džino, tekilos, likerio ir trauktinių) akcijas iš visų mūsų sekamų parduotuvių.</p>

  <p class="mb-4 text-gray-700">Šioje kategorijoje rasite dešimtis aktualių pasiūlymų — tiek atskirų butelių, tiek didesnių pakuočių akcijos. Populiarūs alaus prekiniai ženklai, tarptautinių vynų asortimentas ir stipriųjų gėrimų nuolaidos dažnai keičiasi, tad verta sekti akcijas reguliariai, ypač artėjant šventiniam sezonui.</p>

  <p class="mb-6 text-gray-700">Svarbu atsiminti, kad alkoholinių produktų akcijos kartais būna susietos su lojalumo kortelėmis ar kitomis sąlygomis, todėl palyginkite taisykles prieš pirkdami. Jei ieškote gaiviųjų gėrimų be alkoholio, žr. <a href="/akcijos/nealkoholiniai-gerimai">Nealkoholiniai gėrimai</a>.</p>
</div>
HTML;
    }

    private function alcoholicFaq(): array
    {
        return [
            [
                'question' => 'Kokius alkoholinius gėrimus dažniausiai rasite su nuolaida šioje kategorijoje?',
                'answer' => 'Dažniausiai akcijuojami alus, sidras, vynas (tylusis ir putojantis), taip pat stiprieji alkoholiniai gėrimai — degtinė, viskis, konjakas, brendis, romas, džinas, tekila ir likeriai.',
            ],
            [
                'question' => 'Kokio dydžio nuolaidos šioje kategorijoje būna dažniausiai?',
                'answer' => 'Nuolaidos svyruoja nuo nedidelių iki reikšmingų, ypač populiariems arba sezoniniams (pvz. švenčių) gėrimams — tuomet pasitaiko ir didesnių procentinių nuolaidų.',
            ],
            [
                'question' => 'Kuriose parduotuvėse verta tikrinti alkoholinių gėrimų akcijas?',
                'answer' => 'Rekomenduojama palyginti didžiųjų tinklų ir regioninių parduotuvių pasiūlymus — dažnai didesni tinklai bei specializuotos vyno/alkoholio parduotuvės skelbia daug įvairių akcijų.',
            ],
            [
                'question' => 'Kaip rasti geriausią pasiūlymą šioje kategorijoje?',
                'answer' => 'Filtruokite pagal nuolaidos dydį, skaičiuokite kainą už litrą, tikrinkite pirkimo sąlygas (pvz. ar akcija galioja perkant kelis vienetus ar su lojalumo kortele) ir palyginkite kelias parduotuves.',
            ],
        ];
    }

    private function nonAlcoholicDescription(): string
    {
        return <<<'HTML'
<div class="category-description-block p-0 lg:p-4">
  <h2 class="text-3xl font-bold mb-6 leading-tight">Nealkoholinių gėrimų akcijos: atraskite naujausius pasiūlymus</h2>

  <p class="mb-4 text-gray-700">Pildote šaldytuvą gaiviesiems gėrimams ar ieškote pigesnio varianto kasdienai? Kategorija Nealkoholiniai gėrimai apima viską nuo klasikinių gazuotų gėrimų iki nealkoholinių alaus ir vyno alternatyvų, energinių gėrimų, sulčių bei giros — pavyzdžiui, dažnai rasite patrauklius pasiūlymus tokiems gėrimams kaip Pepsi, Coca-Cola ar Red Bull, taip pat nealkoholiniams radleriams.</p>

  <p class="mb-4 text-gray-700">Šioje kategorijoje rasite dešimtis aktualių pasiūlymų: tiek atskirų butelių, tiek didesnių pakuočių akcijos. Nuolaidos dažniausiai svyruoja apie 30–40 %, o ypač stiprūs pasiūlymai dažnai būna gazuotiems ir energiniams gėrimams.</p>

  <p class="mb-6 text-gray-700">Skirtingi pardavėjai išsiskiria savo asortimentu — didesniems tinklams dažnai verta palyginti kelis variantus prieš perkant. Jei ieškote alkoholinių gėrimų, žr. <a href="/akcijos/alkoholiniai-gerimai">Alkoholiniai gėrimai</a>.</p>
</div>
HTML;
    }

    private function nonAlcoholicFaq(): array
    {
        return [
            [
                'question' => 'Kokius gėrimus dažniausiai rasite su nuolaida šioje kategorijoje?',
                'answer' => 'Dažniau akcijuojami gazuoti ir negazuoti gaivieji gėrimai, limonados, energetiniai gėrimai, sultys, gira, taip pat nealkoholiniai alaus, vyno ir sidro alternatyvos.',
            ],
            [
                'question' => 'Kokio dydžio nuolaidos šioje kategorijoje būna dažniausiai?',
                'answer' => 'Nuolaidos svyruoja nuo nedidelių iki reikšmingų — yra daug mažesnių akcijų, bet kartais pasitaiko ir vidutinių arba didesnių procentinių nuolaidų, ypač populiariems arba sezoniniams produktams.',
            ],
            [
                'question' => 'Kuriose parduotuvėse verta tikrinti gaiviųjų gėrimų akcijas?',
                'answer' => 'Rekomenduojama palyginti didžiųjų tinklų ir regioninių parduotuvių pasiūlymus — dažnai didesni tinklai bei kai kurios vietinės parduotuvės skelbia daug įvairių gėrimų nuolaidų.',
            ],
            [
                'question' => 'Ar gėrimų nuolaidos priklauso nuo sezono?',
                'answer' => 'Taip — vasarą dažniau matyti akcijų gaivesniems ir gazuotiems gėrimams, o žiemą — karštiems gėrimams ir sultims.',
            ],
            [
                'question' => 'Kaip rasti geriausią pasiūlymą šioje kategorijoje?',
                'answer' => 'Filtruokite pagal nuolaidos dydį, skaičiuokite kainą už litrą ir palyginkite kelias parduotuves.',
            ],
        ];
    }
};
