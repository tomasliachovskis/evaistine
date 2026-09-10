# Meta title/description analizė — TIK realūs TOP autoritetai (2026-09-09)

Metodika: tikrinta REALIAIS Google paieškos rezultatais (ne savireferentinėm užklausom su
svetainės pavadinimu). Į analizę įtraukti TIK domenai, kurie pasikartojo TOP pozicijose
KELETE nepriklausomų užklausų (store akcijos, store leidinys, kategorija). Vienkartiškai
pasirodę arba tik savireferentinėse paieškose pasirodę domenai (gudrusis.lt, akcijukas.lt,
kimbino.lt, imkakcija.lt) **atmesti** — nėra įrodyto autoriteto, netyrinėti giliau.

## Patvirtinti realūs TOP autoritetai (pasikartoję ≥3 nepriklausomose užklausose)

| Domenas | Pasirodė TOP šioms užklausoms |
|---|---|
| **akcijos.lt** | maxima akcijos, lidl leidinys (2x), rimi akcijos (2x), iki leidinys, akcijų leidiniai hub |
| **raskakcija.lt** | maxima akcijos (3x), lidl leidinys (2x), rimi akcijos, iki leidinys (2x) |
| **akcijuleidinys.lt** | rimi akcijos, iki leidinys, akcijų leidiniai hub |
| **topakcijos.lt** | rimi akcijos, pieno produktai akcijos (2x — bendra IR store+kategorija), akcijų leidiniai hub |

Šie keturi yra vienintelis realiai patikrintas rinkos etalonas. Visa žemiau esanti analizė ir
rekomendacijos remiasi TIK jų realiais title/description, surinktais tiesiai iš puslapių
šiandien.

## Realūs title/description (tiesiai iš HTML, ne iš SERP santraukos)

| Domenas | Puslapio tipas | Title | Description |
|---|---|---|---|
| akcijos.lt | store leidinys | `RIMI savaitinis akcijų leidinys Nr. 37 \| 2026-09-08 - 2026-09-14` | „Rimi" akcijų leidinys – kokybiškos maisto ir buities prekės už gerą kainą!... atnaujinamas kiekvieną antradienį... |
| raskakcija.lt | store akcijos | `RIMI akcijos \| RaskAkcija.lt` | RIMI akcijos ir nuolaidos. Rask daug gerų akcijų naujausiam RIMI leidiny. |
| raskakcija.lt | store leidinys | `Naujas MAXIMA leidinys Nr.37 nuo 2026.09.08 iki 2026.09.14 \| RaskAkcija.lt` | — |
| akcijuleidinys.lt | store akcijos | `RIMI akcijos \| AkcijuLeidinys.lt` | RIMI akcijos ir nuolaidos, RIMI ir kitų prekybos tinklų akcijų ir nuolaidų leidiniai |
| topakcijos.lt | kategorija | `Akcijos \| Pieno 🥛 produktai ir kiaušiniai \| Sūriai, jogurtas, varškė` | **live kainų sąrašas**: `€2.39 Majonezas HELLMANN'S..., €1.69 ROKIŠKIO sūris...` |
| topakcijos.lt | store+kategorija | `MAXIMA akcijos \| Pieno 🥛 produktai ir kiaušiniai \| Sūriai, jogurtas, varškė` | tas pats live-kainų stilius, konkretūs Maxima produktai |

## Ką iš to matome — 3 realūs, patvirtinti raštai

1. **VISI KETURI naudoja DIDŽIOSIOMIS RAIDĖMIS store pavadinimą** (RIMI, MAXIMA) be išimties.
   Tai nėra atsitiktinumas iš vieno silpno konkurento — tai VISŲ patvirtintų autoritetų
   pasirinkimas. **Rekomendacija: apsvarstyti sugrąžinti DIDŽIOSIOMIS store pavadinimą bent
   `/akcijos/{store}` ir `/leidinys/{store}` title'uose** — šiandien padarytas sprendimas
   (normalus registras) prieštarauja VISO patvirtinto rinkos etalono elgesiui, ne tik
   estetiniam skoniui. Tai vienintelė šio failo rekomendacija, kuri tiesiogiai grąžina atgal
   ką pakeitėme šįvakar — verta rimtai apsvarstyti, o ne atmesti dėl estetikos.
