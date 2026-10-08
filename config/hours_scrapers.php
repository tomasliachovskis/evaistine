<?php

// Pharmacies whose addresses and opening hours scrapers/hours/scrape.js
// collects (store name => stores.slug). `hours:scrape --all` runs each one,
// and /vaistines links their address pages. Each pharmacy's source (nuolaidos.lt,
// its own site, or scrapers/hours/manual-locations.json) is set in scrape.js;
// keep both lists in sync. See docs/evaistine.md "Pharmacy addresses and hours".
return [
    'Eurovaistinė' => 'eurovaistine',
    'Gintarinė vaistinė' => 'gintarine-vaistine',
    'Camelia' => 'camelia',
    'Benu vaistinė' => 'benu-vaistine',
    'Apotheka' => 'apotheka',
    'N vaistinė' => 'nvaistine',
    'Mano vaistinė' => 'mano-vaistine',
    'Ramunėlės vaistinė' => 'ramuneles-vaistine',
    '100 metų vaistinė' => '100-metu-vaistine',
    'Ąžuolyno vaistinė' => 'azuolyno-vaistine',
    'LSMU vaistinė' => 'lsmu-vaistine',
    'Universiteto vaistinė' => 'universiteto-vaistine',
    'Piliulė' => 'piliule',
    'Rx vaistinė' => 'rx-vaistine',
    'InternetineVaistine.lt' => 'internetine-vaistine',
];
