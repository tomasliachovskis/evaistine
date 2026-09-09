# Keyword page idėjos (maisto/chemijos, Maxima/Rimi/Norfa/Iki/Lidl fokusas)

Metodika: (1) tikrinu tikrą mėn. paieškų apimtį LT per onebeyondsearch.com google-search-volume
įrankį (raw keyword, be "akcija"/"kaina" priesagos — pastarosios rodo ~0), (2) tikrinu ar realiai
turime tokių prekių `products` lentelėje (`name LIKE`) ir kiek jų su aktyvia nuolaida. Į galutinį
sąrašą įtraukiu tik tuos, kur abu kriterijai geri. Jau esami 240 keyword pages praleidžiami.

## PATVIRTINTA (ankstesnė sesija)

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida |
|---|---|---|---|
| oro gaiviklis | 260 | 203 | 122 |
| skalbinių minkštiklis | 210 | 110 | 74 |
| burnos skalavimo skystis | 480 | 55 | 22 |
| kukurūzai | 320 | 332 | 65 |
| garstyčios | 480 | 65 | 13 |
| razinos | 260 | 44 | 8 |
| kramtomoji guma | 320 | 37 | 3 |
| margarinas | 320 | 24 | 5 |
| baliklis | 320 | 24 | 9 |

Atmesta: vata (dviprasmiška — cukraus vata vs kosmetinė vata), kondicionierius plaukams
(tikslios frazės prekių 0, reikėtų platesnio matcho), augalinis pienas (prekių 0).

---

## Iteracijos (naujos idėjos)

### Iteracija 1 — buitinė chemija / pakavimo prekės / šviežios daržovės

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| alyvuogės | 140 | 295 | 61 | ✅ |
| pupelės | 880 | 237 | 54 | ✅ (dalis rezultatų — kavos pupelės, bet realių pupelių irgi daug) |
| lęšiai | 880 | 106 | 20 | ✅ |
| folija | 320 | 24 | 10 | ✅ |
| kepimo popierius | 260 | 20 | 11 | ✅ |
| grybai | 3600 (sezoniniai svyravimai) | 36 | 7 | ⚠️ Ribinis — mažai aktyvių, dalis paieškų greičiausiai apie grybavimą, ne pirkimą |
| brokoliai | 390 | 23 | 3 | ❌ Per mažai aktyvių |
| burokėliai | 170 | 139 | 18 | ⚠️ Rezultatuose daug sėklų paketėlių (sodo prekės), ne maisto — reikėtų exclude_terms |
| maistinė plėvelė | 90 | 4 | 2 | ❌ Per mažai prekių |
| vienkartinės pirštinės | 210 | 3 | 0 | ❌ Prekių nėra su nuolaida |
| stiklo valiklis | 90 | 7 | 1 | ❌ Per mažai |
| žiediniai kopūstai | 170 | 6 | 0 | ❌ |
| porai | 480 | 11 | 1 | ❌ |
| avinžirniai | 1000 | 35 | 1 | ❌ Geras paieškų kiekis, bet mūsų kataloge beveik nėra su nuolaida |

### Iteracija 2 — riešutai/sėklos/padažai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| migdolai | 590 | 135 | 39 | ✅ Stiprus |
| chia sėklos | 4400 | 25 | 5 | ⚠️ Milžiniška paieškų apimtis, bet mažai aktyvių — verta, jei planuojam plėsti šią kategoriją |
| anakardžiai | 140 | 44 | 13 | ✅ |
| linų sėmenys | 590 | 23 | 5 | ⚠️ Geras volume, mažoka aktyvių — ribinis |
| moliūgų sėklos | 210 | 35 | 4 | ❌ Per mažai aktyvių |
| pesto padažas | 140 | 11 | 3 | ❌ Per mažai |
| kokosų drožlės | 260 | 3 | 1 | ❌ Per mažai prekių |
| salotų padažas | 480 | 7 | 0 | ❌ Nėra su nuolaida |
| barbekiu padažas | 210 | 10 | 0 | ❌ Nėra su nuolaida |
| saulėgrąžų sėklos | 90 | 19 | 6 | ❌ Per mažai paieškų |
| soja padažas / čili padažas / sezamo sėklos / skalbimo gelis / popieriniai indai | ≤70 | — | — | ❌ Per mažai paieškų |

