<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportCategories extends Command
{
    protected $signature = 'categories:import';
    protected $description = 'Import categories from JSON data';

    public function handle()
    {
        die();

        $jsonData = '{
    "categories": [
        {
            "name": "Vaisiai, daržovės ir gėlės",
            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/c/SH-15",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/245b0af2d0117604cff3456d294925bdaedbe337-nFoJeGfQj7",
            "descendants": [
                {
                    "name": "Vaisiai ir uogos",
                    "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/c/SH-15-3",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Bananai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/bananai/c/SH-15-3-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Citrinos",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/citrinos/c/SH-15-3-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Egzotiniai vaisiai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/egzotiniai-vaisiai/c/SH-15-3-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Melionai ir arbūzai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/melionai-ir-arbuzai/c/SH-15-3-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Obuoliai ",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/obuoliai-/c/SH-15-3-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kriaušės",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/kriauses/c/SH-15-3-20",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vynuogės ",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/vynuoges-/c/SH-15-3-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kaulavaisiai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/kaulavaisiai/c/SH-15-3-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Uogos",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/uogos/c/SH-15-3-23",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Avokadai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/avokadai/c/SH-15-3-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Apelsinai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/apelsinai/c/SH-15-3-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Greipfrutai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/greipfrutai/c/SH-15-3-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mandarinai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/vaisiai-ir-uogos/mandarinai/c/SH-15-3-6",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Daržovės ir grybai",
                    "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/c/SH-15-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Pomidorai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/pomidorai/c/SH-15-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Agurkai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/agurkai/c/SH-15-1-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Paprikos",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/paprikos/c/SH-15-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Moliūgai ir cukinijos",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/moliugai-ir-cukinijos/c/SH-15-1-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Salotos ir jų mišiniai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/salotos-ir-ju-misiniai/c/SH-15-1-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Bulvės",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/bulves/c/SH-15-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Burokėliai ir kiti šakniavaisiai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/burokeliai-ir-kiti-sakniavaisiai/c/SH-15-1-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kukurūzai, žirniai, pupelės ir smidrai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/kukuruzai-zirniai-pupeles-ir-smidrai/c/SH-15-1-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Svogūnai, porai ir česnakai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/svogunai-porai-ir-cesnakai/c/SH-15-1-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Prieskoninės daržovės ir žolelės",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/prieskonines-darzoves-ir-zoleles/c/SH-15-1-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Grybai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/grybai/c/SH-15-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Apdorotos daržovės",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/apdorotos-darzoves/c/SH-15-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kopūstai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/kopustai/c/SH-15-1-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Morkos",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/morkos/c/SH-15-1-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Baklažanai",
                            "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/darzoves-ir-grybai/baklazanai/c/SH-15-1-15",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Gėlės",
                    "url": "/e-parduotuve/lt/produktai/vaisiai-darzoves-ir-geles/geles/c/SH-8-10",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Augaliniai produktai",
            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/c/SH-77",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_auto,q_auto:low,w_auto/ecom-cms/69200f0d16bca05841de6113210e023b6c90a9d8",
            "descendants": [
                {
                    "name": "Augaliniai gėrimai",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-gerimai/c/SH-77-8",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Augaliniai gėrimai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-gerimai/augaliniai-gerimai/c/SH-77-23",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kreminiai augaliniai produktai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-gerimai/kreminiai-augaliniai-produktai/c/SH-77-25",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šaldyti produktai",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/saldyti-produktai/c/SH-77-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Augaliniai ledai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/saldyti-produktai/augaliniai-ledai/c/SH-77-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veganiškos picos",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/saldyti-produktai/veganiskos-picos/c/SH-77-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti patiekalai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/saldyti-produktai/saldyti-patiekalai/c/SH-77-20",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Augalinės sūrio ir tofu alternatyvos",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augalines-surio-ir-tofu-alternatyvos/c/SH-77-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Augalinės sūrio alternatyvos",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augalines-surio-ir-tofu-alternatyvos/augalines-surio-alternatyvos/c/SH-77-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tofu",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augalines-surio-ir-tofu-alternatyvos/tofu/c/SH-77-17",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Augaliniai riebalai, majonezas, užtepėlės",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-riebalai-majonezas-uztepeles/c/SH-77-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Augalinės užtepėlės ",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-riebalai-majonezas-uztepeles/augalines-uztepeles-/c/SH-77-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Augaliniai valgomieji riebalai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-riebalai-majonezas-uztepeles/augaliniai-valgomieji-riebalai/c/SH-77-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Augaliniai majonezo pakaitalai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-riebalai-majonezas-uztepeles/augaliniai-majonezo-pakaitalai/c/SH-77-14",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Augalinės mėsos ir žuvies alternatyvos",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augalines-mesos-ir-zuvies-alternatyvos/c/SH-77-7",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Augaliniai desertai",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-desertai/c/SH-77-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Augaliniai desertai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-desertai/augaliniai-desertai/c/SH-77-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Augaliniai desertiniai batonėliai",
                            "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/augaliniai-desertai/augaliniai-desertiniai-batoneliai/c/SH-77-19",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Pusgaminiai",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/pusgaminiai/c/SH-77-9",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Konditerijos gaminiai",
                    "url": "/e-parduotuve/lt/produktai/augaliniai-produktai/konditerijos-gaminiai/c/SH-77-3",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Pieno produktai ir kiaušiniai",
            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/c/SH-11",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/86eccc53b09c56e6ba3689c0930a85456b9bccf8-JQWiyrTDrM",
            "descendants": [
                {
                    "name": "Pienas",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/pienas/c/SH-11-8",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Ilgo galiojimo pienas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/pienas/ilgo-galiojimo-pienas/c/SH-11-8-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pasterizuotas pienas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/pienas/pasterizuotas-pienas/c/SH-11-8-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pieno gėrimai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/pienas/pieno-gerimai/c/SH-11-8-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sutirštintas pienas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/pienas/sutirstintas-pienas/c/SH-11-8-16",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sūris",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/c/SH-11-9",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Fermentiniai sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/fermentiniai-suriai/c/SH-11-9-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kietieji sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/kietieji-suriai/c/SH-11-9-23",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tarkuotas sūris",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/tarkuotas-suris/c/SH-11-9-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Maskarponės ir rikotos sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/maskarpones-ir-rikotos-suriai/c/SH-11-9-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Fetos ir mocarelos sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/fetos-ir-mocarelos-suriai/c/SH-11-9-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pelėsiniai sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/pelesiniai-suriai/c/SH-11-9-29",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Avių ir ožkų pieno sūris",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/aviu-ir-ozku-pieno-suris/c/SH-11-9-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sūrio užkandžiai ir lazdelės",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/surio-uzkandziai-ir-lazdeles/c/SH-11-9-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tepamieji sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/tepamieji-suriai/c/SH-11-9-30",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kepamieji sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/kepamieji-suriai/c/SH-11-9-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lydyti sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/suris/lydyti-suriai/c/SH-11-9-25",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kefyras, rūgpienis ir pasukos",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kefyras-rugpienis-ir-pasukos/c/SH-11-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kefyras",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kefyras-rugpienis-ir-pasukos/kefyras/c/SH-11-5-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pasukos",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kefyras-rugpienis-ir-pasukos/pasukos/c/SH-11-5-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Rūgpienis",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kefyras-rugpienis-ir-pasukos/rugpienis/c/SH-11-5-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skoniniai kefyro gėrimai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kefyras-rugpienis-ir-pasukos/skoniniai-kefyro-gerimai/c/SH-11-5-5",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Grietinė ir grietinėlė",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/grietine-ir-grietinele/c/SH-11-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Grietinė ",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/grietine-ir-grietinele/grietine-/c/SH-11-2-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Grietinėlė",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/grietine-ir-grietinele/grietinele/c/SH-11-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kastinys",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/grietine-ir-grietinele/kastinys/c/SH-11-2-3",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Varškės produktai",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/varskes-produktai/c/SH-11-11",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Varškė",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/varskes-produktai/varske/c/SH-11-11-36",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Grūdėta varškė",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/varskes-produktai/grudeta-varske/c/SH-11-11-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Varškės sūriai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/varskes-produktai/varskes-suriai/c/SH-11-11-37",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Majonezas ",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/majonezas-/c/SH-11-7",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Majonezas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/majonezas-/majonezas/c/SH-11-7-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Padažai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/majonezas-/padazai/c/SH-11-7-01",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Jogurtai ir desertai",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/c/SH-11-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Jogurtai be pagardų",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/jogurtai-be-pagardu/c/SH-11-4-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Jogurtai su pagardais",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/jogurtai-su-pagardais/c/SH-11-4-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Geriamieji jogurtai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/geriamieji-jogurtai/c/SH-11-4-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Desertiniai užkandžių batonėliai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/desertiniai-uzkandziu-batoneliai/c/SH-11-4-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Desertai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/desertai/c/SH-11-4-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Glaistyti varškės sūreliai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/jogurtai-ir-desertai/glaistyti-varskes-sureliai/c/SH-11-4-6",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kiaušiniai",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/c/SH-11-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "0 - Ekologiški kiaušiniai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/0---ekologiski-kiausiniai/c/SH-11-6-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "1 - Laisvai laikomų vištų kiaušiniai ",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/1---laisvai-laikomu-vistu-kiausiniai-/c/SH-11-6-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "2 - Ant kraiko laikomų vištų kiaušiniai ",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/2---ant-kraiko-laikomu-vistu-kiausiniai-/c/SH-11-6-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti kiaušiniai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/kiti-kiausiniai/c/SH-11-6-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti kiaušinių produktai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/kiausiniai/kiti-kiausiniu-produktai/c/SH-11-6-17",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sviestas",
                    "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/sviestas/c/SH-11-10",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sviestas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/sviestas/sviestas/c/SH-11-10-34",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Riebalų mišiniai",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/sviestas/riebalu-misiniai/c/SH-11-10-32",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Margarinas",
                            "url": "/e-parduotuve/lt/produktai/pieno-produktai-ir-kiausiniai/sviestas/margarinas/c/SH-11-10-31",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Duonos gaminiai ",
            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/c/SH-3",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/03e13501a91016e42a8902364fdd76629ea4ba1a-BnAGwTKirZ",
            "descendants": [
                {
                    "name": "Šviežiai kepti duonos gaminiai",
                    "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/c/SH-3-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Nesaldžios bandelės ir užkandžiai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/nesaldzios-bandeles-ir-uzkandziai/c/SH-3-7-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saldžios bandelės ir spurgos",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/saldzios-bandeles-ir-spurgos/c/SH-3-7-28",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviežiai kepti pyragai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/svieziai-kepti-pyragai/c/SH-3-7-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviežiai kepta tamsi duona",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/svieziai-kepta-tamsi-duona/c/SH-3-7-29",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviežiai kepta  šviesi duona",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/svieziai-kepti-duonos-gaminiai/svieziai-kepta-sviesi-duona/c/SH-3-7-30",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Duona",
                    "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/c/SH-3-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Juoda duona",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/juoda-duona/c/SH-3-2-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviesi duona",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/sviesi-duona/c/SH-3-2-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sumuštinių duona",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/sumustiniu-duona/c/SH-3-2-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Batonas",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/batonas/c/SH-3-2-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mėsainių ir porcijinės duonelės",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duona/mesainiu-ir-porcijines-duoneles/c/SH-3-1-1",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Bandelės ir spurgos",
                    "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/bandeles-ir-spurgos/c/SH-3-1",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Duonos pakaitalai",
                    "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/c/SH-3-7",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Duoniukai, riestainiai, džiūvėsiai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/duoniukai-riestainiai-dziuvesiai/c/SH-3-2-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lavašas",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/lavasas/c/SH-3-2-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Malti džiūvėsėliai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/malti-dziuveseliai/c/SH-3-4-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konditeriniai krepšeliai, šaukšteliai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/konditeriniai-krepseliai-sauksteliai/c/SH-3-4-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tortų pagrindai, papločiai",
                            "url": "/e-parduotuve/lt/produktai/duonos-gaminiai-/duonos-pakaitalai/tortu-pagrindai-paplociai/c/SH-3-4-20",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Mėsa ir žuvis ",
            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/c/SH-9",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/3320378346a08c730fcabca46dd28e6422ee9c07-oiFVjkNDws",
            "descendants": [
                {
                    "name": "Šviežia mėsa",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/c/SH-9-8",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Jautiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/jautiena/c/SH-9-8-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiauliena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/kiauliena/c/SH-9-8-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Triušiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/triusiena/c/SH-9-8-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Subproduktai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/subproduktai/c/SH-9-8-25",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veršiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-mesa/versiena/c/SH-9-8-27",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šviežia paukštiena",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/c/SH-9-12",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Vištiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/vistiena/c/SH-9-12-41",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kalakutiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/kalakutiena/c/SH-9-12-39",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Antiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/antiena/c/SH-9-12-38",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Malta paukštiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/malta-paukstiena/c/SH-9-8-24",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žąsiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezia-paukstiena/zasiena/c/SH-9-12-5",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Malta mėsa",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/malta-mesa/c/SH-9-24-2",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Marinuota mėsa ir žuvis",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/marinuota-mesa-ir-zuvis/c/SH-9-15",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Marinuota kiauliena ir jautiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/marinuota-mesa-ir-zuvis/marinuota-kiauliena-ir-jautiena/c/SH-9-15-44",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Marinuota paukštiena",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/marinuota-mesa-ir-zuvis/marinuota-paukstiena/c/SH-9-15-45",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviežios dešrelės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/marinuota-mesa-ir-zuvis/sviezios-desreles/c/SH-9-15-46",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Marinuota žuvis",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/marinuota-mesa-ir-zuvis/marinuota-zuvis/c/SH-9-16-49",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šviežios žuvys ir jūrų gėrybės",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezios-zuvys-ir-juru-gerybes/c/SH-9-16",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Visa žuvis",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezios-zuvys-ir-juru-gerybes/visa-zuvis/c/SH-9-16-50",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žuvų filė",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezios-zuvys-ir-juru-gerybes/zuvu-file/c/SH-9-16-51",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Jūrų gėrybės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/sviezios-zuvys-ir-juru-gerybes/juru-gerybes/c/SH-9-16-10",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Apdorotos mėsos ir paukštienos gaminiai ",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/c/SH-9-24-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Vytintos dešros",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/vytintos-desros/c/SH-9-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vytinti mėsos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/vytinti-mesos-gaminiai/c/SH-9-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Karštai rūkytos dešros",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/karstai-rukytos-desros/c/SH-9-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Karštai rūkyti mėsos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/karstai-rukyti-mesos-gaminiai/c/SH-9-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaltai rūkytos dešros",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/saltai-rukytos-desros/c/SH-9-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaltai rūkyti mėsos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/saltai-rukyti-mesos-gaminiai/c/SH-9-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Rūkytos dešrelės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/rukytos-desreles/c/SH-9-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Virtos dešros ir kumpiai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/virtos-desros-ir-kumpiai/c/SH-9-20",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Virti ir kepti mėsos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/virti-ir-kepti-mesos-gaminiai/c/SH-9-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Virtos dešrelės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/virtos-desreles/c/SH-9-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mėsos užkandžiai ir dešrelės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/mesos-uzkandziai-ir-desreles/c/SH-9-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mėsos konservai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/mesos-konservai/c/SH-9-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Paštetai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/pastetai/c/SH-9-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mėsos patiekalai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-mesos-ir-paukstienos-gaminiai-/mesos-patiekalai/c/SH-9-41",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Apdorotos žuvies gaminiai",
                    "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/c/SH-9-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Silkės ir jų gaminiai ",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/silkes-ir-ju-gaminiai-/c/SH-9-1-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Karštai ir šaltai rūkyta žuvis",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/karstai-ir-saltai-rukyta-zuvis/c/SH-9-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Apdorota žuvis",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/apdorota-zuvis/c/SH-9-1-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Apdorotos jūros gėrybės ",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/apdorotos-juros-gerybes-/c/SH-9-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuota žuvis ir jūros gėrybės",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/konservuota-zuvis-ir-juros-gerybes/c/SH-9-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žuvies patiekalai",
                            "url": "/e-parduotuve/lt/produktai/mesa-ir-zuvis-/apdorotos-zuvies-gaminiai/zuvies-patiekalai/c/SH-9-1-16",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Šaldytas maistas",
            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/c/SH-13",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/eab789c8f9f033166344b7cd0e1b286e9b8ccc5b-VGUKh6QlyO",
            "descendants": [
                {
                    "name": "Šaldytos daržovės, vaisiai ir uogos",
                    "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldytos-darzoves-vaisiai-ir-uogos/c/SH-13-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šaldyti vaisiai ir uogos",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldytos-darzoves-vaisiai-ir-uogos/saldyti-vaisiai-ir-uogos/c/SH-13-5-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytos daržovės ir daržovių mišiniai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldytos-darzoves-vaisiai-ir-uogos/saldytos-darzoves-ir-darzoviu-misiniai/c/SH-13-5-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytos bulvės",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldytos-darzoves-vaisiai-ir-uogos/saldytos-bulves/c/SH-13-5-20",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti grybai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldytos-darzoves-vaisiai-ir-uogos/saldyti-grybai/c/SH-13-5-18",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šaldyti kulinarijos ir konditerijos gaminiai",
                    "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/c/SH-13-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šaldytos picos ir picos užkandžiai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldytos-picos-ir-picos-uzkandziai/c/SH-13-4-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti koldūnai ir virtiniai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyti-koldunai-ir-virtiniai/c/SH-13-4-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti tradiciniai gaminiai (cepelinai, didžkukuliai)",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyti-tradiciniai-gaminiai-cepelinai-didzkukuliai-/c/SH-13-4-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytas paruoštas vartojimui maistas",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldytas-paruostas-vartojimui-maistas/c/SH-13-4-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti blynai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyti-blynai/c/SH-13-4-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyta tešla",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyta-tesla/c/SH-13-4-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti duonos gaminiai, konditerija",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyti-duonos-gaminiai-konditerija/c/SH-13-4-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti veganiški ir vegetariški kulinarijos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyti-kulinarijos-ir-konditerijos-gaminiai/saldyti-veganiski-ir-vegetariski-kulinarijos-gaminiai/c/SH-13-4-01",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šaldyta mėsa, žuvis ir jūrų gėrybės",
                    "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/c/SH-13-3",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šaldyta žuvis",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/saldyta-zuvis/c/SH-13-3-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytos jūrų gėrybės",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/saldytos-juru-gerybes/c/SH-13-3-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytos krevetės",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/saldytos-krevetes/c/SH-13-3-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyti žuvų piršteliai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/saldyti-zuvu-pirsteliai/c/SH-13-3-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Krabų lazdelės",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/krabu-lazdeles/c/SH-13-3-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldyta mėsa",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/saldyta-mesa-zuvis-ir-juru-gerybes/saldyta-mesa/c/SH-13-3-14",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Ledai ",
                    "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledai-/c/SH-13-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Ledai porcijomis",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledai-/ledai-porcijomis/c/SH-13-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ledai indeliuose ir dėžutėse",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledai-/ledai-indeliuose-ir-dezutese/c/SH-13-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ledai BE",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledai-/ledai-be/c/SH-13-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Augaliniai ledai",
                            "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledai-/augaliniai-ledai/c/SH-13-1-22",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Ledo kubeliai",
                    "url": "/e-parduotuve/lt/produktai/saldytas-maistas/ledo-kubeliai/c/SH-13-1-3",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Bakalėja",
            "url": "/e-parduotuve/lt/produktai/bakaleja/c/SH-2",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/74b8af2f9dd7d272af0fa25fd2292bee397ab0d5-vqpdRZogSE",
            "descendants": [
                {
                    "name": "Specialusis maistas",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/c/SH-2-20",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Be glitimo",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/be-glitimo/c/SH-2-20-119",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Produktai be pridėtinio cukraus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/produktai-be-pridetinio-cukraus/c/SH-2-20-127",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Supermaistas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/supermaistas/c/SH-2-20-126",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sporto mityba",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/sporto-mityba/c/SH-2-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Be kiaušinių",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/specialusis-maistas/be-kiausiniu/c/SH-2-20-120",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Pasaulio skoniai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/c/SH-2-15",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kokosų pienas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/kokosu-pienas/c/SH-2-15-96",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/makaronai/c/SH-2-15-97",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sojos padažai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/sojos-padazai/c/SH-2-15-99",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tailando virtuvė",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/tailando-virtuve/c/SH-2-15-100",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Meksikos virtuvė",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/meksikos-virtuve/c/SH-2-15-98",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Japonų virtuvė",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/japonu-virtuve/c/SH-2-15-93",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kinų virtuvė",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/kinu-virtuve/c/SH-2-15-94",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitų šalių maistas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/pasaulio-skoniai/kitu-saliu-maistas/c/SH-2-15-95",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sausi pusryčiai ir batonėliai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/sausi-pusryciai-ir-batoneliai/c/SH-2-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sausi pusryčiai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/sausi-pusryciai-ir-batoneliai/sausi-pusryciai/c/SH-2-18-114",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Javainių batonėliai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/sausi-pusryciai-ir-batoneliai/javainiu-batoneliai/c/SH-2-18-113",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Granola",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/sausi-pusryciai-ir-batoneliai/granola/c/SH-2-18-115",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Konservuotas maistas",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/c/SH-2-10",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Konservuoti agurkai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-agurkai/c/SH-2-10-57",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti žirneliai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-zirneliai/c/SH-2-10-63",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti kukurūzai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-kukuruzai/c/SH-2-10-60",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti burokėliai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-burokeliai/c/SH-2-10-58",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotos paprikos ir pipirai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuotos-paprikos-ir-pipirai/c/SH-2-10-65",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saulėje džiovinti pomidorai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/sauleje-dziovinti-pomidorai/c/SH-2-10-55",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti grybai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-grybai/c/SH-2-10-59",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotos alyvuogės ",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuotos-alyvuoges-/c/SH-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotos žaliosios alyvuogės ",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuotos-zaliosios-alyvuoges-/c/SH-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti avinžirniai, pupelės ir lęšiai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-avinzirniai-pupeles-ir-lesiai/c/SH-2-10-66",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti pomidorai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-pomidorai/c/SH-2-10-61",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitos konservuotos daržovės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/kitos-konservuotos-darzoves/c/SH-2-10-56",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotos sriubos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuotos-sriubos/c/SH-2-10-68",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti patiekalai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-patiekalai/c/SH-2-10-67",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuoti vaisiai ir uogos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/konservuoti-vaisiai-ir-uogos/c/SH-2-10-62",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Uogienės, džemai ir tyrės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/uogienes-dzemai-ir-tyres/c/SH-2-10-72",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Medus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/medus/c/SH-2-10-69",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šokolado ir riešutų kremai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/sokolado-ir-riesutu-kremai/c/SH-2-10-70",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sirupai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konservuotas-maistas/sirupai/c/SH-22-51",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Aliejus ir actas",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/c/SH-2-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Alyvuogių aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/alyvuogiu-aliejus/c/SH-2-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saulėgrąžų aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/saulegrazu-aliejus/c/SH-2-1-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Rapsų aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/rapsu-aliejus/c/SH-2-1-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kokosų aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/kokosu-aliejus/c/SH-2-1-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vynuogių kauliukų aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/vynuogiu-kauliuku-aliejus/c/SH-2-1-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitas aliejus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/kitas-aliejus/c/SH-2-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Actas ir koncentruotos citrinų sultys",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/actas-ir-koncentruotos-citrinu-sultys/c/SH-2-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Balzaminis actas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/aliejus-ir-actas/balzaminis-actas/c/SH-2-1-8",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Makaronai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/c/SH-2-11",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Figūriniai makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/figuriniai-makaronai/c/SH-2-11-73",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ilgieji ir plokštieji makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/ilgieji-ir-plokstieji-makaronai/c/SH-2-11-74",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pilno grūdo makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/pilno-grudo-makaronai/c/SH-2-11-77",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vamzdeliniai ir lazanijos makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/vamzdeliniai-ir-lazanijos-makaronai/c/SH-2-11-76",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiaušinių makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/makaronai/kiausiniu-makaronai/c/SH-2-11-75",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kruopos",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/c/SH-2-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Grikiai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/grikiai/c/SH-2-6-36",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ryžiai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/ryziai/c/SH-2-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Miežinės ir perlinės kruopos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/miezines-ir-perlines-kruopos/c/SH-2-6-37",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Manų kruopos, bulguras, kuskusas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/manu-kruopos-bulguras-kuskusas/c/SH-2-6-39",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitos kruopos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/kitos-kruopos/c/SH-2-6-38",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sėlenos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/selenos/c/SH-2-5-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dribsniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/dribsniai/c/SH-2-18-112",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Greitai paruošiamos košės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/greitai-paruosiamos-koses/c/SH-2-5-36",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ankštiniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kruopos/ankstiniai/c/SH-2-17",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Miltai ir miltų mišiniai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/miltai-ir-miltu-misiniai/c/SH-2-12",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kvietiniai miltai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/miltai-ir-miltu-misiniai/kvietiniai-miltai/c/SH-2-12-80",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti miltai ir krakmolas",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/miltai-ir-miltu-misiniai/kiti-miltai-ir-krakmolas/c/SH-2-12-79",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Miltų mišiniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/miltai-ir-miltu-misiniai/miltu-misiniai/c/SH-2-12-81",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Miltų mišiniai be glitimo",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/miltai-ir-miltu-misiniai/miltu-misiniai-be-glitimo/c/SH-2-12-95",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Greitai paruošiamas maistas",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/c/SH-2-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Greitai paruošiamos bulvių košės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/greitai-paruosiamos-bulviu-koses/c/SH-2-4-25",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Greitai paruošiami makaronai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/greitai-paruosiami-makaronai/c/SH-2-4-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Greitai paruošiami padažai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/greitai-paruosiami-padazai/c/SH-2-4-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Greitai paruošiamos sriubos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/greitai-paruosiamos-sriubos/c/SH-2-4-28",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sultiniai ir jų kubeliai ",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/greitai-paruosiamas-maistas/sultiniai-ir-ju-kubeliai-/c/SH-2-4-22",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Padažai, garstyčios, krienai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/c/SH-2-14",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kečupai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/kecupai/c/SH-2-14-86",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pomidorų padažai ir pasta",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/pomidoru-padazai-ir-pasta/c/SH-2-14-92",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "BBQ padažai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/bbq-padazai/c/SH-20-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Padažai maisto ruošimui ir makaronams",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/padazai-maisto-ruosimui-ir-makaronams/c/SH-2-14-88",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Krienai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/krienai/c/SH-2-14-87",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Garstyčios",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/garstycios/c/SH-2-14-85",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pesto padažai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/pesto-padazai/c/SH-2-14-90",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Adžika",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/adzika/c/SH-2-14-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti padažai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/kiti-padazai/c/SH-2-14-93",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Užtepėlės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/padazai-garstycios-krienai/uztepeles/c/SH-2-14-10",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Prieskoniai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/c/SH-2-16",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Druska",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/druska/c/SH-2-16-101",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pipirai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/pipirai/c/SH-2-16-103",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Prieskoniai ir žolelės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/prieskoniai-ir-zoleles/c/SH-2-16-104",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Prieskonių mišiniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/prieskoniu-misiniai/c/SH-2-16-105",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Universalūs prieskoniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/universalus-prieskoniai/c/SH-2-16-106",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Marinatai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/prieskoniai/marinatai/c/SH-2-16-102",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Konditerijos priedai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/konditerijos-priedai/c/SH-2-9",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kepinių priedai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konditerijos-priedai/kepiniu-priedai/c/SH-2-9-47",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konditerijos papuošimai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konditerijos-priedai/konditerijos-papuosimai/c/SH-2-9-49",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Želė, kisielius, pudingai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/konditerijos-priedai/zele-kisielius-pudingai/c/SH-2-9-52",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Cukrus ir saldikliai",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/cukrus-ir-saldikliai/c/SH-2-9-50",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Rudasis cukrus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/cukrus-ir-saldikliai/rudasis-cukrus/c/SH-2-9-61",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Baltasis cukrus ",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/cukrus-ir-saldikliai/baltasis-cukrus-/c/SH-2-9-60",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saldikliai ir kitas cukrus",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/cukrus-ir-saldikliai/saldikliai-ir-kitas-cukrus/c/SH-2-9-62",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kava ir kakava",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/c/SH-2-8",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Malta kava",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/malta-kava/c/SH-2-8-45",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kavos pupelės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/kavos-pupeles/c/SH-2-8-44",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kavos kapsulės",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/kavos-kapsules/c/SH-2-8-43",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tirpioji kava",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/tirpioji-kava/c/SH-2-8-46",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kakava",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/kakava/c/SH-2-8-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Cikorijos gėrimai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/kava-ir-kakava/cikorijos-gerimai/c/SH-2-8-41",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Arbata",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/c/SH-2-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Juodoji arbata",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/juodoji-arbata/c/SH-2-2-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žalioji arbata",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/zalioji-arbata/c/SH-2-2-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žolelių arbata",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/zoleliu-arbata/c/SH-2-2-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vaisinė arbata",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/vaisine-arbata/c/SH-2-2-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitos arbatos",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/kitos-arbatos/c/SH-2-2-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Arbatų rinkiniai",
                            "url": "/e-parduotuve/lt/produktai/bakaleja/arbata/arbatu-rinkiniai/c/SH-2-2-10",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šventiniai rinkiniai ",
                    "url": "/e-parduotuve/lt/produktai/bakaleja/sventiniai-rinkiniai-/c/SH-2-9-51",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "RIMI konditerija ir kulinarija",
            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/c/SH-34",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_auto,q_auto:low,w_auto/ecom-cms/3955f0bae18f402ea5c7da6c65cc0916739dfc33-VBQfc5SeH1",
            "descendants": [
                {
                    "name": "Konditerijos gaminiai",
                    "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/c/SH-3-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kiti desertai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/kiti-desertai/c/SH-3-4-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pyragai ir vyniotiniai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/pyragai-ir-vyniotiniai/c/SH-3-4-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pyragaičiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/pyragaiciai/c/SH-3-4-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šakočiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/sakociai/c/SH-3-4-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šventiniai gaminiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/sventiniai-gaminiai/c/SH-3-4-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tortai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/konditerijos-gaminiai/tortai/c/SH-3-4-56",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Paruoštas maistas, kulinarija",
                    "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/c/SH-9-10",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Salotos ir mišrainės",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/salotos-ir-misraines/c/SH-9-10-32",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pagrindiniai patiekalai ",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/pagrindiniai-patiekalai-/c/SH-9-10-30",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lietiniai blyneliai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/lietiniai-blyneliai/c/SH-9-10-31",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Švieži makaronai, virtiniai ir tešla",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/sviezi-makaronai-virtiniai-ir-tesla/c/SH-9-10-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sušiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/susiai/c/SH-9-10-34",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sriubos ir sultiniai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/sriubos-ir-sultiniai/c/SH-9-10-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sumuštiniai, lavašai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/sumustiniai-lavasai/c/SH-9-10-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Užkandžiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/uzkandziai/c/SH-9-10-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Užtepėlės ir humusas",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/uztepeles-ir-humusas/c/SH-9-10-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pusgaminiai",
                            "url": "/e-parduotuve/lt/produktai/rimi-konditerija-ir-kulinarija/paruostas-maistas-kulinarija/pusgaminiai/c/SH-9-10-14",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Vaikų ir kūdikių prekės",
            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/c/SH-7",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/a133cc9462c092f79bf0e24276358a4873576868-X8xkxcBW85",
            "descendants": [
                {
                    "name": "Pieno mišiniai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/pieno-misiniai/c/SH-7-4",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Kūdikių košės",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-koses/c/SH-7-3-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sausos košės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-koses/sausos-koses/c/SH-7-3-4-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Paruoštos košės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-koses/paruostos-koses/c/SH-7-3-4-1",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kūdikių tyrelės",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-tyreles/c/SH-7-3",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Vaisinės ir desertinės kūdikių tyrelės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-tyreles/vaisines-ir-desertines-kudikiu-tyreles/c/SH-7-3-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mėsos ir daržovių tyrelės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-tyreles/mesos-ir-darzoviu-tyreles/c/SH-7-3-2",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kūdikių užkandžiai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-uzkandziai/c/SH-7-7",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Kūdikių gėrimai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-gerimai/c/SH-7-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Arbatos",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-gerimai/arbatos/c/SH-7-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sultys ir vanduo",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-gerimai/sultys-ir-vanduo/c/SH-7-1-2",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sauskelnės, servetėlės ir paklotai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/c/SH-7-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Ekologiškos sauskelnės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/ekologiskos-sauskelnes/c/SH-7-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sauskelnės su lipdukais",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/sauskelnes-su-lipdukais/c/SH-7-5-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sauskelnės-kelnaitės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/sauskelnes-kelnaites/c/SH-7-5-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Drėgnos ir popierinės servetėlės ",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/dregnos-ir-popierines-serveteles-/c/SH-7-5-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Paklotai ",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/paklotai-/c/SH-7-5-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Specialios sauskelnės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/sauskelnes-serveteles-ir-paklotai/specialios-sauskelnes/c/SH-7-5-4",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kūdikių higienos ir sveikatos prekės",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-higienos-ir-sveikatos-prekes/c/SH-7-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šampūnai, prausikliai, kremai ir losjonai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-higienos-ir-sveikatos-prekes/sampunai-prausikliai-kremai-ir-losjonai/c/SH-7-2-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Burnos priežiūros priemonės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-higienos-ir-sveikatos-prekes/burnos-prieziuros-priemones/c/SH-7-2-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Higienos ir sveikatos reikmenys",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-higienos-ir-sveikatos-prekes/higienos-ir-sveikatos-reikmenys/c/SH-7-2-56",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dantų pastos ir šepetėliai vaikams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/dantu-pastos-ir-sepeteliai-vaikams/c/SH-6-1-11",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kūdikių maitinimo reikmenys",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-maitinimo-reikmenys/c/SH-7-10",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Buteliukai ir žindukai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-maitinimo-reikmenys/buteliukai-ir-zindukai/c/SH-14-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Seilinukai, indai ir įrankiai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-maitinimo-reikmenys/seilinukai-indai-ir-irankiai/c/SH-7-10-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Čiulptukai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/kudikiu-maitinimo-reikmenys/ciulptukai/c/SH-14-1-3",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Prekės mamoms",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/prekes-mamoms/c/SH-7-2-8",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Vaikiškos pėdkelnės",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaikiskos-pedkelnes/c/SH-14-27",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Vaikų ir kūdikių žaislai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/c/SH-14-11",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kūdikių žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/kudikiu-zaislai/c/SH-14-1-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žaisliniai šautuvai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/zaisliniai-sautuvai/c/SH-14-2-20",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Stalo žaidimai ir dėlionės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/stalo-zaidimai-ir-deliones/c/SH-14-2-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Mašinėlės ir trasos",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/masineles-ir-trasos/c/SH-14-2-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Figūrėlės ir lėlės ",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/figureles-ir-leles-/c/SH-14-2-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lavinamieji žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/lavinamieji-zaislai/c/SH-14-2-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "LEGO konstruktoriai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/lego-konstruktoriai/c/SH-14-2-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Minkšti žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/minksti-zaislai/c/SH-14-2-32",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lauko žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/lauko-zaislai/c/SH-14-2-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Figūrėlės",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/figureles/c/SH-14-2-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Grožio rinkiniai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/grozio-rinkiniai/c/SH-14-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Robotai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/robotai/c/SH-14-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Profesijų žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/profesiju-zaislai/c/SH-14-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Muzikiniai žaislai",
                            "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaiku-ir-kudikiu-zaislai/muzikiniai-zaislai/c/SH-14-17",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Vaikiški apatiniai drabužiai",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/vaikiski-apatiniai-drabuziai/c/SH-14-28",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Skalbimo priemonės kūdikiams",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/skalbimo-priemones-kudikiams/c/SH-7-10-11",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Apatinis trikotažas",
                    "url": "/e-parduotuve/lt/produktai/vaiku-ir-kudikiu-prekes/apatinis-trikotazas/c/SH-7-12",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Saldumynai ir užkandžiai",
            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/c/SH-23",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/eb3b3a82ca46d49512ea3a160e407042814425be",
            "descendants": [
                {
                    "name": "Džiovinti vaisiai ir riešutai",
                    "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/c/SH-2-3",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sveriami džiovinti vaisiai ir riešutai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/sveriami-dziovinti-vaisiai-ir-riesutai/c/SH-2-3-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Džiovinti riešutai pakuotėse",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/dziovinti-riesutai-pakuotese/c/SH-2-3-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Džiovintos daržovės",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/dziovintos-darzoves/c/SH-2-3-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saulėgrąžos, moliūgų ir kitos sėklos",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/saulegrazos-moliugu-ir-kitos-seklos/c/SH-2-3-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Džiovinti vaisiai pakuotėse",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/dziovinti-vaisiai-ir-riesutai/dziovinti-vaisiai-pakuotese/c/SH-2-3-20",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Užkandžiai",
                    "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/c/SH-2-23",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Bulvių traškučiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/bulviu-traskuciai/c/SH-2-23-134",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Duonos traškučiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/duonos-traskuciai/c/SH-2-23-135",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Daržovių traškučiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/darzoviu-traskuciai/c/SH-2-23-140",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kukurūzų užkandžiai ir spragėsiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/kukuruzu-uzkandziai-ir-spragesiai/c/SH-2-23-137",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Trapučiai ir paplotėliai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/trapuciai-ir-paploteliai/c/SH-2-23-24",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Padažai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/padazai/c/SH-2-23-138",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti užkandžiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/uzkandziai/kiti-uzkandziai/c/SH-2-23-25",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Saldumynai",
                    "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/c/SH-12",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šokoladiniai batonėliai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/sokoladiniai-batoneliai/c/SH-12-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šokolado plytelės",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/sokolado-plyteles/c/SH-12-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saldainiai ir dražė",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/saldainiai-ir-draze/c/SH-12-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saldainiai dėžutėse",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/saldainiai-dezutese/c/SH-12-4-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sveriami saldainiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/sveriami-saldainiai/c/SH-12-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti saldumynai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/kiti-saldumynai/c/SH2-24-134",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Zefyrai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/zefyrai/c/SH-12-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Guminukai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/guminukai/c/SH-12-43",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pastilės ir kramtomoji guma",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/saldumynai/pastiles-ir-kramtomoji-guma/c/SH-23-8",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sausainiai ir vafliai",
                    "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/sausainiai-ir-vafliai/c/SH-2-101",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sausainiai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/sausainiai-ir-vafliai/sausainiai/c/SH-12-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vafliai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/sausainiai-ir-vafliai/vafliai/c/SH-12-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sveriami sausainiai ir vafliai",
                            "url": "/e-parduotuve/lt/produktai/saldumynai-ir-uzkandziai/sausainiai-ir-vafliai/sveriami-sausainiai-ir-vafliai/c/SH-3-4-10",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Gėrimai",
            "url": "/e-parduotuve/lt/produktai/gerimai/c/SH-4",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/a31ac2d921b1d1a98be063ea648f974628d3b7a6-aEZxoIGaRh",
            "descendants": [
                {
                    "name": "Vanduo",
                    "url": "/e-parduotuve/lt/produktai/gerimai/vanduo/c/SH-4-10",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Negazuotas vanduo",
                            "url": "/e-parduotuve/lt/produktai/gerimai/vanduo/negazuotas-vanduo/c/SH-4-10-17",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Gazuotas vanduo",
                            "url": "/e-parduotuve/lt/produktai/gerimai/vanduo/gazuotas-vanduo/c/SH-4-10-16",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Gaivieji gėrimai",
                    "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/c/SH-4-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Gazuoti vaisvandeniai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/gazuoti-vaisvandeniai/c/SH-4-2-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Negazuoti vaisvandeniai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/negazuoti-vaisvandeniai/c/SH-4-2-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Gira",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/gira/c/SH-4-2-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šalta ir fermentuota arbata",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/salta-ir-fermentuota-arbata/c/SH-4-2-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tonikai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/tonikai/c/SH-4-2-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sporto ir funkciniai gėrimai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/gaivieji-gerimai/sporto-ir-funkciniai-gerimai/c/SH-4-6",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sultys, nektarai ir sulčių gėrimai",
                    "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/c/SH-4-11-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sultys",
                            "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/sultys/c/SH-4-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nektarai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/nektarai/c/SH-4-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sulčių gėrimai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/sulciu-gerimai/c/SH-4-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šviežios sultys ir glotnučiai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/sviezios-sultys-ir-glotnuciai/c/SH-4-5-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sirupai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/sultys-nektarai-ir-sulciu-gerimai/sirupai/c/SH-4-5",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Energiniai gėrimai",
                    "url": "/e-parduotuve/lt/produktai/gerimai/energiniai-gerimai/c/SH-4-1",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Nealkoholiniai gėrimai",
                    "url": "/e-parduotuve/lt/produktai/gerimai/nealkoholiniai-gerimai/c/SH-07-7-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Nealkoholinis alus",
                            "url": "/e-parduotuve/lt/produktai/gerimai/nealkoholiniai-gerimai/nealkoholinis-alus/c/SH-07-7-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": " Nealkoholinis vynas",
                            "url": "/e-parduotuve/lt/produktai/gerimai/nealkoholiniai-gerimai/-nealkoholinis-vynas/c/SH-07-7-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nealkoholinis sidras ir kokteiliai",
                            "url": "/e-parduotuve/lt/produktai/gerimai/nealkoholiniai-gerimai/nealkoholinis-sidras-ir-kokteiliai/c/SH-07-7-3",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Alkoholiniai ir nealkoholiniai gėrimai",
            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/c/SH-1",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/e74762cdb27874e161addfdf7ad791af31e71423",
            "descendants": [
                {
                    "name": "Nealkoholiniai gėrimai",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/nealkoholiniai-gerimai/c/SH-1-7",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Nealkoholinis alus",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/nealkoholiniai-gerimai/nealkoholinis-alus/c/SH-1-7-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nealkoholinis sidras ir kokteiliai",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/nealkoholiniai-gerimai/nealkoholinis-sidras-ir-kokteiliai/c/SH-1-7-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nealkoholinis vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/nealkoholiniai-gerimai/nealkoholinis-vynas/c/SH-1-7-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kiti nealkoholiniai gėrimai ",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/nealkoholiniai-gerimai/kiti-nealkoholiniai-gerimai-/c/SH-1-7-14",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Alus",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/c/SH-1-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šviesus alus",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/sviesus-alus/c/SH-1-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tamsus alus",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/tamsus-alus/c/SH-1-1-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Alaus pakuotės",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/alaus-pakuotes/c/SH-1-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitų šalių alus ir alaus kokteiliai",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/kitu-saliu-alus-ir-alaus-kokteiliai/c/SH-1-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Alaus kokteiliai",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/alus/alaus-kokteiliai/c/SH-1-1-1",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sidras",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/sidras/c/SH-1-9",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Kokteiliai",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/kokteiliai/c/SH-1-5",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Vynas",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/c/SH-1-13",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šampanas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/sampanas/c/SH-1-13-28",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Putojantis vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/putojantis-vynas/c/SH-1-13-24",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vermutas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/vermutas/c/SH-1-13-30",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Baltasis vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/baltasis-vynas/c/SH-1-13-23",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Raudonasis vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/raudonasis-vynas/c/SH-1-13-25",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Rausvasis vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/rausvasis-vynas/c/SH-1-13-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Stiprintas vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/stiprintas-vynas/c/SH-1-13-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vaisių ir uogų vynas",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/vaisiu-ir-uogu-vynas/c/SH-1-13-29",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sezoniniai vyno gėrimai",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/vynas/sezoniniai-vyno-gerimai/c/SH-1-13-6",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Romas",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/romas/c/SH-1-8",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Džinas",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/dzinas/c/SH-1-4",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Tekila",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/tekila/c/SH-1-10",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Konjakas",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/konjakas/c/SH-1-6",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Brendis",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/brendis/c/SH-1-2",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Trauktinė ir likeris",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/trauktine-ir-likeris/c/SH-1-11",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Degtinė",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/degtine/c/SH-1-3",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Viskis",
                    "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/viskis/c/SH-1-12",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Airiškas viskis",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/viskis/airiskas-viskis/c/SH-1-12-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Škotiškas viskis",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/viskis/skotiskas-viskis/c/SH-1-12-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "JAV ir Kanados viskis (Burbonas)",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/viskis/jav-ir-kanados-viskis-burbonas-/c/SH-1-12-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitas viskis",
                            "url": "/e-parduotuve/lt/produktai/alkoholiniai-ir-nealkoholiniai-gerimai/viskis/kitas-viskis/c/SH-1-12-20",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "Kosmetika ir higiena",
            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/c/SH-6",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/a68e9019618c2caf007e99602ac89335aaf35c3a-hnuWJoSpSr",
            "descendants": [
                {
                    "name": "Burnos priežiūros priemonės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/c/SH-6-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Dantų pastos",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/dantu-pastos/c/SH-6-1-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dantų šepetėliai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/dantu-sepeteliai/c/SH-6-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dantų siūlai ir krapštukai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/dantu-siulai-ir-krapstukai/c/SH-6-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalavimo skysčiai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/skalavimo-skysciai/c/SH-6-1-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dantų protezų priežiūrai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/burnos-prieziuros-priemones/dantu-protezu-prieziurai/c/SH-6-1-10",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Veido priežiūros priemonės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/c/SH-6-11",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Veido kremai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/veido-kremai/c/SH-6-11-61",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veido kaukės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/veido-kaukes/c/SH-6-11-60",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veido priežiūros priemonės problematiškai odai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/veido-prieziuros-priemones-problematiskai-odai/c/SH-6-11-63",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veido prausikliai, tonikai ir makiažo valikliai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/veido-prausikliai-tonikai-ir-makiazo-valikliai/c/SH-6-11-62",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veido priežiūros priemonės vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/veido-prieziuros-priemones-vyrams/c/SH-6-11-64",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lūpų balzamai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/lupu-balzamai/c/SH-6-11-58",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vatos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/veido-prieziuros-priemones/vatos-gaminiai/c/SH-6-11-59",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Plaukų priežiūros priemonės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/c/SH-6-8",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Šampūnai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/sampunai/c/SH-6-8-42",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kondicionieriai ir balzamai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/kondicionieriai-ir-balzamai/c/SH-6-8-36",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Plaukų kaukės, serumai ir aliejai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/plauku-kaukes-serumai-ir-aliejai/c/SH-6-8-40",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Plaukų priežiūros priemonės vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/plauku-prieziuros-priemones-vyrams/c/SH-6-8-41",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Plaukų lakas",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/plauku-lakas/c/SH-6-8-47",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Plaukų formavimo priemonės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/plauku-formavimo-priemones/c/SH-6-8-93",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Plaukų dažai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/plauku-dazai/c/SH-6-8-38",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šukos ir šepečiai plaukams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/plauku-prieziuros-priemones/sukos-ir-sepeciai-plaukams/c/SH-6-8-11",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kūno priežiūros priemonės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/c/SH-6-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Dušo želės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/duso-zeles/c/SH-6-5-24",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skystas muilas",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/skystas-muilas/c/SH-6-5-30",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Gabalinis muilas",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/gabalinis-muilas/c/SH-6-5-25",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kūno losjonai, kremai, pieneliai ir šveitikliai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/kuno-losjonai-kremai-pieneliai-ir-sveitikliai/c/SH-6-5-26",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vonios putos ir druska",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/vonios-putos-ir-druska/c/SH-6-5-31",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ekstraktai voniai ir pirties prekės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/ekstraktai-voniai-ir-pirties-prekes/c/SH-6-5-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Saulės kosmetika",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/saules-kosmetika/c/SH-6-5-29",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Drėgnos servetėlės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/dregnos-serveteles/c/SH-6-5-23",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vonios kempinės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kuno-prieziuros-priemones/vonios-kempines/c/SH-6-16",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Rankų ir pėdų priežiūros priemonės ",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/ranku-ir-pedu-prieziuros-priemones-/c/SH-6-13-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Rankų kremai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/ranku-ir-pedu-prieziuros-priemones-/ranku-kremai/c/SH-6-5-28",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pėdų priežiūros priemonės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/ranku-ir-pedu-prieziuros-priemones-/pedu-prieziuros-priemones/c/SH-6-5-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nagų priežiūrai ",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/ranku-ir-pedu-prieziuros-priemones-/nagu-prieziurai-/c/SH-6-13-3",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Intymios higienos prekės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/c/SH-6-3",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Higieniniai įklotai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/higieniniai-iklotai/c/SH-6-3-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Higieniniai paketai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/higieniniai-paketai/c/SH-6-3-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tamponai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/tamponai/c/SH-6-3-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Menstruacinės taurelės,kelnaitės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/menstruacines-taureles-kelnaites/c/SH-6-3-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Intymios higienos prausikliai ir servetėlės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/intymios-higienos-prausikliai-ir-serveteles/c/SH-6-3-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Urologiniai įklotai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/intymios-higienos-prekes/urologiniai-iklotai/c/SH-6-3-9",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Skutimosi ir depiliacijos prekės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/c/SH-6-9",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Skustuvai ir skutimosi peiliukai moterims",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/skustuvai-ir-skutimosi-peiliukai-moterims/c/SH-6-9-47",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Depiliacijos ir skutimosi kosmetika moterims",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/depiliacijos-ir-skutimosi-kosmetika-moterims/c/SH-6-9-44",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skutimosi kosmetika vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/skutimosi-kosmetika-vyrams/c/SH-6-9-49",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Po skutimosi kosmetika vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/po-skutimosi-kosmetika-vyrams/c/SH-6-9-46",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skustuvai ir skutimosi peiliukai vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/skustuvai-ir-skutimosi-peiliukai-vyrams/c/SH-6-9-48",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Barzdos priežiūros priemonės vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/skutimosi-ir-depiliacijos-prekes/barzdos-prieziuros-priemones-vyrams/c/SH-6-9-43",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Dezodorantai",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/dezodorantai/c/SH-6-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Dezodorantai moterims",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/dezodorantai/dezodorantai-moterims/c/SH-6-2-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dezodorantai vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/dezodorantai/dezodorantai-vyrams/c/SH-6-2-7",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ekologiški, natūralūs dezodorantai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/dezodorantai/ekologiski-naturalus-dezodorantai/c/SH-6-2-9",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kvepalai",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kvepalai/c/SH-6-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kvepalai moterims",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kvepalai/kvepalai-moterims/c/SH-6-6-32",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kvepalai vyrams",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kvepalai/kvepalai-vyrams/c/SH-6-6-33",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Dekoratyvinė kosmetika",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/dekoratyvine-kosmetika/c/SH-6-25",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Medicinos prekės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/c/SH-6-7",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Vitaminai, maisto papildai ir nereceptiniai vaistai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/vitaminai-maisto-papildai-ir-nereceptiniai-vaistai/c/SH-6-7-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pleistrai ir tvarsčiai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/pleistrai-ir-tvarsciai/c/SH-6-7-34",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Veido kaukės ir dezinfekcinės priemonės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/veido-kaukes-ir-dezinfekcines-priemones/c/SH-6-7-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Nėštumo testai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/nestumo-testai/c/SH-6-3-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lubrikantai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/lubrikantai/c/SH-6-3-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Prezervatyvai",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/medicinos-prekes/prezervatyvai/c/SH-6-3-15",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Pėdkelnės ir kojinės",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/c/SH-6-15",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Moteriškos pėdkelnės ir kojinės virš kelių",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/moteriskos-pedkelnes-ir-kojines-virs-keliu/c/SH-6-10-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Moteriškos kojinės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/moteriskos-kojines/c/SH-6-10-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vyriškos kojinės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/vyriskos-kojines/c/SH-6-10-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vaikiškos pėdkelnės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/vaikiskos-pedkelnes/c/SH-6-10-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vaikiškos kojinės",
                            "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/pedkelnes-ir-kojines/vaikiskos-kojines/c/SH-6-14",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Kosmetikos rinkiniai",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/kosmetikos-rinkiniai/c/SH-6-35",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Gamtai draugiški produktai",
                    "url": "/e-parduotuve/lt/produktai/kosmetika-ir-higiena/gamtai-draugiski-produktai/c/SH-6-36",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Buitinės chemijos ir valymo priemonės",
            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/c/SH-16",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/4e2471e7010d2c1e8c04cfffb4ba4415c185e7bc-6dtVpyM7kf",
            "descendants": [
                {
                    "name": "Tualetinis popierius, rankšluosčiai ir servetėlės ",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualetinis-popierius-ranksluosciai-ir-serveteles-/c/SH-6-13-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Tualetinis popierius",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualetinis-popierius-ranksluosciai-ir-serveteles-/tualetinis-popierius/c/SH-10-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Popieriniai rankšluosčiai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualetinis-popierius-ranksluosciai-ir-serveteles-/popieriniai-ranksluosciai/c/SH-10-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kosmetinės servetėlės ir nosinaitės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualetinis-popierius-ranksluosciai-ir-serveteles-/kosmetines-serveteles-ir-nosinaites/c/SH-10-11",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Skalbimo priemonės",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/c/SH-10-36-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Skysti skalbikliai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skysti-skalbikliai/c/SH-10-23-60",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalbimo milteliai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skalbimo-milteliai/c/SH-10-23-57",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalbimo kapsulės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skalbimo-kapsules/c/SH-10-36-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Muilas skalbimui",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/muilas-skalbimui/c/SH-16-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalbinių minkštikliai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skalbiniu-minkstikliai/c/SH-10-23-59",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dėmių valikliai ir balinimo priemonės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/demiu-valikliai-ir-balinimo-priemones/c/SH-10-23-52",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalbinių servetėlės ir dezinfekantai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skalbiniu-serveteles-ir-dezinfekantai/c/SH-10-23-61",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skalbimo priemonės kūdikiams",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbimo-priemones/skalbimo-priemones-kudikiams/c/SH-10-22",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Skalbyklių priežiūros priemonės",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/skalbykliu-prieziuros-priemones/c/SH-16-10",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Indų plovimo priemonės",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/indu-plovimo-priemones/c/SH-10-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Indaplovių priemonės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/indu-plovimo-priemones/indaploviu-priemones/c/SH-10-6-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Indų plovikliai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/indu-plovimo-priemones/indu-plovikliai/c/SH-10-6-7",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Virtuvės ir vonios kambario valikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/virtuves-ir-vonios-kambario-valikliai/c/SH-10-32",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Tualeto valikliai - gaivikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualeto-valikliai---gaivikliai/c/SH-10-29",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Pakabinami tualeto valikliai - gaivikliai ",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualeto-valikliai---gaivikliai/pakabinami-tualeto-valikliai---gaivikliai-/c/SH-10-29-77",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Tualeto valikliai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/tualeto-valikliai---gaivikliai/tualeto-valikliai/c/SH-10-29-78",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Langų valikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/langu-valikliai/c/SH-10-12",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Grindų valikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/grindu-valikliai/c/SH-10-5",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Kiti valikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/kiti-valikliai/c/SH-10-18",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Namų valymo reikmenys ",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/namu-valymo-reikmenys-/c/SH-10-30",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Kempinėlės ir šluostės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/namu-valymo-reikmenys-/kempineles-ir-sluostes/c/SH-10-30-79",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pirštinės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/namu-valymo-reikmenys-/pirstines/c/SH-10-30-80",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šepečiai, šluotos",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/namu-valymo-reikmenys-/sepeciai-sluotos/c/SH-10-30-81",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šiukšlių maišai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/namu-valymo-reikmenys-/siuksliu-maisai/c/SH-10-25",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Parazitų naikinimo priemonės",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/parazitu-naikinimo-priemones/c/SH-10-20",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Avalynės priežiūra ir aksesuarai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/avalynes-prieziura-ir-aksesuarai/c/SH-10-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Avalynės aksesuarai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/avalynes-prieziura-ir-aksesuarai/avalynes-aksesuarai/c/SH-10-2-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Batų kempinėlės ir šepečiai",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/avalynes-prieziura-ir-aksesuarai/batu-kempineles-ir-sepeciai/c/SH-10-2-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Batų priežiūros priemonės",
                            "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/avalynes-prieziura-ir-aksesuarai/batu-prieziuros-priemones/c/SH-10-2-3",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Oro gaivikliai",
                    "url": "/e-parduotuve/lt/produktai/buitines-chemijos-ir-valymo-priemones/oro-gaivikliai/c/SH-10-17",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Namų ūkio, gyvūnų ir laisvalaikio prekės",
            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/c/SH-10",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/84ed9285035fb3383ace03a464439d9ad9c827a3-CVEF1vqmZM",
            "descendants": [
                {
                    "name": "Gyvūnų prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/c/SH-5",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Sausas kačių maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/sausas-kaciu-maistas/c/SH-5-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotas kačių maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/konservuotas-kaciu-maistas/c/SH-5-2-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skanėstai katėms",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/skanestai-katems/c/SH-5-2-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sausas šunų maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/sausas-sunu-maistas/c/SH-5-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Konservuotas šunų maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/konservuotas-sunu-maistas/c/SH-5-3-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Skanėstai šunims",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/skanestai-sunims/c/SH-5-3-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kitų gyvūnų maistas ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/kitu-gyvunu-maistas-/c/SH-5-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kraikas ir higienos reikmenys",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/kraikas-ir-higienos-reikmenys/c/SH-5-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Gyvūnų žaislai ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/gyvunu-zaislai-/c/SH-5-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytas šunų maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/saldytas-sunu-maistas/c/SH-5-3-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Šaldytas kačių maistas",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/gyvunu-prekes/saldytas-kaciu-maistas/c/SH-5-2-5",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Namų apyvokos reikmenys ",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/c/SH-10-37",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Baterijos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/baterijos/c/SH-10-14-27",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Ilgintuvai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/ilgintuvai/c/SH-10-14-32",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lemputės ir žibintuvėliai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/lemputes-ir-zibintuveliai/c/SH-10-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Klijai ir lipnios juostos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/klijai-ir-lipnios-juostos/c/SH-10-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Degtukai ir žiebtuvėliai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/degtukai-ir-ziebtuveliai/c/SH-10-37-84",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žvakės ir smilkalai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/zvakes-ir-smilkalai/c/SH-10-36",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Dulkių siurblių maišeliai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/dulkiu-siurbliu-maiseliai/c/SH-10-16-35",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kapų žvakės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/kapu-zvakes/c/SH-10-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lietpalčiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/lietpalciai/c/SH-10-16",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Siuvimo ir mezgimo priemonės ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/siuvimo-ir-mezgimo-priemones-/c/SH-10-40",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Krepšiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/namu-apyvokos-reikmenys-/krepsiai/c/SH-10-41",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Virtuvės ir stalo serviravimo reikmenys ",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/c/SH-10-38",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Serviravimo indai ir įrankiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/serviravimo-indai-ir-irankiai/c/SH-10-38-2",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Maisto laikymo indai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/maisto-laikymo-indai/c/SH-10-38-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Puodai, keptuvės ir kepimo formos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/puodai-keptuves-ir-kepimo-formos/c/SH-10-38-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Arbatinukai, kavinukai, vandens filtrai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/arbatinukai-kavinukai-vandens-filtrai/c/SH-10-33-90",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Maisto gaminimo ir laikymo medžiagos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/maisto-gaminimo-ir-laikymo-medziagos/c/SH-10-33-92",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Maisto ruošimo įrankiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/maisto-ruosimo-irankiai/c/SH-10-33-99",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Gertuvės ir termosai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/gertuves-ir-termosai/c/SH-10-38-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Virtuvės tekstilė",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/virtuves-tekstile/c/SH-10-33-115",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vandens filtrai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/vandens-filtrai/c/SH-10-33-112",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Pjaustymo lentelės ir padėklai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/virtuves-ir-stalo-serviravimo-reikmenys-/pjaustymo-lenteles-ir-padeklai/c/SH-10-38-27",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Laisvalaikio prekės ",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/c/SH-63-09",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Griliui",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/griliui/c/SH-10-52",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Priemonės nuo uodų ir erkių",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/priemones-nuo-uodu-ir-erkiu/c/SH-10-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Stovyklavimo prekės ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/stovyklavimo-prekes-/c/SH-63-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Lauko pramogų prekės ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/lauko-pramogu-prekes-/c/SH-63-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vandens pramogų prekės ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/laisvalaikio-prekes-/vandens-pramogu-prekes-/c/SH-63-12",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Sporto ir aktyvaus laisvalaikio prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/sporto-ir-aktyvaus-laisvalaikio-prekes/c/SH-10-909",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Sodo prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/sodo-prekes/c/SH-10-54",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Trąšos ir dirvožemio mišiniai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/sodo-prekes/trasos-ir-dirvozemio-misiniai/c/SH-10-64",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sėklos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/sodo-prekes/seklos/c/SH-10-57",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sodo ir daržo įrankiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/sodo-prekes/sodo-ir-darzo-irankiai/c/SH-10-67",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Knygos ir žurnalai",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/c/SH-10-4",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Knygos vaikams",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/knygos-vaikams/c/SH-10-4-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Knygos suaugusiems",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/knygos-suaugusiems/c/SH-10-4-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žurnalai vaikams ir jaunimui",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/zurnalai-vaikams-ir-jaunimui/c/SH-10-4-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žurnalai suaugusiems",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/zurnalai-suaugusiems/c/SH-10-4-5",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kryžiažodžiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/kryziazodziai/c/SH-10-4-8",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Kalendoriai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/knygos-ir-zurnalai/kalendoriai/c/SH-10-4-7",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": " Jūsų šventei",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/-jusu-sventei/c/SH-63-08",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Dovanų pakavimo priemonės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/-jusu-sventei/dovanu-pakavimo-priemones/c/SH-10-28-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Proginė atributika",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/-jusu-sventei/progine-atributika/c/SH-10-24-65",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Vienkartiniai indai ir servetėlės ",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/-jusu-sventei/vienkartiniai-indai-ir-serveteles-/c/SH-10-31",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Mokyklinės ir kanceliarinės prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/c/SH-10-7",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Penalai, kuprinės, krepšiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/penalai-kuprines-krepsiai/c/SH-10-7-15",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Rašymo priemonės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/rasymo-priemones/c/SH-10-7-12",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Piešimo  ir lipdymo priemonės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/piesimo-ir-lipdymo-priemones/c/SH-10-7-9",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Žirklės, liniuotės, drožtukai, trintukai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/zirkles-liniuotes-droztukai-trintukai/c/SH-10-7-13",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Klijai ir lipnios juostos",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/klijai-ir-lipnios-juostos/c/SH-10-7-52",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Popierius ir popieriaus produktai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/popierius-ir-popieriaus-produktai/c/SH-10-7-11",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sąsiuviniai langeliais",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/sasiuviniai-langeliais/c/SH-10-7-21",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Sąsiuviniai linijomis",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/sasiuviniai-linijomis/c/SH-10-7-22",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Segtuvai, aplankai ir įmautės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/segtuvai-aplankai-ir-imautes/c/SH-10-7-18",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Atšvaitai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/mokyklines-ir-kanceliarines-prekes/atsvaitai/c/SH-10-7-16",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Buitinė technika ir elektronika ",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/buitine-technika-ir-elektronika-/c/SH-10-37-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Elektronikos prekės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/buitine-technika-ir-elektronika-/elektronikos-prekes/c/SH-10-10",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Smulki virtuvės technika",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/buitine-technika-ir-elektronika-/smulki-virtuves-technika/c/SH-10-16-44",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Namų apyvokos prietaisai ir įrankiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/buitine-technika-ir-elektronika-/namu-apyvokos-prietaisai-ir-irankiai/c/SH-10-37-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Grožio ir sveikatos technika",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/buitine-technika-ir-elektronika-/grozio-ir-sveikatos-technika/c/SH-10-16-36",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Automobilių prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/c/SH-8-1",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Automobilinė chemija",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/automobiline-chemija/c/SH-8-1-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Automobilių švaros priemonės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/automobiliu-svaros-priemones/c/SH-8-1-4",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Automobilių oro gaivikliai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/automobiliu-oro-gaivikliai/c/SH-8-1-3",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Automobilių aksesuarai ir priedai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/automobiliu-aksesuarai-ir-priedai/c/SH-8-1-6",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Automobilio lemputės",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/automobiliu-prekes/automobilio-lemputes/c/SH-8-1-2",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Suvenyrai",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/suvenyrai/c/SH-10-88",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Daiktų ir drabužių priežiūros prekės",
                    "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/daiktu-ir-drabuziu-prieziuros-prekes/c/SH-23-6",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Drabužių priežiūra",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/daiktu-ir-drabuziu-prieziuros-prekes/drabuziu-prieziura/c/SH-10-19",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Daiktų laikymo dėžės ir krepšiai",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/daiktu-ir-drabuziu-prieziuros-prekes/daiktu-laikymo-dezes-ir-krepsiai/c/SH-10-37-14",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Namų tekstilė",
                            "url": "/e-parduotuve/lt/produktai/namu-ukio-gyvunu-ir-laisvalaikio-prekes/daiktu-ir-drabuziu-prieziuros-prekes/namu-tekstile/c/SH-10-37-85",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                }
            ]
        },
        {
            "name": "„Vikis“ – prekių krautuvėlė",
            "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/c/SH-18",
            "iconUrl": "https://rimibaltic-web-res.cloudinary.com/image/upload/c_limit,dpr_auto,f_png,q_auto:low,w_auto/ecom-cms/a0e50d3b0b6f72bbb7890364809ebf6082fee77c-KYnOSZ1a0R",
            "descendants": [
                {
                    "name": "Augaliniai produktai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/augaliniai-produktai/c/SH-18-3",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Pieno produktai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/pieno-produktai/c/SH-48-3",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Sūris ir kiaušiniai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/suris-ir-kiausiniai/c/SH-48-30",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Duonos gaminiai ir konditerija",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/duonos-gaminiai-ir-konditerija/c/SH-48-36",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Apdorotos mėsos ir žuvies gaminiai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/apdorotos-mesos-ir-zuvies-gaminiai/c/SH-18-2",
                    "iconUrl": null,
                    "descendants": [
                        {
                            "name": "Apdorotos mėsos gaminiai",
                            "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/apdorotos-mesos-ir-zuvies-gaminiai/apdorotos-mesos-gaminiai/c/SH-18-2-1",
                            "iconUrl": null,
                            "descendants": []
                        },
                        {
                            "name": "Apdorotos žuvies gaminiai",
                            "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/apdorotos-mesos-ir-zuvies-gaminiai/apdorotos-zuvies-gaminiai/c/SH-18-2-2",
                            "iconUrl": null,
                            "descendants": []
                        }
                    ]
                },
                {
                    "name": "Šaldyti produktai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/saldyti-produktai/c/SH-18-1",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Bakalėja",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/bakaleja/c/SH-18-45",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Saldumynai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/saldumynai/c/SH-18-46",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": " Užkandžiai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/-uzkandziai/c/SH-18-47",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Gėrimai",
                    "url": "/e-parduotuve/lt/produktai/-vikis-prekiu-krautuvele/gerimai/c/SH-18-94",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        },
        {
            "name": "Parama, paslaugos",
            "url": "/e-parduotuve/lt/produktai/parama-paslaugos/c/SH-20",
            "iconUrl": null,
            "descendants": [
                {
                    "name": "Pristatymo abonementas",
                    "url": "/e-parduotuve/lt/produktai/parama-paslaugos/pristatymo-abonementas/c/SH20-1",
                    "iconUrl": null,
                    "descendants": []
                },
                {
                    "name": "Parama",
                    "url": "/e-parduotuve/lt/produktai/parama-paslaugos/parama/c/SH-20-2",
                    "iconUrl": null,
                    "descendants": []
                }
            ]
        }
    ]
}
';

        $data = json_decode($jsonData, true);

        $excludedCategories = [
            'RIMI konditerija ir kulinarija',
            '„Vikis“ – prekių krautuvėlė',
            'Parama, paslaugos'
        ];

        foreach ($data['categories'] as $category) {
            if (!in_array($category['name'], $excludedCategories)) {
                $this->importCategory($category);
            }
        }

        $this->info('Categories imported successfully!');
    }

    private function importCategory($categoryData, $parentId = null)
    {
        $slug = Str::slug($categoryData['name']);

        if (Category::where('slug', $slug)->exists()) {
            $this->info("Skipping category '{$categoryData['name']}' - slug already exists");
            return;
        }

        $category = Category::create([
            'name' => $categoryData['name'],
            'slug' => $slug,
            'description' => null,
            'parent_id' => $parentId,
        ]);

        if (!empty($categoryData['descendants'])) {
            foreach ($categoryData['descendants'] as $child) {
                $this->importCategory($child, $category->id);
            }
        }
    }
}
