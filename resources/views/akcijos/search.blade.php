<x-layouts.app :title="'Paieška: ' . $query" :canonical="$canonical" :robots="$robots">
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
        $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[40px] text-[16px] leading-snug text-left transition-colors '
            . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
        // Plain query-param navigation, same as the sort dropdown next to it
        // (this page isn't Livewire, unlike discount-filters' multi-select
        // store toggle) — single store at a time, "Visos" clears it.
        $selectedStoreName = $selectedStore ? collect($allStores)->firstWhere('slug', $selectedStore)['name'] ?? $selectedStore : null;
        $storeHref = fn (?string $slug) => $basePath . '?' . http_build_query(array_merge(
            request()->except(['page', 'store']),
            $slug ? ['store' => $slug] : []
        ));
    @endphp

    {{-- Title block matches discounts-layout.tsx's <h1> treatment used by every
         other listing page, for visual consistency. --}}
    <div class="base-container gap-4 pb-4 pt-4 sm:pb-5">
        <h1 class="flex min-w-0 flex-wrap items-baseline gap-x-1.5">
            <span>Paieškos rezultatai: „{{ $query }}“</span>
            @if ($total > 0)
                <span class="whitespace-nowrap tabular-nums text-gray-500">({{ number_format($total, 0, ',', ' ') }})</span>
            @endif
        </h1>

        @if (!empty($deals) || $selectedStore)
            <div class="mt-4 flex w-full items-center justify-between gap-2 rounded-2xl bg-[#e8e8e8] px-4 min-h-[40px]" x-data="{ sortOpen: false, storeOpen: false }" @click.outside="sortOpen = false; storeOpen = false">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <div class="relative shrink-0">
                        <button type="button" @click="storeOpen = !storeOpen" class="inline-flex h-full cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] text-gray-900 hover:bg-[#dedede]" aria-haspopup="listbox" :aria-expanded="storeOpen">
                            <x-app-icon name="store" class="size-4 shrink-0" />
                            <span class="max-w-[140px] truncate">{{ $selectedStoreName ?? 'Parduotuvė' }}</span>
                            <x-app-icon name="chevron-down" class="size-4 shrink-0 opacity-70" />
                        </button>
                        <div x-show="storeOpen" x-cloak class="absolute left-0 top-full z-30 mt-1.5 max-h-[360px] min-w-[240px] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                            <a href="{{ $storeHref(null) }}" class="{{ $rowClass($selectedStore === null) }}">Visos parduotuvės</a>
                            @foreach ($allStores as $storeOption)
                                <a href="{{ $storeHref($storeOption['slug']) }}" class="{{ $rowClass($selectedStore === $storeOption['slug']) }}">
                                    <span class="flex h-6 w-8 shrink-0 items-center justify-center">
                                        <img src="/assets/stores/{{ $storeOption['slug'] }}.svg" alt="" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">
                                    </span>
                                    <span class="truncate">{{ $storeOption['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="relative shrink-0">
                    <button type="button" @click="sortOpen = !sortOpen" class="inline-flex h-full cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] text-gray-900 hover:bg-[#dedede]" aria-haspopup="listbox" :aria-expanded="sortOpen">
                        <x-app-icon name="arrow-down-up" class="size-4 shrink-0" />
                        <span class="max-w-[160px] truncate">{{ $orderOptions[$currentOrder] ?? $orderOptions['popular'] }}</span>
                        <x-app-icon name="chevron-down" class="size-4 shrink-0 opacity-70" />
                    </button>
                    <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 min-w-[240px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                        @foreach ($orderOptions as $value => $label)
                            <a
                                href="{{ $basePath }}?{{ http_build_query(array_merge(request()->except(['page', 'order']), $value === 'popular' ? [] : ['order' => $value])) }}"
                                class="{{ $rowClass($currentOrder === $value) }}"
                            >
                                <x-app-icon :name="$orderIcons[$value]" class="size-3.5 shrink-0 opacity-90" />
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

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
                                class="w-[calc(50%-0.25rem)] sm:w-[calc(33.333%-0.5rem)] lg:w-[calc(25%-0.5625rem)] xl:w-[calc(20%-0.6rem)]"
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
