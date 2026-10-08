<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical">
    <x-page-breadcrumbs :items="$breadcrumbs" current="/privatumo-politika" />

    {{-- Describes what the site really stores (docs/evaistine.md, "Legal").
         Change it when data handling changes (analytics, a new login
         provider, a mail provider), and update the date below. --}}
    <div class="base-container">
        <div class="flex max-w-3xl flex-col gap-4 pb-[56px]">
            <h1>Privatumo politika</h1>
            <p class="mb-6 text-sm text-gray-600">Paskutinį kartą atnaujinta: 2026-10-08</p>

            <h2 class="mb-4 text-xl font-semibold">1. Duomenų valdytojas</h2>
            <p class="mb-6 text-base">
                Jūsų asmens duomenis tvarko svetainės evaistine.lt valdytojas (toliau &ndash; &bdquo;mes&ldquo;).
                Klausimais dėl asmens duomenų rašykite
                <a href="mailto:info@evaistine.lt" class="text-dark-green underline">info@evaistine.lt</a>.
            </p>

            <h2 class="mb-4 text-xl font-semibold">2. Kokius duomenis tvarkome ir kodėl</h2>
            <p class="mb-4 text-base">Kainas palyginti galite neprisijungę ir nieko apie save nenurodę. Duomenis tvarkome tik tada, kai patys pasinaudojate šiomis funkcijomis:</p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2"><strong>Paskyra.</strong> El. pašto adresas, o jei jungiatės per Google ar Facebook, ir vardas bei to paslaugų teikėjo paskyros identifikatorius. Reikia, kad galėtumėte prisijungti (prisijungimo nuoroda ar kodas siunčiami el. paštu). Pagrindas: sutartis, t. y. jūsų prašymu teikiama paslauga.</li>
                <li class="mb-2"><strong>Sekamos prekės ir pasirinktos vaistinės.</strong> Kokias prekes sekate ir kurias vaistines pasirinkote. Reikia, kad galėtume parodyti jūsų sąrašą ir pranešti apie kainos pokyčius. Pagrindas: sutartis.</li>
                <li class="mb-2"><strong>Laiškai.</strong> El. pašto adresas, pasirinktos vaistinės ir kokius laiškus norite gauti (kainų pranešimai, savaitės apžvalga, nauji leidiniai). Pagrindas: jūsų sutikimas, kurį galite bet kada atšaukti nuoroda kiekviename laiške.</li>
                <li class="mb-2"><strong>Paieškos užklausos.</strong> Ką ieškojote, IP adresas ir šalis. Reikia svetainės paieškai tobulinti ir piktnaudžiavimui stabdyti. Pagrindas: teisėtas interesas.</li>
                <li class="mb-2"><strong>Serverio žurnalai.</strong> IP adresas, naršyklė, aplankyti puslapiai ir klaidos. Reikia svetainės saugumui ir veikimui užtikrinti. Pagrindas: teisėtas interesas.</li>
            </ul>
            <p class="mb-6 text-base">
                Sveikatos duomenų nerenkame. Atkreipkite dėmesį, kad sekamų prekių sąrašas gali atskleisti jūsų
                pomėgius ar poreikius, todėl jį matote tik jūs ir naudojame tik pranešimams siųsti.
            </p>

            <h2 class="mb-4 text-xl font-semibold">3. Kiek laiko saugome</h2>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">Paskyros, sekamų prekių ir vaistinių duomenis saugome, kol turite paskyrą.</li>
                <li class="mb-2">Laiškų prenumeratos duomenis saugome, kol jos neatsisakote.</li>
                <li class="mb-2">Paieškos užklausas su IP adresu saugome 12 mėnesių.</li>
                <li class="mb-2">Serverio žurnalus saugome ne ilgiau kaip 30 dienų.</li>
            </ul>

            <h2 class="mb-4 text-xl font-semibold">4. Kam perduodame duomenis</h2>
            <p class="mb-6 text-base">
                Duomenų neparduodame ir reklamos tikslais neperduodame. Juos gali tvarkyti tik mūsų paslaugų
                teikėjai: serverių nuomos ir el. laiškų siuntimo paslaugų teikėjai, o jungiantis per Google ar
                Facebook &ndash; atitinkamai Google ir Meta. Vaistinėms jūsų duomenų neperduodame: paspaudę
                nuorodą į vaistinę, pereinate į jos svetainę, kur galioja jos privatumo politika.
            </p>

            <h2 class="mb-4 text-xl font-semibold">5. Slapukai</h2>
            <p class="mb-4 text-base">
                Naudojame tik būtinus slapukus, be kurių svetainė neveiktų. Analitikos ir reklamos slapukų nenaudojame,
                todėl jūsų sutikimo jiems nereikia:
            </p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2"><code>evaistinelt_session</code> &ndash; prisijungimo sesija (2 valandos);</li>
                <li class="mb-2"><code>XSRF-TOKEN</code> &ndash; apsauga nuo suklastotų užklausų (2 valandos);</li>
                <li class="mb-2"><code>evaistine_parduotuves_v1</code>, <code>evaistine_visos_parduotuves</code> &ndash; jūsų pasirinktos vaistinės, kad sąrašai būtų rodomi pagal jas.</li>
            </ul>
            <p class="mb-6 text-base">
                Naršyklės atmintyje (localStorage) taip pat saugome jūsų pasirinktas vaistines ir filtrų nustatymus.
                Šiuos duomenis bet kada galite ištrinti naršyklės nustatymuose.
            </p>

            <h2 class="mb-4 text-xl font-semibold">6. Jūsų teisės</h2>
            <p class="mb-4 text-base">Pagal Bendrąjį duomenų apsaugos reglamentą (BDAR) turite teisę:</p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">sužinoti, kokius jūsų duomenis tvarkome, ir gauti jų kopiją;</li>
                <li class="mb-2">reikalauti ištaisyti netikslius duomenis;</li>
                <li class="mb-2">reikalauti ištrinti duomenis (&bdquo;teisė būti pamirštam&ldquo;);</li>
                <li class="mb-2">apriboti duomenų tvarkymą arba nesutikti su juo;</li>
                <li class="mb-2">gauti savo duomenis perkeliamu formatu;</li>
                <li class="mb-2">bet kada atšaukti sutikimą gauti laiškus.</li>
            </ul>
            <p class="mb-6 text-base">
                Norėdami pasinaudoti teisėmis, rašykite
                <a href="mailto:info@evaistine.lt" class="text-dark-green underline">info@evaistine.lt</a>. Atsakysime per mėnesį.
                Jei manote, kad jūsų duomenis tvarkome netinkamai, galite pateikti skundą Valstybinei duomenų apsaugos
                inspekcijai (<a href="https://vdai.lrv.lt" target="_blank" rel="noopener noreferrer" class="text-dark-green underline">vdai.lrv.lt</a>).
            </p>

            <h2 class="mb-4 text-xl font-semibold">7. Duomenų saugumas</h2>
            <p class="mb-6 text-base">
                Svetainė veikia per šifruotą ryšį (HTTPS). Prieiga prie duomenų suteikiama tik tiems, kam jos reikia
                svetainei prižiūrėti. Slaptažodžiai, jei juos naudojate, saugomi tik užšifruoti.
            </p>

            <h2 class="mb-4 text-xl font-semibold">8. Politikos keitimas</h2>
            <p class="mb-6 text-base">
                Pasikeitus duomenų tvarkymui, atnaujinsime šią politiką ir datą puslapio viršuje. Svetainės naudojimo
                sąlygos aprašytos <a href="/naudojimosi-taisykles" class="text-dark-green underline">naudojimosi taisyklėse</a>.
            </p>
        </div>
    </div>
</x-layouts.app>
