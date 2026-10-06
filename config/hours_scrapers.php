<?php

// Canonical store => nuolaidos.lt slug map for scrapers/hours/scrape.js.
// nuolaidos.lt's own slug matches our stores.slug in every case checked so
// far. Excludes N vaistinė (zero physical locations tracked there) — the
// scraper skips submission for any store that comes back with 0 locations
// anyway, so an occasional false positive here is harmless.
return [
    'Eurovaistinė' => 'eurovaistine',
    'Gintarinė vaistinė' => 'gintarine-vaistine',
    'Camelia' => 'camelia',
    'Benu vaistinė' => 'benu-vaistine',
    'Apotheka' => 'apotheka',
    'Ramunėlės vaistinė' => 'ramuneles-vaistine',
];
