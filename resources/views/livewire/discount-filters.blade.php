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
            {{-- items-start (not items-center): on mobile the filter buttons
                 wrap into their own 2-line stack (see the comment below),
                 making this row taller than the sort button — items-center
                 was floating the sort button vertically mid-way between the
                 two stacked filter rows, disconnected from either. Aligning
                 to the top instead keeps it visually paired with the first
                 filter row, and sm:items-center below restores centering
                 once the filters go back to one line at sm+. --}}
            <div class="mb-4 flex w-full flex-wrap items-start justify-between gap-x-2 gap-y-1 rounded-2xl bg-[#e8e8e8] px-4 py-1.5 min-h-[40px] sm:flex-nowrap sm:items-center sm:py-0 sm:mb-[17px]" x-data="{ sortOpen: false }" @click.outside="sortOpen = false">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    @php
                        // The button itself must show whichever store/
                        // category the URL already fixes (e.g. "Parduotuvė:
                        // Lidl") — matching the old <x-store-nav-tabs> pill's
                        // "Kategorija: X" behavior — not just a bare facet
                        // name that only reveals the selection once opened.
                        $activeStoreName = $activeStoreSlug ? (collect($allStores)->firstWhere('slug', $activeStoreSlug)['name'] ?? null) : null;
                        $activeCategoryName = $activeCategorySlug ? (collect($allCategories)->firstWhere('slug', $activeCategorySlug)['name'] ?? null) : null;

                        // Collapsed mobile "Filtrai" pill must still say what's
                        // active instead of a bare "Filtrai" label — same
                        // reasoning as the desktop pills above, condensed into
                        // one string. Prefers the URL-fixed facet name (same
                        // source as the desktop pills); falls back to a count
                        // when the selection instead came from the checkbox
                        // list (multi-select, no single name to show).
                        $mobileFilterParts = [];
                        if ($activeStoreName) {
                            $mobileFilterParts[] = $activeStoreName;
                        } elseif (count($selectedStores) === 1) {
                            $mobileFilterParts[] = collect($allStores)->firstWhere('slug', $selectedStores[0])['name'] ?? $selectedStores[0];
                        } elseif (count($selectedStores) > 1) {
                            $mobileFilterParts[] = count($selectedStores) . ' parduotuvės';
                        }
                        if ($activeCategoryName) {
                            $mobileFilterParts[] = $activeCategoryName;
                        } elseif (count($selectedCategories) === 1) {
                            $mobileFilterParts[] = collect($allCategories)->firstWhere('slug', $selectedCategories[0])['name'] ?? $selectedCategories[0];
                        } elseif (count($selectedCategories) > 1) {
                            $mobileFilterParts[] = count($selectedCategories) . ' kategorijos';
                        }
                        $mobileFilterLabel = count($mobileFilterParts) > 0 ? implode(', ', $mobileFilterParts) : 'Filtrai';
                    @endphp
                    {{-- Desktop (sm+): each facet gets its own inline pill,
                         sized to its own content — no wrapping problem here,
                         there's room. --}}
                    @if ($showStoreFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'store' ? null : 'store')" class="hidden h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] {{ $activeStoreName ? 'font-bold text-gray-900' : 'text-gray-900' }} hover:bg-[#dedede] sm:inline-flex">
                            <x-app-icon name="store" class="size-4 shrink-0" />
                            <span class="truncate">{{ $activeStoreName ? "Parduotuvė: {$activeStoreName}" : 'Parduotuvės' }}{{ count($selectedStores) > 0 ? ' (' . count($selectedStores) . ')' : '' }}</span>
                            <x-app-icon name="chevron-down" class="size-3.5 shrink-0 text-gray-500 transition-transform" x-bind:class="$wire.openPanel === 'store' ? 'rotate-180' : ''" />
                        </button>
                    @endif
                    @if ($showCategoryFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'category' ? null : 'category')" class="hidden h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] {{ $activeCategoryName ? 'font-bold text-gray-900' : 'text-gray-900' }} hover:bg-[#dedede] sm:inline-flex">
                            <x-app-icon name="layout-grid" class="size-4 shrink-0" />
                            <span class="truncate">{{ $activeCategoryName ? "Kategorija: {$activeCategoryName}" : 'Kategorijos' }}{{ count($selectedCategories) > 0 ? ' (' . count($selectedCategories) . ')' : '' }}</span>
                            <x-app-icon name="chevron-down" class="size-3.5 shrink-0 text-gray-500 transition-transform" x-bind:class="$wire.openPanel === 'category' ? 'rotate-180' : ''" />
                        </button>
                    @endif
                    {{-- Mobile: a single "Filtrai" pill combining both facets
                         into one sheet instead of two full-width buttons that
                         wrap into their own 2-line stack and collide with the
                         sort button — always stays on one row (min-w-0 +
                         truncate lets the label itself shrink/ellipsize
                         instead of the row wrapping) regardless of how long
                         the active store/category name is. Shows
                         $mobileFilterLabel (built above) instead of a bare
                         "Filtrai" so the active selection is still visible
                         when the sheet is collapsed. --}}
                    @if ($showStoreFilter || $showCategoryFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'combined' ? null : 'combined')" class="inline-flex h-full min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[16px] {{ $activeCount > 0 ? 'font-bold text-gray-900' : 'text-gray-900' }} hover:bg-[#dedede] sm:hidden">
                            <x-app-icon name="filter" class="size-4 shrink-0" />
                            <span class="truncate">{{ $mobileFilterLabel }}</span>
                            @if ($activeCount > 0)
                                <span class="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-green text-[11px] font-bold text-white">{{ $activeCount }}</span>
                            @endif
                            <x-app-icon name="chevron-down" class="size-3.5 shrink-0 text-gray-500 transition-transform" x-bind:class="$wire.openPanel === 'combined' ? 'rotate-180' : ''" />
                        </button>
                    @endif
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
                        <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Parduotuvės</div>
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
                        <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Kategorija</div>
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
            {{-- Mobile-only "Filtrai" sheet — both facets stacked, each under
                 its own label so it's still clear which section is which. --}}
            @if ($showStoreFilter || $showCategoryFilter)
                <div x-show="$wire.openPanel === 'combined'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:hidden" @click.self="$wire.openPanel = null">
                    <div class="flex max-h-[82vh] w-full flex-col gap-4 overflow-y-auto rounded-t-2xl bg-white p-4">
                        @if ($showStoreFilter)
                            <section>
                                <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Parduotuvė</div>
                                @include('components.partials.discount-filter-sections', [
                                    'facet' => 'stores',
                                    'items' => $allStores,
                                    'activeSlug' => $activeStoreSlug,
                                    'hrefFor' => $storeHrefFor,
                                    'rowClass' => $rowClass,
                                ])
                            </section>
                        @endif
                        @if ($showCategoryFilter)
                            <section>
                                <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Kategorija</div>
                                @include('components.partials.discount-filter-sections', [
                                    'facet' => 'categories',
                                    'items' => $allCategories,
                                    'activeSlug' => $activeCategorySlug,
                                    'hrefFor' => $categoryHrefFor,
                                    'allHref' => $categoryAllHref,
                                    'rowClass' => $rowClass,
                                ])
                            </section>
                        @endif
                    </div>
                </div>
            @endif

        @endif

        @php $current = (int) ($pagination['current_page'] ?? 1); $last = (int) ($pagination['last_page'] ?? 1); @endphp

        <div wire:loading.class="opacity-50" wire:target="toggleStore,toggleCategory,setOrder,toggleCard,togglePlus" class="flex w-full min-w-0 flex-col transition-opacity">
            @if ($showCarousels)
                {{-- wire:ignore keeps carousel HTML across sort updates; carouselHtml
                     is cleared in dehydrate() so the deal payload stays out of
                     wire:snapshot after the first response. --}}
                <div wire:ignore class="flex flex-col gap-8 sm:gap-14">
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
                                class="w-[calc(50%-0.25rem)] sm:w-[calc(33.333%-0.5rem)] lg:w-[calc(25%-0.5625rem)] xl:w-[calc(20%-0.6rem)]"
                            />
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
