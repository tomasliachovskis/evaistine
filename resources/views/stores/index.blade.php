@php
    // Confirmed-to-have-physical-locations set, kept in sync with
    // discount/src/components/stores/store-locations-links.tsx (its own
    // comment there points at nuolaidos/config/hours_scrapers.php).
    $storesWithLocations = ['maxima', 'iki', 'lidl', 'norfa', 'rimi', 'aibe', 'silas', 'cia', 'gruste',
        'kubas', 'vynoteka', 'thomas-philipps', 'apotheka', 'benu-vaistine', 'bikuva', 'camelia',
        'elimart', 'ermitazas', 'eurokos', 'eurovaistine', 'gintarine-vaistine', 'jupoja', 'jysk',
        'moki-vezi', 'pepco', 'senukai'];

    $sortedStores = collect($stores)->sortBy([
        fn ($a, $b) => ($a['discounts_count'] === 0) <=> ($b['discounts_count'] === 0),
        fn ($a, $b) => $b['discounts_count'] <=> $a['discounts_count'],
    ])->values();

    $withLocations = $sortedStores->filter(fn ($s) => in_array($s['slug'], $storesWithLocations, true));
@endphp

<x-layouts.app
    :title="$pageMeta['seo']['meta_title'] ?? 'Parduotuvių akcijos ir leidiniai Lietuvoje'"
    :description="$pageMeta['seo']['meta_description'] ?? null"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-4 pb-8 sm:pb-10">
        <header>
            <h1 class="min-w-0 font-extrabold text-gray-900">{{ $pageMeta['seo']['h1'] ?? 'Parduotuvių akcijos Lietuvoje' }}</h1>
            @if (!empty($pageMeta['seo']['intro']))
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-600">{{ $pageMeta['seo']['intro'] }}</p>
            @endif
        </header>

        <section aria-labelledby="stores-list-heading">
            <h2 id="stores-list-heading" class="mb-2.5 text-base font-bold text-gray-900">Prekybos tinklai</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($sortedStores as $store)
                    <x-store-card :store="$store" layout="grid" />
                @endforeach
            </div>
        </section>

        @if ($withLocations->isNotEmpty())
            <section aria-labelledby="store-locations-heading" class="flex flex-col gap-2.5">
                <h2 id="store-locations-heading" class="text-base font-bold text-gray-900">Parduotuvių adresai ir darbo laikas</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($withLocations as $store)
                        <a href="/parduotuves/{{ $store['slug'] }}" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                            {{ $store['name'] }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (!empty($pageMeta['faq']))
            <section aria-labelledby="faq-heading" class="mt-2">
                <h2 id="faq-heading" class="section-heading mb-4">Dažniausiai užduodami klausimai</h2>
                <x-faq-accordion :items="$pageMeta['faq']" />
            </section>
        @endif
    </div>
</x-layouts.app>
