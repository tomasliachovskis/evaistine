<?php

return [

    'noise_words' => [
        'akcija', 'akcijos', 'akcijų', 'nuolaida', 'nuolaidos', 'nuolaidų',
        'pigiau', 'pigiausia', 'pigiausias', 'pigiausi', 'kaina', 'kainos', 'kainu',
        'top', 'kur', 'geriausia', 'geriausias', 'geriausi',
    ],

    'store_names' => [
        'eurovaistinė', 'eurovaistine', 'gintarinė', 'gintarine', 'camelia', 'benu',
        'apotheka', 'n vaistinė', 'nvaistine', 'ramunėlės', 'ramuneles', 'vaistinė', 'vaistine',
        'vaistinės', 'vaistines', 'pigu', 'leidinys', 'leidiniai', 'katalogas', 'flyer',
    ],

    'skip_term_groups' => [
        '2025', '2026', '1000', '600', '1', 'd', 'pro', 'kaina', 'imk', 'tv', 'new',
        'vilnius', 'kaune', 'palanga', 'akcija', 'akcijos', 'maksima', 'aibes', 'eurokos',
        'livosil', 'gradiali', 'probro', 'detralex', 'essentiale', 'aterolip', 'kolagenas',
        'prostamol', 'nataspin', 'lioton', 'uno', 'samsung', 'televizoriai', 'telefonai',
        'lagaminai', 'padangos', 'akumuliatoriai', 'spa', 'poilsis', 'dormeo', 'ermitazas',
        'issipildymo', 'isipildymo', 'tualetinis', 'popierius', 'oro', 'forte',
        'maistas', 'kaciu', 'gruzdintuve', 'karsto', 'briketai', 'stogo',
        'danga', 'lauko', 'burnos', 'higiena', 'saldainiams', 'saldytuvai', 'kompiuteriai',
        'monitorius', 'orkaite', 'keptuves', 'irankiai', 'laminatas', 'stihl', 'siurblys',
        'vaistine', 'skalbiklis', 'plyteles', 'trasos', 'parkas', 'magnis', 'akiniai',
        'crocs', 'atrask', 'sanatorija', 'iphone', 'makita', 'fiskars',
        'husqvarna', 'dyson', 'bioderma', 'filorga', 'diclac', 'lyprinol', 'lidonium',
        'kapa-zvake', 'kapu-zvakes', 'fotoknyga', 'go3', 'iqos', 'lemon-gym', 'radarom',
        'vynoteka', 'impuls', 'balance', 'kamado', 'lietvamzdza', 'monitora', 'lagamina',
        'veja-robotai', 'darom-2026', 'kopecia', 'davines', 'pomi-t', 'pomit',
        'bro', 'bioderma', 'filorga', 'aparatai', 'fjord', 'schultz-trasa',
        'saldytuvai', 'husqvarna', 'monitora', 'laminata', 'lietvamzdza', 'kapa-zvake',
        'regitra-numera', 'ziemina-kombinezonai-vaika',
    ],

    'merge_into' => [
        'kavos' => 'kava',
        'kavai' => 'kava',
        'pupeles' => 'kava',
        'coca' => 'cola',
        'lasisai' => 'lasisa',
        'file' => 'lasisa',
        'bulvems' => 'bulves',
        'zuvu' => 'taukai',
        'zaisla' => 'zaislams',
        'zaislai' => 'zaislams',
        'leda' => 'ledams',
        'ledai' => 'ledams',
    ],

    /*
     * Ahrefs kartais neteisingai priskiria kategoriją (pvz. „coca cola“ → Bonds).
     * Jei keyword aiškiai maisto/gerimo produktas – neblokuojame.
     */
    'allowed_categories' => [
        'Soft drinks', 'Beverages', 'Groceries', 'Grocery', 'Food', 'Snacks',
        'Dairy', 'Meat', 'Seafood', 'Produce', 'Coffee', 'Tea', 'Juice',
        'Alcoholic beverages', 'Beer', 'Wine', 'Household supplies', 'Cleaning',
        'Laundry', 'Baby', 'Pet food', 'Frozen', 'Bakery', 'Candy', 'Chocolate',
    ],

    'product_keyword_needles' => [
        // gerimai
        'cola', 'coca', 'pepsi', 'fanta', 'sprite', 'mirinda', '7up',
        'alus', 'sultys', 'sultis', 'vanduo', 'gerimai', 'limonadas', 'energetinis',
        'kava', 'arbata', 'pienas', 'kefiras', 'gira',
        // maistas
        'duona', 'pienas', 'sviestas', 'varske', 'kumpis', 'desra', 'kiausin',
        'bulv', 'mork', 'banan', 'obuol', 'citrin', 'agurk', 'pomidor',
        'lasis', 'zuv', 'krevet', 'maltin', 'koldun', 'makaron', 'ryz',
        'aliejus', 'cukrus', 'medus', 'saldain', 'sokolad', 'ledai', 'sausain',
        'skalbimo', 'indaplov', 'plovik', 'tualetinis', 'popier',
    ],

    'known_brands' => [
        'paulig', 'lavazza', 'dallmayr', 'dolce', 'gusto', 'nespresso', 'jacobs',
        'nutella', 'frosch', 'ariel', 'persil', 'lenor', 'fairy', 'finish',
        'nescafe', 'starbucks', 'illy', 'kimbo', 'lor', 'melitta', 'tchibo',
        'jura', 'philips', 'bosch', 'siemens', 'lego', 'barbie', 'hot wheels',
    ],

    'brand_min_volume' => 100,

    'min_group_volume' => 50,

    'priority' => [
        'high' => 300,
        'medium' => 100,
    ],

];
