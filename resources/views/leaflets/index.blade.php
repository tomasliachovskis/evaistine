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

    // Same editorial store order as the home hero chips / /parduotuves
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
            <h1>Naujausi akcijų leidiniai iš visų parduotuvių</h1>
            <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base">
                Visi Maxima, Lidl, Iki, Rimi, Norfa ir kitų parduotuvių akcijų leidiniai vienoje vietoje.
                Peržiūrėkite naujausius pasiūlymus ir sutaupykite apsipirkdami.
            </p>
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

        {{-- Same sticky pill-bar language as discount-filters.blade.php's
             filter bar / leaflet-quick-links.blade.php — was a plain
             unstickied chip row before, now docks under the header on
             scroll like every other listing-page filter bar. Flat link
             pills (no own border/background) instead of
             <x-keyword-chips-row>'s bordered white chips — those read as a
             second, nested pill design once placed inside this bar.

             Same "top N visible + full list in a modal" split as
             kuponai/partials/website-chip-bar.blade.php, with a
             narrower-screens-show-fewer taper: 2 chips always visible
             (guaranteed to fit next to "Visos parduotuvės" even on a small
             phone), a 3rd from 400px up, and the remaining top-5 (desktop
             cap) from sm+ (640px) up — real store-name lengths vary too
             much to know exactly how many fit at a given width without
             measuring at runtime, so this approximates it with breakpoints
             rather than a JS overflow measurement (no precedent for that
             elsewhere in the chip-bar patterns this is based on). The modal
             always lists every store regardless of breakpoint, reusing
             discount-filter-sections.blade.php like the header's
             Kategorijos dropdown and the akcijos filter sheet. --}}
        @php
            $topStoreChips = $storeChips->take(5);
            // Sorting: the same dropdown as the product listings' sort
            // button (discount-filters.blade.php), as links over ?order=.
            $leafletOrder = request('order', 'best');
            $leafletOrderOptions = [
                'best' => 'Populiariausi',
                'newest' => 'Naujausi',
                'old' => 'Baigsis greitai',
            ];
            $storeChipRowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base leading-snug text-left transition-colors '
                . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
        @endphp
        <div
            data-sticky-filter-bar
            x-data="{ allStoresOpen: false, sortOpen: false }"
            @keydown.escape.window="allStoresOpen = false; sortOpen = false"
            class="sticky top-[calc(var(--header-h)+env(safe-area-inset-top,0px))] z-[60] flex items-center gap-1 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-2 py-1 min-h-14 sm:gap-2 sm:px-[20px]"
        >
            <nav aria-label="Parduotuvės" class="scroll-cards-x flex min-w-0 flex-1 flex-nowrap items-center gap-1 max-sm:hidden">
                @foreach ($topStoreChips as $chip)
                    @php
                        // Phones show only the "Visos parduotuvės" picker
                        // (owner's request): chips there were cut off.
                        $chipVisibilityClass = 'hidden sm:inline-flex';
                    @endphp
                    <a href="{{ $chip['href'] }}" data-ga-event="filter_select" data-ga-item="store:{{ $chip['slug'] }}" data-ga-source="leidiniai_chip_bar" class="{{ $chipVisibilityClass }} min-h-12 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3 text-base font-semibold text-gray-900 hover:bg-[#dedede]">
                        @if (!empty($chip['logo_slug']))
                            <x-store-logo :slug="$chip['logo_slug']" :name="$chip['title']" size="xs" />
                        @endif
                        <span>{{ $chip['title'] }}{{ !empty($chip['matching_offers_count']) ? ' (' . $chip['matching_offers_count'] . ')' : '' }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Pinned outside the scrollable nav, same recipe as
                 discount-filters.blade.php's Parduotuvė/Kategorija toolbar
                 buttons — always visible, and on mobile it's the only
                 element in the bar (nav above is hidden there). --}}
            <button
                type="button"
                @click="allStoresOpen = true"
                data-ga-event="filter_select"
                data-ga-item="store:all"
                data-ga-source="leidiniai_chip_bar"
                class="inline-flex min-h-12 min-w-0 shrink cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl px-2 text-base font-semibold text-gray-900 hover:bg-[#dedede] sm:shrink-0 sm:px-3"
            >
                <x-app-icon name="store" class="size-5 shrink-0" />
                <span class="min-w-0 truncate"><span class="sm:hidden">Parduotuvės</span><span class="hidden sm:inline">Visos parduotuvės</span></span>
                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-action text-sm font-bold text-white">{{ $storeChips->count() }}</span>
                <x-app-icon name="chevron-down" class="size-4 shrink-0 text-gray-500 transition-transform" x-bind:class="allStoresOpen ? 'rotate-180' : ''" />
            </button>

            <template x-teleport="body">
                <div x-show="allStoresOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @click.self="allStoresOpen = false">
                    <div class="max-h-[80vh] w-full max-w-[420px] overflow-y-auto rounded-2xl bg-white p-4">
                        <div class="mb-1.5 flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wide text-gray-400">Parduotuvė</span>
                            <button type="button" @click="allStoresOpen = false" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <x-app-icon name="x" class="size-5" />
                            </button>
                        </div>
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'stores',
                            'items' => $storeChips->map(fn ($chip) => [
                                'slug' => $chip['slug'],
                                'name' => $chip['title'],
                                'offers_count' => $chip['matching_offers_count'],
                            ])->all(),
                            'activeSlug' => null,
                            'hrefFor' => fn ($slug) => "/leidinys/{$slug}",
                            'rowClass' => $storeChipRowClass,
                            'gaSource' => 'leidiniai_all_stores_modal',
                            // Full directory, not a page-scoped filter — show
                            // every store immediately, same reasoning as
                            // site-header's Kategorijos modal.
                            'visibleLimit' => $storeChips->count(),
                        ])
                    </div>
                </div>
            </template>

            <div class="relative ml-auto shrink-0" @click.outside="sortOpen = false">
                <button type="button" @click="sortOpen = !sortOpen" class="inline-flex min-h-12 min-w-12 cursor-pointer items-center justify-center gap-2 rounded-xl text-base font-semibold text-gray-900 hover:bg-[#dedede] sm:px-3" aria-haspopup="listbox" :aria-expanded="sortOpen" aria-label="Rūšiuoti">
                    <x-app-icon name="arrow-down-up" class="size-5 shrink-0" />
                    <span class="hidden truncate sm:inline">{{ $leafletOrderOptions[$leafletOrder] ?? 'Populiariausi' }}</span>
                    <x-app-icon name="chevron-down" class="hidden size-5 shrink-0 opacity-70 sm:block" />
                </button>
                <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 min-w-[240px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                    @foreach ($leafletOrderOptions as $value => $label)
                        <a href="{{ $value === 'best' ? '/leidiniai' : '/leidiniai?order=' . $value }}" class="{{ $storeChipRowClass($leafletOrder === $value) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
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
                // editorial order (StoreListPriority-grouped, current
                // leaflet first per store) — only re-sort for the other two
                // explicit choices.
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
    </div>
</x-layouts.app>
