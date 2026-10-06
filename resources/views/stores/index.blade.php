@php
    // Stores whose physical locations scrapers/hours/scrape.js collects.
    $storesWithLocations = array_values(config('hours_scrapers'));

    // Every store, not just those with offers — leaflet-only stores (Jysk,
    // Senukai...) are real stores too, their cards link to /leidinys/{slug}.
    // Stores with an offers page (show_discounts_page) first, then
    // the rest — each group simply by id, no offer/leaflet-count ranking.
    // Inactive stores (no offer and no leaflet — the dimmed cards, same rule
    // as <x-store-card>) always go last.
    $sortedStores = collect($stores)
        ->sortBy(fn ($s) => [
            ($s['discounts_count'] ?? 0) > 0 || ($s['leaflets_count'] ?? 0) > 0 ? 0 : 1,
            ($s['shows_discounts_page'] ?? true) ? 0 : 1,
            $s['id'],
        ])
        ->values();

    $withLocations = $sortedStores->filter(fn ($s) => in_array($s['slug'], $storesWithLocations, true));
@endphp

<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []"
    :title="$pageMeta['seo']['meta_title'] ?? 'Vaistinių akcijos ir leidiniai Lietuvoje'"
    :description="$pageMeta['seo']['meta_description'] ?? null"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($faqSchema)
            <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-4 pb-8 sm:pb-10">
        <header>
            <h1 class="min-w-0">{{ $pageMeta['seo']['h1'] ?? 'Vaistinių akcijos Lietuvoje' }}</h1>
            @if (!empty($pageMeta['seo']['intro']))
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-600 sm:text-base">{{ $pageMeta['seo']['intro'] }}</p>
            @endif
        </header>

        <section aria-labelledby="stores-list-heading">
            <div class="section-heading-row">
                <h2 id="stores-list-heading" class="section-heading">Vaistinių tinklai</h2>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($sortedStores as $store)
                    <x-store-card :store="$store" layout="grid" />
                @endforeach
            </div>
        </section>

        @if ($withLocations->isNotEmpty())
            <section aria-labelledby="store-locations-heading" class="section-card">
                <div class="section-heading-row">
                    <h2 id="store-locations-heading" class="section-heading">Vaistinių adresai ir darbo laikas</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($withLocations as $store)
                        <a href="/vaistines/{{ $store['slug'] }}" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:border-green hover:text-dark-green min-h-12">
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
