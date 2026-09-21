@php
    // Reuses <x-keyword-chips-row>'s title/href/matching_offers_count shape
    // (built for category pages' related-search chips) for a per-store
    // active-leaflet-count chip row here instead — same scrollable pill
    // pattern, different content.
    $storeChips = collect($leaflets)
        ->groupBy('store_slug')
        ->map(fn ($group, $slug) => [
            'slug' => $slug,
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

    // $leaflets is already active-only (buildAllLeaflets()'s own ->active()
    // scope) — real cover images (confirmed live, e.g.
    // /storage/flyers/pages/103/page-1.webp) were previously in no
    // structured data anywhere on this page.
    $itemListSchema = ! empty($leaflets) ? \App\Support\ItemListSchema::build(
        'Visi akcijų leidiniai',
        collect($leaflets)->map(fn ($l) => [
            'name' => $l['title'],
            'href' => $l['view_url'] ?? "/leidinys/{$l['store_slug']}",
            'image' => $l['image_url'] ?? null,
        ])->all()
    ) : null;
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? 'Akcijų leidiniai – visų parduotuvių savaitės katalogai')"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($itemListSchema)
            <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush

    <x-breadcrumb-trail :items="$breadcrumbs" :current="$canonical" />

    <div class="base-container mx-auto flex flex-col gap-5 pb-8 sm:gap-6 sm:pb-10">
        <div class="flex flex-col gap-2">
            <h1>
                @if (! empty($seo['leaflet_store_count_label'] ?? null))
                    Visi akcijų leidiniai – {{ $seo['leaflet_store_count_label'] }}+ parduotuvių
                @else
                    Populiariausi akcijų leidiniai
                @endif
            </h1>
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

        {{-- Same sticky pill-bar language as discount-filters.blade.php's
             filter bar / leaflet-quick-links.blade.php — was a plain
             unstickied chip row before, now docks under the header on
             scroll like every other listing-page filter bar. Flat link
             pills (no own border/background) instead of
             <x-keyword-chips-row>'s bordered white chips — those read as a
             second, nested pill design once placed inside this bar. --}}
        <div data-sticky-filter-bar class="sticky top-[calc(3.5rem+env(safe-area-inset-top,0px))] z-[60] rounded-2xl border border-gray-300 bg-[#e8e8e8] px-4 py-3 min-h-[60px] sm:min-h-[52px] sm:px-[20px]">
            <nav aria-label="Parduotuvės" class="scroll-cards-x flex flex-nowrap items-center gap-1">
                @foreach ($storeChips as $chip)
                    <a href="{{ $chip['href'] }}" data-ga-event="filter_select" data-ga-item="store:{{ $chip['slug'] }}" data-ga-source="leidiniai_chip_bar" class="inline-flex h-full shrink-0 items-center gap-2 whitespace-nowrap rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
                        @if (!empty($chip['logo_slug']))
                            <x-store-logo :slug="$chip['logo_slug']" :name="$chip['title']" size="xs" />
                        @endif
                        <span>{{ $chip['title'] }}{{ !empty($chip['matching_offers_count']) ? ' (' . $chip['matching_offers_count'] . ')' : '' }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

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
