<?php

// Canonical store => e-shop scraper filename map, shared by scrapers:run.
// N vaistinė and Ramunėlės vaistinė have no e-shop of their own (their
// "buy online" links go to gintarine.lt and 100metu.lt), so they only have
// leaflet scrapers. Shared code: scrapers/lib/.
return [
    'Eurovaistinė' => 'eurovaistine.js',
    'Gintarinė vaistinė' => 'gintarine-vaistine.js',
    'Camelia' => 'camelia.js',
    'Benu vaistinė' => 'benu-vaistine.js',
    'Apotheka' => 'apotheka.js',
    'InternetineVaistine.lt' => 'internetine-vaistine.js',
    'Mano vaistinė' => 'mano-vaistine.js',
    'Piliulė' => 'piliule.js',
    'Universiteto vaistinė' => 'universiteto-vaistine.js',
    'Ąžuolyno vaistinė' => 'azuolyno-vaistine.js',
    'Rx vaistinė' => 'rx-vaistine.js',
    'LSMU vaistinė' => 'lsmu-vaistine.js',
    '100 metų vaistinė' => '100-metu-vaistine.js',
];
