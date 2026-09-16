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
            // No fallback for a missing store logo file (renders as a plain
            // broken image) — only pass a slug through when the file
            // actually exists, so a store without one just shows plain text.
            'logo_slug' => file_exists(public_path("assets/stores/{$slug}.svg")) ? $slug : null,
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

    <div class="base-container mx-auto flex flex-col gap-5 pb-8 sm:gap-6 sm:pb-10">
        <div class="flex flex-col gap-2">
            <h1>Populiariausi akcijų leidiniai</h1>
            <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base">
                Visi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje.
                Peržiūrėkite naujausius pasiūlymus ir sutaupykite apsipirkdami.
            </p>
            <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 sm:text-sm">
                <span>{{ count($leaflets) }} savaitės katalogai</span>
                @if ($freshnessLabel)
                    <x-content-freshness :label="$freshnessLabel" />
                @endif
            </p>
        </div>

        <x-keyword-chips-row :pages="$storeChips" aria-label="Parduotuvės" />

        @if (empty($leaflets))
            <div class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-sm text-gray-600">Šiuo metu leidinių nėra.</p>
            </div>
        @else
            @php
                // Split rather than rely on sort order alone — an SEO audit
                // flagged expired leaflets sitting in the same grid, same
                // size, as current ones across the whole site, not just a
                // single store's hub (see leaflets/hub.blade.php for the
                // same fix there).
                $activeLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) !== 'expired'));
                $expiredLeaflets = array_values(array_filter($leaflets, fn ($l) => ($l['status'] ?? null) === 'expired'));
            @endphp

            @foreach ([['heading' => null, 'items' => $activeLeaflets], ['heading' => 'Pasibaigę leidiniai', 'items' => $expiredLeaflets]] as $group)
                @continue(empty($group['items']))
                <div class="{{ $loop->index > 0 ? 'mt-6' : '' }}">
                    @if ($group['heading'])
                        <h2 class="section-heading mb-3">{{ $group['heading'] }}</h2>
                    @endif
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach ($group['items'] as $leaflet)
                            <x-leaflet-card :leaflet="$leaflet" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.app>
