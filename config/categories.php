<?php

// Everything category-specific in one place: the 12 root categories, their
// hand-checked Lithuanian case forms, and which ones count as "popular".
// Products and category mappers only ever point at these roots. Categories
// are shown without icons (owner's decision, 2026-10-07).
return [
    // Display order (home strip, store page carousels). The seed migration
    // keeps its own copy of this list; add a new root in both places.
    'roots' => [
        'Nereceptiniai vaistai' => 'nereceptiniai-vaistai',
        'Vitaminai ir maisto papildai' => 'vitaminai-ir-maisto-papildai',
        'Veido priežiūra' => 'veido-prieziura',
        'Kūno priežiūra ir apsauga nuo saulės' => 'kuno-prieziura-ir-apsauga-nuo-saules',
        'Plaukų priežiūra' => 'plauku-prieziura',
        'Dekoratyvinė kosmetika ir kvepalai' => 'dekoratyvine-kosmetika-ir-kvepalai',
        'Higiena' => 'higiena',
        'Mamai ir vaikui' => 'mamai-ir-vaikui',
        'Medicinos prekės ir prietaisai' => 'medicinos-prekes-ir-prietaisai',
        'Ortopedija ir kompresinės prekės' => 'ortopedija-ir-kompresines-prekes',
        'Akių priežiūra ir optika' => 'akiu-prieziura-ir-optika',
        'Sportas, svorio kontrolė, arbatos ir spec. maistas' => 'sportas-svorio-kontrole-arbatos-ir-spec-maistas',
    ],

    // Shorter names where the full one blows a meta description's length
    // budget next to a store name. Unlisted names are already short, or get
    // cut at their first comma.
    'short_labels' => [
        'Vitaminai ir maisto papildai' => 'Vitaminai ir papildai',
        'Kūno priežiūra ir apsauga nuo saulės' => 'Kūno priežiūra',
        'Dekoratyvinė kosmetika ir kvepalai' => 'Dekoratyvinė kosmetika',
        'Medicinos prekės ir prietaisai' => 'Medicinos prekės',
        'Ortopedija ir kompresinės prekės' => 'Ortopedija',
        'Akių priežiūra ir optika' => 'Akių priežiūra',
        'Sportas, svorio kontrolė, arbatos ir spec. maistas' => 'Sportas ir spec. maistas',
    ],

    // Genitive ("nereceptinių vaistų akcijos"), keyed by the short name.
    'genitive_labels' => [
        'Nereceptiniai vaistai' => 'nereceptinių vaistų',
        'Vitaminai ir papildai' => 'vitaminų ir maisto papildų',
        'Veido priežiūra' => 'veido priežiūros priemonių',
        'Kūno priežiūra' => 'kūno priežiūros priemonių',
        'Plaukų priežiūra' => 'plaukų priežiūros priemonių',
        'Dekoratyvinė kosmetika' => 'dekoratyvinės kosmetikos',
        'Higiena' => 'higienos prekių',
        'Mamai ir vaikui' => 'prekių mamai ir vaikui',
        'Medicinos prekės' => 'medicinos prekių',
        'Ortopedija' => 'ortopedijos prekių',
        'Akių priežiūra' => 'akių priežiūros priemonių',
        'Sportas ir spec. maistas' => 'sporto ir specialaus maisto prekių',
    ],

    // Dative ("akcijos nereceptiniams vaistams"), keyed by the short name.
    'dative_labels' => [
        'Nereceptiniai vaistai' => 'nereceptiniams vaistams',
        'Vitaminai ir papildai' => 'vitaminams ir maisto papildams',
        'Veido priežiūra' => 'veido priežiūros priemonėms',
        'Kūno priežiūra' => 'kūno priežiūros priemonėms',
        'Plaukų priežiūra' => 'plaukų priežiūros priemonėms',
        'Dekoratyvinė kosmetika' => 'dekoratyvinei kosmetikai',
        'Higiena' => 'higienos prekėms',
        'Mamai ir vaikui' => 'prekėms mamai ir vaikui',
        'Medicinos prekės' => 'medicinos prekėms',
        'Ortopedija' => 'ortopedijos prekėms',
        'Akių priežiūra' => 'akių priežiūros priemonėms',
        'Sportas ir spec. maistas' => 'sporto ir specialaus maisto prekėms',
    ],

    // Typical items in each category, dative "X, Y ir Z", for the
    // store+category meta description. Describes what's sold, never what it
    // treats or does (no health claims).
    'item_examples' => [
        'Nereceptiniai vaistai' => 'vaistams nuo skausmo, peršalimo ir virškinimo sutrikimų',
        'Vitaminai ir papildai' => 'vitaminui D, magniui ir omega-3',
        'Veido priežiūra' => 'veido kremams, serumams ir prausikliams',
        'Kūno priežiūra' => 'kūno kremams, dezodorantams ir apsaugai nuo saulės',
        'Plaukų priežiūra' => 'šampūnams, kondicionieriams ir plaukų kaukėms',
        'Dekoratyvinė kosmetika' => 'pudroms, lūpų dažams ir kvepalams',
        'Higiena' => 'dantų pastoms, intymios higienos priemonėms ir servetėlėms',
        'Mamai ir vaikui' => 'sauskelnėms, kūdikių mišiniams ir vaikų kosmetikai',
        'Medicinos prekės' => 'kraujospūdžio matuokliams, termometrams ir pleistrams',
        'Ortopedija' => 'įtvarams, kompresinėms kojinėms ir ortopediniams vidpadžiams',
        'Akių priežiūra' => 'kontaktiniams lęšiams, lęšių skysčiams ir akių lašams',
        'Sportas ir spec. maistas' => 'sporto papildams, arbatoms ir sveikam maistui',
    ],

    // Sorted first by the "Populiariausi" listing sort and preferred for the
    // home deal pool.
    'popular_slugs' => [
        'nereceptiniai-vaistai',
        'vitaminai-ir-maisto-papildai',
        'veido-prieziura',
        'higiena',
        'mamai-ir-vaikui',
    ],

    // Keyword pages are split into two blocks on the home page and
    // /pigiausios-prekes: medicines and supplements (these roots) and care
    // (every other root).
    'keyword_medicine_group' => [
        'nereceptiniai-vaistai',
        'vitaminai-ir-maisto-papildai',
        'sportas-svorio-kontrole-arbatos-ir-spec-maistas',
    ],

    // Root categories kept out of the home page's "top products" sections.
    'excluded_top_product_slugs' => [],
];