### Iteracija 3 — konservai, aliejai, augaliniai pieno pakaitalai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| sardinės | 4400 | 22 | 7 | ✅ Labai stiprus |
| alyvuogių aliejus | 2900 | 110 | 21 | ✅ Stiprus |
| pomidorų padažas | 590 | 117 | 14 | ✅ |
| avižiniai dribsniai | 590 | 37 | 7 | ✅ |
| rapsų aliejus | 210 | 30 | 9 | ✅ |
| šprotai | 390 | 25 | 6 | ✅ |
| kokosų aliejus | 590 | 21 | 2 | ❌ Per mažai aktyvių (patikslinta gramatika — "kokosų", ne "kokoso") |
| riešutų sviestas | 720 | 0 | 0 | ❌ Tokios prekės mūsų kataloge nėra |
| kokosų pienas / migdolų pienas | 170 kiekvienas | 0 | 0 | ❌ Prekės vadinamos "gėrimas" (pvz. "migdolų gėrimas"), ne "pienas" — pavadinimo neatitikimas, ne realus trūkumas |
| žemės riešutų sviestas / kukurūzų dribsniai / tuno konservai / picos padažas / musli batonėliai | ≤170 | — | — | ❌ Per mažai paieškų arba prekių |

### Iteracija 4 — asmens higiena / vaistinės prekės

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| prezervatyvai | 2400 | 58 | 29 | ✅ Labai stiprus |
| veido kaukė | 1600 | 158 | 33 | ✅ Stiprus |
| micelinis vanduo | 720 | 42 | 17 | ✅ |
| lūpų balzamas | 320 | 83 | 33 | ✅ |
| rankų kremas | 260 | 63 | 30 | ✅ |
| vitaminas D | 8100 | 4 (tikslia fraze) | 0 | ❌ Tikriausiai kitokia frazė kataloge (pvz. "D3 vitaminas") — reikėtų atskiro patikrinimo |
| kolagenas | 6600 | 3 (grynas papildas) | 0 | ⚠️ Dauguma "kolagenas" atitikmenų — plaukų priežiūros prekės, ne papildas; reikia tikslesnio matcho |
| omega 3 | 4400 | 2–5 | 1–2 | ❌ Per mažai aktyvių |
| vitaminas C | 2400 | 2 (tikslia fraze) | 0 | ❌ Tikriausiai kitokia frazė |
| probiotikai | 2400 | 2 | 0 | ❌ |
| geležies papildai | 1600 | 0 | 0 | ❌ |
| nėštumo testas | 720 | 2 | 0 | ❌ |
| magnio papildai | 480 | 0 | 0 | ❌ |
| multivitaminai | 320 | 13 | 2 | ❌ Per mažai aktyvių |
| talkas | 320 | 0 | 0 | ❌ |
| kūdikių kremas / skutimosi putos / akių kremas | rodė 0 su viena keista Aug-2026 reikšme | — | — | ❌ Nepatikimi duomenys (panašiai kaip anksčiau "vonios kambario prekės") |
| kūdikių aliejus / skutimosi gelis / intymios higienos gelis | ≤70 | — | — | ❌ Per mažai paieškų |

### Iteracija 5 — javainiai, gėrimai, saldumynai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| krekeriai | 590 | 135 | 28 | ✅ Stiprus |
| žalioji arbata | 590 | 61 | 16 | ✅ |
| avižų košė | 480 | 24 | 12 | ✅ |
| ledinukai | 210 | 58 | 13 | ✅ |
| meduoliai | 880 | 37 | 6 | ⚠️ Ribinis, bet vertas (sezoninis — Kalėdos) |
| kombucha | 2400 | 30 | 3 | ❌ Didelis paieškų atotrūkis nuo mūsų katalogo |
| kuskusas | 2400 | 18 | 3 | ❌ Tas pats |
| bulguras | 1300 | 10 | 2 | ❌ |
| rizotas | 480 | 3 | 0 | ❌ |
| kinva | 260 | 0 | 0 | ❌ |
| kokosų vanduo | 210 | 5 | 0 | ❌ |
| limonadas | 210 | 8 | 1 | ❌ |
| skystas šokoladas / kakavos gėrimas / želė saldainiai | ≤50 | — | — | ❌ Per mažai paieškų |

### Iteracija 6 — virtuvės reikmenys

Pastaba: "puodai" ir "keptuvės" jau yra tarp esamų 240 puslapių — praleista kaip dublikatai.

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| pjaustymo lentelė | 260 | 21 | 9 | ✅ |
| termosas | 880 | 15 | 4 | ⚠️ Ribinis |
| šluota | 320 | 15 | 4 | ⚠️ Ribinis |
| gertuvė | 590 | 61 | 3 | ❌ Per mažai aktyvių, nepaisant didelio volume |
| katilas | 480 | 1 | 0 | ❌ |
| indų šepetėlis / kvapios žvakės / indų rinkinys | rodė 0 su viena Aug-2026 reikšme | — | — | ❌ Nepatikimi duomenys |
| šluostė grindims / virtuvinės pirštinės / popieriniai puodeliai / vienkartinės lėkštės / peiliai virtuvei | ≤140 | — | — | ❌ Per mažai paieškų |

