<?php

// Ported from discount/src/lib/stores.ts — static per-store frontend metadata
// (brand color, hero-logo inversion) that has no equivalent field on the
// Store model. Keep this in sync with that file until the discount repo
// frontend is retired.
return [
    'stores' => [
        ['name' => 'Maxima', 'slug' => 'maxima'],
        ['name' => 'Iki', 'slug' => 'iki'],
        ['name' => 'Lidl', 'slug' => 'lidl'],
        ['name' => 'Norfa', 'slug' => 'norfa'],
        ['name' => 'Rimi', 'slug' => 'rimi'],
        ['name' => 'Aibė', 'slug' => 'aibe'],
        ['name' => 'Šilas', 'slug' => 'silas'],
        ['name' => 'Čia', 'slug' => 'cia'],
        ['name' => 'Grustė', 'slug' => 'gruste'],
        ['name' => 'Express Market', 'slug' => 'express-market'],
        ['name' => 'Koops', 'slug' => 'koops'],
        ['name' => 'Gulbelė', 'slug' => 'gulbele'],
        ['name' => 'Kubas', 'slug' => 'kubas'],
        ['name' => 'Vynoteka', 'slug' => 'vynoteka'],
        ['name' => 'Thomas Philipps', 'slug' => 'thomas-philipps'],
        ['name' => 'ePromo', 'slug' => 'epromo'],
    ],

    'brand_colors' => [
        'maxima' => '#E31937',
        'lidl' => '#0050AA',
        'iki' => '#E30613',
        'rimi' => '#006DB6',
        'norfa' => '#009639',
        'aibe' => '#FF6600',
        'silas' => '#00843D',
        'cia' => '#1B5E20',
        'gruste' => '#C62828',
        'express-market' => '#1565C0',
        'koops' => '#2E7D32',
        'gulbele' => '#F57C00',
        'kubas' => '#374151',
        'vynoteka' => '#A7295B',
        'thomas-philipps' => '#E10014',
        'epromo' => '#ED1C24',
    ],

    'brand_color_fallback' => '#374151',

    // Logos rendered on a dark hero background that need a CSS invert filter.
    'hero_invert_logos' => ['maxima', 'norfa'],
];