2. **`topakcijos.lt` (vienintelis iš 4, turintis realų kategorijos puslapį) description'e
   NEBE bendra frazė — tai LIVE konkrečių prekių + kainų sąrašas.** Tai stipriausias, labiausiai
   pasitikėjimą kuriantis CTR signalas iš visų matytų: vartotojas SERP'e iš karto pamato tikrą
   kainą, ne pažadą. **Rekomendacija žemiau, §2 (kategorija).**
3. **`akcijos.lt`/`raskakcija.lt` store-leidinio title'e visada `Nr.{issue}` + tiksli data** —
   patvirtina, kad mūsų šiandien padarytas `/leidinys/{store}` formatas su `Nr.{issue}` yra
   teisingas, autoritetų patvirtintas pasirinkimas — NEKEISTI.
4. **Nė vienas iš 4 autoritetų title'e nemini konkretaus max % skaičiaus** (nei store, nei
   kategorijos lygiu) — visi remiasi Nr./data/store pavadinimu, ne nuolaidos dydžiu. Mūsų
   `-50% akcija` (Šilas) YRA nukrypimas nuo patvirtinto etalono elgesio, ne jo sekimas.
   **Rekomendacija: šis eksperimentas rizikingesnis, nei atrodė vakar — jį verta A/B stebėti
   per Search Console CTR, ne tik priimti kaip savaime geresnį.**

## Rekomendacijos pagal MŪSŲ konkretų puslapio tipą

### `/akcijos/{store}` — dabar: `Šilas -50% akcija rugsėjį – 91+ pasiūlymų`
Visi 4 autoritetai: `{STORE} akcijos | {Site}` arba `{STORE} savaitinis akcijų leidinys Nr.{N} | {datos}`
— **jokio %, DIDŽIOSIOMIS, arba Nr.+data vietoj %.**
- **Rekomendacija**: `{STORE} akcijos – Nr.{issue}, {count}+ pasiūlymų | SuperAkcijos.lt`
  (DIDŽIOSIOMIS, Nr. vietoj %, live count — mišinys iš to, ką VISI 4 autoritetai daro).
  Alternatyva, jei nori išlaikyti savo % eksperimentą: bent grąžinti DIDŽIOSIOMIS raides,
  paliekant % — tai vienintelis elementas, kur MES nukrypom nuo VISŲ keturių vienbalsiai.

### `/leidinys/{store}` — dabar: `Šilas naujas savaitės leidinys, Nr.37 2026.09.08`
Tiksliai atitinka `raskakcija.lt`/`akcijos.lt` formatą (Nr.+data) — **NEKEISTI formato**,
tik apsvarstyti DIDŽIOSIOMIS store pavadinimą dėl §"Ką matome" punkto 1.
- **Rekomendacija**: `ŠILAS naujas savaitės leidinys, Nr.37 2026.09.08 | SuperAkcijos.lt`
  (vienintelis pakeitimas — registras).

### `/akcijos/{category}` (pvz. pieno produktai) — dabar: `Pieno produktai ir kiaušiniai akcijos – pigiausios kainos, iki 55% nuolaidos`
`topakcijos.lt` yra VIENINTELIS iš 4 su realiu kategorijos puslapiu, ir jo description'as
(live kainų sąrašas) yra aiškiai stipriausias CTR signalas visame šiame tyrime.
- **Rekomendacija title**: nekeisti smarkiai — mūsiškis jau geras (turi "pigiausios kainos" +
  %). Galima palikti.
- **Rekomendacija description (didžiausias impact šiame faile)**: pakeisti bendrą frazę į
  2-3 realių pigiausių prekių + kainų sąrašą tos kategorijos, pvz.: `Nuo €0.89: ROKIŠKIO
  pienas 1l, €1.39: Sūris MOZZARELLA, €0.99: DVARO varškė. 550+ pasiūlymų iš Maxima, Rimi,
  Lidl ir kt.` — techniškai lengva (jau turime kainas DB), tikslus `topakcijos.lt` sėkmingo
  rašto atkartojimas, be jokios apgaulės (realios kainos, ne fiktyvios).

