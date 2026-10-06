<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seeds the GPT-generated leidinys hub descriptions for the 10
     * StoreListPriority::PRIORITY_SLUGS stores, generated and reviewed
     * locally this session -- baked into a migration so `migrate --force`
     * (already part of deploy.sh) carries them to prod without needing
     * OPENAI_API_KEY or storage/app/store_leaflet_semantic_research.json
     * present there. Re-running `descriptions:generate store-leaflet`
     * on prod later will simply overwrite these with a fresh version.
     */
    public function up(): void
    {
        $descriptions = array (
  'maxima' => '<div class="space-y-5">
  <h2 class="section-heading">Maxima leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maximos leidinys – tai nuolat atnaujinamas parduotuvių katalogas, kurio naujas Maxima leidinys paprastai pasirodo kas savaitę. Dažniausiai tai Maxima savaitės akcijų leidinys; internete neretai ieškoma ir pagal formuluotę Maxima leidinys Nr., nes serijos būna numeruojamos skirtingoms savaitėms atskirti.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Maxima?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maxima – didžiausias Lietuvos prekybos tinklas, kurio šaknys siekia 1992 metus, kai Vilniuje atidaryta pirmoji „Vilniaus prekybos“ parduotuvė. MAXIMA prekės ženklas pradėtas naudoti 1998 metais, o šiandien tinklas veikia ne tik Lietuvoje, bet ir Latvijoje, Estijoje, Lenkijoje bei Bulgarijoje ir priklauso „Maxima Grupė“ įmonių šeimai. Parduotuvės skirstomos į MAXIMA X, XX, XXX ir XXXX formatus – nuo kaimynystės tipo parduotuvių iki ypač plataus asortimento prekybos centrų, kuriuos atspindi ir leidinio temų įvairovė – nuo maisto produktų iki buitinės chemijos ar namų prekių. Daugiau praktinės informacijos apie adresus ir darbo laiką rasite puslapyje <a href="https://evaistine.lt/parduotuves/maxima">Maxima parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Maxima leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maxima skelbia kelias leidinių serijas, todėl paieškose greta užklausų naujas Maxima leidinys ar Maxima akcijų leidinys dažnai sutinkami ir teminiai numeriai.</p>
    <ul class="list-disc pl-5 space-y-1">
      <li class="leading-relaxed">AČIŪ savaitinis leidinys – pagrindinė savaitinė serija su kasdienio apsipirkimo asortimentu: maisto produktai, šviežios gėrybės ir buities prekės.</li>
      <li class="leading-relaxed">MAXIMOS leidinys SKONIŲ DIENOS – teminis maisto produktų rinkinys, akcentuojantis skonių kryptis ir gastronomines naujienas.</li>
      <li class="leading-relaxed">Švaros mugė – leidinys, skirtas buitinės chemijos ir valymo priemonėms, taip pat namų ūkio smulkmenoms.</li>
      <li class="leading-relaxed">ITALIJOS MĖNUO – itališkų skonių ir produktų teminis leidinys, suburiantis makaronus, padažus, alyvuogių aliejų ir panašias prekes.</li>
    </ul>
  </div>

  <h2 class="section-heading">Kur rasti Maxima leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį galite filtruoti pagal temas. Dabartinį pasirinkimą patogu peržvelgti kategorijose <a href="/akcijos/maxima/buitine-chemija-valymo-priemones">Buitinė chemija, valymo priemonės</a>, <a href="/akcijos/maxima/gerimai-kava-arbata">Gėrimai, kava, arbata</a>, <a href="/akcijos/maxima/kosmetika-ir-higiena">Kosmetika ir higiena</a> ar <a href="/akcijos/maxima/namu-ukio-ir-laisvalaikio-prekes">Namų ūkio ir laisvalaikio prekės</a>.</p>
    <p class="leading-relaxed">Tarp populiarių temų rasite ir tikslinius sąrašus, pavyzdžiui, <a href="https://evaistine.lt/akcijos/valikliai">Valikliai</a> ar <a href="https://evaistine.lt/akcijos/skalbimo-priemones">Skalbimo priemonės</a>, jei domina konkreti leidinio sritis.</p>
  </div>
</div>',
  'lidl' => '<div class="space-y-5">
  <h2 class="section-heading">Lidl leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl leidinys – tai reguliariai atnaujinamas katalogas, kuriame vienoje vietoje pristatomos naujai pasirodančios maisto ir ne maisto prekių rubrikos bei teminės kolekcijos. Naujas Lidl leidinys paprastai pasirodo kelis kartus per mėnesį, todėl aktualų turinį patogu sekti internetu. Kiekviename leidinyje aiškiai išskiriamos skiltys, kad greitai rastumėte jus dominančias kategorijas.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Lidl?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl – Vokietijoje įkurtas tarptautinis diskonto prekybos tinklas, veikiantis daugelyje Europos šalių. Lietuvoje pirmąsias parduotuves atidarė 2016 metais ir nuo tada išplėtė tinklą įvairiuose miestuose, siūlydamas patogias, greitam apsipirkimui pritaikytas parduotuves. Šio tinklo ypatybė – nuolat kintančios teminės ne maisto prekių programos šalia kasdienio maisto asortimento: pirkėjai čia randa Parkside įrankių, Silvercrest smulkios buitinės technikos, Livarno namų sprendimų, Crivit sporto ir Esmara drabužių linijas. Dauguma asortimento sudaryta iš privačių prekių ženklų, todėl leidinyje ryškiai pristatomos tiek maisto, tiek sezoninės tematikos rubrikos. Daugiau praktinės informacijos rasite puslapyje <a href="https://evaistine.lt/parduotuves/lidl">Lidl parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Lidl leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl akcijų leidinys pateikiamas kaip vienas reguliarus leidinys, dažniausiai kelių dešimčių puslapių apimties. Dažnai ieškoma kaip Lidl maisto prekių akcijų leidinys ar Lidl ne maisto prekių akcijų leidinys; abu turinio tipai paprastai pristatomi tame pačiame kataloge – nuo maisto rubrikų iki teminių ne maisto kolekcijų.</p>
  </div>

  <h2 class="section-heading">Kur rasti Lidl leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenka į patogų sąrašą parduotuvės akcijų puslapyje, kad galėtumėte peržiūrėti turinį kaip filtruojamą sąrašą, o ne vartant puslapius. Ten lengva atsirinkti maisto produktus, namų apyvokos prekes, įrankius, sporto ir drabužių kategorijas, kai Lidl akcijų leidinys pristato naują temą. Atnaujinus leidinį, sąrašas automatiškai pasipildo naujais įrašais, tad visa informacija pateikiama vienoje vietoje.</p>
  </div>
</div>',
  'iki' => '<div class="space-y-5">
  <h2 class="section-heading">Iki leidynys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki leidinys – tai periodiškai atnaujinamas IKI parduotuvių katalogas su aiškiai sudėliotu prekių asortimentu ir aktualiu turiniu. Nauji numeriai pasirodo kelis kartus per mėnesį; paieškose jis dažnai randamas įvedus „naujausias Iki leidinys“, „Iki savaitėlė“ ar net „Iki savaitėlė akcijų leidinys“.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Iki?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki yra vienas seniausių šiuolaikinių Lietuvos prekybos tinklų, veikiantis nuo 1992 metų. Tinklas priklauso Vokietijos REWE grupei, o strateginė partnerystė nuo 2018 metų suteikia stabilų tiekimo ir asortimento valdymo pagrindą. Kaip nacionalinis supermarketų tinklas, Iki išsiskiria plačiu maisto prekių pasirinkimu, papildytu namų ūkio ir gyvūnų prekėmis, todėl leidinys nuosekliai apima kasdienės paklausos tematiką. Tinkle veikia lojalumo programa IKI Premija, o parduotuvės patogiai išsidėsčiusios įvairiuose šalies miestuose ir rajonuose. Parduotuvėlių adresus, darbo laikus ir kontaktus rasite puslapyje <a href="https://evaistine.lt/parduotuves/iki">Iki parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Iki leidyniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki skelbia vieną nuolat atnaujinamą katalogą – Iki leidinį, kuriame apžvelgiamas platus maisto ir kasdienio vartojimo prekių pasirinkimas. Įprastai jis būna keliolikos puslapių apimties, tad aktualų turinį galima greitai peržvelgti vienoje vietoje.</p>
  </div>

  <h2 class="section-heading">Kur rasti naujausio Iki leidinio turinį?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į patogų sąrašą, kurį galima naršyti pagal temas ir prekių rūšis. Jei domina mėsos ir žuvies ar kepinių skiltys, užsukite į nuolat atnaujinamus sąrašus: <a href="/akcijos/iki/mesa-ir-zuvis">Mėsa ir žuvis </a> ir <a href="/akcijos/iki/duonos-gaminiai">Duonos gaminiai </a>.</p>
    <p class="leading-relaxed">Tarp populiarių paieškų pagal produktus – konkrečios mėsos rūšys. Greitai pasieksite temas <a href="https://evaistine.lt/akcijos/jautiena">Jautiena</a> ar <a href="https://evaistine.lt/akcijos/vistienos-krutinele">Vištienos krūtinėlė</a>, kad matytumėte, kaip jos pateikiamos naujausiame Iki leidinyje.</p>
  </div>
</div>',
  'rimi' => '<div class="space-y-5">
  <h2 class="section-heading">Rimi leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi leidinys – tai spausdintas ir skaitmeninis katalogas, kuriame vienoje vietoje pateikiamas einamos savaitės prekių asortimentas ir rubrikos. Naujas Rimi leidinys pasirodo kas savaitę, tad informacija atsinaujina pastoviu ritmu. Jį patogu perversti internete arba pasiimti parduotuvėje.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Rimi?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi Lietuvoje veikia kaip Rimi Baltic dalis, priklausanti Švedijos koncernui ICA Gruppen. Tinklas vysto du parduotuvių formatus – Rimi Hyper ir Rimi Super – kad patogiai aptarnautų tiek didesnius savaitinius apsipirkimus, tiek kasdienius užėjimus. Asortimente rasite pilną kasdienio pirkimo krepšelį: šviežius vaisius ir daržoves, duoną ir kepinius, mėsą bei pieno produktus, taip pat buities ir higienos, naminių gyvūnėlių prekes. Rimi taip pat valdo internetinę parduotuvę ir siūlo pristatymą į namus, todėl tai, ką matote leidinyje, lengvai pasiekiama ir internetu. Parduotuvės adresus, darbo laikus ir kitą informaciją rasite puslapyje <a href="https://evaistine.lt/parduotuves/rimi">Rimi parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Rimi leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi turi vieną reguliariai leidžiamą seriją pavadinimu „Rimi“ – tai Rimi savaitinis leidinys, apžvelgiantis pagrindines maisto, buities ir higienos rubrikas. Leidinys paprastai yra kelių dešimčių puslapių apimties; pirkėjai jį neretai vadina Rimi akcijų leidiniu.</p>
  </div>

  <h2 class="section-heading">Kur rasti Rimi akcijų leidinio turinį?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite evaistine.lt – taip aktualų turinį galima filtruoti pagal temas be vartymo po puslapius. Iš karto peržvelkite šias sritis: <a href="/akcijos/rimi/vaiku-ir-kudikiu-prekes">Vaikų ir kūdikių prekės</a>, <a href="/akcijos/rimi/buitine-chemija-valymo-priemones">Buitinė chemija, valymo priemonės</a>, <a href="/akcijos/rimi/kosmetika-ir-higiena">Kosmetika ir higiena</a>.</p>
    <p class="leading-relaxed">Tarp dažniausiai ieškomų temų, kurios dažnai atsispindi ir leidinio turinyje, yra <a href="https://evaistine.lt/akcijos/sauskelnems">Sauskelnės</a> bei <a href="https://evaistine.lt/akcijos/skalbiklis">Skalbiklis</a>.</p>
  </div>
</div>',
  'norfa' => '<div class="space-y-5">
  <h2 class="section-heading">Norfa leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa leidinys – tai reguliariai atnaujinamas šio prekybos tinklo prekių katalogas, apžvelgiantis aktualų asortimentą ir temines rubrikas. Naujas numeris paprastai pasirodo kas dvi savaites. Internete jis dažnai ieškomas kaip Norfa akcijų leidinys ar Norfa savaitinis akcijų leidinys.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Norfa?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa yra Lietuvos kapitalo prekybos tinklas, priklausantis Norfos mažmenos įmonei. Tinklas veikia nacionaliniu mastu ir orientuojasi į kasdienio apsipirkimo parduotuves, kuriose greta maisto produktų rasite buitinę chemiją, kosmetiką, tekstilę, naminių gyvūnų prekes ir smulkią buitinę techniką. Šio tinklo pasiūlymai ir asortimento naujienos nuosekliai pristatomos per periodiškai leidžiamą leidinį, todėl jį patogu naudoti kaip nuorodą į tai, kas svarbiausia pirkėjams dabar. Norfa taip pat turi lojalumo programą NORFA kortelė, kuri yra svarbi bendros parduotuvių ekosistemos dalis. Praktinę informaciją apie parduotuvių adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/norfa">Norfa parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Norfa leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa palaiko vieną pagrindinę leidinių seriją – NORFA. Tai kelių dešimčių puslapių katalogas, kuriame nuosekliai pateikiamos dažniausiai ieškomų kategorijų temos: nuo kasdienių maisto produktų iki buities prekių apžvalgų.</p>
  </div>

  <h2 class="section-heading">Kur rasti Norfa akcijų leidinį internete?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite evaistine.lt kategorijose – ten patogu peržiūrėti tai, ką rodo naujas Norfa savaitinis akcijų leidinys, nebeknibinėjant puslapis po puslapio. Jei jus domina kepiniai, pradėkite nuo skyriaus <a href="/akcijos/norfa/duonos-gaminiai">Duonos gaminiai</a> – čia matysite visus šiuo metu leidinyje aptinkamus įrašus su kainomis vienoje vietoje.</p>
    <p class="leading-relaxed">Susijusios populiarios temos visame portale: <a href="https://evaistine.lt/akcijos/duona">Duona</a> ir <a href="https://evaistine.lt/akcijos/bandeles">Bandelės</a> – jos padeda greitai rasti leidinyje dažniausiai pasitaikančius kepinių įrašus.</p>
  </div>
</div>',
  'aibe' => '<div class="space-y-5">
  <h2 class="section-heading">Aibė leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė leidinys – tai patogus būdas vienoje vietoje apžvelgti kaimynystės parduotuvėlių siūlomas naujienas ir teminį pasirinkimą. Naujas Aibė leidinys paprastai pasirodo du kartus per mėnesį, todėl „Aibė akcijų leidinys“ yra reguliariai atnaujinama serija, kurią patogu sekti.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Aibė?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė – nuo 1999 metų veikiantis nepriklausomų prekybininkų aljansas, suvienijęs kaimynystės parduotuves bendram tinklui ir bendrai rinkodarai. Šiandien jie apjungia apie 1400 mažo formato parduotuvių Lietuvoje ir Latvijoje, todėl Aibė išlieka arti namų ir kasdienių maršrutų. Tinklo stiprybė – patogumas bei vietinis, bendruomenėms artimas asortimentas, atspindintis kasdienius maisto produktus ir buitines prekes. Prekinio ženklo pozicionavimas „mes Jūsų kaimynai“ pabrėžia būtent šį artumą ir greitą apsipirkimą, o lojalumo programa AIBĖ JUMS papildo bendrą ekosistemą. Daugiau informacijos apie parduotuvių vietas ir kontaktus rasite puslapyje <a href="https://evaistine.lt/parduotuves/aibe">Aibė parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Aibė leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė leidinių serija yra viena reguliari – nuolat leidžiamas Aibė leidinys, kuriame telpa kasdienių maisto produktų ir buitinių prekių pasiūlymų apžvalga. Leidinio apimtis paprastai būna kelių dešimčių puslapių, todėl patogu greitai peržvelgti visą aktualų turinį.</p>
  </div>

  <h2 class="section-heading">Kur rasti Aibė leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį evaistine.lt pateikia kaip patogiai naršomą, filtruojamą turinį Aibė skiltyje. Tai greitesnis būdas peržiūrėti, kas įtraukta į Aibė akcijų leidinį, nei versti leidinio puslapius po vieną.</p>
  </div>
</div>',
  'express-market' => '<div class="space-y-5">
  <h2 class="section-heading">Express Market leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market leidinys yra kompaktiškas šio kaimynystės tinklo prekių katalogas, atnaujinamas reguliariai. Pirkėjai jo dažnai ieško pagal frazes Express Market akcijų leidinys ar naujas Express Market leidinys — abu pavadinimai reiškia tą pačią periodiškai pasirodančią seriją. Jame pateikiamos atrinktos rubrikos, kad greitai peržvelgtumėte, kas šiuo metu pristatoma parduotuvėse arti namų.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Express Market?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market yra kaimynystės parduotuvių tinklas, veikiantis Lietuvoje nuo 2003 metų. Tinklą valdo UAB Kilminė, orientuodama veiklą į kompaktiškas parduotuves arti namų ar darbo vietos. Skirtingai nei dideli hipermarketai, šis formatas akcentuoja patogumą ir greitį: užsukti dėl kasdienių prekių, pasinaudoti sąskaitų apmokėjimo ar grynųjų išėmimo paslaugomis. Tinklas užima nišą tarp spaudos kioskų ir didžiųjų supermarketų — išlaikydamas greitą aptarnavimą, bet siūlydamas platesnį kasdienio maisto asortimentą. Dėl tokios koncepcijos leidinys yra koncentruotas ir praktiškas: jis atspindi aktualų, greitam apsipirkimui pritaikytą turinį, kurį pirkėjai randa netoli namų.</p>
  </div>

  <h2 class="section-heading">Naujausi Express Market leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market skelbia vieną nuolat pasikartojantį leidinį; tai kelių puslapių apžvalga, kurioje telpa svarbiausios rubrikos be perteklinės gausos. Leidinyje dažniausiai matysite kasdienio maisto, šviežių vaisių ir daržovių bei higienos prekių temines skiltis, atitinkančias arti namų apsipirkimo įpročius.</p>
  </div>

  <h2 class="section-heading">Kur rasti naują Express Market leidinį internete?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenka į patogų sąrašą mūsų akcijų skiltyje, kad turinį būtų galima peržiūrėti kaip filtrų valdomą katalogą pagal kategorijas. Taip greičiau rasite konkrečias kasdienių prekių grupes nei vartydami leidinį puslapis po puslapio. Sąraše pateikiami tie patys leidinio įrašai, tik patogiai išdėstyti vienas po kito su pagrindine informacija.</p>
  </div>
</div>',
  'silas' => '<div class="space-y-5">
  <h2 class="section-heading">Šilas leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas leidinys – reguliariai leidžiama parduotuvės skrajutė su aiškiai sudėliotomis prekių rubrikomis, kurią pirkėjai dažnai vadina ir Šilas akcijų leidiniu. Naujas leidinys paprastai pasirodo kas dvi savaites, todėl informaciją apie asortimentą patogu sekti periodiškai. Jame apžvelgiamos kasdieniam krepšeliui svarbios temos – nuo bakalėjos iki buitinės chemijos ir kosmetikos prekių.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Šilas?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas – Kaune gimęs mažo formato kaimynystės parduotuvių tinklas, orientuotas į patogumą ir artumą namams. Įmonė veikia nuo 1992 metų ir yra Lietuvos kapitalo verslas, valdomas UAB „Eiginta“. Tinklas šiandien apima apie 33 parduotuves Kauno ir Vilniaus regionuose, todėl jis išlieka regioniškai stiprus ir gerai pažįstamas vietos pirkėjams. Šilas nuosekliai remia lietuviškus tiekėjus, o asortimente daug dėmesio skiriama šviežioms daržovėms, kepiniams ir kasdienėms maisto bei buities prekėms – tai atsispindi ir leidinyje, kuriame akcentuojamos artimos kaimynystės pirkinių kategorijos. Parduotuvės adresus, darbo laikus ir kitą praktinę informaciją rasite puslapyje <a href="https://evaistine.lt/parduotuves/silas">Šilas parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Šilas leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas leidžia vieną nuolatinį leidinį „Šilas“, kuriame apžvelgiamas pagrindinis kasdienių pirkinių asortimentas. Tai kelių puslapių apimties serija, kuri įprastai atnaujinama kas dvi savaites, todėl patogu sekti pasikartojantį leidinio ritmą.</p>
  </div>

  <h2 class="section-heading">Kur rasti Šilas leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite pagal temas: peržiūrėkite <a href="/akcijos/silas/bakaleja">Bakalėja</a>, <a href="/akcijos/silas/pieno-produktai-ir-kiausiniai">Pieno produktai ir kiaušiniai</a>, <a href="/akcijos/silas/mesa-ir-zuvis">Mėsa ir žuvis</a> ar <a href="/akcijos/silas/kosmetika-ir-higiena">Kosmetika ir higiena</a>. Ten Šilas akcijų ir nuolaidų leidinio turinys pateikiamas kaip patogus, filtruojamas sąrašas pagal kategorijas.</p>
    <p class="leading-relaxed">Jei domina konkrečios temos, pravartu užsukti ir į populiarius raktinius puslapius – pavyzdžiui, <a href="https://evaistine.lt/akcijos/makaronai">Makaronai</a> ar <a href="https://evaistine.lt/akcijos/aliejus">Aliejus</a>.</p>
  </div>
</div>',
  'cia' => '<div class="space-y-5">
  <h2 class="section-heading">Čia leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Čia Market leidinys – tai reguliariai atnaujinamas katalogas, kuriame vienoje vietoje matyti, kas šiuo metu įtraukta į tinklo pasiūlymų sąrašą. Kadangi pavadinimas „Čia“ paieškoje gali reikšti bet ką, katalogo ieškokite su aiškiu junginiu, pvz., „Čia Market leidinys“ arba „Čia leidinys“, ir lengvai rasite naujausią numerį. Naujienos skelbiamos nuosekliai visus metus, todėl patogu sekti leidinio turinį, kai tik pasirodo naujas numeris.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Čia?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">ČIA MARKET – Lietuvoje gimęs kaimynystės formato prekybos tinklas, kilęs iš Žemaitijos ir pradėjęs veiklą kaip pieno produktų parduotuvių tinklas. Per laiką išaugęs į patogias kasdienes parduotuves, šiandien jis vienija maždaug šimtą prekybos vietų daugiau nei keliose dešimtyse šalies savivaldybių, todėl yra lengvai pasiekiamas tiek miestuose, tiek mažesniuose miesteliuose. Tinklo profilį papildo ir kelios atskiros Džiugas sūrių parduotuvės, pabrėžiančios ryšį su lietuvišku maisto paveldu. Kaimynystės patogumas, platus geografinis padengimas ir kasdieniams pirkiniams pritaikytas asortimentas daro ČIA MARKET veikimą artimą vietos bendruomenėms. Informaciją apie adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/cia">Čia parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Čia leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Čia leidinys yra vienas nuolat leidžiamas katalogas, kuriame telpa svarbiausi einamojo laikotarpio pasiūlymai. Paprastai tai keliolikos puslapių apimties Čia Market leidinys, apžvelgiantis kasdienius maisto, gėrimų bei buities prekių pasirinkimus, aktualius plačiai pirkėjų auditorijai.</p>
  </div>

  <h2 class="section-heading">Kur rasti Čia Market leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">eVaistine.lt iš Čia Market akcijų leidinio surenka visas prekes ir jų kainas į patogų, ieškomą ir filtruojamą sąrašą. Jei norite peržiūrėti leidinio turinį kaip aiškų sąrašą, užsukite į <a href="https://evaistine.lt/akcijos/cia">Čia Market leidinio prekių sąrašą</a> ir filtruokite pagal jus dominančias prekių kategorijas be vartymo po atskirus leidinio puslapius.</p>
  </div>
</div>',
  'kubas' => '<div class="space-y-5">
  <h2 class="section-heading">Kubas leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Kubas leidinys pristato šio tinklo kasdienio maisto, buities ir pramoninių prekių asortimentą vienoje vietoje. Nauji numeriai skelbiami reguliariai, todėl patogu sekti, kas šiuo metu pateikiama parduotuvėse įvairiuose Lietuvos miestuose. Kubas parduotuvės leidinys aiškiai suskaidytas pagal prekių tipus, kad greitai peržvelgtumėte aktualius skyrius.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Kubas?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">2000 metais Šiauliuose įkurtas Kubas yra regioninis mažmeninės prekybos tinklas, veikiantis kaip vietinis universalus prekybos centras. Šiandien jis vienija apie 32 parduotuves Vilniuje, Kaune, Šiauliuose, Panevėžyje, Marijampolėje ir Meškučiuose, todėl patogiai pasiekiamas skirtinguose Lietuvos regionuose. Tinklas orientuotas į šeimas, dirbančius žmones ir senjorus, siūlydamas platų kasdienio maisto, pramoninių ir buities prekių pasirinkimą iš lietuviškų ir užsienio gamintojų. Informaciją apie aktualų prekių pasiūlos vaizdą Kubas nuosekliai pateikia ir per Kubas akcijų leidinį, kuris apibendrina, kas rodoma parduotuvėse. Daugiau apie adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/kubas">Kubas parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Kubas leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Kubas leidžia vieną nuolatinį leidinį, kuriame apžvelgiamos pagrindinės kasdienio maisto ir pramoninių prekių temos visoms tinklo parduotuvėms. Numeriai dažniausiai yra keliolikos puslapių apimties, tad lengva greitai peržvelgti visus skirsnius vienoje vietoje.</p>
  </div>

  <h2 class="section-heading">Kur rasti Kubas leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenkame į patogų sąrašą, kurį rasite puslapyje <a href="https://evaistine.lt/akcijos/kubas">Kubas leidinio prekės</a>. Čia galite peržiūrėti viską, kas publikuojama Kubas parduotuvės leidinyje, ir greitai rasti dominančius skyrius nebevartant leidinio puslapis po puslapio.</p>
  </div>
</div>',
);

        foreach ($descriptions as $slug => $html) {
            Store::where('slug', $slug)->update(['leaflet_description' => $html]);
        }
    }

    public function down(): void
    {
        $descriptions = array (
  'maxima' => '<div class="space-y-5">
  <h2 class="section-heading">Maxima leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maximos leidinys – tai nuolat atnaujinamas parduotuvių katalogas, kurio naujas Maxima leidinys paprastai pasirodo kas savaitę. Dažniausiai tai Maxima savaitės akcijų leidinys; internete neretai ieškoma ir pagal formuluotę Maxima leidinys Nr., nes serijos būna numeruojamos skirtingoms savaitėms atskirti.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Maxima?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maxima – didžiausias Lietuvos prekybos tinklas, kurio šaknys siekia 1992 metus, kai Vilniuje atidaryta pirmoji „Vilniaus prekybos“ parduotuvė. MAXIMA prekės ženklas pradėtas naudoti 1998 metais, o šiandien tinklas veikia ne tik Lietuvoje, bet ir Latvijoje, Estijoje, Lenkijoje bei Bulgarijoje ir priklauso „Maxima Grupė“ įmonių šeimai. Parduotuvės skirstomos į MAXIMA X, XX, XXX ir XXXX formatus – nuo kaimynystės tipo parduotuvių iki ypač plataus asortimento prekybos centrų, kuriuos atspindi ir leidinio temų įvairovė – nuo maisto produktų iki buitinės chemijos ar namų prekių. Daugiau praktinės informacijos apie adresus ir darbo laiką rasite puslapyje <a href="https://evaistine.lt/parduotuves/maxima">Maxima parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Maxima leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Maxima skelbia kelias leidinių serijas, todėl paieškose greta užklausų naujas Maxima leidinys ar Maxima akcijų leidinys dažnai sutinkami ir teminiai numeriai.</p>
    <ul class="list-disc pl-5 space-y-1">
      <li class="leading-relaxed">AČIŪ savaitinis leidinys – pagrindinė savaitinė serija su kasdienio apsipirkimo asortimentu: maisto produktai, šviežios gėrybės ir buities prekės.</li>
      <li class="leading-relaxed">MAXIMOS leidinys SKONIŲ DIENOS – teminis maisto produktų rinkinys, akcentuojantis skonių kryptis ir gastronomines naujienas.</li>
      <li class="leading-relaxed">Švaros mugė – leidinys, skirtas buitinės chemijos ir valymo priemonėms, taip pat namų ūkio smulkmenoms.</li>
      <li class="leading-relaxed">ITALIJOS MĖNUO – itališkų skonių ir produktų teminis leidinys, suburiantis makaronus, padažus, alyvuogių aliejų ir panašias prekes.</li>
    </ul>
  </div>

  <h2 class="section-heading">Kur rasti Maxima leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį galite filtruoti pagal temas. Dabartinį pasirinkimą patogu peržvelgti kategorijose <a href="/akcijos/maxima/buitine-chemija-valymo-priemones">Buitinė chemija, valymo priemonės</a>, <a href="/akcijos/maxima/gerimai-kava-arbata">Gėrimai, kava, arbata</a>, <a href="/akcijos/maxima/kosmetika-ir-higiena">Kosmetika ir higiena</a> ar <a href="/akcijos/maxima/namu-ukio-ir-laisvalaikio-prekes">Namų ūkio ir laisvalaikio prekės</a>.</p>
    <p class="leading-relaxed">Tarp populiarių temų rasite ir tikslinius sąrašus, pavyzdžiui, <a href="https://evaistine.lt/akcijos/valikliai">Valikliai</a> ar <a href="https://evaistine.lt/akcijos/skalbimo-priemones">Skalbimo priemonės</a>, jei domina konkreti leidinio sritis.</p>
  </div>
</div>',
  'lidl' => '<div class="space-y-5">
  <h2 class="section-heading">Lidl leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl leidinys – tai reguliariai atnaujinamas katalogas, kuriame vienoje vietoje pristatomos naujai pasirodančios maisto ir ne maisto prekių rubrikos bei teminės kolekcijos. Naujas Lidl leidinys paprastai pasirodo kelis kartus per mėnesį, todėl aktualų turinį patogu sekti internetu. Kiekviename leidinyje aiškiai išskiriamos skiltys, kad greitai rastumėte jus dominančias kategorijas.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Lidl?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl – Vokietijoje įkurtas tarptautinis diskonto prekybos tinklas, veikiantis daugelyje Europos šalių. Lietuvoje pirmąsias parduotuves atidarė 2016 metais ir nuo tada išplėtė tinklą įvairiuose miestuose, siūlydamas patogias, greitam apsipirkimui pritaikytas parduotuves. Šio tinklo ypatybė – nuolat kintančios teminės ne maisto prekių programos šalia kasdienio maisto asortimento: pirkėjai čia randa Parkside įrankių, Silvercrest smulkios buitinės technikos, Livarno namų sprendimų, Crivit sporto ir Esmara drabužių linijas. Dauguma asortimento sudaryta iš privačių prekių ženklų, todėl leidinyje ryškiai pristatomos tiek maisto, tiek sezoninės tematikos rubrikos. Daugiau praktinės informacijos rasite puslapyje <a href="https://evaistine.lt/parduotuves/lidl">Lidl parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Lidl leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Lidl akcijų leidinys pateikiamas kaip vienas reguliarus leidinys, dažniausiai kelių dešimčių puslapių apimties. Dažnai ieškoma kaip Lidl maisto prekių akcijų leidinys ar Lidl ne maisto prekių akcijų leidinys; abu turinio tipai paprastai pristatomi tame pačiame kataloge – nuo maisto rubrikų iki teminių ne maisto kolekcijų.</p>
  </div>

  <h2 class="section-heading">Kur rasti Lidl leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenka į patogų sąrašą parduotuvės akcijų puslapyje, kad galėtumėte peržiūrėti turinį kaip filtruojamą sąrašą, o ne vartant puslapius. Ten lengva atsirinkti maisto produktus, namų apyvokos prekes, įrankius, sporto ir drabužių kategorijas, kai Lidl akcijų leidinys pristato naują temą. Atnaujinus leidinį, sąrašas automatiškai pasipildo naujais įrašais, tad visa informacija pateikiama vienoje vietoje.</p>
  </div>
</div>',
  'iki' => '<div class="space-y-5">
  <h2 class="section-heading">Iki leidynys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki leidinys – tai periodiškai atnaujinamas IKI parduotuvių katalogas su aiškiai sudėliotu prekių asortimentu ir aktualiu turiniu. Nauji numeriai pasirodo kelis kartus per mėnesį; paieškose jis dažnai randamas įvedus „naujausias Iki leidinys“, „Iki savaitėlė“ ar net „Iki savaitėlė akcijų leidinys“.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Iki?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki yra vienas seniausių šiuolaikinių Lietuvos prekybos tinklų, veikiantis nuo 1992 metų. Tinklas priklauso Vokietijos REWE grupei, o strateginė partnerystė nuo 2018 metų suteikia stabilų tiekimo ir asortimento valdymo pagrindą. Kaip nacionalinis supermarketų tinklas, Iki išsiskiria plačiu maisto prekių pasirinkimu, papildytu namų ūkio ir gyvūnų prekėmis, todėl leidinys nuosekliai apima kasdienės paklausos tematiką. Tinkle veikia lojalumo programa IKI Premija, o parduotuvės patogiai išsidėsčiusios įvairiuose šalies miestuose ir rajonuose. Parduotuvėlių adresus, darbo laikus ir kontaktus rasite puslapyje <a href="https://evaistine.lt/parduotuves/iki">Iki parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Iki leidyniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Iki skelbia vieną nuolat atnaujinamą katalogą – Iki leidinį, kuriame apžvelgiamas platus maisto ir kasdienio vartojimo prekių pasirinkimas. Įprastai jis būna keliolikos puslapių apimties, tad aktualų turinį galima greitai peržvelgti vienoje vietoje.</p>
  </div>

  <h2 class="section-heading">Kur rasti naujausio Iki leidinio turinį?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į patogų sąrašą, kurį galima naršyti pagal temas ir prekių rūšis. Jei domina mėsos ir žuvies ar kepinių skiltys, užsukite į nuolat atnaujinamus sąrašus: <a href="/akcijos/iki/mesa-ir-zuvis">Mėsa ir žuvis </a> ir <a href="/akcijos/iki/duonos-gaminiai">Duonos gaminiai </a>.</p>
    <p class="leading-relaxed">Tarp populiarių paieškų pagal produktus – konkrečios mėsos rūšys. Greitai pasieksite temas <a href="https://evaistine.lt/akcijos/jautiena">Jautiena</a> ar <a href="https://evaistine.lt/akcijos/vistienos-krutinele">Vištienos krūtinėlė</a>, kad matytumėte, kaip jos pateikiamos naujausiame Iki leidinyje.</p>
  </div>
</div>',
  'rimi' => '<div class="space-y-5">
  <h2 class="section-heading">Rimi leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi leidinys – tai spausdintas ir skaitmeninis katalogas, kuriame vienoje vietoje pateikiamas einamos savaitės prekių asortimentas ir rubrikos. Naujas Rimi leidinys pasirodo kas savaitę, tad informacija atsinaujina pastoviu ritmu. Jį patogu perversti internete arba pasiimti parduotuvėje.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Rimi?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi Lietuvoje veikia kaip Rimi Baltic dalis, priklausanti Švedijos koncernui ICA Gruppen. Tinklas vysto du parduotuvių formatus – Rimi Hyper ir Rimi Super – kad patogiai aptarnautų tiek didesnius savaitinius apsipirkimus, tiek kasdienius užėjimus. Asortimente rasite pilną kasdienio pirkimo krepšelį: šviežius vaisius ir daržoves, duoną ir kepinius, mėsą bei pieno produktus, taip pat buities ir higienos, naminių gyvūnėlių prekes. Rimi taip pat valdo internetinę parduotuvę ir siūlo pristatymą į namus, todėl tai, ką matote leidinyje, lengvai pasiekiama ir internetu. Parduotuvės adresus, darbo laikus ir kitą informaciją rasite puslapyje <a href="https://evaistine.lt/parduotuves/rimi">Rimi parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Rimi leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Rimi turi vieną reguliariai leidžiamą seriją pavadinimu „Rimi“ – tai Rimi savaitinis leidinys, apžvelgiantis pagrindines maisto, buities ir higienos rubrikas. Leidinys paprastai yra kelių dešimčių puslapių apimties; pirkėjai jį neretai vadina Rimi akcijų leidiniu.</p>
  </div>

  <h2 class="section-heading">Kur rasti Rimi akcijų leidinio turinį?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite evaistine.lt – taip aktualų turinį galima filtruoti pagal temas be vartymo po puslapius. Iš karto peržvelkite šias sritis: <a href="/akcijos/rimi/vaiku-ir-kudikiu-prekes">Vaikų ir kūdikių prekės</a>, <a href="/akcijos/rimi/buitine-chemija-valymo-priemones">Buitinė chemija, valymo priemonės</a>, <a href="/akcijos/rimi/kosmetika-ir-higiena">Kosmetika ir higiena</a>.</p>
    <p class="leading-relaxed">Tarp dažniausiai ieškomų temų, kurios dažnai atsispindi ir leidinio turinyje, yra <a href="https://evaistine.lt/akcijos/sauskelnems">Sauskelnės</a> bei <a href="https://evaistine.lt/akcijos/skalbiklis">Skalbiklis</a>.</p>
  </div>
</div>',
  'norfa' => '<div class="space-y-5">
  <h2 class="section-heading">Norfa leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa leidinys – tai reguliariai atnaujinamas šio prekybos tinklo prekių katalogas, apžvelgiantis aktualų asortimentą ir temines rubrikas. Naujas numeris paprastai pasirodo kas dvi savaites. Internete jis dažnai ieškomas kaip Norfa akcijų leidinys ar Norfa savaitinis akcijų leidinys.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Norfa?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa yra Lietuvos kapitalo prekybos tinklas, priklausantis Norfos mažmenos įmonei. Tinklas veikia nacionaliniu mastu ir orientuojasi į kasdienio apsipirkimo parduotuves, kuriose greta maisto produktų rasite buitinę chemiją, kosmetiką, tekstilę, naminių gyvūnų prekes ir smulkią buitinę techniką. Šio tinklo pasiūlymai ir asortimento naujienos nuosekliai pristatomos per periodiškai leidžiamą leidinį, todėl jį patogu naudoti kaip nuorodą į tai, kas svarbiausia pirkėjams dabar. Norfa taip pat turi lojalumo programą NORFA kortelė, kuri yra svarbi bendros parduotuvių ekosistemos dalis. Praktinę informaciją apie parduotuvių adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/norfa">Norfa parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Norfa leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Norfa palaiko vieną pagrindinę leidinių seriją – NORFA. Tai kelių dešimčių puslapių katalogas, kuriame nuosekliai pateikiamos dažniausiai ieškomų kategorijų temos: nuo kasdienių maisto produktų iki buities prekių apžvalgų.</p>
  </div>

  <h2 class="section-heading">Kur rasti Norfa akcijų leidinį internete?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite evaistine.lt kategorijose – ten patogu peržiūrėti tai, ką rodo naujas Norfa savaitinis akcijų leidinys, nebeknibinėjant puslapis po puslapio. Jei jus domina kepiniai, pradėkite nuo skyriaus <a href="/akcijos/norfa/duonos-gaminiai">Duonos gaminiai</a> – čia matysite visus šiuo metu leidinyje aptinkamus įrašus su kainomis vienoje vietoje.</p>
    <p class="leading-relaxed">Susijusios populiarios temos visame portale: <a href="https://evaistine.lt/akcijos/duona">Duona</a> ir <a href="https://evaistine.lt/akcijos/bandeles">Bandelės</a> – jos padeda greitai rasti leidinyje dažniausiai pasitaikančius kepinių įrašus.</p>
  </div>
</div>',
  'aibe' => '<div class="space-y-5">
  <h2 class="section-heading">Aibė leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė leidinys – tai patogus būdas vienoje vietoje apžvelgti kaimynystės parduotuvėlių siūlomas naujienas ir teminį pasirinkimą. Naujas Aibė leidinys paprastai pasirodo du kartus per mėnesį, todėl „Aibė akcijų leidinys“ yra reguliariai atnaujinama serija, kurią patogu sekti.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Aibė?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė – nuo 1999 metų veikiantis nepriklausomų prekybininkų aljansas, suvienijęs kaimynystės parduotuves bendram tinklui ir bendrai rinkodarai. Šiandien jie apjungia apie 1400 mažo formato parduotuvių Lietuvoje ir Latvijoje, todėl Aibė išlieka arti namų ir kasdienių maršrutų. Tinklo stiprybė – patogumas bei vietinis, bendruomenėms artimas asortimentas, atspindintis kasdienius maisto produktus ir buitines prekes. Prekinio ženklo pozicionavimas „mes Jūsų kaimynai“ pabrėžia būtent šį artumą ir greitą apsipirkimą, o lojalumo programa AIBĖ JUMS papildo bendrą ekosistemą. Daugiau informacijos apie parduotuvių vietas ir kontaktus rasite puslapyje <a href="https://evaistine.lt/parduotuves/aibe">Aibė parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Aibė leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Aibė leidinių serija yra viena reguliari – nuolat leidžiamas Aibė leidinys, kuriame telpa kasdienių maisto produktų ir buitinių prekių pasiūlymų apžvalga. Leidinio apimtis paprastai būna kelių dešimčių puslapių, todėl patogu greitai peržvelgti visą aktualų turinį.</p>
  </div>

  <h2 class="section-heading">Kur rasti Aibė leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį evaistine.lt pateikia kaip patogiai naršomą, filtruojamą turinį Aibė skiltyje. Tai greitesnis būdas peržiūrėti, kas įtraukta į Aibė akcijų leidinį, nei versti leidinio puslapius po vieną.</p>
  </div>
</div>',
  'express-market' => '<div class="space-y-5">
  <h2 class="section-heading">Express Market leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market leidinys yra kompaktiškas šio kaimynystės tinklo prekių katalogas, atnaujinamas reguliariai. Pirkėjai jo dažnai ieško pagal frazes Express Market akcijų leidinys ar naujas Express Market leidinys — abu pavadinimai reiškia tą pačią periodiškai pasirodančią seriją. Jame pateikiamos atrinktos rubrikos, kad greitai peržvelgtumėte, kas šiuo metu pristatoma parduotuvėse arti namų.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Express Market?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market yra kaimynystės parduotuvių tinklas, veikiantis Lietuvoje nuo 2003 metų. Tinklą valdo UAB Kilminė, orientuodama veiklą į kompaktiškas parduotuves arti namų ar darbo vietos. Skirtingai nei dideli hipermarketai, šis formatas akcentuoja patogumą ir greitį: užsukti dėl kasdienių prekių, pasinaudoti sąskaitų apmokėjimo ar grynųjų išėmimo paslaugomis. Tinklas užima nišą tarp spaudos kioskų ir didžiųjų supermarketų — išlaikydamas greitą aptarnavimą, bet siūlydamas platesnį kasdienio maisto asortimentą. Dėl tokios koncepcijos leidinys yra koncentruotas ir praktiškas: jis atspindi aktualų, greitam apsipirkimui pritaikytą turinį, kurį pirkėjai randa netoli namų.</p>
  </div>

  <h2 class="section-heading">Naujausi Express Market leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Express Market skelbia vieną nuolat pasikartojantį leidinį; tai kelių puslapių apžvalga, kurioje telpa svarbiausios rubrikos be perteklinės gausos. Leidinyje dažniausiai matysite kasdienio maisto, šviežių vaisių ir daržovių bei higienos prekių temines skiltis, atitinkančias arti namų apsipirkimo įpročius.</p>
  </div>

  <h2 class="section-heading">Kur rasti naują Express Market leidinį internete?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenka į patogų sąrašą mūsų akcijų skiltyje, kad turinį būtų galima peržiūrėti kaip filtrų valdomą katalogą pagal kategorijas. Taip greičiau rasite konkrečias kasdienių prekių grupes nei vartydami leidinį puslapis po puslapio. Sąraše pateikiami tie patys leidinio įrašai, tik patogiai išdėstyti vienas po kito su pagrindine informacija.</p>
  </div>
</div>',
  'silas' => '<div class="space-y-5">
  <h2 class="section-heading">Šilas leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas leidinys – reguliariai leidžiama parduotuvės skrajutė su aiškiai sudėliotomis prekių rubrikomis, kurią pirkėjai dažnai vadina ir Šilas akcijų leidiniu. Naujas leidinys paprastai pasirodo kas dvi savaites, todėl informaciją apie asortimentą patogu sekti periodiškai. Jame apžvelgiamos kasdieniam krepšeliui svarbios temos – nuo bakalėjos iki buitinės chemijos ir kosmetikos prekių.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Šilas?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas – Kaune gimęs mažo formato kaimynystės parduotuvių tinklas, orientuotas į patogumą ir artumą namams. Įmonė veikia nuo 1992 metų ir yra Lietuvos kapitalo verslas, valdomas UAB „Eiginta“. Tinklas šiandien apima apie 33 parduotuves Kauno ir Vilniaus regionuose, todėl jis išlieka regioniškai stiprus ir gerai pažįstamas vietos pirkėjams. Šilas nuosekliai remia lietuviškus tiekėjus, o asortimente daug dėmesio skiriama šviežioms daržovėms, kepiniams ir kasdienėms maisto bei buities prekėms – tai atsispindi ir leidinyje, kuriame akcentuojamos artimos kaimynystės pirkinių kategorijos. Parduotuvės adresus, darbo laikus ir kitą praktinę informaciją rasite puslapyje <a href="https://evaistine.lt/parduotuves/silas">Šilas parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Šilas leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šilas leidžia vieną nuolatinį leidinį „Šilas“, kuriame apžvelgiamas pagrindinis kasdienių pirkinių asortimentas. Tai kelių puslapių apimties serija, kuri įprastai atnaujinama kas dvi savaites, todėl patogu sekti pasikartojantį leidinio ritmą.</p>
  </div>

  <h2 class="section-heading">Kur rasti Šilas leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas surenkame į sąrašą, kurį rasite pagal temas: peržiūrėkite <a href="/akcijos/silas/bakaleja">Bakalėja</a>, <a href="/akcijos/silas/pieno-produktai-ir-kiausiniai">Pieno produktai ir kiaušiniai</a>, <a href="/akcijos/silas/mesa-ir-zuvis">Mėsa ir žuvis</a> ar <a href="/akcijos/silas/kosmetika-ir-higiena">Kosmetika ir higiena</a>. Ten Šilas akcijų ir nuolaidų leidinio turinys pateikiamas kaip patogus, filtruojamas sąrašas pagal kategorijas.</p>
    <p class="leading-relaxed">Jei domina konkrečios temos, pravartu užsukti ir į populiarius raktinius puslapius – pavyzdžiui, <a href="https://evaistine.lt/akcijos/makaronai">Makaronai</a> ar <a href="https://evaistine.lt/akcijos/aliejus">Aliejus</a>.</p>
  </div>
</div>',
  'cia' => '<div class="space-y-5">
  <h2 class="section-heading">Čia leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Čia Market leidinys – tai reguliariai atnaujinamas katalogas, kuriame vienoje vietoje matyti, kas šiuo metu įtraukta į tinklo pasiūlymų sąrašą. Kadangi pavadinimas „Čia“ paieškoje gali reikšti bet ką, katalogo ieškokite su aiškiu junginiu, pvz., „Čia Market leidinys“ arba „Čia leidinys“, ir lengvai rasite naujausią numerį. Naujienos skelbiamos nuosekliai visus metus, todėl patogu sekti leidinio turinį, kai tik pasirodo naujas numeris.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Čia?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">ČIA MARKET – Lietuvoje gimęs kaimynystės formato prekybos tinklas, kilęs iš Žemaitijos ir pradėjęs veiklą kaip pieno produktų parduotuvių tinklas. Per laiką išaugęs į patogias kasdienes parduotuves, šiandien jis vienija maždaug šimtą prekybos vietų daugiau nei keliose dešimtyse šalies savivaldybių, todėl yra lengvai pasiekiamas tiek miestuose, tiek mažesniuose miesteliuose. Tinklo profilį papildo ir kelios atskiros Džiugas sūrių parduotuvės, pabrėžiančios ryšį su lietuvišku maisto paveldu. Kaimynystės patogumas, platus geografinis padengimas ir kasdieniams pirkiniams pritaikytas asortimentas daro ČIA MARKET veikimą artimą vietos bendruomenėms. Informaciją apie adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/cia">Čia parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Čia leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Čia leidinys yra vienas nuolat leidžiamas katalogas, kuriame telpa svarbiausi einamojo laikotarpio pasiūlymai. Paprastai tai keliolikos puslapių apimties Čia Market leidinys, apžvelgiantis kasdienius maisto, gėrimų bei buities prekių pasirinkimus, aktualius plačiai pirkėjų auditorijai.</p>
  </div>

  <h2 class="section-heading">Kur rasti Čia Market leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">eVaistine.lt iš Čia Market akcijų leidinio surenka visas prekes ir jų kainas į patogų, ieškomą ir filtruojamą sąrašą. Jei norite peržiūrėti leidinio turinį kaip aiškų sąrašą, užsukite į <a href="https://evaistine.lt/akcijos/cia">Čia Market leidinio prekių sąrašą</a> ir filtruokite pagal jus dominančias prekių kategorijas be vartymo po atskirus leidinio puslapius.</p>
  </div>
</div>',
  'kubas' => '<div class="space-y-5">
  <h2 class="section-heading">Kubas leidinys: viskas, ką reikia žinoti</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Kubas leidinys pristato šio tinklo kasdienio maisto, buities ir pramoninių prekių asortimentą vienoje vietoje. Nauji numeriai skelbiami reguliariai, todėl patogu sekti, kas šiuo metu pateikiama parduotuvėse įvairiuose Lietuvos miestuose. Kubas parduotuvės leidinys aiškiai suskaidytas pagal prekių tipus, kad greitai peržvelgtumėte aktualius skyrius.</p>
  </div>

  <h2 class="section-heading">Kodėl verta rinktis Kubas?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">2000 metais Šiauliuose įkurtas Kubas yra regioninis mažmeninės prekybos tinklas, veikiantis kaip vietinis universalus prekybos centras. Šiandien jis vienija apie 32 parduotuves Vilniuje, Kaune, Šiauliuose, Panevėžyje, Marijampolėje ir Meškučiuose, todėl patogiai pasiekiamas skirtinguose Lietuvos regionuose. Tinklas orientuotas į šeimas, dirbančius žmones ir senjorus, siūlydamas platų kasdienio maisto, pramoninių ir buities prekių pasirinkimą iš lietuviškų ir užsienio gamintojų. Informaciją apie aktualų prekių pasiūlos vaizdą Kubas nuosekliai pateikia ir per Kubas akcijų leidinį, kuris apibendrina, kas rodoma parduotuvėse. Daugiau apie adresus ir darbo laikus rasite puslapyje <a href="https://evaistine.lt/parduotuves/kubas">Kubas parduotuvės ir kontaktai</a>.</p>
  </div>

  <h2 class="section-heading">Naujausi Kubas leidiniai</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Kubas leidžia vieną nuolatinį leidinį, kuriame apžvelgiamos pagrindinės kasdienio maisto ir pramoninių prekių temos visoms tinklo parduotuvėms. Numeriai dažniausiai yra keliolikos puslapių apimties, tad lengva greitai peržvelgti visus skirsnius vienoje vietoje.</p>
  </div>

  <h2 class="section-heading">Kur rasti Kubas leidinio pasiūlymus?</h2>
  <div class="mt-2 space-y-2">
    <p class="leading-relaxed">Šio leidinio prekes ir kainas evaistine.lt surenkame į patogų sąrašą, kurį rasite puslapyje <a href="https://evaistine.lt/akcijos/kubas">Kubas leidinio prekės</a>. Čia galite peržiūrėti viską, kas publikuojama Kubas parduotuvės leidinyje, ir greitai rasti dominančius skyrius nebevartant leidinio puslapis po puslapio.</p>
  </div>
</div>',
);

        Store::whereIn('slug', array_keys($descriptions))->update(['leaflet_description' => null]);
    }
};
