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
    @endphp

    {{-- Title block matches discounts-layout.tsx's <h1> treatment used by every
         other listing page, for visual consistency. --}}
    <div class="base-container gap-4 pb-4 pt-4 sm:pb-5">
        <h1 class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 font-semibold text-gray-900">
            <span class="text-gray-900">Paieškos rezultatai: „{{ $query }}“</span>
            @if ($total > 0)
                <span class="whitespace-nowrap tabular-nums text-gray-500">({{ number_format($total, 0, ',', ' ') }})</span>
            @endif
        </h1>

        @if (!empty($deals))
            <div class="mt-4 flex w-full items-center justify-end rounded-2xl bg-[#e8e8e8] px-4 min-h-[40px]" x-data="{ sortOpen: false }" @click.outside="sortOpen = false">
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
                        'params' => [
                            'mode' => 'search',
                            'primary_slug' => $query,
                            'order' => $currentOrder,
                        ],
                    ]))"
                >
                    <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 2xl:grid-cols-4" x-ref="grid">
                        @foreach ($deals as $deal)
                            <x-deal-card :deal="$deal" class="h-full" />
                        @endforeach
                    </div>

                    @if ($current < $last)
                        <div class="mt-6 flex justify-center">
                            <button
                                type="button"
                                @click="loadMore()"
                                :disabled="loading || page >= lastPage"
                                data-ga-event="load_more_click"
                                data-ga-source="search"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-green bg-white px-6 text-sm font-bold text-green transition-colors hover:bg-green/5 hover:text-dark-green disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span x-show="!loading" x-text="`Rodyti daugiau (${shown} iš ${total})`"></span>
                                <span x-show="loading" x-cloak>Kraunama...</span>
                            </button>
                            {{-- Same crawler-only fallback link as discount-filters.blade.php. --}}
                            <a href="{{ $basePath }}?{{ http_build_query(array_merge(request()->except('page'), ['page' => $current + 1])) }}" rel="next" class="sr-only" tabindex="-1" aria-hidden="true">Kitas puslapis</a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
