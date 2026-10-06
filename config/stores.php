<?php

// Static per-store frontend metadata (brand color, hero-logo inversion)
// that has no equivalent field on the Store model.
return [
    // All 15 pharmacies: the 7 physical chains, then online pharmacies from
    // VVKT's list of pharmacies allowed to sell remotely (docs/evaistine.md).
    'stores' => [
        ['name' => 'Eurovaistinė', 'slug' => 'eurovaistine', 'website' => 'https://www.eurovaistine.lt'],
        ['name' => 'Gintarinė vaistinė', 'slug' => 'gintarine-vaistine', 'website' => 'https://www.gintarine.lt'],
        ['name' => 'Camelia', 'slug' => 'camelia', 'website' => 'https://www.camelia.lt'],
        ['name' => 'Benu vaistinė', 'slug' => 'benu-vaistine', 'website' => 'https://www.benu.lt'],
        ['name' => 'Apotheka', 'slug' => 'apotheka', 'website' => 'https://www.apotheka.lt'],
        ['name' => 'N vaistinė', 'slug' => 'nvaistine', 'website' => 'https://www.nvaistine.lt'],
        ['name' => 'Ramunėlės vaistinė', 'slug' => 'ramuneles-vaistine', 'website' => 'https://www.ramunelesvaistine.lt'],
        ['name' => 'InternetineVaistine.lt', 'slug' => 'internetine-vaistine', 'website' => 'https://internetinevaistine.lt'],
        ['name' => 'Mano vaistinė', 'slug' => 'mano-vaistine', 'website' => 'https://www.manovaistine.lt'],
        ['name' => 'Piliulė', 'slug' => 'piliule', 'website' => 'https://www.piliule.lt'],
        ['name' => 'Universiteto vaistinė', 'slug' => 'universiteto-vaistine', 'website' => 'https://www.universitetovaistine.eu'],
        ['name' => 'Ąžuolyno vaistinė', 'slug' => 'azuolyno-vaistine', 'website' => 'https://azuolynovaistine.lt'],
        ['name' => 'Rx vaistinė', 'slug' => 'rx-vaistine', 'website' => 'https://rx-vaistine.lt'],
        ['name' => 'LSMU vaistinė', 'slug' => 'lsmu-vaistine', 'website' => 'https://eshop.lsmu.lt/vaistine/'],
        ['name' => '100 metų vaistinė', 'slug' => '100-metu-vaistine', 'website' => 'https://www.100metu.lt'],
    ],

    // Case forms for pharmacy names that already say "vaistinė" (or would
    // read oddly with it appended), so PharmacyName::phrase() inflects the
    // name itself instead of adding a second "vaistinė" ("Benu vaistinė
    // vaistinėje"). Names not listed (Camelia, Apotheka) get "{name}
    // vaistinė{ending}". Add every new pharmacy whose name says "vaistinė".
    'name_forms' => [
        'Eurovaistinė' => [
            'nominative' => 'Eurovaistinė', 'genitive' => 'Eurovaistinės', 'accusative' => 'Eurovaistinę',
            'locative' => 'Eurovaistinėje', 'plural' => 'Eurovaistinės', 'genitive_plural' => 'Eurovaistinių',
            'locative_plural' => 'Eurovaistinėse',
        ],
        'Gintarinė vaistinė' => [
            'nominative' => 'Gintarinė vaistinė', 'genitive' => 'Gintarinės vaistinės', 'accusative' => 'Gintarinę vaistinę',
            'locative' => 'Gintarinėje vaistinėje', 'plural' => 'Gintarinės vaistinės', 'genitive_plural' => 'Gintarinių vaistinių',
            'locative_plural' => 'Gintarinėse vaistinėse',
        ],
        'Benu vaistinė' => [
            'nominative' => 'Benu vaistinė', 'genitive' => 'Benu vaistinės', 'accusative' => 'Benu vaistinę',
            'locative' => 'Benu vaistinėje', 'plural' => 'Benu vaistinės', 'genitive_plural' => 'Benu vaistinių',
            'locative_plural' => 'Benu vaistinėse',
        ],
        'N vaistinė' => [
            'nominative' => 'N vaistinė', 'genitive' => 'N vaistinės', 'accusative' => 'N vaistinę',
            'locative' => 'N vaistinėje', 'plural' => 'N vaistinės', 'genitive_plural' => 'N vaistinių',
            'locative_plural' => 'N vaistinėse',
        ],
        'Ramunėlės vaistinė' => [
            'nominative' => 'Ramunėlės vaistinė', 'genitive' => 'Ramunėlės vaistinės', 'accusative' => 'Ramunėlės vaistinę',
            'locative' => 'Ramunėlės vaistinėje', 'plural' => 'Ramunėlės vaistinės', 'genitive_plural' => 'Ramunėlės vaistinių',
            'locative_plural' => 'Ramunėlės vaistinėse',
        ],
        'Mano vaistinė' => [
            'nominative' => 'Mano vaistinė', 'genitive' => 'Mano vaistinės', 'accusative' => 'Mano vaistinę',
            'locative' => 'Mano vaistinėje', 'plural' => 'Mano vaistinės', 'genitive_plural' => 'Mano vaistinių',
            'locative_plural' => 'Mano vaistinėse',
        ],
        'Universiteto vaistinė' => [
            'nominative' => 'Universiteto vaistinė', 'genitive' => 'Universiteto vaistinės', 'accusative' => 'Universiteto vaistinę',
            'locative' => 'Universiteto vaistinėje', 'plural' => 'Universiteto vaistinės', 'genitive_plural' => 'Universiteto vaistinių',
            'locative_plural' => 'Universiteto vaistinėse',
        ],
        'Ąžuolyno vaistinė' => [
            'nominative' => 'Ąžuolyno vaistinė', 'genitive' => 'Ąžuolyno vaistinės', 'accusative' => 'Ąžuolyno vaistinę',
            'locative' => 'Ąžuolyno vaistinėje', 'plural' => 'Ąžuolyno vaistinės', 'genitive_plural' => 'Ąžuolyno vaistinių',
            'locative_plural' => 'Ąžuolyno vaistinėse',
        ],
        'Rx vaistinė' => [
            'nominative' => 'Rx vaistinė', 'genitive' => 'Rx vaistinės', 'accusative' => 'Rx vaistinę',
            'locative' => 'Rx vaistinėje', 'plural' => 'Rx vaistinės', 'genitive_plural' => 'Rx vaistinių',
            'locative_plural' => 'Rx vaistinėse',
        ],
        'LSMU vaistinė' => [
            'nominative' => 'LSMU vaistinė', 'genitive' => 'LSMU vaistinės', 'accusative' => 'LSMU vaistinę',
            'locative' => 'LSMU vaistinėje', 'plural' => 'LSMU vaistinės', 'genitive_plural' => 'LSMU vaistinių',
            'locative_plural' => 'LSMU vaistinėse',
        ],
        '100 metų vaistinė' => [
            'nominative' => '100 metų vaistinė', 'genitive' => '100 metų vaistinės', 'accusative' => '100 metų vaistinę',
            'locative' => '100 metų vaistinėje', 'plural' => '100 metų vaistinės', 'genitive_plural' => '100 metų vaistinių',
            'locative_plural' => '100 metų vaistinėse',
        ],
        'Piliulė' => [
            'nominative' => 'Piliulė', 'genitive' => 'Piliulės', 'accusative' => 'Piliulę',
            'locative' => 'Piliulėje', 'plural' => 'Piliulės vaistinės', 'genitive_plural' => 'Piliulės vaistinių',
            'locative_plural' => 'Piliulės vaistinėse',
        ],
        // A brand written as a domain: never declined.
        'InternetineVaistine.lt' => [
            'nominative' => 'InternetineVaistine.lt', 'genitive' => 'InternetineVaistine.lt', 'accusative' => 'InternetineVaistine.lt',
            'locative' => 'InternetineVaistine.lt', 'plural' => 'InternetineVaistine.lt', 'genitive_plural' => 'InternetineVaistine.lt',
            'locative_plural' => 'InternetineVaistine.lt',
        ],
    ],

    // The recognizable national pharmacy chains, in editorial display order.
    // Every "main stores first" list (store chips, leaflet hub, default
    // subscriber stores, keyword-page store priority) reads this one list.
    'main_slugs' => ['eurovaistine', 'gintarine-vaistine', 'camelia', 'benu-vaistine', 'apotheka'],

    // Filled in with each chain's real logo color during the design pass.
    'brand_colors' => [
    ],

    'brand_color_fallback' => '#374151',

    // Logos rendered on a dark hero background that need a CSS invert filter.
    'hero_invert_logos' => [],
];
