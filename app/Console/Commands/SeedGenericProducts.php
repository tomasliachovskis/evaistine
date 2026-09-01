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

        // Found via product-name frequency analysis of everything still
        // missing a generic_product_id after generic-products:match — see
        // the session's gap-analysis writeup for full method and counts.
        // 'namu-ukio-ir-laisvalaikio-prekes' stays out of the
        // EXCLUDED_CATEGORY_SLUGS keyword_page loop above (still too
        // heterogeneous as a whole for that), but these are its biggest,
        // unambiguous, non-brand clusters — safe to carve out by hand
        // without touching the blanket exclusion.
        ['slug' => 'knygos', 'name' => 'Knygos', 'emoji' => '📚', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['knyga', 'knygos']],
        ['slug' => 'moteriskos-pedkelnes', 'name' => 'Moteriškos pėdkelnės', 'emoji' => '🧦', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['moteriškos pėdkelnės', 'pėdkelnės']],
        ['slug' => 'sasiuviniai', 'name' => 'Sąsiuviniai', 'emoji' => '📓', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['sąsiuvinis', 'sąsiuviniai']],
        ['slug' => 'zvakes', 'name' => 'Žvakės', 'emoji' => '🕯️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['žvakė', 'žvakės']],
        ['slug' => 'lekstes', 'name' => 'Lėkštės', 'emoji' => '🍽️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['lėkštė', 'lėkštės']],
        ['slug' => 'keptuves', 'name' => 'Keptuvės', 'emoji' => '🍳', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['keptuvė', 'keptuvės']],
        ['slug' => 'puodeliai', 'name' => 'Puodeliai', 'emoji' => '☕', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['puodelis', 'puodeliai']],
        ['slug' => 'patalynes-komplektai', 'name' => 'Patalynės komplektai', 'emoji' => '🛏️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['patalynės komplektas', 'patalynės komplektai']],
        ['slug' => 'led-lemputes', 'name' => 'LED lemputės', 'emoji' => '💡', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['led lemputė', 'led lemputes', 'led lemputės']],

        // Vetted against the existing 178 slugs before adding — skipped
        // several near-duplicates of already-seeded generics (e.g.
        // tualetinis-popierius, dantu-sepeteliai, traskuciai,
        // saldyti-zuvies-pirsteliai, kudikiu-koses already exist; riesutai/
        // prieskoniai/silke/lasisa already act as an umbrella over the more
        // specific term that would've been proposed here).
        ['slug' => 'veido-kauke', 'name' => 'Veido kaukė', 'emoji' => '🧖', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'lupu-dazai', 'name' => 'Lūpų dažai', 'emoji' => '💄', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'plauku-kauke', 'name' => 'Plaukų kaukė', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'higieniniai-paketai', 'name' => 'Higieniniai paketai', 'emoji' => '🩸', 'category' => 'kosmetika-ir-higiena', 'terms' => ['higieniniai paketai', 'hig paketai']],
        ['slug' => 'gaivusis-gerimas', 'name' => 'Gaivusis gėrimas', 'emoji' => '🥤', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'energinis-gerimas', 'name' => 'Energinis gėrimas', 'emoji' => '⚡', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'sausi-pusryciai', 'name' => 'Sausi pusryčiai', 'emoji' => '🥣', 'category' => 'bakaleja'],
        ['slug' => 'lego-konstruktorius', 'name' => 'LEGO konstruktorius', 'emoji' => '🧱', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'stalo-zaidimai', 'name' => 'Stalo žaidimai', 'emoji' => '🎲', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'namu-kvapas-lazdelemis', 'name' => 'Namų kvapas (lazdelėmis)', 'emoji' => '🌸', 'category' => 'buitine-chemija-valymo-priemones', 'terms' => ['namų kvapas', 'kvapas lazdelėmis']],
        ['slug' => 'saldainiu-rinkinys', 'name' => 'Saldainių rinkinys', 'emoji' => '🍬', 'category' => 'saldumynai-ir-uzkandziai'],
        ['slug' => 'kramtomoji-guma', 'name' => 'Kramtomoji guma', 'emoji' => '🍬', 'category' => 'saldumynai-ir-uzkandziai'],
        ['slug' => 'plombyras', 'name' => 'Plombyras', 'emoji' => '🍦', 'category' => 'saldytas-maistas-ir-ledai'],

        // Round 2 — same frequency-analysis method, re-run against the
        // residual after round 1's 19 additions. Matching tries longer
        // (more specific) search-term sequences before shorter ones (see
        // MatchGenericProducts::termSets sortByDesc), so a 2-word generic
        // like "silkių filė" added alongside the existing single-word
        // "silke"/"lasisa" umbrella is picked first for products that
        // actually say "filė" — no risk of the new entry losing to the
        // older, broader one.
        ['slug' => 'dusas-gelis', 'name' => 'Dušo gelis', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena', 'terms' => ['dušo gelis']],
        ['slug' => 'blakstienu-tusas', 'name' => 'Blakstienų tušas', 'emoji' => '💄', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'ranku-kremas', 'name' => 'Rankų kremas', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'nagu-lakas', 'name' => 'Nagų lakas', 'emoji' => '💅', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'tualetinis-vanduo', 'name' => 'Tualetinis vanduo', 'emoji' => '🌸', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'plauku-lakas', 'name' => 'Plaukų lakas', 'emoji' => '💇', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'burnos-skalavimo-skystis', 'name' => 'Burnos skalavimo skystis', 'emoji' => '🦷', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'prieskoniu-misinys', 'name' => 'Prieskonių mišinys', 'emoji' => '🧂', 'category' => 'bakaleja'],
        ['slug' => 'marinuoti-agurkai', 'name' => 'Marinuoti agurkai', 'emoji' => '🥒', 'category' => 'bakaleja'],
        ['slug' => 'riesutu-kremas', 'name' => 'Riešutų kremas', 'emoji' => '🥜', 'category' => 'bakaleja'],
        ['slug' => 'sulciu-gerimas', 'name' => 'Sulčių gėrimas', 'emoji' => '🧃', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'nealkoholinis-alus', 'name' => 'Nealkoholinis alus', 'emoji' => '🍺', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'pomidoru-padazas', 'name' => 'Pomidorų padažas', 'emoji' => '🍅', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'silkiu-file', 'name' => 'Silkių filė', 'emoji' => '🐟', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'lasisos-file', 'name' => 'Lašišos filė', 'emoji' => '🐟', 'category' => 'mesa-ir-zuvis', 'terms' => ['lašišos filė', 'lašišų filė']],
        ['slug' => 'saslykas', 'name' => 'Šašlykas', 'emoji' => '🍢', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'paklodes', 'name' => 'Paklodės', 'emoji' => '🛏️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'kepimo-formos', 'name' => 'Kepimo formos', 'emoji' => '🍰', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'spalvoti-piestukai', 'name' => 'Spalvoti pieštukai', 'emoji' => '🖍️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'biriu-produktu-indai', 'name' => 'Birių produktų indai', 'emoji' => '🫙', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'led-lempos', 'name' => 'LED lempos', 'emoji' => '💡', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'peiliai', 'name' => 'Peiliai', 'emoji' => '🔪', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'seklos', 'name' => 'Sėklos', 'emoji' => '🌱', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'karsto-oro-gruzdintuves', 'name' => 'Karšto oro gruzdintuvės', 'emoji' => '🍟', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'gimtadienio-zvakutes', 'name' => 'Gimtadienio žvakutės', 'emoji' => '🎂', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],

        // Round 3 — two categories of finding here: brand-new product-type
        // clusters (as in rounds 1-2), plus a distinct "silent gap" kind:
        // an existing generic's search_terms use one Lithuanian synonym
        // (e.g. "maistas") while real product names commonly use another
        // ("ėdalas") — same product, different word, so the existing entry
        // never matches them. Since matching is exact-word (no stemming/
        // synonym handling — see MatchGenericProducts::wordsOf), the fix is
        // a new generic_products row with the missing phrasing rather than
        // editing the existing one, same "silke"/"lasisa" umbrella pattern
        // already relied on in round 2.
        ['slug' => 'sunu-edalas', 'name' => 'Šunų ėdalas', 'emoji' => '🐶', 'category' => 'gyvunu-prekes', 'terms' => ['šunų ėdalas', 'ėdalas šunims', 'ėdalas šunų']],
        ['slug' => 'kaciu-edalas', 'name' => 'Kačių ėdalas', 'emoji' => '🐱', 'category' => 'gyvunu-prekes', 'terms' => ['kačių ėdalas', 'ėdalas katėms', 'ėdalas kačių']],
        ['slug' => 'skutimosi-putos', 'name' => 'Skutimosi putos', 'emoji' => '🪒', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'micelinis-vanduo', 'name' => 'Micelinis vanduo', 'emoji' => '💧', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'makiazo-pagrindas', 'name' => 'Makiažo pagrindas', 'emoji' => '💄', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'paakiu-kremas', 'name' => 'Paakių kremas', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'veido-prausiklis', 'name' => 'Veido prausiklis', 'emoji' => '🧼', 'category' => 'kosmetika-ir-higiena'],
        // gazuoti-gerimai (round-0 keyword_page generic) apparently doesn't
        // match here — likely scoped to a different root category — so
        // gerimai-kava-arbata's own "gazuotas gėrimas" products (154+)
        // never matched anything. New slug, not an edit of the existing one.
        ['slug' => 'gazuotas-gerimas-gk', 'name' => 'Gazuotas gėrimas', 'emoji' => '🥤', 'category' => 'gerimai-kava-arbata', 'terms' => ['gazuotas gėrimas', 'gazuotas gaivusis gėrimas']],
        ['slug' => 'izotoninis-gerimas', 'name' => 'Izotoninis gėrimas', 'emoji' => '🏃', 'category' => 'gerimai-kava-arbata'],
        ['slug' => 'kojines', 'name' => 'Kojinės', 'emoji' => '🧦', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'kuprines', 'name' => 'Kuprinės', 'emoji' => '🎒', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'dubeneliai', 'name' => 'Dubenėliai', 'emoji' => '🥣', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'puodai', 'name' => 'Puodai', 'emoji' => '🍲', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'elektriniai-virduliai', 'name' => 'Elektriniai virduliai', 'emoji' => '☕', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'vazonai', 'name' => 'Vazonai', 'emoji' => '🪴', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'rasikliai', 'name' => 'Rašikliai', 'emoji' => '🖊️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],

        // Round 4 — returns are visibly diminishing now (round 1: +3,828,
        // round 2: +1,267, round 3: +629), consistent with having already
        // taken the largest/clearest clusters. Includes 'terms' with the
        // exact grammatical case seen in real product names (e.g. genitive
        // plural "lašišų" vs the dictionary-form "lašišos" used above) —
        // matching is exact-word, not stemmed, so a declension mismatch
        // alone silently loses real matches (found via lasisos-file above
        // still showing 57 unmatched "lašišų filė" products next round).
        ['slug' => 'karstai-rukyta-mesa', 'name' => 'Karštai rūkyta mėsa', 'emoji' => '🥓', 'category' => 'mesa-ir-zuvis', 'terms' => ['karštai rūkytas', 'karštai rūkyta', 'karštai rūkyti', 'karštai rūkytos']],
        ['slug' => 'visciuku-sparneliai', 'name' => 'Viščiukų sparneliai', 'emoji' => '🍗', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'valymo-sluostes', 'name' => 'Valymo šluostės', 'emoji' => '🧽', 'category' => 'buitine-chemija-valymo-priemones', 'terms' => ['valymo šluostės', 'šluostės']],
        ['slug' => 'indu-kempines', 'name' => 'Indų kempinės', 'emoji' => '🧽', 'category' => 'buitine-chemija-valymo-priemones'],
        ['slug' => 'vistienos-sultinys', 'name' => 'Vištienos sultinys', 'emoji' => '🍲', 'category' => 'bakaleja'],
        ['slug' => 'skustuvo-galvutes', 'name' => 'Skustuvo galvutės', 'emoji' => '🪒', 'category' => 'kosmetika-ir-higiena'],

        // Round 5 — biggest find: moteriškos-pedkelnes (round 1) only
        // covered this product under namu-ukio-ir-laisvalaikio-prekes, but
        // the exact same product type is independently sold under
        // kosmetika-ir-higiena too (different stores categorize it
        // differently) — matching is category-scoped, so the round-1 entry
        // never touched this second pool at all. "pėdk" is also a real
        // in-product abbreviation for pėdkelnės, not just the dictionary
        // form.
        ['slug' => 'pedkelnes-kosmetika', 'name' => 'Moteriškos pėdkelnės', 'emoji' => '🧦', 'category' => 'kosmetika-ir-higiena', 'terms' => ['pėdkelnės', 'pėdk', 'moteriškos pėdkelnės']],
        ['slug' => 'zemes-riesutai', 'name' => 'Žemės riešutai', 'emoji' => '🥜', 'category' => 'saldumynai-ir-uzkandziai'],
        ['slug' => 'viscius-blauzdeles', 'name' => 'Viščiukų blauzdelės', 'emoji' => '🍗', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'pastetas', 'name' => 'Paštetas', 'emoji' => '🥫', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'krabu-lazdeles', 'name' => 'Krabų lazdelės', 'emoji' => '🦀', 'category' => 'mesa-ir-zuvis'],
        ['slug' => 'kuno-sveitiklis', 'name' => 'Kūno šveitiklis', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],

        // Round 6 — GPT-assisted (gpt-5-mini via the existing OpenAI
        // integration, same pattern as CategoryMappingService), not pure
        // frequency counting: sampled random batches of still-ungrouped
        // product names per category and asked for generic-product
        // candidates. Useful for surfacing the "long tail" of real,
        // distinct product types that never individually cracked the
        // top-20/30 word-frequency list (each is common enough to matter,
        // just not dominant on its own) — brand names and umbrella terms
        // in the raw suggestions were stripped/rewritten by hand before
        // use, same quality bar as every round above.
        ['slug' => 'hermetiski-indeliai', 'name' => 'Hermetiški indeliai', 'emoji' => '🫙', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['hermetiškas indelis', 'hermetiški indeliai', 'indelis maistui']],
        ['slug' => 'daiktadezes', 'name' => 'Daiktadėžės', 'emoji' => '📦', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'baterijos-elementai', 'name' => 'Baterijos ir elementai', 'emoji' => '🔋', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['baterijos', 'elementai aaa', 'elementai aa']],
        ['slug' => 'tekstiliniai-ranksluosciai', 'name' => 'Tekstiliniai rankšluosčiai', 'emoji' => '🧻', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['rankšluostis', 'kilpinis rankšluostis']],
        ['slug' => 'sulankstomos-kedes', 'name' => 'Sulankstomos kėdės', 'emoji' => '🪑', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'skeciai', 'name' => 'Skėčiai', 'emoji' => '☂️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'plauku-dziovintuvai', 'name' => 'Plaukų džiovintuvai', 'emoji' => '💨', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'kirpimo-masineles', 'name' => 'Kirpimo mašinėlės', 'emoji' => '💈', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'ikrovikliai', 'name' => 'Įkrovikliai', 'emoji' => '🔌', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['įkroviklis', 'įkrovikliai', 'kroviklis']],
        ['slug' => 'klijai', 'name' => 'Klijai', 'emoji' => '🧴', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'klijai-pistoletai', 'name' => 'Klijų pistoletai', 'emoji' => '🔫', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'kepimo-maiseliai', 'name' => 'Kepimo maišeliai', 'emoji' => '🍗', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'stiklines', 'name' => 'Stiklinės', 'emoji' => '🥃', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'gertuves', 'name' => 'Gertuvės', 'emoji' => '🍶', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'vandens-filtrai', 'name' => 'Vandens filtrai', 'emoji' => '💧', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'irankiu-dezes', 'name' => 'Įrankių dėžės', 'emoji' => '🧰', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['įrankių dėžė', 'įrankių krepšys']],
        ['slug' => 'kibirai', 'name' => 'Kibirai', 'emoji' => '🪣', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'padekliukai', 'name' => 'Padėkliukai', 'emoji' => '🧺', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'pledai', 'name' => 'Pledai', 'emoji' => '🧣', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'serviravimo-indai', 'name' => 'Serviravimo indai', 'emoji' => '🍽️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'vienkartines-nosinaites', 'name' => 'Vienkartinės nosinaitės', 'emoji' => '🤧', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'spalvotas-popierius', 'name' => 'Spalvotas popierius', 'emoji' => '🎨', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'flomasteriai', 'name' => 'Flomasteriai', 'emoji' => '🖍️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'pasteles', 'name' => 'Pastelės', 'emoji' => '🖍️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'israsiniai-akumuliatoriai', 'name' => 'Išoriniai akumuliatoriai', 'emoji' => '🔋', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['išorinis akumuliatorius', 'išoriniai akumuliatoriai']],
        ['slug' => 'rankiniai-zibintuvieliai', 'name' => 'Rankiniai žibintuvėliai', 'emoji' => '🔦', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['žibintuvėlis', 'rankinis žibintuvėlis']],
        ['slug' => 'belaides-peles', 'name' => 'Belaidės pelės', 'emoji' => '🖱️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes', 'terms' => ['belaidė pelė', 'belaidė optinė pelė']],
        ['slug' => 'dumu-detektoriai', 'name' => 'Dūmų detektoriai', 'emoji' => '🚨', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'rankiniai-maisytuvai', 'name' => 'Rankiniai maišytuvai', 'emoji' => '🍽️', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'staliniai-sviestuvai', 'name' => 'Staliniai šviestuvai', 'emoji' => '💡', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'garu-valytuvai', 'name' => 'Garų valytuvai', 'emoji' => '💨', 'category' => 'namu-ukio-ir-laisvalaikio-prekes'],
        ['slug' => 'tarpdanciu-siulas', 'name' => 'Tarpdančių siūlas', 'emoji' => '🦷', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'el-dantu-sepetelio-galvutes', 'name' => 'Elektrinio dantų šepetėlio galvutės', 'emoji' => '🦷', 'category' => 'kosmetika-ir-higiena', 'terms' => ['dantų šepetėlio galvutės', 'el. dantų šep. galv.']],
        // Refines the existing 'dezodorantas' generic (from the original
        // 178) with the abbreviated application-form phrasing real product
        // names use — same slug, so this updates it in place rather than
        // creating a duplicate/competing entry.
        ['slug' => 'dezodorantas', 'name' => 'Dezodorantas', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena', 'terms' => ['dezodorantas', 'pieštukinis dezodorantas', 'purškiamas dezodorantas', 'rutulinis dezodorantas', 'piešt dezodor', 'puršk dezodor', 'rutul dezodor']],

        // Round 6b — GPT batches for bakaleja/kosmetika/vaiku-ir-kudikiu-
        // prekes. Cross-checked every suggested name against the current
        // 253 slugs before adding: several (sviestas, vafliai, guminukai,
        // ledai, kakava, arbata, dešrelės, dešra, sausainiai) already exist
        // but scoped to a DIFFERENT root category — same real product
        // cross-listed under bakaleja too (matching is category-scoped, see
        // the pėdkelnės/silkių filė finding in round 5) — so these get a
        // new "-bak" slug rather than being skipped as duplicates. Vague/
        // umbrella suggestions (Užkandis, Kūrybinis rinkinys, Interaktyvus
        // žaislas, Tepamieji kremai) were dropped.
        ['slug' => 'sviestas-bak', 'name' => 'Sviestas', 'emoji' => '🧈', 'category' => 'bakaleja', 'terms' => ['sviestas']],
        ['slug' => 'vafliai-bak', 'name' => 'Vafliai', 'emoji' => '🧇', 'category' => 'bakaleja', 'terms' => ['vafliai']],
        ['slug' => 'guminukai-bak', 'name' => 'Guminukai', 'emoji' => '🍬', 'category' => 'bakaleja', 'terms' => ['guminukai']],
        ['slug' => 'kakava-bak', 'name' => 'Tirpioji kakava', 'emoji' => '🍫', 'category' => 'bakaleja', 'terms' => ['tirpioji kakava', 'kakava']],
        ['slug' => 'arbata-bak', 'name' => 'Arbata', 'emoji' => '🍵', 'category' => 'bakaleja', 'terms' => ['žalioji arbata', 'juodoji arbata', 'arbata']],
        ['slug' => 'desreles-bak', 'name' => 'Dešrelės', 'emoji' => '🌭', 'category' => 'bakaleja', 'terms' => ['dešrelės']],
        ['slug' => 'sausainiai-bak', 'name' => 'Sausainiai', 'emoji' => '🍪', 'category' => 'bakaleja', 'terms' => ['sausainiai']],
        ['slug' => 'dribsniai', 'name' => 'Dribsniai', 'emoji' => '🥣', 'category' => 'bakaleja'],
        ['slug' => 'granola', 'name' => 'Granola', 'emoji' => '🥣', 'category' => 'bakaleja'],
        ['slug' => 'krekeriai', 'name' => 'Krekeriai', 'emoji' => '🍘', 'category' => 'bakaleja'],
        ['slug' => 'pastiles', 'name' => 'Pastilės', 'emoji' => '🍬', 'category' => 'bakaleja'],
        ['slug' => 'kukuruzu-traskuciai', 'name' => 'Kukurūzų traškučiai', 'emoji' => '🌽', 'category' => 'bakaleja'],
        ['slug' => 'baltyminis-batonelis', 'name' => 'Baltyminis batonėlis', 'emoji' => '🍫', 'category' => 'bakaleja'],
        ['slug' => 'marinuoti-burokeliai', 'name' => 'Marinuoti burokėliai', 'emoji' => '🫐', 'category' => 'bakaleja'],
        ['slug' => 'alyvuoges', 'name' => 'Alyvuogės', 'emoji' => '🫒', 'category' => 'bakaleja', 'terms' => ['žaliosios alyvuogės', 'juodosios alyvuogės', 'alyvuogės']],

        ['slug' => 'plauku-serumas', 'name' => 'Plaukų serumas', 'emoji' => '💧', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'plauku-putos', 'name' => 'Plaukų putos', 'emoji' => '🧴', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'plauku-formavimo-priemone', 'name' => 'Plaukų formavimo priemonė', 'emoji' => '💇', 'category' => 'kosmetika-ir-higiena', 'terms' => ['plaukų formavimo gelis', 'plaukų modeliavimo pasta', 'plaukų purškiklis', 'plaukų pudra']],
        ['slug' => 'plauku-sepeciai-sukos', 'name' => 'Plaukų šepečiai ir šukos', 'emoji' => '💇', 'category' => 'kosmetika-ir-higiena', 'terms' => ['plaukų šepetys', 'plaukų šukos']],
        ['slug' => 'plauku-gumeles', 'name' => 'Plaukų gumelės', 'emoji' => '💇', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'veido-tonikas', 'name' => 'Veido tonikas', 'emoji' => '💧', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'akiu-makiazo-valiklis', 'name' => 'Akių makiažo valiklis', 'emoji' => '💧', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'lupu-balzamas', 'name' => 'Lūpų balzamas', 'emoji' => '💄', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'lupu-blizgis', 'name' => 'Lūpų blizgis', 'emoji' => '💄', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'nagu-lako-valiklis', 'name' => 'Nagų lako valiklis', 'emoji' => '💅', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'nagu-zirkles', 'name' => 'Nagų žirklės', 'emoji' => '✂️', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'pleistrai', 'name' => 'Pleistrai', 'emoji' => '🩹', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'vatos-diskeliai', 'name' => 'Vatos diskeliai', 'emoji' => '⚪', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'prezervatyvai', 'name' => 'Prezervatyvai', 'emoji' => '📦', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'kojines-kosmetika', 'name' => 'Kojinės', 'emoji' => '🧦', 'category' => 'kosmetika-ir-higiena', 'terms' => ['kojinės']],
        ['slug' => 'losjonas-po-skutimosi', 'name' => 'Losjonas po skutimosi', 'emoji' => '🪒', 'category' => 'kosmetika-ir-higiena'],
        ['slug' => 'gelis-po-saules', 'name' => 'Gelis po saulės', 'emoji' => '☀️', 'category' => 'kosmetika-ir-higiena'],

        ['slug' => 'dregnosios-servetieles-kudikiams', 'name' => 'Drėgnosios servetėlės kūdikiams', 'emoji' => '🧻', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['drėgnosios servetėlės']],
        ['slug' => 'buteliukas', 'name' => 'Buteliukai', 'emoji' => '🍼', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['buteliukas']],
        ['slug' => 'zindukas', 'name' => 'Žindukai', 'emoji' => '🍼', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['žindukas']],
        ['slug' => 'vaikiskas-sampunas', 'name' => 'Vaikiškas šampūnas', 'emoji' => '🧴', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'kudikiu-prausiklis', 'name' => 'Kūdikių prausiklis', 'emoji' => '🧼', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'kremas-kudikiams', 'name' => 'Kremas kūdikiams', 'emoji' => '🧴', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'puodelis-vaikams', 'name' => 'Puodeliai vaikams', 'emoji' => '🥤', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['puodelis']],
        ['slug' => 'gertuve-vaikams', 'name' => 'Gertuvės vaikams', 'emoji' => '🍼', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['gertuvė']],
        ['slug' => 'dubenelis-vaikams', 'name' => 'Dubenėliai vaikams', 'emoji' => '🥣', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['dubenėlis']],
        ['slug' => 'pliusinis-zaislas', 'name' => 'Pliušiniai žaislai', 'emoji' => '🧸', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['pliušinis žaislas']],
        ['slug' => 'figurele', 'name' => 'Figūrėlės', 'emoji' => '🧸', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['figūrėlė']],
        ['slug' => 'delione', 'name' => 'Dėlionės', 'emoji' => '🧩', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['dėlionė']],
        ['slug' => 'plastilinas-modelinas', 'name' => 'Plastilinas ir modelinas', 'emoji' => '🎨', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['plastilinas', 'modelinas']],
        ['slug' => 'maudymukas', 'name' => 'Maudymukai', 'emoji' => '👙', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['maudymukas']],
        ['slug' => 'kojines-vaikams', 'name' => 'Kojinės vaikams', 'emoji' => '🧦', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['kojinės']],
        ['slug' => 'muilo-burbulai', 'name' => 'Muilo burbulai', 'emoji' => '🫧', 'category' => 'vaiku-ir-kudikiu-prekes'],
        ['slug' => 'zaislinis-automodelis', 'name' => 'Žaisliniai automodeliai', 'emoji' => '🚗', 'category' => 'vaiku-ir-kudikiu-prekes', 'terms' => ['žaislinis automodelis']],
        ['slug' => 'pervystymo-paklotai', 'name' => 'Vienkartiniai pervystymo paklotai', 'emoji' => '👶', 'category' => 'vaiku-ir-kudikiu-prekes'],
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
