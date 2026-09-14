<x-layouts.app :title="$title" :description="$description" :canonical="$canonical">
    <x-page-breadcrumbs :items="$breadcrumbs" current="/privatumo-politika" />

    <div class="base-container">
        <div class="flex flex-col gap-4 pb-[56px]">
            <h1>Privatumo politika</h1>
            <p class="mb-6 text-sm text-gray-600">Paskutinį kartą atnaujinta: {{ now()->format('Y-m-d') }}</p>

            <h2 class="mb-4 text-xl font-semibold">1. Bendrosios nuostatos</h2>
            <p class="mb-6 text-base">
                SuperAkcijos.lt (toliau &ndash; &bdquo;mes&ldquo;, &bdquo;mūsų&ldquo;, &bdquo;svetainė&ldquo;) pripažįsta ir gerbia jūsų privatumą.
                Ši privatumo politika paaiškina, kaip mes renkame, naudojame, saugome ir apsaugome jūsų
                asmeninę informaciją, kai naudojatės mūsų svetaine.
            </p>

            <h2 class="mb-4 text-xl font-semibold">2. Kokią informaciją renkame</h2>
            <p class="mb-4 text-base">Mes galime rinkti šią informaciją:</p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">Techninę informaciją (IP adresas, naršyklės tipas, operacinė sistema)</li>
                <li class="mb-2">Naudojimo duomenis (puslapių, kuriuos lankėtės, informacija)</li>
                <li class="mb-2">Saugikų (cookies) duomenis</li>
                <li class="mb-2">Informaciją, kurią pateikiate savo noru (el. pašto adresas, kontaktinė informacija)</li>
            </ul>

            <h2 class="mb-4 text-xl font-semibold">3. Kaip naudojame jūsų informaciją</h2>
            <p class="mb-4 text-base">Jūsų asmeninę informaciją naudojame:</p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">Svetainės funkcionalumui užtikrinti</li>
                <li class="mb-2">Naudotojų patirties gerinimui</li>
                <li class="mb-2">Statistikos rinkimui ir analizei</li>
                <li class="mb-2">Komunikacijai su jumis (jei pateikėte kontaktinę informaciją)</li>
            </ul>

            <h2 class="mb-4 text-xl font-semibold">4. Jūsų teisės (GDPR)</h2>
            <p class="mb-4 text-base">Pagal GDPR turite šias teises:</p>
            <ul class="mb-6 list-disc pl-6">
                <li class="mb-2">Teisę žinoti, kokią informaciją apie jus renkame</li>
                <li class="mb-2">Teisę prieiti prie savo asmeninių duomenų</li>
                <li class="mb-2">Teisę ištaisyti netikslius duomenis</li>
                <li class="mb-2">Teisę ištrinti savo duomenis (&bdquo;teisė būti pamirštam&ldquo;)</li>
                <li class="mb-2">Teisę apriboti duomenų tvarkymą</li>
                <li class="mb-2">Teisę duomenų perkeliamumui</li>
                <li class="mb-2">Teisę nesutikti su duomenų tvarkymu</li>
            </ul>

            <h2 class="mb-4 text-xl font-semibold">5. Saugikų (Cookies) naudojimas</h2>
            <p class="mb-6 text-base">
                Mes naudojame saugikų technologijas, kad pagerintume jūsų naršymo patirtį.
                Galite valdyti saugikų nustatymus savo naršyklėje. Kai kurie saugikų yra būtini
                svetainės funkcionalumui, kiti naudojami analitikai ir reklamai.
            </p>

            <h2 class="mb-4 text-xl font-semibold">6. Duomenų saugumas</h2>
            <p class="mb-6 text-base">
                Mes imamės atitinkamų techninių ir organizacinių priemonių, kad apsaugotume jūsų
                asmeninę informaciją nuo neteisėto prieigos, keitimo, atskleidimo ar sunaikinimo.
            </p>

            <h2 class="mb-4 text-xl font-semibold">7. Kontaktai</h2>
            <p class="mb-4 text-base">
                Jei turite klausimų dėl šios privatumo politikos ar norite naudoti savo teises,
                kreipkitės į mus:
            </p>
            <p class="mb-6 text-base">El. paštas: info@superakcijos.lt<br></p>
        </div>
    </div>
</x-layouts.app>