### `/akcijos/{store}/{category}` — dabar: `Maxima akcija pieno produktai ir kiaušiniai – iki 35% nuolaidos`
`topakcijos.lt`'s store+kategorija seka TĄ PATĮ live-kainų description raštą kaip bendra
kategorija, tik su store pavadinimu DIDŽIOSIOMIS title'e.
- **Rekomendacija**: description → tas pats live-kainų sąrašas kaip aukščiau, filtruotas tik
  šiam store. Title → `MAXIMA akcijos: Pieno produktai ir kiaušiniai – iki 35% nuolaidos`
  (DIDŽIOSIOMIS store, likusi struktūra nekeista).

### `/leidinys/{store}/{flyer}` — dabar: `Šilas leidinys Nr. 18 | SuperAkcijos.lt` (visiškai nekeista, senas)
Jokio iš 4 autoritetų neturime tiesioginio analogo (jie visi rodo tik hub, ne page-by-page
leidinio skaitytuvą) — čia nėra rinkos etalono, kurį sekti. Mūsų pačių sprendimas.
- **Rekomendacija** (nepagrįsta konkurentų duomenimis, tik logika): pridėti datą kaip
  `/leidinys/{store}` daro: `{STORE} leidinys Nr.{issue} – galioja {from}–{to} | SuperAkcijos.lt`.

### `/akcijos` ir `/leidiniai` (hub'ai) — dabar statiški, be live skaičiaus
Nė vienas iš 4 patvirtintų autoritetų NEBUVO tikrintas jų homepage lygiu šiame tyrime (jie
pasirodė tik store/kategorijos puslapiuose) — **šiam punktui nėra patikimų duomenų, ankstesnė
`gudrusis.lt`-pagrįsta rekomendacija ("live skaičius title'e") ATŠAUKIAMA**, nes ji remėsi
neįrodytu autoritetu. Palikti kaip yra, kol atsiranda tikras pagrindas keisti.

## Kas VIS TIEK galioja iš praėjusios (platesnės) analizės

`og:image`/`og:title`/`og:description` trūkumas ir `FAQPage` schema trūkumas leidinio
puslapiuose — šie radiniai NEPRIKLAUSO nuo to, kuris konkurentas yra autoritetas (tai bendras
socialinio/rich-result standartas, ne SERP-title klausimas), tad lieka galioti nepakitę.

## 6. kainos.lt / kaina24.lt — kita kategorija konkurentų, bet labai reikšminga

Šie du NĖRA akcijų/leidinių agregatoriai (jų verslas — bendra kainų palyginimo paieška per
pigu.lt/varle.lt/joom.com ir pan., ne tik maisto tinklai) — bet jie **realiai dominuoja** būtent
plikam prekės pavadinimui be "akcija"/"leidinys" priesagos (pvz. "cukrus", "cukrus 1kg",
"cukrus kaina") — t.y. TIKSLIAI tokioms užklausoms, kokioms taikomi mūsų `KeywordPage`
puslapiai (`/akcijos/cukrus` ir pan.). Tai daro juos svarbiausiu etalonu būtent šitam puslapio
tipui, nors jų bendras produktas (visų kategorijų marketplace) skiriasi nuo mūsų.

**Techninis apribojimas**: abi svetainės saugomos Cloudflare bot-apsaugos (kaina24.lt) arba yra
grynai JS-renderinamas SPA (kainos.lt) — nepavyko tiesiogiai patikrinti jų HTML/schema/og:image
per curl/WebFetch (403/Cloudflare challenge/tuščias shell). Žemiau esantys title pavyzdžiai yra
TIKSLŪS (paimti iš Google paieškos rezultatų, kuriuos pati Google indeksavo), bet schema/
og:image duomenų apie juos NETURIME patikimų — nespėliojame.

