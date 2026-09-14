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
    //   (category-only or store+category page); plain /akcijos/{store}
    //   otherwise (keyword page — matches existing behavior, unchanged).
    $categoryHrefFor = fn (string $slug) => ($activeStoreSlug !== null ? '/akcijos/' . $activeStoreSlug . '/' . $slug : '/akcijos/' . $slug) . $orderSuffix;
    $storeHrefFor = fn (string $slug) => ($activeCategorySlug !== null ? '/akcijos/' . $slug . '/' . $activeCategorySlug : '/akcijos/' . $slug) . $orderSuffix;

    // "Visos" clears the category facet — back to the plain store page. Only
    // meaningful on a store+category combo page (the only case with a
    // "plain store page" to fall back to); category-only and the hub have
    // no broader "all" to return to (verified against production for the
    // hub case; category-only is new, same reasoning applies).
    $categoryAllHref = $showCategoryFilter && $activeStoreSlug !== null
        ? '/akcijos/' . $activeStoreSlug . $orderSuffix
        : null;

    $contextStoreSlug = $primarySlug && \App\Support\StoreDisplayMeta::isStoreSlug($primarySlug)
        ? $primarySlug
        : null;
@endphp

{{-- mt-6: this component sits directly under varying content on every page
     type (hero, discovery chips, switch-row, "Visos X akcijos" heading) —
     giving the gap here once, on the shared root, keeps every call site from
     needing its own matching bottom margin. --}}
