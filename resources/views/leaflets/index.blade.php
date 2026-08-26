@php
    // Reuses <x-keyword-chips-row>'s title/href/matching_offers_count shape
    // (built for category pages' related-search chips) for a per-store
    // active-leaflet-count chip row here instead — same scrollable pill
    // pattern, different content.
    $storeChips = collect($leaflets)
        ->groupBy('store_slug')
        ->map(fn ($group, $slug) => [
            'title' => $group->first()['store_name'],
            'href' => "/leidinys/{$slug}",
            'matching_offers_count' => $group->where('status', '!=', 'expired')->count(),
        ])
        ->filter(fn ($chip) => $chip['matching_offers_count'] > 0)
        ->sortByDesc('matching_offers_count')
        ->values();
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? 'Akcijų leidiniai – visų parduotuvių savaitės katalogai')"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-5 pb-8 pt-3 sm:gap-6 sm:pb-10 sm:pt-5">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">Populiariausi akcijų leidiniai</h1>
            <p class="max-w-3xl text-sm leading-relaxed text-gray-600 sm:text-base">
                Visi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje –
                {{ count($leaflets) }} savaitės katalogai. Peržiūrėkite naujausius pasiūlymus ir sutaupykite apsipirkdami.
            </p>
        </div>

        <x-keyword-chips-row :pages="$storeChips" aria-label="Parduotuvės" />

        @if (empty($leaflets))
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-sm text-gray-600">Šiuo metu leidinių nėra.</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($leaflets as $leaflet)
                    <x-leaflet-card :leaflet="$leaflet" />
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
