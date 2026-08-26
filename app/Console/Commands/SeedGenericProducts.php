<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\GenericProduct;
use App\Models\KeywordPage;
use Illuminate\Console\Command;

class SeedGenericProducts extends Command
{
    protected $signature = 'generic-products:seed';

    protected $description = 'Seed generic_products from published, category-scoped keyword_pages plus a small manually curated gap list';

    /**
     * Keyword pages that are brand names, not generic products.
     */
    private const BRAND_SLUGS = [
        'actimel', 'akumuliatoriams', 'aptamil', 'dallmayr', 'dolce-gusto',
        'ferrero-rocher', 'friskies', 'gentle-day', 'gourmet-gold', 'lego',
        'lavazza', 'milka', 'monster', 'nutella', 'paulig', 'pedigree',
        'pepsi', 'cola', 'pringles', 'redbull', 'ringuva-skalbiklis',
        'santa-maria', 'sheba', 'vytautas-mineralinis-vanduo', 'whiskas',
        'duona-morta', 'dziugo-suris',
    ];

    /**
     * Root categories that don't map to grocery-basket-style generic products.
     */
    private const EXCLUDED_CATEGORY_SLUGS = [
        'namu-ukio-ir-laisvalaikio-prekes',
        'augalai-geles',
    ];

    /**
     * Keyword pages that are either an exact duplicate of another generic
     * product (same product, different grammatical case or a specific
     * product-line name) or an overly broad "umbrella" term that overlaps
     * 2+ more specific siblings in the same category and would arbitrarily
     * steal their matches — found via pairwise search_terms overlap check.
     */
    private const DUPLICATE_OR_UMBRELLA_SLUGS = [
        'skrostas-karpis', // duplicate of 'karpis'
        'giminiu-desreles', // specific product line, duplicate of 'desreles'
        'zaislams', // same as 'zaislai', different grammatical case
        'skalbimo-priemones', // umbrella over skalbiklis/skalbimo-kapsules/skalbimo-milteliai
        'valikliai', // umbrella over indu-ploviklis/grindu-valiklis/tualeto-valiklis
        'uzkandziai', // umbrella over traskuciai/sausainiai/saldainiai
        'burnos-higiena', // umbrella over dantu-pasta/dantu-sepeteliai
    ];

    /**
     * Keyword pages with no category_slugs recorded — mapped by hand.
     */
    private const CATEGORY_OVERRIDES = [
        'kiausiniai' => 'pieno-produktai-ir-kiausiniai',
    ];

