<x-layouts.app :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" current="/kainu-indeksas" />

    @php
        $unitLabel = fn (?string $basis) => match ($basis) {
            'kg' => '€/kg',
            'l' => '€/l',
            '10vnt' => '€/10 vnt.',
            default => '€',
        };

        // Comparing totals only makes sense between stores that had EVERY
        // chosen item — a 1-item partial total will always look "cheapest"
        // next to a real 5-item total otherwise.
        $cheapestStoreTotal = collect($data['stores'] ?? [])
            ->filter(fn ($s) => $s['full_coverage'])
            ->pluck('total')
            ->filter()
            ->min();
    @endphp

    <div class="base-container mx-auto flex flex-col gap-4 py-2 pb-10 sm:gap-5">

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between sm:gap-6">
            <div class="min-w-0">
                <h1 class="font-semibold text-gray-900">Savaitės krepšelio indeksas</h1>
                <p class="mt-1 max-w-xl text-sm leading-snug text-gray-600 sm:mt-1.5 sm:text-base">Kelių kasdienių prekių kainos didžiausiuose prekybos tinkluose — parenkame savaitei tas prekes, kurios šiuo metu turi geriausią duomenų aprėptį, kad palyginimas visada būtų realus ir pilnas.</p>
            </div>
            @if ($data['snapshot'])
                <div class="inline-flex w-fit items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700">
                    <span class="inline-block size-1.5 rounded-full bg-green"></span>
                    Savaitė nuo {{ \Illuminate\Support\Carbon::parse($data['snapshot']['week_start'])->translatedFormat('Y-m-d') }}
                </div>
            @endif
        </div>

        @if (empty($data['items']))
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                Šią savaitę dar neturime pakankamai duomenų indeksui apskaičiuoti — sugrįžkite netrukus.
            </div>
        @else
            {{-- Sitewide stats — a slim line, not a boxed hero. --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm text-gray-600">
                <span><strong class="font-bold text-gray-900 tabular-nums">{{ number_format($data['stats']['active_discounts'], 0, ',', ' ') }}</strong> aktyvios akcijos, vid. {{ number_format($data['stats']['avg_discount_percent'], 1, ',', '') }}%</span>
                @if ($data['stats']['record'])
                    <span class="flex items-center gap-1.5">
                        <span class="inline-flex items-center rounded-md bg-[#ffdb4d] px-1.5 py-0.5 text-sm font-extrabold leading-tight text-gray-900">−{{ round($data['stats']['record']['discount_percent']) }}%</span>
                        savaitės rekordas, {{ $data['stats']['record']['store'] }}
                    </span>
                @endif
            </div>

            {{-- Every tracked store's real basket total, in one place. --}}
            <div class="rounded-lg border border-gray-200 bg-white p-4 sm:p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="section-heading">Krepšeliai pagal parduotuvę</h2>
                    <span class="text-sm text-gray-500">{{ count($data['items']) }} {{ count($data['items']) === 1 ? 'prekė' : 'prekės' }} šią savaitę</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[480px] border-collapse text-base">
                        <thead>
                            <tr class="border-b-2 border-gray-200 text-left text-sm text-gray-500">
                                <th class="py-2 pr-3 font-semibold">Parduotuvė</th>
                                <th class="py-2 pr-3 text-right font-semibold">Prekių</th>
                                <th class="py-2 pr-3 text-right font-semibold">Krepšelio kaina</th>
                                <th class="py-2 pl-3 text-right font-semibold">Skirtumas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['stores'] as $i => $store)
                                <tr class="border-b border-gray-100 last:border-b-0 {{ $i % 2 === 1 ? 'bg-gray-50' : '' }} hover:bg-gray-100">
                                    <td class="py-2.5 pr-3">
                                        <a href="/akcijos/{{ $store['store_slug'] }}" class="flex items-center gap-3 font-semibold text-gray-800 hover:text-green">
                                            <x-store-logo :slug="$store['store_slug']" :name="$store['store']" size="sm" />
                                            {{ $store['store'] }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums text-gray-500">{{ $store['matched_items'] }}/{{ $store['total_items'] }}</td>
                                    <td class="py-2.5 pr-3 text-right">
                                        @if ($store['total'] !== null)
                                            <span @class([
                                                'font-bold tabular-nums',
                                                'text-green' => $store['full_coverage'],
                                                'text-gray-900' => !$store['full_coverage'],
                                            ])>{{ number_format($store['total'], 2, ',', ' ') }}&nbsp;€</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pl-3 text-right tabular-nums">
                                        @if ($store['matched_items'] === 0)
                                            <span class="text-gray-400">nėra duomenų</span>
                                        @elseif (!$store['full_coverage'])
                                            <span class="text-gray-400">nepilnas krepšelis</span>
                                        @elseif ($store['total'] !== null && $cheapestStoreTotal !== null)
                                            @if ($store['total'] <= $cheapestStoreTotal)
                                                <span class="font-semibold text-green">pigiausiai</span>
                                            @else
                                                <span class="font-semibold text-red-600">+{{ number_format(($store['total'] / $cheapestStoreTotal - 1) * 100, 0) }}%</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">nėra duomenų</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Per-item breakdown: normalized €/kg-€/l-€/10vnt price, fair for comparing one item across stores. --}}
            <div class="rounded-lg border border-gray-200 bg-white p-4 sm:p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="section-heading">Krepšelio prekės</h2>
                    <span class="text-sm text-gray-500">žemiausia kaina šią savaitę</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-base" style="table-layout: fixed;">
                        <thead>
                            <tr class="border-b-2 border-gray-200 text-left text-sm text-gray-500">
                                <th class="w-[55%] py-2 pr-6 font-semibold">Prekė</th>
                                @foreach ($data['tracked_stores'] as $trackedStore)
                                    <th class="py-2 pl-2 pr-3 text-right font-semibold">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <x-store-logo :slug="$trackedStore['slug']" :name="$trackedStore['name']" size="xs" />
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['items'] as $i => $item)
                                <tr class="border-b border-gray-100 last:border-b-0 {{ $i % 2 === 1 ? 'bg-gray-50' : '' }}">
                                    <td class="py-2.5 pr-6">
                                        <p class="font-semibold text-gray-800">{{ $item['name'] }} <span class="font-normal text-gray-400">({{ $unitLabel($item['unit_basis']) }})</span></p>
                                        @if ($item['cheapest_product_url'])
                                            <a href="{{ $item['cheapest_product_url'] }}" class="block truncate text-sm text-gray-500 hover:text-green hover:underline">{{ $item['cheapest_product_name'] }} →</a>
                                        @endif
                                    </td>
                                    @foreach ($data['tracked_stores'] as $trackedStore)
                                        @php $entry = $item['prices_by_store'][$trackedStore['slug']] ?? null; @endphp
                                        <td class="py-2.5 pl-2 pr-3 text-right tabular-nums">
                                            @if ($entry !== null)
                                                <span @class([
                                                    'font-bold',
                                                    'text-green' => $entry['price'] === $item['cheapest_price'],
                                                    'text-gray-700' => $entry['price'] !== $item['cheapest_price'],
                                                ])>{{ number_format($entry['price'], 2, ',', ' ') }}</span>
                                                {{-- Real payable price for the matched pack, not just the
                                                     abstract per-kg/l number above — lets a reader verify it
                                                     against the actual product instead of trusting a bare ratio. --}}
                                                <span class="block text-xs font-normal text-gray-400" title="Reali, sumokama kaina už šią konkrečią pakuotę">{{ number_format($entry['raw_price'], 2, ',', ' ') }} € pak.</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="category-description mt-10 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <h2>Kodėl verta sekti maisto prekių kainas kiekvieną savaitę</h2>
                <p>Kainos didžiuosiuose prekybos tinkluose — <a href="/akcijos/maxima">Maxima</a>, <a href="/akcijos/lidl">Lidl</a>, <a href="/akcijos/rimi">Rimi</a>, <a href="/akcijos/norfa">Norfa</a> ir <a href="/akcijos/iki">Iki</a> — tam pačiam produktui gali skirtis nemažai, o kuris tinklas tuo metu pigiausias priklauso nuo konkrečios prekių kategorijos ir savaitės akcijų. Todėl vienkartinis apsipirkimas vienoje parduotuvėje retai būna pats pigiausias variantas — verta palyginti prieš renkantis, kur eiti su pirkinių sąrašu.</p>

                <h2>Kuo šis indeksas skiriasi nuo oficialaus vartotojų kainų indekso</h2>
                <p>Lietuvos statistikos departamentas kas mėnesį skaičiuoja oficialų vartotojų kainų indeksą (VKI) pagal fiksuotą, kartą per metus atnaujinamą prekių ir paslaugų „krepšelį", kuriame maisto produktai ir nealkoholiniai gėrimai sudaro apie penktadalį viso krepšelio. Tai naudinga bendrai infliacijos tendencijai matyti, bet nerodo, kurioje konkrečioje parduotuvėje pigiausia pirkti šią savaitę. Mūsų krepšelio indeksas veikia kitaip — jis atnaujinamas kas savaitę ir lygina realias, tuo metu galiojančias kainas tarp konkrečių tinklų, o ne bendrą šalies vidurkį.</p>

                <h2>Kaip kinta maisto kainos Lietuvoje</h2>
                <p>Maisto kainos nekyla tolygiai visose kategorijose — šviežių vaisių ir daržovių kainos paprastai svyruoja stipriausiai, priklausomai nuo derliaus sezono, o perdirbti ir ilgai laikomi produktai (pvz. makaronai, ryžiai, aliejus) keičiasi lėčiau. Todėl tas pats krepšelis vienu mėnesiu gali atrodyti brangesnis, o kitu — pigesnis, net jei bendra infliacija nepasikeitė. Sekti kelių konkrečių prekių kainas kiekvieną savaitę leidžia pastebėti šiuos svyravimus anksčiau, nei jie atsispindi oficialioje statistikoje.</p>

                <h2>Kaip sutaupyti apsiperkant kiekvieną savaitę</h2>
                <p>Be bendro krepšelio palyginimo, verta pasitikrinti konkrečių kategorijų akcijas — pavyzdžiui <a href="/akcijos/vaisiai-ir-darzoves">vaisius ir daržoves</a>, <a href="/akcijos/pieno-produktai-ir-kiausiniai">pieno produktus</a> ar <a href="/akcijos/mesa-ir-zuvis">mėsą ir žuvį</a> — nes būtent šiose kategorijose kainų skirtumai tarp tinklų dažniausiai būna didžiausi. Taip pat naudinga sekti savaitės <a href="/leidiniai">akcijų leidinius</a>, kad pastebėtumėte naujus pasiūlymus, kol jie dar galioja.</p>
            </div>

            <div class="category-description mt-6 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <p>
                    <strong>Kaip skaičiuojame:</strong> kiekvieną savaitę parenkame kelias kasdienes prekes, kurios šiuo metu turi geriausią kainų duomenų aprėptį {{ implode(', ', collect(App\Models\Store::whereIn('slug', App\Services\PriceIndexService::TRACKED_STORE_SLUGS)->pluck('name'))->all()) }} tinkluose. Kiekvieno tinklo krepšelio kaina viršuje yra reali, sumokama kaina už tas konkrečias prekes, kurias tas tinklas šiuo metu turi — jei tinklas neturi visų krepšelio prekių, tai pažymėta atskirai, o ne nutylima. Prekių sąraše žemiau kiekvienos prekės kaina perskaičiuota už standartinį kiekį (kg, l arba 10 vnt.), kad palyginimas tarp tinklų būtų sąžiningas nepriklausomai nuo pakuotės dydžio — šios perskaičiuotos kainos nėra sumuojamos į krepšelio bendrą sumą. Kainos remiasi realiais SuperAkcijos.lt sistemoje esančiais duomenimis ir gali kartais atspindėti pavienes šaltinio klaidas.
                </p>
            </div>
        @endif

    </div>
</x-layouts.app>
