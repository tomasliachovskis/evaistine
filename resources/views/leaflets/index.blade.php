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
        ->values();

    // Same editorial store order as the home hero chips / /vaistines
    // directory (App\Support\StoreListPriority) — named chains first in a
    // fixed order, then everyone else by active-leaflet count — instead of
    // raw count desc, which put e.g. Officeday ahead of Maxima/Rimi/Lidl.
    $storeChips = collect(\App\Support\StoreListPriority::sort(
        $storeChips->map(fn ($chip) => [...$chip, 'discounts_count' => $chip['matching_offers_count']])->all()
    ));

    // $leaflets is NOT active-only despite buildAllLeaflets()'s own
    // ->active() scope name — that scope filters on the DB is_active flag,
    // which isn't kept in sync with real valid_to dates (same caveat as
    // StoreFlyerTitleBuilder::toListingArray), so it still includes
    // already-expired leaflets. Real "still browsable today" status is
    // computed separately below per item, so count that instead of
    // count($leaflets) for the header stat.
    $activeLeafletsCount = collect($leaflets)->where('status', '!=', 'expired')->count();

    // ItemList schema still lists every leaflet including expired ones
    // (unfiltered $leaflets) — real cover images (confirmed live, e.g.
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
    :breadcrumbs="$breadcrumbs ?? []"
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? 'Akcijų leidiniai – visų vaistinių savaitės katalogai')"
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
            <h1>Naujausi akcijų leidiniai iš visų vaistinių</h1>
            <x-collapsible-intro class="mt-1.5">
                Visi Maxima, Lidl, Iki, Rimi, Norfa ir kitų vaistinių akcijų leidiniai vienoje vietoje.
                Peržiūrėkite naujausius pasiūlymus ir sutaupykite apsipirkdami.
            </x-collapsible-intro>
            @php
                $leafletCount = (int) $activeLeafletsCount;
                $leafletWord = \App\Support\LithuanianPlural::leafletWord($leafletCount);
                // Adjective agrees with the noun form: 1 galiojantis leidinys,
                // 2-9 galiojantys leidiniai, else galiojančių leidinių.
                $leafletAdjective = match ($leafletWord) {
                    'leidinys' => 'galiojantis',
                    'leidiniai' => 'galiojantys',
                    default => 'galiojančių',
                };
            @endphp
            <x-hero-stats
                class="mt-1"
                :stats="$leafletCount > 0 ? [['icon' => 'newspaper', 'pill' => $leafletCount . ' ' . $leafletAdjective . ' ' . $leafletWord]] : []"
                :freshness="$freshnessLabel"
            />
        </div>

        @php
            // Sorting: links over ?order=, shown like the listings' sort.
            $leafletOrder = request('order', 'best');
            $leafletOrderOptions = [
                'best' => 'Populiariausi',
                'newest' => 'Naujausi',
                'old' => 'Baigsis greitai',
            ];
            $storeChipRowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base leading-snug text-left transition-colors '
                . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
        @endphp
        {{-- The same navigation bar as the offer listings (<x-nav-bar>, not
             pinned): "Vaistinė" opens the store tiles, "Rikiuoti" the
             order. Replaced the pinned gray bar with top-5 store chips. --}}
        <div x-data="{ storeOpen: false, sortOpen: false }" @keydown.escape.window="storeOpen = false; sortOpen = false">
            <x-nav-bar class="!mb-0" x-on:click.outside="sortOpen = false">
                <x-nav-bar-button label="Vaistinė" icon="store" x-on:click="storeOpen = true" aria-haspopup="dialog" data-ga-event="filter_select" data-ga-item="store:all" data-ga-source="leidiniai_nav_bar">
                    Visos vaistinės
                </x-nav-bar-button>
                <x-nav-bar-button
                    label="Rikiuoti"
                    icon="arrow-down-up"
                    wrapper-class="relative flex min-w-0 flex-col gap-1 sm:w-64 sm:shrink-0"
                    x-on:click="sortOpen = !sortOpen"
                    aria-haspopup="listbox"
                    x-bind:aria-expanded="sortOpen"
                >
                    {{ $leafletOrderOptions[$leafletOrder] ?? 'Populiariausi' }}
                    <x-slot:after>
                        <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                            @foreach ($leafletOrderOptions as $value => $orderLabel)
                                <a href="{{ $value === 'best' ? '/leidiniai' : '/leidiniai?order=' . $value }}" class="{{ $storeChipRowClass($leafletOrder === $value) }}">{{ $orderLabel }}</a>
                            @endforeach
                        </div>
                    </x-slot:after>
                </x-nav-bar-button>
            </x-nav-bar>
            <x-leaflet-store-sheet open="storeOpen" />
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

                // 'best' (Populiariausi) keeps buildAllLeaflets()'s own
                // order (the main chains' newest of the last 5 days, then
                // other stores' newest, then the rest by upload date) —
                // only re-sort for the other two explicit choices.
                if ($leafletOrder === 'newest') {
                    $activeLeaflets = collect($activeLeaflets)->sortByDesc('valid_from')->values()->all();
                } elseif ($leafletOrder === 'old') {
                    $activeLeaflets = collect($activeLeaflets)
                        ->sortBy(fn ($l) => $l['valid_to'] !== '' ? $l['valid_to'] : '9999-99-99')
                        ->values()
                        ->all();
                }
            @endphp

            @foreach ([['heading' => null, 'items' => $activeLeaflets], ['heading' => 'Pasibaigę leidiniai', 'items' => $expiredLeaflets]] as $group)
                @continue(empty($group['items']))
                <div class="{{ $loop->index > 0 ? 'mt-6' : '' }}">
                    @if ($group['heading'])
                        <h2 class="section-heading mb-3">{{ $group['heading'] }}</h2>
                    @endif
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($group['items'] as $leaflet)
                            <x-leaflet-card :leaflet="$leaflet" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
        <x-email-signup />
    </div>
</x-layouts.app>