    /**
     * Staples confirmed missing from keyword_pages via product-name frequency
     * analysis but not yet run through the Ahrefs volume pipeline — flagged
     * as 'manual' so they get revisited on the next Ahrefs export.
     */
    private const MANUAL_ADDITIONS = [
        ['slug' => 'minkstiklis', 'name' => 'Minkštiklis', 'emoji' => '🧴', 'category' => 'buitine-chemija-valymo-priemones'],
        ['slug' => 'oro-gaiviklis', 'name' => 'Oro gaiviklis', 'emoji' => '🌸', 'category' => 'buitine-chemija-valymo-priemones'],
        ['slug' => 'arbuzai', 'name' => 'Arbūzai', 'emoji' => '🍉', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'persikai', 'name' => 'Persikai', 'emoji' => '🍑', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'kriauses', 'name' => 'Kriaušės', 'emoji' => '🍐', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'pievagrybiai', 'name' => 'Pievagrybiai', 'emoji' => '🍄', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'burokeliai', 'name' => 'Burokėliai', 'emoji' => '🍠', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'kudikiu-pieno-misinys', 'name' => 'Kūdikių pieno mišinys', 'emoji' => '🍼', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['pieno mišinys', 'pieno mišiniui', 'tolesnio maitinimo mišinys', 'kūdikių pieno mišinys']],
        ['slug' => 'plauku-kondicionierius', 'name' => 'Plaukų kondicionierius', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'degtine', 'name' => 'Degtinė', 'emoji' => '🍸', 'category' => 'alkoholiniai-ir-nealkoholiniai-gerimai'],
        ['slug' => 'konjakas', 'name' => 'Konjakas', 'emoji' => '🥃', 'category' => 'alkoholiniai-ir-nealkoholiniai-gerimai'],
        ['slug' => 'viskis', 'name' => 'Viskis', 'emoji' => '🥃', 'category' => 'alkoholiniai-ir-nealkoholiniai-gerimai'],

        // Found by diffing against nuolaidos.lt's own /produktai grouping (335 names).
        ['slug' => 'tunas', 'name' => 'Tunas', 'emoji' => '🐟', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'kalakutiena', 'name' => 'Kalakutiena', 'emoji' => '🦃', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'kukuruzai', 'name' => 'Kukurūzai', 'emoji' => '🌽', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'zirneliai', 'name' => 'Žirneliai', 'emoji' => '🟢', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'braskes', 'name' => 'Braškės', 'emoji' => '🍓', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'slyvos', 'name' => 'Slyvos', 'emoji' => '🍑', 'category' => 'vaisiai-ir-darzoves'],
        ['slug' => 'pupeles', 'name' => 'Pupelės', 'emoji' => '🫘', 'category' => 'bakaleja'],
        ['slug' => 'lesiai', 'name' => 'Lęšiai', 'emoji' => '🫘', 'category' => 'bakaleja'],
        ['slug' => 'avinzirniai', 'name' => 'Avinžirniai', 'emoji' => '🫘', 'category' => 'bakaleja'],
        ['slug' => 'margarinas', 'name' => 'Margarinas', 'emoji' => '🧈', 'category' => 'pieno-produktai-ir-kiausiniai'],
        ['slug' => 'kakava', 'name' => 'Kakava', 'emoji' => '🍫', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'sardines', 'name' => 'Sardinės', 'emoji' => '🐟', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'miuslis', 'name' => 'Miuslis', 'emoji' => '🥣', 'category' => 'bakaleja', 'terms' => ['miuslis', 'musli', 'javainis']],
    ];

    public function handle(): int
    {
        $categoriesBySlug = Category::whereNull('parent_id')->get()->keyBy('slug');

        $fromKeywordPages = 0;
        $skippedNoCategory = 0;

        KeywordPage::where('is_published', true)->chunk(50, function ($pages) use ($categoriesBySlug, &$fromKeywordPages, &$skippedNoCategory) {
            foreach ($pages as $page) {
                if (in_array($page->slug, self::BRAND_SLUGS, true)
                    || in_array($page->slug, self::DUPLICATE_OR_UMBRELLA_SLUGS, true)) {
                    continue;
                }

                $categorySlug = self::CATEGORY_OVERRIDES[$page->slug]
                    ?? ($page->category_slugs[0] ?? null);

                if ($categorySlug === null) {
                    $this->warn("Skipping '{$page->slug}': no category_slugs and no override");
                    $skippedNoCategory++;
                    continue;
                }

                if (in_array($categorySlug, self::EXCLUDED_CATEGORY_SLUGS, true)) {
                    continue;
                }

                $category = $categoriesBySlug->get($categorySlug);
                if (!$category) {
                    $this->warn("Skipping '{$page->slug}': unknown category slug '{$categorySlug}'");
                    $skippedNoCategory++;
                    continue;
                }

                GenericProduct::updateOrCreate(
                    ['slug' => $page->slug],
                    [
                        'name' => $page->title,
                        'emoji' => $page->emoji,
                        'category_id' => $category->id,
                        'keyword_page_id' => $page->id,
                        'source' => 'keyword_page',
                    ]
                );
                $fromKeywordPages++;
            }
        });

        $manualCount = 0;
        foreach (self::MANUAL_ADDITIONS as $item) {
            $category = $categoriesBySlug->get($item['category']);
            if (!$category) {
                $this->warn("Skipping manual '{$item['slug']}': unknown category '{$item['category']}'");
                continue;
            }

            GenericProduct::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'emoji' => $item['emoji'],
                    'search_terms' => $item['terms'] ?? null,
                    'category_id' => $category->id,
                    'keyword_page_id' => null,
                    'source' => 'manual',
                ]
            );
            $manualCount++;
        }

        $this->info("Seeded {$fromKeywordPages} generic products from keyword_pages, {$manualCount} manual additions, skipped {$skippedNoCategory}.");
        $this->info('Total generic_products: ' . GenericProduct::count());

        return self::SUCCESS;
    }
}