### Realūs title pavyzdžiai (iš Google SERP, patikima)

| Svetainė | Title |
|---|---|
| kainos.lt | `Cukrus kaina nuo 0.35 € (224 pard.)` |
| kainos.lt | `Cukrus 1 Kg kaina nuo 1.05 € (29 pard.)` |
| kaina24.lt | `Cukrus 1 Kg. kainos nuo 0.79 € (207) \| Kaina24.lt` |
| kaina24.lt | `Cukrus kainos nuo 0.25 € (718) \| Kaina24.lt` |
| kaina24.lt | `Cukrus ir druska - kainos nuo 0.98 € \| Kaina24.lt` |

### Raštas — labai aiškus ir tiesiogiai pritaikomas

Abu naudoja TIKSLIAI tą patį formulę: **`{Prekė} kaina(os) nuo {realiai žemiausia kaina} €
({realus pardavėjų/pasiūlymų skaičius})`**. Jokio % ar "akcija" žodžio — vien tikra pigiausia
kaina + realus kiekis. Tai yra **sąžiningiausias ir labiausiai duomenimis paremtas** raštas iš
VISŲ šiame faile tirtų svetainių (dar aiškesnis nei `topakcijos.lt` live-kainų description'as
§2, nes čia tai patys TITLE, ne tik description).

**Tiesiogiai pritaikoma mūsų `KeywordPage` (`/akcijos/{keyword}`) puslapiams** — dabar tokio
puslapio pvz. cukrus title nebuvo tikrintas šioje analizėje atskirai (žr. §4 nebuvo šio tipo),
bet remiantis šiuo radiniu:

- **Rekomendacija title**: `{Keyword} kaina nuo {realiai žemiausia kaina}€ – {count}+
  pasiūlymų | SuperAkcijos.lt` (pvz. `Cukrus kaina nuo 0.89€ – 18+ pasiūlymų |
  SuperAkcijos.lt`) — realus žemiausios kainos skaičius jau turimas DB (`discounted_price`
  MIN), technikai lengva, ir tai TIKSLIAI atkartoja du realius rinkos lyderius vienam iš
  svarbiausių mūsų puslapio tipų (KeywordPage), kurio anksčiau šiame faile visai netyrinėjome.
- Šis radinys **stipriai koreliuoja su §"Ką matome" #4** ankstesniame skyriuje (akcijos/leidinys
  agregatoriai NEnaudoja % title'e) — dabar turime DU nepriklausomus, skirtingus konkurentų
  segmentus (leidinių agregatorius IR kainų palyginimo svetaines), kurie SUTARTINAI naudoja
  realų skaičių (Nr./data arba kaina/count), NE % ar superlatyvus. Tai stiprina išvadą, kad
  mūsų `-50% akcija` raštas yra nukrypimas nuo rinkos normos abiejuose segmentuose, ne tik
  viename.

## TODO
- [ ] Apsvarstyti/nuspręsti: grąžinti DIDŽIOSIOMIS raidėmis store pavadinimą `/akcijos/{store}`
      ir `/leidinys/{store}` title'uose (§"Ką matome" #1) — reikia TAVO sprendimo, ne kodo.
- [ ] `/akcijos/{category}` ir `/akcijos/{store}/{category}` description → live 2-3 prekių
      kainų sąrašas (didžiausias, konkurentų-patvirtintas impact šiame faile).
- [ ] `/leidinys/{store}/{flyer}` title/description atnaujinimas su data (nėra konkurento
      pavyzdžio, bet logiškas trūkumas).
- [ ] `og:image` (flyer thumbnail) visiems leidinio puslapiams — lieka iš ankstesnės analizės.
- [ ] `/akcijos/{keyword}` (KeywordPage, pvz. cukrus) title → `{Keyword} kaina nuo {min}€ –
      {count}+ pasiūlymų` pagal kainos.lt/kaina24.lt raštą (§6) — naujas radinys, dar
      neįvertintas prieš tai buvusiuose §4 puslapio tipuose.
