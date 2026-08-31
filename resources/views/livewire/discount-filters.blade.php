@php
    // Row styling below reuses the exact constants from
    // discount/src/components/common/product-filter-controls.tsx:
    // FILTER_ROW_HEIGHT_CLASS='min-h-[40px]' FILTER_ROW_TEXT_CLASS='text-[16px] leading-snug'
    // FILTER_LIST_GAP_CLASS='gap-0.5', and its active/hover row colors.
    $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[40px] text-[16px] leading-snug text-left transition-colors '
        . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
    $selectedStores = array_values(array_filter(explode(',', $storeFilter)));
    $selectedCategories = array_values(array_filter(explode(',', $categoryFilter)));
    $activeCount = count($selectedStores) + count($selectedCategories);
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
    // "Visos" clears whichever facet the sidebar controls — back to the plain
    // store page, or to the category/keyword page with no store filter. Not
    // shown at all on the plain /akcijos hub (no $primarySlug) — there's no
    // broader "all" to return to, verified against production.
    $allHref = $sidebarMode === 'categories' && $primarySlug !== null
        ? '/akcijos/' . $primarySlug . ($order !== 'popular' ? '?order=' . $order : '')
        : null;

    // Store+category combo page (/akcijos/{store}/{category}) — the store is
    // fixed by the URL path, not a toggleable query-string filter, so it
    // needs its own removable chip: "x" navigates to the category alone
    // rather than a Livewire state change.
    $isStoreCategoryCombo = $mode === 'discounts' && $primarySlug !== null && $secondarySlug !== null;
    $removeStoreHref = $isStoreCategoryCombo
        ? '/akcijos/' . $secondarySlug . ($order !== 'popular' ? '?order=' . $order : '')
        : null;

    $contextStoreSlug = $primarySlug && \App\Support\StoreDisplayMeta::isStoreSlug($primarySlug)
        ? $primarySlug
        : null;
@endphp

{{-- Verified against production: the sort/action bar is visible in carousel
     mode too (e.g. /akcijos?order=price_discount_proc_max still shows a
     "Didž. nuolaida (%)" dropdown above the category carousels) — it was
     wrongly hidden here before. --}}
@php $showActionBar = true; @endphp