### Iteracija 7 — vaistinės prekės (patikslintos frazės)

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| saldikliai | 2900 | 39 | 9 | ✅ |
| pleistrai | 480 | 33 | 4 | ⚠️ Ribinis |
| termometras | 1900 | 17 | 2 | ❌ Per mažai aktyvių |
| d3 vitaminas | 1000 | 0 | 0 | ❌ Tikslesnė frazė nepadėjo — vitaminų D3 prekių kataloge tikrai nėra arba pavadinimas dar kitoks |
| stevija | 880 | 3 | 0 | ❌ |
| nosies purškalas / ausų krapštukai | ≤140 | ≤7 | 0 | ❌ |
| kosulio sirupas / tvarsčiai / plaukų dažai vyrams / gerklės pastilės / vata diskai / higieninis skalavimo skystis / kramtomos vitaminų tabletės / c vitaminas tabletės | ≤50 arba nepatikimi duomenys | — | — | ❌ |

### Iteracija 8 — papildomi maisto produktai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| lydytas sūris | 590 | 40 | 12 | ✅ |
| tofu | 1900 | 22 | 2 | ❌ Didelis atotrūkis — daug paieškų, mažai prekių su nuolaida |
| humusas | 1600 | 10 | 0 | ❌ |
| rauginti kopūstai | 1000 | 8 | 1 | ❌ |
| marinuoti pomidorai | 720 | 5 | 0 | ❌ |
| kepimo milteliai | 480 | 7 | 1 | ❌ |
| želatina | 320 | 10 | 0 | ❌ |
| vanilinis cukrus | 210 | 7 | 1 | ❌ |
| picos pagrindas / kefyro gėrimas / sojos pieno gaminiai | ≤50 arba 0 | — | — | ❌ |
| blynų miltai / duonos kepimo mišinys / soda kepimui / majonezas šaldytuve | rodė 0 su viena Aug-2026 reikšme | — | — | ❌ Nepatikimi duomenys |

### Iteracija 9 — prieskoniai / kepimo priedai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| vanilė | 480 | 411 | 112 | ✅ Labai stiprus (didžiausias aktyvių prekių skaičius iš visų šios sesijos radinių) |
| cinamonas | 2400 | 55 | 10 | ✅ |
| imbieras | 1300 | 51 | 8 | ✅ |
| džiovintos slyvos | 210 | 17 | 1 | ❌ Per mažai aktyvių |
| džiovinti abrikosai | 170 | 7 | 0 | ❌ |
| rūkytas sūris / riešutų mišinys / česnako milteliai / svogūnų milteliai / migdolų gėrimas | ≤90 | — | — | ❌ Per mažai paieškų |
| sumuštinių pasta / žuvies pastetas / kepenėlių pastetas / avižinis gėrimas | rodė 0 su viena Aug-2026 reikšme arba 0 | — | — | ❌ Nepatikimi duomenys / nėra paieškų |

### Iteracija 10 — likę kandidatai

| Keyword | Paieškos/mėn (LT) | Prekių iš viso | Su akt. nuolaida | Verdiktas |
|---|---|---|---|---|
| skrebučiai | 170 | 31 | 5 | ⚠️ Ribinis |
| kepiniai | 260 | 1 | 0 | ❌ |
| minkštiklis (be "skalbinių") | 90 | — | — | ❌ Mažesnis volume nei jau patvirtintas "skalbinių minkštiklis" |
| greito paruošimo makaronai | 90 | — | — | ❌ Per mažai paieškų |
| šokoladiniai kiaušiniai | 70 | — | — | ❌ Per mažai paieškų |
| duonos gaminiai / tirpi sriuba / velykų saldainiai / indų muilas / saldainių mišinys | ≤40 arba 0 | — | — | ❌ |

---

## GALUTINIS SĄRAŠAS — nauji KeywordPage kandidatai (rikiuota pagal aktyvių prekių skaičių)

