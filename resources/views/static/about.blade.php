<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical">
    <x-page-breadcrumbs :items="$breadcrumbs" current="/apie" />

    <div class="base-container">
        <div class="flex flex-col gap-4 pb-[56px]">
            <h1>Apie eVaistine.lt</h1>

            <h2 class="mb-4 text-xl font-semibold">Kas mes esame</h2>
            <p class="mb-6 text-base">
                eVaistine.lt yra kainų ir akcijų palyginimo svetainė — mes nesame parduotuvė ir
                nieko patys neparduodame. Renkame ir vienoje vietoje sudedame Lietuvos prekybos tinklų
                (Maxima, Lidl, Iki, Rimi, Norfa ir kitų) viešai skelbiamas akcijas, nuolaidas ir
                savaitės leidinius, kad galėtumėte greitai palyginti kainas ir rasti geriausią pasiūlymą,
                nereikalaudami vaikščioti po kiekvienos parduotuvės svetainę atskirai.
            </p>

            <h2 class="mb-4 text-xl font-semibold">Kaip veikia kainų rinkimas</h2>
            <p class="mb-6 text-base">
                Kainos, nuolaidos ir leidiniai renkami automatizuotai iš viešai prieinamų prekybos
                tinklų šaltinių ir atnaujinami reguliariai. Kadangi duomenys renkami automatiškai,
                tarp faktinio kainos pasikeitimo parduotuvėje ir jos atsiradimo mūsų svetainėje gali
                praeiti šiek tiek laiko — visada rekomenduojame galutinę kainą patikrinti parduotuvėje
                ar prekybos tinklo svetainėje prieš perkant.
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
            </p>
        </div>
    </div>
</x-layouts.app>
