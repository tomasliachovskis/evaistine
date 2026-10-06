@php
    // Row styling below reuses the exact constants from
    // discount/src/components/common/product-filter-controls.tsx:
    // FILTER_ROW_HEIGHT_CLASS='min-h-[40px]' FILTER_ROW_TEXT_CLASS='text-sm leading-snug'
    // FILTER_LIST_GAP_CLASS='gap-0.5', and its active/hover row colors.
    $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-base leading-snug text-left transition-colors '
        . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
    $selectedStores = array_values(array_filter(explode(',', $storeFilter)));
    $selectedCategories = array_values(array_filter(explode(',', $categoryFilter)));
    // $activeCount is computed further down: only real picks count, not
    // the store/category the URL already fixes — see the comment there.
    $orderOptions = [
        // Keyword pages' 'popular' is really Meilisearch relevance order
        // (KeywordPageService::sortDiscounts() leaves it untouched), not
        // popularity — labelled honestly there. Same URL value either way.
        'popular' => $mode === 'keyword' ? 'Tinkamiausi' : 'Populiariausi',
        'price_min' => 'Mažiausia kaina',
        'price_max' => 'Didžiausia kaina',
        'price_discount_max' => 'Didž. nuolaida (€)',
        'price_discount_proc_max' => 'Didž. nuolaida (%)',
    ];
    $orderIcons = [
        'popular' => 'trending-up',
        'price_min' => 'arrow-down',
        'price_max' => 'arrow-up',
        'price_discount_max' => 'euro',
        'price_discount_proc_max' => 'percent',
    ];
    $orderSuffix = $order !== 'popular' ? '?order=' . $order : '';

    // Explicit href builders per facet — NOT derived from ambient
    // $primarySlug/$secondarySlug, whose meaning differs by page type (a
    // store on one page, a category on another). $activeStoreSlug/
    // $activeCategorySlug (set by AkcijosController per page type) are what
    // actually tell us which URL shape applies:
    // - Picking a category: prefixed with the store when one is fixed
    //   (store+category combo); plain /akcijos/{category} otherwise (plain
    //   hub, or a category-only page switching to a sibling category).
    // - Picking a store: suffixed with the category when one is fixed
    //   (category-only or store+category page); on a keyword page there is
    //   no /akcijos/{store}/{keyword} route (that path only resolves
    //   store+category), so a plain /akcijos/{store} nav silently dropped
    //   the keyword filter entirely (confirmed live bug, 2026-09-15) —
    //   KeywordPageController::show() already reads a ?store= query param
    //   (same one toggleStore()'s wire re-fetch uses), so link there
    //   instead, staying on the keyword page with the store applied.
    // On multi-store pages the stores shown now come along to the next
    // category (?store=), so switching category doesn't drop them.
    $categoryQuery = $activeStoreSlug === null && $storeFilter !== ''
        ? '?' . http_build_query(array_filter(['store' => $storeFilter, 'order' => $order !== 'popular' ? $order : null]))
        : $orderSuffix;
    $categoryHrefFor = fn (string $slug) => ($activeStoreSlug !== null ? '/akcijos/' . $activeStoreSlug . '/' . $slug : '/akcijos/' . $slug) . $categoryQuery;
    $storeHrefFor = function (string $slug) use ($activeCategorySlug, $mode, $primarySlug, $order, $orderSuffix) {
        if ($mode === 'keyword') {
            $query = array_filter(['store' => $slug, 'order' => $order !== 'popular' ? $order : null]);

            return '/akcijos/' . $primarySlug . '?' . http_build_query($query);
        }

        return ($activeCategorySlug !== null ? '/akcijos/' . $slug . '/' . $activeCategorySlug : '/akcijos/' . $slug) . $orderSuffix;
    };

    // "Visos" clears the category facet — back to the plain store page. Only
    // meaningful on a store+category combo page (the only case with a
    // "plain store page" to fall back to); category-only and the hub have
    // no broader "all" to return to (verified against production for the
    // hub case; category-only is new, same reasoning applies).
    $categoryAllHref = $showCategoryFilter && $activeStoreSlug !== null
        ? '/akcijos/' . $activeStoreSlug . $orderSuffix
        : ($showCategoryFilter && $multiStore && $activeCategorySlug !== null ? '/akcijos' . $categoryQuery : null);

    // "Visos" clears the store facet — back to the plain category page. Only
    // meaningful on a store+category combo page, same reasoning as
    // $categoryAllHref above (the only case with a "plain category page" to
    // fall back to — category-only pages have no store fixed to drop, and
    // keyword pages have no /akcijos/{keyword} route sharing this shape).
    $storeAllHref = $showStoreFilter && $activeCategorySlug !== null
        ? '/akcijos/' . $activeCategorySlug . $orderSuffix
        : null;

    $contextStoreSlug = $primarySlug && \App\Support\StoreDisplayMeta::isStoreSlug($primarySlug)
        ? $primarySlug
        : null;
