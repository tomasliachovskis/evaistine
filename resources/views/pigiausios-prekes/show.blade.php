<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" current="/pigiausios-prekes" />

    @php
        // "Pigiausia" is decided by this normalized €/kg-€/l-€/10vnt price,
        // not the raw payable price — a 0.568 l bottle at 1,09 € really is
        // cheaper per litre than a 0.5 l one at 0,99 €, but that's invisible
        // if only the raw price is shown, so every card also displays this.
        $unitLabel = fn (?string $basis) => match ($basis) {
            'kg' => '€/kg',
            'l' => '€/l',
            '10vnt' => '€/10 vnt.',
            default => null,
        };

        // Shopping-list order, not alphabetical — matches how someone
        // actually walks a store. Any category not listed (shouldn't happen
        // with the current basket, but new candidate items could add one)
        // still shows, just after these.
        $categoryOrder = [
            'Pieno produktai ir kiaušiniai',
            'Duonos gaminiai',
            'Mėsa ir žuvis',
            'Vaisiai ir daržovės',
            'Bakalėja',
            'Gėrimai, kava, arbata',
            'Saldumynai ir užkandžiai',
            'Alkoholiniai gėrimai',
        ];

        $groups = collect($data['items'] ?? [])
            ->groupBy('category')
            ->sortBy(fn ($items, $category) => array_search($category, $categoryOrder) !== false
                ? array_search($category, $categoryOrder)
                : count($categoryOrder));
    @endphp

    {{-- This page's content is sized in `em`, not the usual fixed Tailwind
         text-sm/base/lg scale, so a single base font-size here scales
         everything under it together. Aimed at an older audience checking
         prices daily: bigger type than the site default, every price
         visible without clicking anything. --}}
    <div class="base-container mx-auto flex flex-col gap-4 py-2 pb-10 text-lg sm:gap-5">

        <x-type-hero
            title="Pigiausios prekės parduotuvėse"
            subtitle="Kelių kasdienių prekių kainos didžiausiuose prekybos tinkluose — kiekvienos prekės kaina matoma iš karto, jokių papildomų paspaudimų."
        >
            <x-content-freshness :label="$freshnessLabel" />
        </x-type-hero>

        @if (empty($data['items']))
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-[0.9em] text-gray-600">
                Šią savaitę dar neturime pakankamai duomenų šiam sąrašui — sugrįžkite netrukus.
            </div>
        @else
            @foreach ($groups as $category => $categoryItems)
                <div class="flex flex-col gap-3">
                    <h2 class="border-b-2 border-gray-200 pb-2 text-[1.2em] font-semibold text-gray-900">{{ $category }}</h2>

                    <div class="flex flex-col gap-3">
                        @foreach ($categoryItems as $item)
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <div class="mb-3 flex items-baseline justify-between gap-3">
                                    <span class="text-[1.05em] font-bold text-gray-900">{{ $item['name'] }}</span>
                                    <span class="text-[0.8em] text-gray-500">{{ $item['coverage_count'] }}/{{ $item['coverage_total'] }} parduotuvėse</span>
                                </div>

                                {{-- Default view: exactly one (the cheapest) card per store — never
                                     more than 5 cards, so an item never turns into a pile regardless
                                     of how many real offers a store has. A store with more than one
                                     match gets a "+N kiti" toggle; the alternatives open as one panel
                                     spanning the whole row, right under it, with a caret pointing at
                                     whichever store's card was clicked so it's never ambiguous whose
                                     alternatives they are. --}}
                                <div
                                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                                    x-data="{
                                        open: null,
                                        caretLeft: 0,
                                        toggle(slug, el) {
                                            if (this.open === slug) { this.open = null; return; }
                                            this.open = slug;
                                            this.$nextTick(() => {
                                                const cardRect = el.closest('[data-card]').getBoundingClientRect();
                                                const gridRect = this.$el.getBoundingClientRect();
                                                this.caretLeft = (cardRect.left - gridRect.left) + cardRect.width / 2;
                                            });
                                        },
                                    }"
                                >
                                    @foreach ($data['tracked_stores'] as $trackedStore)
                                        @php
                                            $matches = $item['by_store'][$trackedStore['slug']] ?? [];
                                            $cheapestMatch = $matches[0] ?? null;
                                            $others = count($matches) > 1 ? array_slice($matches, 1) : [];
                                        @endphp
                                        @continue ($cheapestMatch === null)
                                        <div
                                            data-card
                                            class="flex flex-col gap-1"
                                            :class="{ 'ring-2 ring-green ring-offset-1 rounded-xl': open === '{{ $trackedStore['slug'] }}' }"
                                        >
                                            <x-price-compare-card
                                                :match="$cheapestMatch"
                                                :store="$trackedStore"
                                                :unit-label="$unitLabel($item['unit_basis'])"
                                                :is-cheapest="$cheapestMatch['price'] === $item['cheapest_price']"
                                            />
                                            @if (count($others) > 0)
                                                <button
                                                    type="button"
                                                    class="inline-flex w-fit items-center gap-1 text-[0.72em] font-bold text-green"
                                                    @click="toggle('{{ $trackedStore['slug'] }}', $el)"
                                                >
                                                    <span x-text="open === '{{ $trackedStore['slug'] }}' ? 'suslėpti' : '+{{ count($others) }} kiti'"></span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="size-3 transition-transform" :class="{ 'rotate-180': open === '{{ $trackedStore['slug'] }}' }"><path d="m6 9 6 6 6-6"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach

                                    @foreach ($data['tracked_stores'] as $trackedStore)
                                        @php
                                            $matches = $item['by_store'][$trackedStore['slug']] ?? [];
                                            $others = count($matches) > 1 ? array_slice($matches, 1) : [];
                                        @endphp
                                        @continue (empty($others))
                                        <div
                                            x-show="open === '{{ $trackedStore['slug'] }}'"
                                            x-cloak
                                            class="relative col-span-full rounded-xl border border-dashed border-green/40 bg-green/5 p-3"
                                        >
                                            <div
                                                class="absolute -top-[7px] size-3.5 rotate-45 border-l border-t border-dashed border-green/40 bg-green/5"
                                                :style="{ left: caretLeft + 'px', marginLeft: '-7px' }"
                                            ></div>
                                            <div class="flex gap-2.5 overflow-x-auto pb-1">
                                                @foreach ($others as $match)
                                                    <a href="{{ $match['product_url'] ?? '#' }}" class="flex w-32 shrink-0 flex-col gap-1.5 rounded-lg border border-gray-200 bg-white p-2 hover:opacity-80">
                                                        <div class="relative aspect-square w-full overflow-hidden rounded-md bg-gray-50">
                                                            @if ($match['product_image_url'])
                                                                <img src="{{ $match['product_image_url'] }}" alt="{{ $match['product_name'] }}" loading="lazy" class="h-full w-full object-contain p-0.5">
                                                            @endif
                                                        </div>
                                                        <p class="line-clamp-2 text-[0.72em] leading-tight text-gray-700">{{ $match['product_name'] }}</p>
                                                        <p class="text-[0.9em] font-bold tabular-nums text-gray-900">{{ number_format($match['raw_price'], 2, ',', ' ') }}&nbsp;€</p>
                                                        @if ($unitLabel($item['unit_basis']))
                                                            <p class="text-[0.62em] tabular-nums text-gray-400">{{ number_format($match['price'], 2, ',', ' ') }}&nbsp;{{ $unitLabel($item['unit_basis']) }}</p>
                                                        @endif
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="category-description mt-6 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <h2>Kodėl verta sekti maisto prekių kainas kiekvieną savaitę</h2>
                <p>Kainos didžiuosiuose prekybos tinkluose — <a href="/akcijos/maxima">Maxima</a>, <a href="/akcijos/lidl">Lidl</a>, <a href="/akcijos/rimi">Rimi</a>, <a href="/akcijos/norfa">Norfa</a> ir <a href="/akcijos/iki">Iki</a> — tam pačiam produktui gali skirtis nemažai, o kuris tinklas tuo metu pigiausias priklauso nuo konkrečios prekių kategorijos ir savaitės akcijų. Todėl vienkartinis apsipirkimas vienoje parduotuvėje retai būna pats pigiausias variantas — verta palyginti prieš renkantis, kur eiti su pirkinių sąrašu.</p>

                <h2>Kaip kinta maisto kainos Lietuvoje</h2>
                <p>Maisto kainos nekyla tolygiai visose kategorijose — šviežių vaisių ir daržovių kainos paprastai svyruoja stipriausiai, priklausomai nuo derliaus sezono, o perdirbti ir ilgai laikomi produktai (pvz. makaronai, ryžiai, aliejus) keičiasi lėčiau. Todėl tos pačios prekės vienu mėnesiu gali atrodyti brangesnės, o kitu — pigesnės, net jei bendra infliacija nepasikeitė. Sekti kelių konkrečių prekių kainas kiekvieną savaitę leidžia pastebėti šiuos svyravimus anksčiau, nei jie atsispindi oficialioje statistikoje.</p>

                <h2>Kaip sutaupyti apsiperkant kiekvieną savaitę</h2>
                <p>Be šio bendro prekių palyginimo, verta pasitikrinti konkrečių kategorijų akcijas — pavyzdžiui <a href="/akcijos/vaisiai-ir-darzoves">vaisius ir daržoves</a>, <a href="/akcijos/pieno-produktai-ir-kiausiniai">pieno produktus</a> ar <a href="/akcijos/mesa-ir-zuvis">mėsą ir žuvį</a> — nes būtent šiose kategorijose kainų skirtumai tarp tinklų dažniausiai būna didžiausi. Taip pat naudinga sekti savaitės <a href="/leidiniai">akcijų leidinius</a>, kad pastebėtumėte naujus pasiūlymus, kol jie dar galioja.</p>
            </div>

            <div class="category-description mt-6 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <p>
                    <strong>Kaip skaičiuojame:</strong> sekame kelias kasdienes prekes {{ count($data['tracked_stores']) }} Lietuvos parduotuvių tinkluose (tarp jų {{ implode(', ', collect($data['tracked_stores'])->take(5)->pluck('name')->all()) }} ir kt.) ir kiekvieną kartą atidarius puslapį rodome tuo metu galiojančias realias kainas — ne senesnę, kartą per savaitę atnaujintą nuotrauką. Kiekvienai prekei kiekvienoje parduotuvėje rodome iki penkių pigiausių šiuo metu galiojančių pasiūlymų — jei parduotuvė tos prekės neturi, tai pažymėta atskirai, o ne nutylima. Kainos remiasi realiais SuperAkcijos.lt sistemoje esančiais duomenimis ir gali kartais atspindėti pavienes šaltinio klaidas.
                </p>
            </div>
        @endif

    </div>

    <style>
        @media print {
            header, nav { display: none !important; }
        }
    </style>
</x-layouts.app>
