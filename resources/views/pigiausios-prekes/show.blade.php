<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []" :title="$title" :description="$description" :canonical="$canonical" :robots="$robots">
    @push('head')
        <script type="application/ld+json">
            {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <x-page-breadcrumbs :items="$breadcrumbs" current="/pigiausios-prekes" />

    {{-- This page's content is sized in `em`, not the usual fixed Tailwind
         text-sm/base/lg scale, so a single base font-size here scales
         everything under it together. Aimed at an older audience checking
         prices daily: bigger type than the site default, every price
         visible without clicking anything. --}}
    <div class="base-container mx-auto flex flex-col gap-4 py-2 pb-10 text-lg sm:gap-12">

        <div class="flex flex-col gap-2">
            <x-type-hero
                title="Pigiausios prekės vaistinėse"
                subtitle="Populiarių vaistų ir kitų vaistinės prekių kainos skirtingose vaistinėse — kiekvienos prekės kaina matoma iš karto, jokių papildomų paspaudimų."
            />
            <x-hero-stats :freshness="$freshnessLabel" />
        </div>

        @if (empty($groups))
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-[0.9em] text-gray-600">
                Šią savaitę dar neturime pakankamai duomenų šiam sąrašui — sugrįžkite netrukus.
            </div>
        @else
            @foreach ($groups as $group)
                <div class="flex flex-col gap-3">
                    <h2 class="section-heading-lg">{{ $group['name'] }}</h2>

                    <div class="flex flex-col gap-8 sm:gap-10">
                        {{-- Each item is one keyword page's own cheapest-per-store
                             comparison (KeywordPageService::buildHomeTeaser()) —
                             same data/shape the homepage teaser uses, just every
                             candidate shown here instead of a random 2. --}}
                        @foreach ($group['items'] as $item)
                            <div>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <span class="text-[1.05em] font-bold text-gray-900">
                                        {{ $item['label'] }}
                                    </span>
                                    <a href="{{ $item['href'] }}" class="section-link text-right">
                                        Žiūrėti visas{{ !empty($item['matching_offers_count']) ? ' ('.number_format($item['matching_offers_count'], 0, ',', ' ').')' : '' }}
                                        <x-app-icon name="chevron-right" class="size-3.5 opacity-80" />
                                    </a>
                                </div>

                                @php
                                    // leading_deals is ordered by store priority first (see
                                    // KeywordPageService::buildIndexBackedTeaserDeals()), not
                                    // by price — index 0 is whichever main chain has this
                                    // product, not necessarily the cheapest one. "Gera kaina"
                                    // must mark the actual lowest discounted_price instead.
                                    $cheapestIndex = collect($item['leading_deals'])
                                        ->pluck('discounted_price')
                                        ->map(fn ($p) => (float) $p)
                                        ->sortBy(fn ($p) => $p > 0 ? $p : INF)
                                        ->keys()
                                        ->first();
                                @endphp
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                    @foreach ($item['leading_deals'] as $index => $deal)
                                        <div class="relative">
                                            @if ($index === $cheapestIndex)
                                                <span class="absolute left-2 top-2 z-20 inline-flex items-center rounded-md bg-deal px-1.5 py-0.5 text-xs font-extrabold uppercase tracking-wide text-deal-foreground">Gera kaina</span>
                                            @endif
                                            <x-deal-card :deal="$deal" source="pigiausios-prekes" :featured="$index === $cheapestIndex" />
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="category-description mt-6 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-dark-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <h2>Kodėl verta palyginti vaistų kainas skirtingose vaistinėse</h2>
                <p>Ta pati prekė skirtingose vaistinėse — {!! collect(\App\Support\StoreListPriority::mainNames())->map(fn ($name, $slug) => '<a href="'.e(\App\Support\PageUrl::listing($slug)).'">'.e($name).'</a>')->join(', ', ' ir ') !!} — gali kainuoti nevienodai, o kuri vaistinė tuo metu pigiausia, priklauso nuo jos akcijų. Todėl verta palyginti kainas prieš perkant, ypač prekes, kurias perkate reguliariai.</p>

                <h2>Kaip keičiasi kainos vaistinėse</h2>
                <p>Vaistinės akcijas skelbia skirtingu ritmu: vienos keičia pasiūlymus kas kelias savaites, kitos leidžia mėnesinius leidinius, o internetinės vaistinės kainas gali keisti dažniau. Dėl to pigiausia vaistinė tai pačiai prekei skirtingais mėnesiais gali būti vis kita.</p>

                <h2>Kaip rasti pigiausią pasiūlymą</h2>
                <p>Be šio prekių palyginimo, verta peržiūrėti konkrečių kategorijų akcijas — pavyzdžiui <a href="{{ \App\Support\PageUrl::listing('vitaminai-ir-maisto-papildai') }}">vitaminus ir maisto papildus</a>, <a href="{{ \App\Support\PageUrl::listing('nereceptiniai-vaistai') }}">nereceptinius vaistus</a> ar <a href="{{ \App\Support\PageUrl::listing('veido-prieziura') }}">veido priežiūros priemones</a>. Naujus pasiūlymus rasite ir vaistinių <a href="/leidiniai">akcijų leidiniuose</a>.</p>
            </div>

            <div class="category-description mt-6 w-full max-w-none border-t border-gray-200 pt-6 text-sm prose prose-sm py-[5px] [&>p]:mb-4 [&>p:last-child]:mb-0 [&_a]:text-dark-green [&_a]:transition-colors [&_a]:hover:text-dark-green [&_a]:hover:underline">
                <p>
                    <strong>Kaip skaičiuojame:</strong> kiekvienai prekei rodome jos pačios akcijų puslapio realiu laiku skaičiuojamą kainų palyginimą — po vieną pigiausią šiuo metu galiojantį pasiūlymą iš kiekvienos vaistinės, kuri tą prekę turi (iki penkių vaistinių). Kainos remiasi realiais eVaistine.lt sistemoje esančiais duomenimis ir gali kartais atspindėti pavienes šaltinio klaidas.
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