@endphp

{{-- mt-6: this component sits directly under varying content on every page
     type (hero, discovery chips, switch-row, "Visos X akcijos" heading) —
     giving the gap here once, on the shared root, keeps every call site from
     needing its own matching bottom margin. --}}
<div
    class="mt-6 flex w-full flex-col max-sm:gap-1"
    {{-- busy: the list is being updated, shown as a "Ieškome pasiūlymų…" card
         over the list. Set around each filter/sort request (run()) and on
         taps that load a new page (category links). Multi-store pages get
         "Mano vaistinės" applied by the server (App\Support\MyStores);
         the browser applies them after load only if that didn't happen. --}}
    x-data="{
        busy: false,
        // Runs a Livewire call with the loader on until it's done. With
        // reveal, scrolls the bar to the top first so the loader and the new
        // list are in view (on phones the list starts below the fold).
        run(call, reveal = false) {
            this.busy = true;
            if (reveal) this.$root.querySelector('[data-sticky-filter-bar]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return call().finally(() => { this.busy = false; });
        },
        @if ($multiStore)
            sameAsMine() {
                return $wire.storeFilter.split(',').filter(Boolean).sort().join(',') === $store.myStores.filterValue();
            },
            applyMine() {
                if (!$store.myStores.active()) return;
                this.run(() => $wire.applyStores($store.myStores.filterValue()));
            },
            // The store sheet's ticks: marked here only (instant), applied
            // in one request on 'Rodyti' (owner: the list reloading after
            // every tap was slow).
            picked: [],
            openStores() {
                this.picked = this.checkedStores();
                $wire.openPanel = 'store';
            },
            togglePicked(slug) {
                this.picked = this.picked.includes(slug) ? this.picked.filter((s) => s !== slug) : [...this.picked, slug];
            },
            applyPicked() {
                $wire.openPanel = null;
                const value = [...this.picked].sort().join(',');
                if (value === this.checkedStores().sort().join(',')) return;
                if (!value) {
                    this.showAllStores();
                    return;
                }
                this.run(() => $wire.applyStores(value), true);
            },
            // 1 pasiūlymą, 2 pasiūlymus, 10 pasiūlymų, 21 pasiūlymą.
            offersLabel(n) {
                const count = n.toLocaleString('lt-LT');
                if (n % 100 >= 11 && n % 100 <= 19) return count + ' pasiūlymų';
                if (n % 10 === 1) return count + ' pasiūlymą';
                if (n % 10 === 0) return count + ' pasiūlymų';
                return count + ' pasiūlymus';
            },
            // The store button's text, in words: 'Visos vaistinės', 'Mano:
            // Maxima, Lidl', 'Maxima, Norfa ir dar 2'.
            storeLabel() {
                const picked = this.checkedStores();
                if (!picked.length) return 'Visos vaistinės';
                const names = picked.map((s) => $store.myStores.name(s));
                const text = names.length > 3 ? names.slice(0, 2).join(', ') + ' ir dar ' + (names.length - 2) : names.join(', ');
                return (this.sameAsMine() ? 'Mano: ' : '') + text;
            },
            // The store filter sheet's checkbox list.
            checkedStores() {
                return $wire.storeFilter.split(',').filter(Boolean);
            },
            // Back to every store: a fresh load of the page without ?store,
            // so pages with carousels (the /akcijos hub) get them back.
            showAllStores() {
                this.busy = true;
                $store.myStores.setShowAll(true);
                const url = new URL(location.href);
                url.searchParams.delete('store');
                location.href = url.pathname + url.search;
            },
        @endif
    }"
    x-init="
        @if ($multiStore)
            if (!new URL(location.href).searchParams.has('store') && !$store.myStores.showAll && !sameAsMine()) applyMine();
        @endif
    "
    @pageshow.window="busy = false"
    @if ($multiStore)
        @my-stores-changed.window="$store.myStores.active() ? applyMine() : ($wire.storeFilter && showAllStores())"
    @endif
>
        @if ($showFilters)
            @php
                $activeStoreName = $activeStoreSlug ? (collect($allStores)->firstWhere('slug', $activeStoreSlug)['name'] ?? null) : null;
                $activeCategoryName = $activeCategorySlug ? (collect($allCategories)->firstWhere('slug', $activeCategorySlug)['name'] ?? null) : null;
                // Server-side text for the store button (Alpine keeps it
                // current on multi-store pages, see storeLabel()).
                $storeNames = collect($selectedStores)->map(fn ($slug) => collect($allStores)->firstWhere('slug', $slug)['name'] ?? $slug)->values();
                $storeButtonText = $activeStoreName
                    ?? ($storeNames->isEmpty() ? 'Visos vaistinės'
                        : ($storeNames->count() > 3 ? $storeNames->take(2)->implode(', ') . ' ir dar ' . ($storeNames->count() - 2) : $storeNames->implode(', ')));
                $categoryButtonText = $activeCategoryName ?? 'Visos kategorijos';
                $storeValueText = $multiStore ? "busy ? 'Ieškome pasiūlymų…' : storeLabel()" : null;
                $storeButtonClick = $multiStore ? 'openStores()' : "\$wire.openPanel = 'store'";
            @endphp
            {{-- One navigation bar for every offer listing (owner's request,
                 2026-10-02): each facet is a labelled button that says in
                 words what is shown now ("Vaistinės: Maxima, Lidl",
                 "Kategorija: Duonos gaminiai") and opens its list, so moving
                 from one store or category to another is one obvious tap.
                 It replaced the green "Rodomos tik jūsų vaistinės" bar,
                 the separate pills and the phone-only "Filtrai" sheet.
                 Not pinned (owner's decision): at ~95px with its labels it
                 covered too much of the list, and phones never pinned it.
                 relative z-30 keeps the sort dropdown above the cards (their heart button is z-20).
                 data-sticky-filter-bar: see site-header.blade.php. --}}
            <x-nav-bar
                data-sticky-filter-bar
                class="scroll-mt-[calc(var(--header-h)+0.75rem)]"
                x-data="{ sortOpen: false }"
                x-on:click.outside="sortOpen = false"
            >
                @if ($showStoreFilter)
                    <x-nav-bar-button
                        :label="$activeStoreSlug ? 'Vaistinė' : 'Vaistinės'"
                        :value-text="$storeValueText"
                        x-on:click="{{ $storeButtonClick }}"
                        aria-haspopup="dialog"
                    >
                        <x-slot:leading>
                            <x-app-icon x-show="!busy" name="store" class="size-6 shrink-0 text-dark-green" />
                            <span x-show="busy" x-cloak class="size-6 shrink-0 animate-spin rounded-full border-[3px] border-green-soft border-t-action"></span>
                        </x-slot:leading>
                        {{ $storeButtonText }}
                    </x-nav-bar-button>
                @endif
                @if ($showCategoryFilter)
                    <x-nav-bar-button label="Kategorija" icon="layout-grid" x-on:click="$wire.openPanel = 'category'" aria-haspopup="dialog">
                        {{ $categoryButtonText }}
                    </x-nav-bar-button>
                @endif
                @if ($showSort)
                    <x-nav-bar-button
                        label="Rikiuoti"
                        icon="arrow-down-up"
                        wrapper-class="relative flex min-w-0 flex-col gap-1 sm:w-64 sm:shrink-0"
                        x-on:click="sortOpen = !sortOpen"
                        aria-haspopup="listbox"
                        x-bind:aria-expanded="sortOpen"
                    >
                        {{ $orderOptions[$order] }}
                        <x-slot:after>
                            <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                                @foreach ($orderOptions as $value => $orderLabel)
                                    <button type="button" @click="run(() => $wire.setOrder('{{ $value }}'), true); sortOpen = false; window.trackGaEvent && window.trackGaEvent('sort_change', { sort_value: '{{ $value }}' })" class="{{ $rowClass($order === $value) }}">
                                        <x-app-icon :name="$orderIcons[$value]" class="size-5 shrink-0 opacity-90" />
                                        {{ $orderLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </x-slot:after>
                    </x-nav-bar-button>
                @endif
            </x-nav-bar>

            {{-- Store list: bottom sheet on phones, centered on desktop.
                 Multi-store pages: tick the stores to show, "Rodyti" loads
                 the list. A store's own pages: pick another store to open
                 its page. --}}
            @if ($showStoreFilter)
                <div x-show="$wire.openPanel === 'store'" x-cloak class="sheet-backdrop z-[70]" @click.self="$wire.openPanel = null" @click="if (!$event.defaultPrevented && $event.target.closest('a[href]')) { $wire.openPanel = null; busy = true; }">
                    <div class="sheet-panel px-5 pb-5">
                        <div class="sheet-handle"></div>
                        <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                            <h2 class="text-2xl font-bold text-gray-900">{{ $multiStore ? 'Kurių vaistinių akcijas rodyti?' : 'Pasirinkite vaistinę' }}</h2>
                            <button type="button" @click="$wire.openPanel = null" class="sheet-close" aria-label="Uždaryti">
                                <x-app-icon name="x" class="size-7" />
                            </button>
                        </div>
                        @if ($multiStore)
                            <p x-show="!$store.myStores.active()" class="mb-3 text-lg leading-snug text-gray-700">Pažymėkite, kur perkate. Galėsite išsaugoti jas kaip savo vaistines.</p>
                        @endif
                        @if ($multiStore)
                            {{-- Same tiles as the "Mano vaistinės" picker
                                 (owner's preference). Taps only tick here;
                                 "Rodyti" loads the list once. Tiles stay
                                 links to the store's page for crawlers. --}}
                            <button
                                type="button"
                                @click="picked = []"
                                class="mb-2.5 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 px-4 text-lg font-bold transition-colors"
                                :class="picked.length ? 'border-gray-200 bg-white text-gray-900 hover:border-gray-300' : 'border-action bg-green-soft text-dark-green'"
                            >
                                <x-app-icon name="store" class="size-5" />
                                Visos vaistinės
                            </button>
                            <div class="flex flex-wrap gap-2.5">
                                @foreach ($allStores as $storeOption)
                                    <x-store-pick-tile
                                        :slug="$storeOption['slug']"
                                        :name="$storeOption['name']"
                                        :href="$storeHrefFor($storeOption['slug'])"
                                        checked="picked.includes('{{ $storeOption['slug'] }}')"
                                        toggle="togglePicked('{{ $storeOption['slug'] }}')"
                                    />
                                @endforeach
                            </div>
                        @else
                            {{-- A store's own page: the same tiles, the
                                 current store marked; a tap opens that
                                 store's page. --}}
                            <div class="flex flex-wrap gap-2.5">
                                @foreach ($allStores as $storeOption)
                                    <x-store-pick-tile
                                        :slug="$storeOption['slug']"
                                        :name="$storeOption['name']"
                                        :href="$storeHrefFor($storeOption['slug'])"
                                        :active="$storeOption['slug'] === $activeStoreSlug"
                                        :checkbox="false"
                                    />
                                @endforeach
                            </div>
                            @if ($storeAllHref)
                                <a href="{{ $storeAllHref }}" class="mt-2.5 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-gray-200 px-4 text-lg font-bold text-gray-900 hover:border-gray-300">
                                    <x-app-icon name="store" class="size-5" />
                                    Visos vaistinės
                                </a>
                            @endif
                        @endif
                        @if ($multiStore)
                            <div class="sticky bottom-0 -mx-5 mt-3 flex flex-col gap-2 border-t border-gray-200 bg-white px-5 pt-3">
                                <button
                                    type="button"
                                    @click="applyPicked()"
                                    class="flex min-h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-action px-4 text-lg font-bold text-white hover:bg-action-hover"
                                    x-text="picked.length ? 'Rodyti pažymėtas (' + picked.length + ')' : 'Rodyti visas vaistines'"
                                >Rodyti</button>
                                {{-- Keep the ticked stores as "Mano vaistinės" (and show them). --}}
                                <button
                                    type="button"
                                    x-show="picked.length && [...picked].sort().join(',') !== $store.myStores.filterValue()"
                                    @click="$wire.openPanel = null; $store.myStores.set(picked)"
                                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-base font-bold text-dark-green ring-1 ring-green-soft-border hover:bg-green-soft"
                                >
                                    <x-app-icon name="check" class="size-5" />
                                    Išsaugoti kaip mano vaistines
                                </button>
                                {{-- Back to the saved stores after "Visos" or other ticks. --}}
                                <button
                                    type="button"
                                    x-show="$store.myStores.active() && [...picked].sort().join(',') !== $store.myStores.filterValue()"
                                    @click="picked = [...$store.myStores.slugs]; $store.myStores.setShowAll(false); applyPicked()"
                                    class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-base font-bold text-dark-green ring-1 ring-green-soft-border hover:bg-green-soft"
                                >
                                    <x-app-icon name="store" class="size-5" />
                                    <span x-text="'Rodyti tik mano: ' + $store.myStores.slugs.map((s) => $store.myStores.name(s)).join(', ')"></span>
                                </button>
                            </div>
                        @endif
                        @if ($activeStoreSlug !== null)
                            <a href="/leidinys/{{ $activeStoreSlug }}" data-ga-event="filter_select" data-ga-item="leidiniai:{{ $activeStoreSlug }}" data-ga-source="filter_leaflet_shortcut" class="mt-2 flex w-full items-center gap-2 rounded-2xl border border-gray-200 px-3 min-h-12 text-base font-semibold text-dark-green transition-colors hover:bg-gray-50">
                                <x-app-icon name="bookmark" class="size-5 shrink-0" />
                                <span class="min-w-0 flex-1 truncate">{{ $activeStoreName }} savaitės leidiniai</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($showCategoryFilter)
                <div x-show="$wire.openPanel === 'category'" x-cloak class="sheet-backdrop z-[70]" @click.self="$wire.openPanel = null" @click="if (!$event.defaultPrevented && $event.target.closest('a[href]')) { $wire.openPanel = null; busy = true; }">
                    <div class="sheet-panel px-5 pb-5">
                        <div class="sheet-handle"></div>
                        <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                            <h2 class="text-2xl font-bold text-gray-900">Pasirinkite kategoriją</h2>
                            <button type="button" @click="$wire.openPanel = null" class="sheet-close" aria-label="Uždaryti">
                                <x-app-icon name="x" class="size-7" />
                            </button>
                        </div>
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'categories',
                            'items' => $allCategories,
                            'activeSlug' => $activeCategorySlug,
                            'hrefFor' => $categoryHrefFor,
                            'allHref' => $categoryAllHref,
                            'rowClass' => $rowClass,
                            'gaSource' => 'listing_filter_category',
                        ])
                    </div>
                </div>
            @endif
        @endif

        @php $current = (int) ($pagination['current_page'] ?? 1); $last = (int) ($pagination['last_page'] ?? 1); @endphp

        <div class="relative flex w-full min-w-0 flex-col">
            {{-- Loader while the list updates (see busy on the root). The card
                 is sticky so it stays in view on a long list. --}}
            <div x-show="busy" x-cloak class="absolute inset-0 z-30 flex justify-center rounded-2xl bg-background/75" role="status" aria-live="polite">
                <div class="sticky top-[calc(var(--header-h)+6rem)] mt-16 flex h-fit items-center gap-3 rounded-2xl bg-white px-6 py-4 text-lg font-bold text-gray-900 shadow-lg ring-1 ring-gray-200">
                    <span class="size-7 shrink-0 animate-spin rounded-full border-4 border-green-soft border-t-action"></span>
                    Ieškome pasiūlymų…
                </div>
            </div>
            @if ($showCarousels)
                {{-- wire:ignore keeps carousel HTML across sort updates; carouselHtml
                     is cleared in dehydrate() so the deal payload stays out of
                     wire:snapshot after the first response. --}}
                {{-- Distinct wire:keys: without them a switch to the grid
                     (stores picked on the hub) morphed the grid into this
                     wire:ignore block and the carousels stayed on screen. --}}
                <div wire:key="listing-carousels" wire:ignore class="flex flex-col gap-8 sm:gap-14">
                    {!! $carouselHtml !!}
                </div>
            @else
                @php
                    // Keyword pages' "leading deals" (cheapest per store)
                    // used to be folded into the front of the grid on page 1
                    // — removed per explicit product decision. The grid now
                    // always shows exactly $deals, the real paginated set.
                    $displayDeals = $deals;
                @endphp
                <div
                    wire:key="listing-grid"
                    class="flex w-full flex-col"
                    x-data="listingLoadMore(@js([
                        'endpoint' => route('akcijos.deals.partial'),
                        'page' => $current,
                        'lastPage' => $last,
                        'shown' => count($displayDeals),
                        'total' => (int) ($pagination['total'] ?? count($displayDeals)),
                        'wireId' => $this->getId(),
                        'gaSource' => 'listing',
                        'manual' => true,
                        'params' => [
                            'mode' => $mode,
                            'primary_slug' => $primarySlug,
                            'secondary_slug' => $secondarySlug,
                            'order' => $order,
                            'store' => $storeFilter,
                            'category' => $categoryFilter,
                            'card' => $cardOnly ? '1' : '0',
                            'plus' => $plusOnly ? '1' : '0',
                        ],
                    ]))"
                >
                    {{-- CSS Grid abandoned entirely for this listing —
                         confirmed on device this session: cards render
                         overlapping each other (row track heights computing
                         to ~0, all rows starting at the same offset) even
                         with only 3 items, ruling out stretch/aspect-ratio
                         and page-size/memory theories tried earlier. The
                         only layouts confirmed working on the same device
                         (store-page carousels, homepage teaser) are all
                         flex, not grid — so this switches to flex-wrap +
                         explicit basis widths (2/3/4/5 columns matching the
                         old grid-cols breakpoints), which doesn't share
                         CSS Grid's row-track-sizing algorithm at all. --}}
                    <div class="flex w-full flex-wrap gap-2 sm:gap-3" x-ref="grid">
                        @foreach ($displayDeals as $deal)
                            <x-deal-card
                                :deal="$deal"
                                :stretch="false"
                                :context-store-slug="$contextStoreSlug"
                                class="deal-card-width"
                            />
                        @endforeach
                    </div>

                    @if ($current < $last)
                        {{-- "Rodyti daugiau" button, not infinite scroll: the
                             price table, related links and FAQ below the grid
                             stay reachable instead of being pushed down by
                             every auto-loaded page. A real <a href="?page=N"
                             rel="next"> so crawlers follow it too; JS loads
                             the next page in place (listingLoadMore). --}}
                        <div class="mt-6 flex flex-col items-center gap-3" x-show="page < lastPage">
                            <div class="flex w-full max-w-xs flex-col items-center gap-1.5">
                                <p class="text-sm text-gray-600">
                                    Parodyta <span class="font-bold tabular-nums text-gray-900" x-text="shown">{{ count($displayDeals) }}</span>
                                    iš <span class="font-bold tabular-nums text-gray-900">{{ number_format((int) ($pagination['total'] ?? count($displayDeals)), 0, ',', ' ') }}</span>
                                </p>
                                <div class="h-1 w-full overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-full rounded-full bg-action transition-[width] duration-300" :style="`width: ${Math.min(100, Math.round(shown / total * 100))}%`" style="width: {{ min(100, (int) round(count($displayDeals) / max(1, (int) ($pagination['total'] ?? 1)) * 100)) }}%"></div>
                                </div>
                            </div>
                            <a
                                href="?page={{ $current + 1 }}"
                                :href="`?page=${page + 1}`"
                                rel="next"
                                @click.prevent="loadMore()"
                                :aria-busy="loading"
                                class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border border-green bg-white px-8 text-base font-bold text-dark-green shadow-sm transition-colors hover:bg-green-soft hover:text-dark-green active:bg-green-soft sm:w-auto sm:min-w-72"
                                :class="loading && 'pointer-events-none opacity-70'"
                            >
                                <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" /></svg>
                                <span x-text="loading ? 'Kraunama...' : 'Rodyti daugiau'">Rodyti daugiau</span>
                                <x-app-icon name="chevron-down" class="size-4" x-show="!loading" />
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
