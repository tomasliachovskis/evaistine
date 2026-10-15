# Duomenų surinkimo dashboard (`/admin/collection-health`)

Viena vieta, kur matosi, kurie nuolaidų / leidinių surinkimai užstrigo arba turi anomaliją. Perkelta iš superakcijos (`/Users/tomas/www/nuolaidos`, ten ir planas su tyrimu) 2026-10-15 be pakeitimų: abiejų projektų taisyklės ir laiškas vienodi, laiško tema prasideda domenu. Pavyzdžiai su Lidl/Norfa ir testo serijos yra iš superakcijos duomenų. Laiškas čia siunčiamas 08:30, nes naktinis scrape baigiasi 08:00.

## Problema

Senas `LatestScraperRunsWidget` remiasi `scraper_runs`, bet scraperiai sukasi lokaliai: run'ai rašomi į lokalią DB, eilutės POSTinamos į prod. Prod `scraper_runs` scrape įrašai sustoję ties 2026-09-14, o lokalus `items_count` visada 0. Todėl tas widget'as negalėjo pagauti nei praleisto run'o, nei tuščio surinkimo.

## Kaip veikia

- `App\Services\CollectionHealthService::report()` (tik SELECT užklausos) remiasi **tik tuo, kas realiai atėjo į DB**: `discount_temp`, `discounts`, `store_flyers`, `failed_jobs`, `category_mappers`. `scraper_runs` nenaudojamas.
- Parduotuvių sąrašas: e-shop iš `config('scrapers')`, leidiniai iš `config('flyer_scrapers')`. Todėl parduotuvė, iš kurios nieko neatėjo, vis tiek matosi.
- `App\Filament\Pages\CollectionHealth` rodo plyteles, „Reikia dėmesio“ sąrašą, e-shop lentelę su 15 d. sparkline ir leidinių lentelę. `wire:poll.60s`, rezultatas cache'inamas 120 s (`collection-health:report`), mygtukas „Perskaičiuoti“ jį išvalo. Navigacijos ženklelis rodo kritinių skaičių.
- Migracija `2026_10_12_120000_add_created_at_index_to_discount_temp_table` prideda `discount_temp.created_at` indeksą, kad 15 d. užklausa neskenuotų visos lentelės.

## Taisyklės

| Signalas | Lygis | Taisyklė |
|---|---|---|
| Nesurinkta | KRIT | e-shop parduotuvė be eilučių > 30 h |
| Praleistas visas run'as | KRIT (≤ 2 d. senumo), kitaip INFO | diena, kai duomenis atsiuntė < 50 % e-shop parduotuvių. Skaičiuojamos tik dienos iki paskutinės normalios dienos (tęsiasi iki šiandien = jau „Nesurinkta“) |
| 0 aktyvių e-shop nuolaidų | KRIT | e-shop parduotuvė, kurios `discounts` (be `store_flyer_id`) aktyvių 0, ir kai eilutės ateina, ir kai ne |
| 0 aktyvių nuolaidų (Gemini) | KRIT | tik leidinių parduotuvė su `extract_discounts_from_flyer`, bet 0 aktyvių nuolaidų. Leidinių parduotuvės be ištraukimo nuolaidų niekada neturi, jos netikrinamos |
| Eilutės be datų | KRIT | per 24 val. > 20 % e-shop eilučių nei `start_at`, nei `end_at` neatitinka `YYYY-MM-DD` (min. 10 eilučių) |
| Eilutės be kainos | KRIT | per 24 val. > 20 % eilučių `discounted_price` tuščia arba ≤ 0 |
| Pasibaigusios eilutės | ĮSP | per 24 val. > 20 % eilučių `end_at` < šiandien |
| Atėjo, bet nepridedama | ĮSP | aktyvių e-shop nuolaidų < 50 % per 24 val. atėjusių unikalių prekių (kai jau neparodė priežasties eilutės be datų/kainos) |
| Kiekio anomalija | ĮSP | paskutinė diena su duomenimis (šiandien arba vakar) vs bazė < −50 % arba > +200 % |
| Neapdorota | ĮSP | `discount_temp.processed = 0`, seniausia > 30 min |
| Leidinys baigėsi | KRIT | vėliausias `valid_to` < šiandien, ir nėra naujesnio leidinio be datų |
| Leidinys baigiasi šiandien | KRIT | `valid_to` = šiandien |
| Leidinys baigiasi per 1–2 d. | ĮSP | |
| Leidiniai be `valid_to` | ĮSP | svetainė juos laiko galiojančiais amžinai, Gemini ištraukimas jiems praleidžiamas. Senesnius nei 21 d. kasdien 03:50 uždaro `flyers:expire-undated` (`valid_to` = vakar) |
| Puslapių apdorojimas | ĮSP | `processing_status = failed`, arba `pending`/`processing` > 1 h (tik negaliojusiems) |
| Gemini kvota | KRIT | `discounts_retry_state.is_quota_exceeded` dar neapdorotam galiojančiam leidiniui |
| Gemini nutrauktas / kartojamas | ĮSP | `gave_up`, arba `attempts ≥ 3` |
| Leidinys be nuolaidų | ĮSP | Gemini apdorotas, 0 susietų `discounts`, ir parduotuvė neturi jokių aktyvių nuolaidų |
| Laukia Gemini | ĮSP | galiojantis `ready` leidinys be `discounts_processed_at` ir be retry state > 1 h (`--pending` ima tik 3 d. senumo, todėl toks niekada nebus pakartotas) |
| failed_jobs | ĮSP (paskutinis ≤ 7 d.), kitaip INFO | |
| Nesusietos kategorijos | INFO | `category_mappers.category_id IS NULL` |
| Seni retry state | INFO | pasibaigę leidiniai arba parduotuvės su išjungtu ištraukimu: tik skaičius |

