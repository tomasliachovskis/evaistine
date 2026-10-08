<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical">
    <x-page-breadcrumbs :items="$breadcrumbs" current="/apie" />

    <div class="base-container">
        <div class="flex flex-col gap-4 pb-[56px]">
            <h1>Apie eVaistine.lt</h1>

            <h2 class="mb-4 text-xl font-semibold">Kas mes esame</h2>
            <p class="mb-6 text-base">
                eVaistine.lt yra kainų ir akcijų palyginimo svetainė — mes nesame vaistinė ir
                nieko patys neparduodame. Renkame ir vienoje vietoje sudedame Lietuvos vaistinių
                ({{ \App\Support\StoreListPriority::mainNamesText() }} ir kitų) viešai skelbiamas kainas,
                akcijas ir leidinius, kad galėtumėte greitai palyginti kainas ir rasti geriausią pasiūlymą,
                nereikėdami tikrinti kiekvienos vaistinės svetainės atskirai.
            </p>

            <h2 class="mb-4 text-xl font-semibold">Kaip veikia kainų rinkimas</h2>
            <p class="mb-6 text-base">
                Kainos, nuolaidos ir leidiniai renkami automatizuotai iš viešai prieinamų vaistinių
                svetainių ir atnaujinami reguliariai. Kadangi duomenys renkami automatiškai,
                tarp faktinio kainos pasikeitimo vaistinėje ir jos atsiradimo mūsų svetainėje gali
                praeiti šiek tiek laiko — visada rekomenduojame galutinę kainą patikrinti vaistinėje
                ar jos svetainėje prieš perkant. Informacija svetainėje nėra medicininė konsultacija —
                dėl vaistų vartojimo pasitarkite su vaistininku ar gydytoju.
            </p>

            <h2 class="mb-4 text-xl font-semibold" id="kontaktai">Kontaktai</h2>
            <p class="mb-4 text-base">
                Turite klausimų, pastebėjote neteisingą informaciją ar norite susisiekti dėl bendradarbiavimo?
                Rašykite mums:
            </p>
            <p class="mb-6 text-base">El. paštas: <a href="mailto:info@evaistine.lt" class="text-primary underline hover:text-dark-green">info@evaistine.lt</a></p>
            <p class="text-base">
                Su asmens duomenų tvarkymu susijusius klausimus rasite
                <a href="/privatumo-politika" class="text-primary underline hover:text-dark-green">privatumo politikoje</a>.
                Kas atsako už prekes, kainas ir pirkimą, aprašyta
                <a href="/naudojimosi-taisykles" class="text-primary underline hover:text-dark-green">naudojimosi taisyklėse</a>.
            </p>
        </div>
    </div>
</x-layouts.app>