<div class="flex flex-row gap-8">
    {{-- Desktop sidebar — persistent category (or store) nav, discounts-layout.tsx's
         sticky Card wrapper. Always the SAME list regardless of mode; only the
         active row changes. --}}
    <div class="hidden w-full max-w-[300px] shrink-0 gap-3 sm:sticky sm:top-25 sm:flex sm:h-[calc(100vh-120px)]">
        <div class="h-full w-full overflow-y-auto pr-2">
            @include('components.partials.discount-filter-sections')
        </div>
    </div>

    <div class="flex w-full min-w-0 flex-1 flex-col max-sm:gap-1">
        @if ($showActionBar)
            <div class="mb-4 flex w-full items-center justify-between gap-2 rounded-2xl bg-[#e8e8e8] px-4 min-h-[40px] sm:mb-[17px]" x-data="{ sortOpen: false }" @click.outside="sortOpen = false">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <button type="button" wire:click="$toggle('panelOpen')" class="inline-flex h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] text-gray-900 hover:bg-[#dedede] sm:hidden">
                        <x-app-icon name="filter" class="size-4 shrink-0" />
                        Filtrai{{ $activeCount > 0 ? " ({$activeCount})" : '' }}
                    </button>
                    <div class="hidden min-w-0 flex-wrap gap-2 sm:flex">
                        @if ($isStoreCategoryCombo)
                            <a href="{{ $removeStoreHref }}" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                                {{ $primaryStoreName ?? $primarySlug }}
                                <x-app-icon name="x" class="size-4" />
                            </a>
                        @endif
                        @foreach ($selectedStores as $slug)
                            <button type="button" @click="window.trackGaEvent && window.trackGaEvent('filter_apply', { filter_type: 'store', filter_value: '{{ $slug }}', action: 'toggle_off' })" wire:click="toggleStore('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                                {{ collect($allStores)->firstWhere('slug', $slug)['name'] ?? $slug }}
                                <x-app-icon name="x" class="size-4" />
                            </button>
                        @endforeach
                        @foreach ($selectedCategories as $slug)
                            <button type="button" @click="window.trackGaEvent && window.trackGaEvent('filter_apply', { filter_type: 'category', filter_value: '{{ $slug }}', action: 'toggle_off' })" wire:click="toggleCategory('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                                {{ collect($allCategories)->firstWhere('slug', $slug)['name'] ?? $slug }}
                                <x-app-icon name="x" class="size-4" />
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="relative shrink-0">
                    <button type="button" @click="sortOpen = !sortOpen" class="inline-flex h-full cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] text-gray-900 hover:bg-[#dedede]" aria-haspopup="listbox" :aria-expanded="sortOpen">
                        <x-app-icon name="arrow-down-up" class="size-4 shrink-0" />
                        <span class="hidden max-w-[140px] truncate sm:inline">{{ $orderOptions[$order] }}</span>
                        <x-app-icon name="chevron-down" class="size-4 shrink-0 opacity-70" />
                    </button>
                    <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 min-w-[240px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                        @foreach ($orderOptions as $value => $label)
                            <button type="button" wire:click="setOrder('{{ $value }}')" @click="sortOpen = false; window.trackGaEvent && window.trackGaEvent('sort_change', { sort_value: '{{ $value }}' })" class="{{ $rowClass($order === $value) }}">
                                <x-app-icon :name="$orderIcons[$value]" class="size-3.5 shrink-0 opacity-90" />
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div x-show="$wire.panelOpen" x-cloak class="fixed inset-0 z-50 bg-black/40 sm:hidden" @click.self="$wire.panelOpen = false">
                <div class="absolute inset-x-0 bottom-0 max-h-[82vh] overflow-y-auto rounded-t-2xl bg-white p-4">
                    @include('components.partials.discount-filter-sections')
                </div>
            </div>

            <div class="mb-3 flex flex-wrap gap-2 sm:hidden">
                @if ($isStoreCategoryCombo)
                    <a href="{{ $removeStoreHref }}" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                        {{ $primaryStoreName ?? $primarySlug }}
                        <x-app-icon name="x" class="size-4" />
                    </a>
                @endif
                @foreach ($selectedStores as $slug)
                    <button type="button" wire:click="toggleStore('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                        {{ collect($allStores)->firstWhere('slug', $slug)['name'] ?? $slug }}
                        <x-app-icon name="x" class="size-4" />
                    </button>
                @endforeach
                @foreach ($selectedCategories as $slug)
                    <button type="button" wire:click="toggleCategory('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                        {{ collect($allCategories)->firstWhere('slug', $slug)['name'] ?? $slug }}
                        <x-app-icon name="x" class="size-4" />
                    </button>
                @endforeach
            </div>
        @endif

        @php $current = (int) ($pagination['current_page'] ?? 1); $last = (int) ($pagination['last_page'] ?? 1); @endphp

        <div wire:loading.class="opacity-50" wire:target="toggleStore,toggleCategory,setOrder,toggleCard,togglePlus" class="flex w-full min-w-0 flex-col transition-opacity">
            @if ($showCarousels)
                {{-- wire:ignore keeps carousel HTML across sort updates; carouselHtml
                     is cleared in dehydrate() so the deal payload stays out of
                     wire:snapshot after the first response. --}}
                <div wire:ignore>
                    {!! $carouselHtml !!}
                </div>
            @else
                <div
                    class="flex w-full flex-col"
                    x-data="listingLoadMore(@js([
                        'endpoint' => route('akcijos.deals.partial'),
                        'page' => $current,
                        'lastPage' => $last,
                        'shown' => count($deals),
                        'total' => (int) ($pagination['total'] ?? count($deals)),
                        'wireId' => $this->getId(),
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
                    <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 2xl:grid-cols-4" x-ref="grid">
                        @foreach ($deals as $deal)
                            <x-deal-card :deal="$deal" class="h-full" :context-store-slug="$contextStoreSlug" />
                        @endforeach
                    </div>

                    @if ($current < $last)
                        <div class="mt-6 flex justify-center">
                            <button
                                type="button"
                                @click="loadMore()"
                                :disabled="loading || page >= lastPage"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-green bg-white px-6 text-sm font-bold text-green transition-colors hover:bg-green/5 hover:text-dark-green disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span x-show="!loading" x-text="`Rodyti daugiau (${shown} iš ${total})`"></span>
                                <span x-show="loading" x-cloak>Kraunama...</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
