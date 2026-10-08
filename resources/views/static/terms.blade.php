<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical">
    <x-page-breadcrumbs :items="$breadcrumbs" current="/naudojimosi-taisykles" />

    {{-- Wording and the research behind it: docs/evaistine.md ("Legal").
         Update the date below whenever the text changes. --}}
    <div class="base-container">
        <div class="flex max-w-3xl flex-col gap-4 pb-[56px]">
            <h1>Naudojimosi taisyklės</h1>
            <p class="mb-6 text-sm text-gray-600">Galioja nuo 2026-10-08</p>

            <h2 class="mb-4 text-xl font-semibold">1. Kas valdo svetainę</h2>
            <p class="mb-6 text-base">
                Svetainę evaistine.lt (toliau &ndash; &bdquo;eVaistine.lt&ldquo;, &bdquo;mes&ldquo;) valdo eVaistine.lt valdytojas.
                Susisiekti galite el. paštu <a href="mailto:info@evaistine.lt" class="text-dark-green underline">info@evaistine.lt</a>.
                Naudodamiesi svetaine sutinkate su šiomis taisyklėmis.
            </p>

            <h2 class="mb-4 text-xl font-semibold">2. Kas yra eVaistine.lt</h2>
            <p class="mb-4 text-base">
                eVaistine.lt yra vaistų ir vaistinės prekių kainų palyginimo svetainė. Mes nesame vaistinė,
                vaistų gamintojas ar platintojas:
            </p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">neparduodame vaistų ir kitų prekių, nepriimame užsakymų ir mokėjimų;</li>
                <li class="mb-2">neteikiame farmacinės ar medicininės konsultacijos;</li>
                <li class="mb-2">nerekomenduojame ir nereklamuojame konkrečių vaistų ar prekių. Prekės ir vaistinės rikiuojamos pagal kainą ir kitus objektyvius požymius, o ne už užmokestį.</li>
            </ul>

            <h2 class="mb-4 text-xl font-semibold">3. Receptiniai vaistai</h2>
            <p class="mb-6 text-base">
                Svetainėje rodomi tik nereceptiniai vaistai, maisto papildai, kosmetika ir kitos vaistinės prekės.
                Receptinių vaistų kainų nerenkame ir nerodome.
            </p>

            <h2 class="mb-4 text-xl font-semibold">4. Kainos ir kita informacija</h2>
            <p class="mb-6 text-base">
                Kainas, akcijas, nuotraukas ir prekių aprašymus automatiškai renkame iš viešai prieinamų vaistinių
                svetainių ir leidinių, juos atnaujiname reguliariai. Vaistinės kainas keičia dažnai, todėl
                informacija svetainėje gali būti pavėlavusi, netiksli ar nepilna. Mes negarantuojame, kad kaina,
                prekės buvimas ar akcijos sąlygos sutaps su vaistinės pasiūlymu. Galioja ta kaina ir tos sąlygos,
                kurias matote vaistinėje ar jos svetainėje pirkimo metu.
            </p>

            <h2 class="mb-4 text-xl font-semibold">5. Pirkimas vaistinėje</h2>
            <p class="mb-6 text-base">
                Paspaudę nuorodą į vaistinę, pereinate į jos svetainę. Prekę perkate iš vaistinės pagal jos
                taisykles. Už prekę, jos kokybę, kainą, pristatymą, grąžinimą ir farmacinę konsultaciją atsako
                vaistinė. Mes nesame pirkimo&ndash;pardavimo sutarties šalis.
            </p>

            <h2 class="mb-4 text-xl font-semibold">6. Sveikatos informacija</h2>
            <p class="mb-6 text-base">
                Informacija svetainėje yra bendro pobūdžio. Ji nėra medicininė konsultacija ir nepakeičia gydytojo
                ar vaistininko patarimo. Prieš vartodami vaistą atidžiai perskaitykite pakuotės lapelį ir vartokite
                jį taip, kaip nurodyta. Jei abejojate, ar vaistas ar maisto papildas jums tinka, pasitarkite su
                gydytoju ar vaistininku.
            </p>

            <h2 class="mb-4 text-xl font-semibold">7. Nuorodos į kitas svetaines</h2>
            <p class="mb-6 text-base">
                Svetainėje yra nuorodų į vaistinių ir kitų įmonių svetaines. Už jų turinį, veikimą ir asmens
                duomenų tvarkymą atsako tų svetainių valdytojai.
            </p>

            <h2 class="mb-4 text-xl font-semibold">8. Prekių ženklai ir nuotraukos</h2>
            <p class="mb-6 text-base">
                Vaistinių pavadinimai, logotipai, prekių ženklai ir prekių nuotraukos priklauso jų savininkams.
                Juos rodome tik tam, kad būtų galima atpažinti vaistinę ar prekę. Jei esate teisių savininkas ir
                norite, kad jūsų turinio nerodytume ar jį pataisytume, parašykite mums, ir tai padarysime.
            </p>

            <h2 class="mb-4 text-xl font-semibold">9. Atsakomybės ribojimas</h2>
            <p class="mb-6 text-base">
                Svetainę teikiame tokią, kokia ji yra. Kiek leidžia teisės aktai, neatsakome už nuostolius, kurie
                atsirado dėl netikslios ar pasenusios informacijos, vaistinės veiksmų, pirkimo vaistinėje ar
                svetainės veikimo sutrikimų. Šis ribojimas netaikomas, kai atsakomybės riboti negalima pagal
                teisės aktus.
            </p>

            <h2 class="mb-4 text-xl font-semibold">10. Pastebėjote klaidą?</h2>
            <p class="mb-6 text-base">
                Jei kaina, prekė ar kita informacija neteisinga, parašykite
                <a href="mailto:info@evaistine.lt" class="text-dark-green underline">info@evaistine.lt</a>.
                Klaidas taisome kuo greičiau.
            </p>

            <h2 class="mb-4 text-xl font-semibold">11. Taisyklių keitimas</h2>
            <p class="mb-6 text-base">
                Taisykles galime keisti. Nauja redakcija galioja nuo jos paskelbimo šiame puslapyje, data
                nurodyta puslapio viršuje. Kaip tvarkome asmens duomenis, aprašyta
                <a href="/privatumo-politika" class="text-dark-green underline">privatumo politikoje</a>.
            </p>
        </div>
    </div>
</x-layouts.app>
