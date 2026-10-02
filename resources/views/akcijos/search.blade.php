<x-layouts.app
    app-title="Paieška"
    back-href="/" :title="'Paieška: ' . $query" :canonical="$canonical" :robots="$robots">
    @php
        // Same order options/icons/row styling as
        // livewire/discount-filters.blade.php's sort dropdown, ported here as
        // plain <a href> links (query-param navigation) since this page isn't
        // a Livewire component — the load-more button's params already carry
        // the current order, so ?order=... keeps working across loads.
        $orderOptions = [
            'popular' => 'Populiariausi',
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
        $currentOrder = request('order', 'popular');
        $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[40px] text-sm leading-snug text-left transition-colors '
            . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
        // Plain query-param navigation, same as the sort dropdown next to it
        // (this page isn't Livewire, unlike discount-filters' multi-select
        // store toggle) — single store at a time, "Visos" clears it.
        // ?store can hold several slugs ("Mano parduotuvės", applied below).
        $selectedStoreSlugs = array_values(array_filter(explode(',', (string) $selectedStore)));
        $selectedStoreName = match (true) {
            count($selectedStoreSlugs) > 1 => count($selectedStoreSlugs) . ' parduotuvės',
            count($selectedStoreSlugs) === 1 => collect($allStores)->firstWhere('slug', $selectedStoreSlugs[0])['name'] ?? $selectedStoreSlugs[0],
            default => null,
        };
        $storeHref = fn (?string $slug) => $basePath . '?' . http_build_query(array_merge(
            request()->except(['page', 'store']),
            $slug ? ['store' => $slug] : []
        ));
    @endphp

    {{-- Title block matches discounts-layout.tsx's <h1> treatment used by every
         other listing page, for visual consistency. --}}
    <div class="base-container gap-4 pb-4 pt-4 sm:pb-5">
        <x-search-box :value="$query" class="mb-4" />
        <h1 class="flex min-w-0 flex-wrap items-baseline gap-x-1.5">
            <span>Paieškos rezultatai: „{{ $query }}“</span>
            @if ($total > 0)
                <span class="whitespace-nowrap tabular-nums text-gray-500">({{ number_format($total, 0, ',', ' ') }})</span>
            @endif
        </h1>

        {{-- One navigation bar, same as the offer listings
             (livewire/discount-filters.blade.php): labelled buttons that say
             what's shown. Plain links here (this page isn't Livewire), so
             each store tile links to the results with that store added or
             removed. "Mano parduotuvės" are applied by the server
             (App\Support\MyStores); the redirect to ?store= below only runs
             when that didn't happen (no cookie yet). --}}
        @php
            // Crawler links only: a tap just ticks (see picked below).
            $storeToggleHref = fn (string $slug) => $storeHref($slug);
            $storeNamesShown = collect($selectedStoreSlugs)->map(fn ($slug) => collect($allStores)->firstWhere('slug', $slug)['name'] ?? $slug);
            $storeButtonText = $storeNamesShown->isEmpty() ? 'Visos parduotuvės'
                : ($storeNamesShown->count() > 3 ? $storeNamesShown->take(2)->implode(', ') . ' ir dar ' . ($storeNamesShown->count() - 2) : $storeNamesShown->implode(', '));
        @endphp
        <div
            x-data="{
                urlStores: @js((string) $selectedStore),
                storeOpen: false,
                sortOpen: false,
                // A sort option or 'Rodyti' loads the results again: show a
                // loader until the new page arrives.
                busy: false,
                // The store sheet's ticks: marked here only, loaded once on
                // 'Rodyti' (a reload per tap was slow).
                picked: [],
                openStores() {
                    this.picked = this.urlStores.split(',').filter(Boolean);
                    this.storeOpen = true;
                },
                togglePicked(slug) {
                    this.picked = this.picked.includes(slug) ? this.picked.filter((s) => s !== slug) : [...this.picked, slug];
                },
                applyPicked() {
                    this.storeOpen = false;
                    const value = this.picked.join(',');
                    if ([...this.picked].sort().join(',') === this.urlStores.split(',').filter(Boolean).sort().join(',')) return;
                    if (!value) $store.myStores.setShowAll(true);
                    this.busy = true;
                    location.href = this.withStores(value);
                },
                withStores(value) {
                    const url = new URL(location.href);
                    url.searchParams.delete('page');
                    value ? url.searchParams.set('store', value) : url.searchParams.delete('store');
                    return url.pathname + url.search;
                },
                sameAsMine() { return this.urlStores.split(',').filter(Boolean).sort().join(',') === $store.myStores.filterValue(); },
            }"
            x-init="if (!urlStores && $store.myStores.active() && !$store.myStores.showAll) location.replace(withStores($store.myStores.filterValue()))"
            @my-stores-changed.window="location.href = withStores($store.myStores.filterValue())"
            @keydown.escape.window="storeOpen = false; sortOpen = false"
            @pageshow.window="busy = false"
        >
            @if (!empty($deals) || $selectedStore)
                <x-nav-bar class="mt-4" x-on:click.outside="sortOpen = false" x-on:click="if ($event.target.closest('a[href]')) busy = true">
                    <x-nav-bar-button label="Parduotuvės" icon="store" x-on:click="openStores()" aria-haspopup="dialog">
                        <span x-show="$store.myStores.active() && sameAsMine()" x-cloak>Mano: </span>{{ $storeButtonText }}
                    </x-nav-bar-button>
                    <x-nav-bar-button
                        label="Rikiuoti"
                        icon="arrow-down-up"
                        wrapper-class="relative flex min-w-0 flex-col gap-1 sm:w-64 sm:shrink-0"
                        x-on:click="sortOpen = !sortOpen"
                        aria-haspopup="listbox"
                        x-bind:aria-expanded="sortOpen"
                    >
                        {{ $orderOptions[$currentOrder] ?? $orderOptions['popular'] }}
                        <x-slot:after>
                            <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 w-full min-w-[260px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                                @foreach ($orderOptions as $value => $orderLabel)
                                    <a
                                        href="{{ $basePath }}?{{ http_build_query(array_merge(request()->except(['page', 'order']), $value === 'popular' ? [] : ['order' => $value])) }}"
                                        class="{{ $rowClass($currentOrder === $value) }}"
                                    >
                                        <x-app-icon :name="$orderIcons[$value]" class="size-5 shrink-0 opacity-90" />
                                        {{ $orderLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </x-slot:after>
                    </x-nav-bar-button>
                </x-nav-bar>

                <div x-show="busy" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center bg-black/30" role="status" aria-live="polite">
                    <div class="flex items-center gap-3 rounded-2xl bg-white px-6 py-4 text-lg font-bold text-gray-900 shadow-lg">
                        <span class="size-7 shrink-0 animate-spin rounded-full border-4 border-green-soft border-t-action"></span>
                        Ieškome pasiūlymų…
                    </div>
                </div>
                <div x-show="storeOpen" x-cloak class="sheet-backdrop z-[70]" @click.self="storeOpen = false">
                    <div class="sheet-panel px-5 pb-5">
                        <div class="sheet-handle"></div>
                        <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                            <h2 class="text-2xl font-bold text-gray-900">Kurių parduotuvių prekes rodyti?</h2>
                            <button type="button" @click="storeOpen = false" class="sheet-close" aria-label="Uždaryti">
                                <x-app-icon name="x" class="size-7" />
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="picked = []"
                            class="mb-2.5 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 px-4 text-lg font-bold"
                            :class="picked.length ? 'border-gray-200 bg-white text-gray-900 hover:border-gray-300' : 'border-action bg-green-soft text-dark-green'"
                        >
                            <x-app-icon name="store" class="size-5" />
                            Visos parduotuvės
                        </button>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($allStores as $storeOption)
                                <x-store-pick-tile
                                    :slug="$storeOption['slug']"
                                    :name="$storeOption['name']"
                                    :href="$storeToggleHref($storeOption['slug'])"
                                    checked="picked.includes('{{ $storeOption['slug'] }}')"
                                    toggle="togglePicked('{{ $storeOption['slug'] }}')"
                                />
                            @endforeach
                        </div>
                        <div class="sticky bottom-0 -mx-5 mt-3 flex flex-col gap-2 border-t border-gray-200 bg-white px-5 pt-3">
                            <button
                                type="button"
                                @click="applyPicked()"
                                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-action px-4 text-lg font-bold text-white hover:bg-action-hover"
                                x-text="picked.length ? 'Rodyti pažymėtas (' + picked.length + ')' : 'Rodyti visas parduotuves'"
                            >Rodyti</button>
                            <button
                                type="button"
                                x-show="picked.length && [...picked].sort().join(',') !== $store.myStores.filterValue()"
                                @click="storeOpen = false; busy = true; $store.myStores.set(picked)"
                                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-base font-bold text-dark-green ring-1 ring-green-soft-border hover:bg-green-soft"
                            >
                                <x-app-icon name="check" class="size-5" />
                                Išsaugoti kaip mano parduotuves
                            </button>
                            <button
                                type="button"
                                x-show="$store.myStores.active() && [...picked].sort().join(',') !== $store.myStores.filterValue()"
                                @click="$store.myStores.setShowAll(false); picked = [...$store.myStores.slugs]; applyPicked()"
                                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 text-base font-bold text-dark-green ring-1 ring-green-soft-border hover:bg-green-soft"
                            >
                                <x-app-icon name="store" class="size-5" />
                                <span x-text="'Rodyti tik mano: ' + $store.myStores.slugs.map((s) => $store.myStores.name(s)).join(', ')"></span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-4 flex w-full flex-wrap gap-8">
            @if (empty($deals))
                <p class="w-full text-center font-bold">Pagal Jūsų užklausą neradome nei vienos prekės</p>
                {{-- Only rendered on the empty state itself (no cost on normal
                     searches) — content-gap signal: what people search for
                     that we don't have. --}}
                <script>
                    // app.js loads as a module (deferred) — wait for
                    // DOMContentLoaded so window.trackGaEvent is defined
                    // before this classic inline script (which would
                    // otherwise run first) tries to call it.
                    document.addEventListener('DOMContentLoaded', () => {
                        window.trackGaEvent?.('search_no_results', { search_term: @json($query) });
                    });
                </script>
            @else
                @php
                    $current = (int) ($pagination['current_page'] ?? 1);
                    $last = (int) ($pagination['last_page'] ?? 1);
                @endphp

                {{-- Same AJAX-accumulating load-more pattern as
                     livewire/discount-filters.blade.php's grid (listingLoadMore
                     Alpine component + /akcijos/_deals partial endpoint), just
                     without a Livewire wireId since this page isn't Livewire. --}}
                <div
                    class="flex w-full flex-col"
                    x-data="listingLoadMore(@js([
                        'endpoint' => route('akcijos.deals.partial'),
                        'page' => $current,
                        'lastPage' => $last,
                        'shown' => count($deals),
                        'total' => (int) ($pagination['total'] ?? count($deals)),
                        'gaSource' => 'search',
                        'params' => [
                            'mode' => 'search',
                            'primary_slug' => $query,
                            'order' => $currentOrder,
                            'store' => $selectedStore,
                        ],
                    ]))"
                >
                    {{-- flex-wrap, not CSS Grid — matches
                         discount-filters.blade.php's main listing grid
                         exactly (a real CSS-Grid row-track-sizing bug found
                         on mobile Safari this session), and must stay
                         identical to it since load-more here shares the same
                         listing-deals-chunk.blade.php partial, which now
                         renders these same width classes. --}}
                    <div class="flex w-full flex-wrap gap-2 sm:gap-3" x-ref="grid">
                        @foreach ($deals as $deal)
                            <x-deal-card
                                :deal="$deal"
                                :stretch="false"
                                class="deal-card-width"
                            />
                        @endforeach
                    </div>

                    @if ($current < $last)
                        {{-- Infinite scroll: same IntersectionObserver sentinel
                             pattern as discount-filters.blade.php. --}}
                        <div x-ref="sentinel" class="mt-6 flex justify-center py-4">
                            <span x-show="loading" x-cloak class="inline-flex items-center gap-2 text-sm font-medium text-gray-500">
                                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" /></svg>
                                Kraunama...
                            </span>
                            {{-- Same crawler-only fallback link as discount-filters.blade.php. --}}
                            <a href="{{ $basePath }}?{{ http_build_query(array_merge(request()->except('page'), ['page' => $current + 1])) }}" rel="next" class="sr-only" tabindex="-1" aria-hidden="true">Kitas puslapis</a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
