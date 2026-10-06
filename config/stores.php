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
