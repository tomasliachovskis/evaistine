<?php

namespace App\Support;

class FoodCategorySlugs
{
    public const FOOD = [
        'vaisiai-ir-darzoves',
        'pieno-produktai-ir-kiausiniai',
        'duonos-gaminiai',
        'mesa-ir-zuvis',
        'saldytas-maistas-ir-ledai',
        'bakaleja',
        'saldumynai-ir-uzkandziai',
        'gerimai-kava-arbata',
        'alkoholiniai-ir-nealkoholiniai-gerimai',
    ];

    public const NON_FOOD = [
        'kosmetika-ir-higiena',
        'buitine-chemija-valymo-priemones',
        'namu-ukio-ir-laisvalaikio-prekes',
        'gyvunu-prekes',
        'augalai-geles',
    ];

    public const EXCLUDED_FROM_BEST_AND_NON_FOOD = [
        'vaiku-ir-kudikiu-prekes',
    ];
}
