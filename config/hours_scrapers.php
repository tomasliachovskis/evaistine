<?php

// Canonical store => nuolaidos.lt slug map for scrapers/hours/scrape.js.
// nuolaidos.lt's own slug matches our stores.slug in every case checked so
// far. Excludes stores confirmed to have zero physical locations tracked
// there (Avon, AVS, Lankava, N vaistinė, Officeday, Oriflame, Takko,
// Tupperware — direct-sales/catalog brands or simply untracked) — the
// scraper skips submission for any store that comes back with 0 locations
// anyway, so an occasional false positive here is harmless.
return [
    'Maxima' => 'maxima',
    'Iki' => 'iki',
    'Lidl' => 'lidl',
    'Norfa' => 'norfa',
    'Rimi' => 'rimi',
    'Aibė' => 'aibe',
    'Šilas' => 'silas',
    'Čia' => 'cia',
    'Grustė' => 'gruste',
    'Express Market' => 'express-market',
    'Koops' => 'koops',
    'Gulbelė' => 'gulbele',
    'Kubas' => 'kubas',
    'Vynoteka' => 'vynoteka',
    'Thomas Philipps' => 'thomas-philipps',
    'ePromo' => 'epromo',
    'Apotheka' => 'apotheka',
    'Benu vaistinė' => 'benu-vaistine',
    'Bikuva' => 'bikuva',
    'Camelia' => 'camelia',
    'Elimart' => 'elimart',
    'Ermitažas' => 'ermitazas',
    'Eurokos' => 'eurokos',
    'Eurovaistinė' => 'eurovaistine',
    'Gintarinė vaistinė' => 'gintarine-vaistine',
    'Jupoja' => 'jupoja',
    'Jysk' => 'jysk',
    'Moki Veži' => 'moki-vezi',
    'Pepco' => 'pepco',
    'Promo Cash&Carry' => 'promo-cash-carry',
    'Ramunėlės vaistinė' => 'ramuneles-vaistine',
    'Senukai' => 'senukai',
    'TECHasas' => 'techasas',
    'Technorama' => 'technorama',
    'Douglas' => 'douglas',
    'Ikea' => 'ikea',
    'Lytagra' => 'lytagra',
    'Mary Kay' => 'mary-kay',
    'Švaros Prekės' => 'svaros-prekes',
];