<div class="mt-6 flex w-full flex-col max-sm:gap-1">
        @if ($showFilters)
            <div class="mb-4 flex w-full items-center justify-between gap-2 rounded-2xl bg-[#e8e8e8] px-4 min-h-[40px] sm:mb-[17px]" x-data="{ sortOpen: false }" @click.outside="sortOpen = false">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    @php
                        // The button itself must show whichever store/
                        // category the URL already fixes (e.g. "Parduotuvė:
                        // Lidl") — matching the old <x-store-nav-tabs> pill's
                        // "Kategorija: X" behavior — not just a bare facet
                        // name that only reveals the selection once opened.
                        $activeStoreName = $activeStoreSlug ? (collect($allStores)->firstWhere('slug', $activeStoreSlug)['name'] ?? null) : null;
                        $activeCategoryName = $activeCategorySlug ? (collect($allCategories)->firstWhere('slug', $activeCategorySlug)['name'] ?? null) : null;
                    @endphp
                    @if ($showStoreFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'store' ? null : 'store')" class="inline-flex h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] {{ $activeStoreName ? 'font-bold text-gray-900' : 'text-gray-900' }} hover:bg-[#dedede]">
                            <x-app-icon name="store" class="size-4 shrink-0" />
                            {{ $activeStoreName ? "Parduotuvė: {$activeStoreName}" : 'Parduotuvės' }}{{ count($selectedStores) > 0 ? ' (' . count($selectedStores) . ')' : '' }}
                        </button>
                    @endif
                    @if ($showCategoryFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'category' ? null : 'category')" class="inline-flex h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] {{ $activeCategoryName ? 'font-bold text-gray-900' : 'text-gray-900' }} hover:bg-[#dedede]">
                            <x-app-icon name="layout-grid" class="size-4 shrink-0" />
                            {{ $activeCategoryName ? "Kategorija: {$activeCategoryName}" : 'Kategorijos' }}{{ count($selectedCategories) > 0 ? ' (' . count($selectedCategories) . ')' : '' }}
                        </button>
                    @endif
                    <div class="hidden min-w-0 flex-wrap gap-2 sm:flex">
                        @foreach ($selectedStores as $slug)
                            @php $selectedStoreData = collect($allStores)->firstWhere('slug', $slug); @endphp
                            <button type="button" @click="window.trackGaEvent && window.trackGaEvent('filter_apply', { filter_type: 'store', filter_value: '{{ $slug }}', action: 'toggle_off' })" wire:click="toggleStore('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                                <x-store-logo :slug="$slug" :name="$selectedStoreData['name'] ?? $slug" size="xs" />
                                {{ $selectedStoreData['name'] ?? $slug }}
                                <x-app-icon name="x" class="size-4" />
                            </button>
                        @endforeach
                        @foreach ($selectedCategories as $slug)
                            <button type="button" @click="window.trackGaEvent && window.trackGaEvent('filter_apply', { filter_type: 'category', filter_value: '{{ $slug }}', action: 'toggle_off' })" wire:click="toggleCategory('{{ $slug }}')" class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700">
                                {{ collect($allCategories)->firstWhere('slug', $slug)['name'] ?? $slug }}
                                <x-app-icon name="x" class="size-4" />
                            </button>
                        @endforeach
                        @if ($activeCount > 0)
                            <button type="button" wire:click="clearFilters" class="inline-flex cursor-pointer items-center gap-1.5 px-2 py-2 text-sm font-bold text-[#c0392b] hover:opacity-80">
                                <x-app-icon name="x" class="size-4" />
                                Išvalyti viską
                            </button>
                        @endif
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

            {{-- Centered modal on desktop (not a right-docked drawer) — per
                 explicit product decision. Mobile keeps the bottom-sheet feel
                 (items-end), desktop centers it (sm:items-center) with margin
                 on every side and rounded corners all around. Up to two
                 independent instances now (store + category can both be
                 available at once), sharing the exact same shell — only one
                 is ever open at a time ($wire.openPanel). --}}
            @if ($showStoreFilter)
                <div x-show="$wire.openPanel === 'store'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @click.self="$wire.openPanel = null">
                    <div class="max-h-[82vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 sm:max-h-[80vh] sm:w-full sm:max-w-[420px] sm:rounded-2xl">
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'stores',
                            'items' => $allStores,
                            'activeSlug' => $activeStoreSlug,
                            'hrefFor' => $storeHrefFor,
                            'rowClass' => $rowClass,
                        ])
                    </div>
                </div>
            @endif
            @if ($showCategoryFilter)
                <div x-show="$wire.openPanel === 'category'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @click.self="$wire.openPanel = null">
                    <div class="max-h-[82vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 sm:max-h-[80vh] sm:w-full sm:max-w-[420px] sm:rounded-2xl">
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'categories',
                            'items' => $allCategories,
                            'activeSlug' => $activeCategorySlug,
                            'hrefFor' => $categoryHrefFor,
                            'allHref' => $categoryAllHref,
                            'rowClass' => $rowClass,
                        ])
                    </div>
                </div>
            @endif

            <div class="mb-3 flex flex-wrap gap-2 sm:hidden">
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
                @if ($activeCount > 0)
                    <button type="button" wire:click="clearFilters" class="inline-flex cursor-pointer items-center gap-1.5 px-2 py-2 text-sm font-bold text-[#c0392b] hover:opacity-80">
                        <x-app-icon name="x" class="size-4" />
                        Išvalyti viską
                    </button>
                @endif
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
                @php
                    // Keyword pages' leading "cheapest per store" deals —
                    // folded into the grid itself (no separate section),
                    // only under the default popular sort, only on the
                    // grid's first page, and de-duplicated against $deals
                    // so the same discount never renders twice.
                    $showLeadingDeals = ! empty($leadingDeals)
                        && $order === 'popular'
                        && $page === 1
                        && $storeFilter === ''
                        && $categoryFilter === '';
                    if ($showLeadingDeals) {
                        $leadingIds = collect($leadingDeals)->pluck('id')->filter()->all();
                        $displayDeals = [
                            ...$leadingDeals,
                            ...array_values(array_filter($deals, fn ($deal) => ! in_array($deal['id'] ?? null, $leadingIds, true))),
                        ];
                    } else {
                        $displayDeals = $deals;
                    }
                @endphp
                <div
                    class="flex w-full flex-col"
                    x-data="listingLoadMore(@js([
                        'endpoint' => route('akcijos.deals.partial'),
                        'page' => $current,
                        'lastPage' => $last,
                        'shown' => count($displayDeals),
                        'total' => (int) ($pagination['total'] ?? count($displayDeals)),
                        'wireId' => $this->getId(),
                        'gaSource' => 'listing',
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
                    <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 xl:grid-cols-5" x-ref="grid">
                        @foreach ($displayDeals as $deal)
                            <x-deal-card :deal="$deal" class="h-full" :context-store-slug="$contextStoreSlug" />
                        @endforeach
                    </div>

                    @if ($current < $last)
                        {{-- Infinite scroll: this empty div is the
                             IntersectionObserver target (see listingLoadMore
                             in app.js) — no click needed, loadMore() fires
                             automatically as it nears the viewport. The
                             spinner only shows while a fetch is in flight. --}}
                        <div x-ref="sentinel" class="mt-6 flex justify-center py-4">
                            <span x-show="loading" x-cloak class="inline-flex items-center gap-2 text-sm font-medium text-gray-500">
                                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9" /></svg>
                                Kraunama...
                            </span>
                            {{-- Real user pagination is the auto-loading sentinel above;
                                 this plain href exists only so a crawler following actual
                                 links (not just the sitemap) can reach page
                                 {{ $current + 1 }} onward. Visually hidden, not part of
                                 the tab order. --}}
                            <a href="?page={{ $current + 1 }}" rel="next" class="sr-only" tabindex="-1" aria-hidden="true">Kitas puslapis</a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