| # | Keyword | Paieškos/mėn (LT) | Prekių su akt. nuolaida | Kategorija |
|---|---|---|---|---|
| 1 | vanilė | 480 | 112 | Kepimo priedai |
| 2 | oro gaiviklis | 260 | 122 | Buitinė chemija |
| 3 | skalbinių minkštiklis | 210 | 74 | Buitinė chemija |
| 4 | kukurūzai | 320 | 65 | Konservai/daržovės |
| 5 | alyvuogės | 140 | 61 | Konservai |
| 6 | pupelės | 880 | 54 | Konservai |
| 7 | veido kaukė | 1600 | 33 | Kosmetika |
| 8 | lūpų balzamas | 320 | 33 | Kosmetika |
| 9 | prezervatyvai | 2400 | 29 | Vaistinės prekės |
| 10 | rankų kremas | 260 | 30 | Kosmetika |
| 11 | krekeriai | 590 | 28 | Užkandžiai |
| 12 | lęšiai | 880 | 20 | Konservai |
| 13 | alyvuogių aliejus | 2900 | 21 | Aliejai |
| 14 | migdolai | 590 | 39 | Riešutai |
| 15 | žalioji arbata | 590 | 16 | Gėrimai |
| 16 | micelinis vanduo | 720 | 17 | Kosmetika |
| 17 | burnos skalavimo skystis | 480 | 22 | Burnos higiena |
| 18 | garstyčios | 480 | 13 | Padažai |
| 19 | ledinukai | 210 | 13 | Saldumynai |
| 20 | avižų košė | 480 | 12 | Pusryčiai |
| 21 | lydytas sūris | 590 | 12 | Sūriai |
| 22 | pomidorų padažas | 590 | 14 | Padažai |
| 23 | anakardžiai | 140 | 13 | Riešutai |
| 24 | folija | 320 | 10 | Namų apyvoka |
| 25 | cinamonas | 2400 | 10 | Prieskoniai |
| 26 | kepimo popierius | 260 | 11 | Namų apyvoka |
| 27 | saldikliai | 2900 | 9 | Saldikliai |
| 28 | pjaustymo lentelė | 260 | 9 | Virtuvės reikmenys |
| 29 | rapsų aliejus | 210 | 9 | Aliejai |
| 30 | margarinas | 320 | 5 | Kepimo priedai |
| 31 | baliklis | 320 | 9 | Buitinė chemija |
| 32 | razinos | 260 | 8 | Kepimo priedai |
| 33 | imbieras | 1300 | 8 | Prieskoniai |
| 34 | šprotai | 390 | 6 | Konservai |
| 35 | meduoliai | 880 | 6 | Saldumynai (sezoninis) |
| 36 | grybai | 3600 | 7 | Daržovės (sezoninis) |

36 patvirtinti kandidatai. Ribiniai (⚠️, verta apsvarstyti, bet silpnesni): chia sėklos, linų sėmenys, kolagenas (reikia tikslesnio matcho), pleistrai, termosas, šluota, skrebučiai.

**Kitas žingsnis**: peržiūrėti šį sąrašą ir nuspręsti, kuriuos kurti kaip realius KeywordPage įrašus (per `ImportManualKeywordPagesCommand` ar admin panelę).

---

## ATNAUJINIMAS — sukurta 2026-09-09

Vartotojas paprašė sukurti visus geruosius keyword'us, kol jis neprie kompo. Prieš importą pašalinau
**vanilė** — vartotojas pastebėjo, kad ji netiktų (pagauna oro gaiviklius, dušo želes, valiklius
"vanilla" kvapo, jogurtus/bandeles su vanile — ne tikrą vanilę), taip patikrintas realiais pavyzdžiais.
Likę 35 kandidatai suvesti į `database/data/new-keyword-pages-2026-09.json` (title + category_slugs)
ir importuoti per `sail artisan keywords:import-manual database/data/new-keyword-pages-2026-09.json --apply`
(GPT sugeneravo grammar/meta/faq/search_terms/exclude_terms turinį kiekvienam puslapiui automatiškai).

**Rezultatas**: visi 35 sukurti sėkmingai, 34 automatiškai publikuoti (`is_published=true`), 1 —
**imbieras** — automatiškai paliktas nepublikuotas (`KEEP UNPUBLISHED – below min_active_offers`),
nes tikslesnis GPT sugeneruotas atitikimas (ne mano ankstesnė gruboka `LIKE` patikra) rado 0 realiai
tinkančių prekių — dauguma "imbieras" atitikmenų buvo arbatos/gėrimai su imbiero skoniu, ne pats
imbieras. Patikrinta ir realiai gyva: `/akcijos/oro-gaiviklis` ir `/akcijos/margarinas` grąžina 200,
`/akcijos/imbieras` grąžina 404 (teisingai, nes nepublikuotas).

Iš viso keyword pages dabar: **275** (buvo 240).

