<?php

// Static per-store frontend metadata (brand color, hero-logo inversion)
// that has no equivalent field on the Store model.
return [
    'stores' => [
        ['name' => 'Eurovaistinė', 'slug' => 'eurovaistine'],
        ['name' => 'Gintarinė vaistinė', 'slug' => 'gintarine-vaistine'],
        ['name' => 'Camelia', 'slug' => 'camelia'],
        ['name' => 'Benu vaistinė', 'slug' => 'benu-vaistine'],
        ['name' => 'Apotheka', 'slug' => 'apotheka'],
        ['name' => 'N vaistinė', 'slug' => 'nvaistine'],
        ['name' => 'Ramunėlės vaistinė', 'slug' => 'ramuneles-vaistine'],
    ],

    // Case forms for chain names that already say "vaistinė", so
    // PharmacyName::phrase() inflects the name itself instead of appending
    // a second "vaistinė" ("Benu vaistinė vaistinėje"). Names without it
    // (Camelia, Apotheka) get "{name} vaistinė{ending}" there.
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