Tas pats lygis + ta pati žinutė kelioms parduotuvėms sujungiama į vieną eilutę („Čia, Kubas: leidinys galioja tik iki šiandien“).

### Kodėl taip skaičiuojama

- **E-shop ir leidinių eilutės atskirai.** `discount_temp` maišo e-shop scraperių eilutes ir Gemini ištraukimą iš leidinių (`store_flyer_id`/`page_image_path` užpildyti). Kitaip Lidl 10-11 „553“ atrodo kaip šuolis, nors tai 128 e-shop + 425 iš leidinio.
- **`COUNT(DISTINCT product_url)`, ne eilutės.** Du run'ai tą pačią dieną padvigubina eilutes (10-02 Maxima 7460 = 2×3756). Norfa neturi `product_url`, jai naudojamas `name`.
- **Bazė = dienų prieš 8–14 d. mediana (be tuščių dienų).** 14 d. mediana „suvalgo“ laipsnišką kritimą: Lidl 14 d. mediana 95 rodė tik −28 %, savaitės senumo bazė 149 rodo −54 %.
- **`LOWER(store)`** grupavimui. Mišrios raidės (lidl/Lidl) GROUP BY rezultate yra `utf8mb4_unicode_ci` artefaktas, ne bug'as.
- Prie anomalijos pridedamas 14 d. intervalas, kai max/min ≥ 5 (Rimi: svyruoja 197 … 7228).

### Kodėl eilučių kokybė

2026-10-15 Norfa pakeitė datų formatą (`09 03-09 16` → `10.15-10.28`), scraperis visoms 364 eilutėms siuntė `"Invalid Date"`. `discount_temp.start_at/end_at` yra `varchar`, API jas priėmė, `discounts:process` be datų eilutes tyliai praleido ir pažymėjo `processed`. Scraper run'as buvo `success`, eilučių kiekis normalus, todėl dashboard'as liko žalias. Dabar tai matosi kaip „be galiojimo datų“ ir „atėjo, bet nepridedama“.

## Rytinis laiškas

`collection-health:digest` (kasdien 08:30, production) siunčia visiems `users.is_admin` adminams, kiekvieną dieną, ir kai viskas gerai:

- lentelė per e-shop parduotuvę už 24 val.: atėjo (unikalios prekės), įprastai (bazė), be datų, pasibaigę, be kainos, naujų nuolaidų, aktyvių. Raudona: atėjo 0 arba > 50 % mažiau nei įprastai, aktyvių 0 arba < 50 % atėjusių;
- KRIT ir ĮSP problemos iš dashboard'o (INFO ne).

`sail artisan collection-health:digest --dry-run` atspausdina tą patį terminale. Lokaliai laiškas nueina į MailHog (`http://localhost:8025`).

## Testai

`tests/Feature/CollectionHealthServiceTest.php` naudoja realias prod serijas (2026-09-28 → 10-12): Lidl kritimas −54 %, Iki/Norfa stabilūs, Norfa be URL ir su dviem run'ais per dieną, leidinio eilutės neįskaičiuojamos, 10-10 praleistas run'as (taip pat 10-01, 10-04), Jupoja pasibaigęs, Čia + Kubas sujungti, Grustė 0 nuolaidų, Maxima be `valid_to`.

Testinė DB neturi `discount_temp.store` stulpelio (realioje DB jis pridėtas ne migracija), todėl testas jį prideda `setUp` metu.

```
sail artisan test tests/Feature/CollectionHealthServiceTest.php
```

## Prod

`deploy.sh` paleidžia `migrate` (indeksas). Patikrinti: `php artisan tinker --execute="print_r(app(App\Services\CollectionHealthService::class)->issues());"`.
