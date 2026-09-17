@php
    // Row styling below reuses the exact constants from
    // discount/src/components/common/product-filter-controls.tsx:
    // FILTER_ROW_HEIGHT_CLASS='min-h-[40px]' FILTER_ROW_TEXT_CLASS='text-[16px] leading-snug'
    // FILTER_LIST_GAP_CLASS='gap-0.5', and its active/hover row colors.
    $rowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-[18px] leading-snug text-left transition-colors '
        . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
    $selectedStores = array_values(array_filter(explode(',', $storeFilter)));
    $selectedCategories = array_values(array_filter(explode(',', $categoryFilter)));
    // $activeCount is (re)computed further down, once $activeStoreName/
    // $activeCategoryName exist, so it also counts a URL-fixed facet, not
    // just a checkbox pick — see the comment there.
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
    //   (category-only or store+category page); on a keyword page there is
    //   no /akcijos/{store}/{keyword} route (that path only resolves
    //   store+category), so a plain /akcijos/{store} nav silently dropped
    //   the keyword filter entirely (confirmed live bug, 2026-09-15) —
    //   KeywordPageController::show() already reads a ?store= query param
    //   (same one toggleStore()'s wire re-fetch uses), so link there
    //   instead, staying on the keyword page with the store applied.
    $categoryHrefFor = fn (string $slug) => ($activeStoreSlug !== null ? '/akcijos/' . $activeStoreSlug . '/' . $slug : '/akcijos/' . $slug) . $orderSuffix;
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
        : null;

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
<div class="mt-6 flex w-full flex-col max-sm:gap-1">
        @if ($showFilters)
            {{-- items-center at every breakpoint: mobile only ever shows the
                 single combined "Filtrai" pill here (the separate store/
                 category pills are sm:inline-flex, hidden below sm), so
                 there's no multi-line stack to protect against anymore —
                 items-start left that one pill hugging the bar's top edge
                 with dead space below it instead of vertically centered. --}}
            {{-- Sticky so the store/category/sort controls stay reachable
                 while scrolling a long grid instead of scrolling away with
                 no way back short of scrolling all the way to the top. `top`
                 matches the fixed site header's own height exactly
                 (layouts/app.blade.php's <main> padding-top) so the bar sits
                 flush under it rather than overlapping or leaving a gap;
                 z-30 keeps it below the header (z-50) and any open modal
                 (z-50/z-[9999]) but above normal page content. --}}
            {{-- Deliberately a single static shape (no full-bleed-when-stuck
                 class swap, no IntersectionObserver) — an earlier version
                 dynamically swapped width/rounding once "stuck" via a
                 sentinel + IntersectionObserver, which combined with native
                 CSS sticky recalculation and the header's own independent
                 scroll-driven hide/show caused a real, hard-to-pin browser
                 rendering bug (the fixed header's top row visually vanishing
                 while its own layout/DOM position measured correctly —
                 confirmed live 2026-09-16). Fewer scroll-reactive moving
                 parts fighting each other, at the cost of the full-bleed
                 nicety on mobile. --}}
            {{-- top offset is a plain constant matching the header's top
                 row height (3.5rem) at every breakpoint — the top row never
                 hides, so this never needs to change. At lg+ that means the
                 bar docks in the same spot the nav-links row occupies when
                 visible; that row hides on scroll (site-header.blade.php)
                 and the bar is simply already sitting where it left off,
                 no coordination between the two needed. --}}
            <div
                {{-- z-[60], above the header's own z-50: the bar docks at
                     top:3.5rem unconditionally (see comment below), which
                     is exactly where the nav-links row sits while it's
                     still visible (before its own scroll-triggered hide
                     catches up) — without a higher z-index the bar was
                     rendering BEHIND that still-visible row and disappearing
                     outright, not just briefly overlapping it. --}}
                class="sticky top-[calc(3.5rem+env(safe-area-inset-top,0px))] z-[60] mb-4 flex w-full flex-wrap items-center justify-between gap-x-2 gap-y-1 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-4 py-3 min-h-[60px] sm:flex-nowrap sm:py-0 sm:min-h-[52px] sm:mb-[17px] sm:px-[20px]"
                x-data="{ sortOpen: false }"
                @click.outside="sortOpen = false"
            >
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
                    @php
                        // A facet counts as "active" whether it's fixed by
                        // the URL ($activeStoreName/$activeCategoryName —
                        // one implied selection) or picked via the checkbox
                        // multi-select ($selectedStores/$selectedCategories)
                        // — either way, something is selected, so the badge
                        // should show. Previously only the checkbox path
                        // showed a badge, so a store_category/category page's
                        // URL-fixed facet (already named in the label, e.g.
                        // "Kategorija: Bakalėja") never got one at all.
                        $storeBadgeCount = $activeStoreName ? 1 : count($selectedStores);
                        $categoryBadgeCount = $activeCategoryName ? 1 : count($selectedCategories);
                        // Mobile's combined pill badge — same "URL-fixed
                        // counts too" fix as the desktop pills above, so it
                        // doesn't disagree with them on a store_category/
                        // category page (mobile's label already includes
                        // the URL-fixed name via $mobileFilterLabel below,
                        // but its badge previously only counted checkbox
                        // picks, e.g. showing no badge at all on a plain
                        // store_category page).
                        $activeCount = $storeBadgeCount + $categoryBadgeCount;
                    @endphp
                    @if ($showStoreFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'store' ? null : 'store')" class="hidden h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[18px] {{ $activeStoreName ? 'font-bold text-gray-900' : 'font-semibold text-gray-900' }} hover:bg-[#dedede] sm:inline-flex">
                            <x-app-icon name="store" class="size-5 shrink-0" />
                            <span class="truncate">{{ $activeStoreName ? "Parduotuvė: {$activeStoreName}" : 'Parduotuvės' }}</span>
                            @if ($storeBadgeCount > 0)
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green text-sm font-bold text-white">{{ $storeBadgeCount }}</span>
                            @endif
                            <x-app-icon name="chevron-down" class="size-4 shrink-0 text-gray-500 transition-transform" x-bind:class="$wire.openPanel === 'store' ? 'rotate-180' : ''" />
                        </button>
                    @endif
                    @if ($showCategoryFilter)
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'category' ? null : 'category')" class="hidden h-full shrink-0 cursor-pointer items-center gap-2 rounded-2xl px-2 text-[18px] {{ $activeCategoryName ? 'font-bold text-gray-900' : 'font-semibold text-gray-900' }} hover:bg-[#dedede] sm:inline-flex">
                            <x-app-icon name="layout-grid" class="size-5 shrink-0" />
                            <span class="truncate">{{ $activeCategoryName ? "Kategorija: {$activeCategoryName}" : 'Kategorijos' }}</span>
                            @if ($categoryBadgeCount > 0)
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green text-sm font-bold text-white">{{ $categoryBadgeCount }}</span>
                            @endif
                            <x-app-icon name="chevron-down" class="size-4 shrink-0 text-gray-500 transition-transform" x-bind:class="$wire.openPanel === 'category' ? 'rotate-180' : ''" />
                        </button>
                    @endif
                    {{-- Same pill style as the store/category buttons above,
                         but a plain nav link (no panel to open) — routes to
                         this store's own leaflet page when a store is fixed
                         (matches the "{store} savaitės leidiniai" shortcut
                         already in the mobile/store panels), otherwise the
                         general leaflets hub. --}}
                    @php
                        $leafletsCount = $activeStoreSlug !== null
                            ? \App\Models\Store::where('slug', $activeStoreSlug)->first()?->flyers()->ready()->count()
                            : null;
                    @endphp
                    <a href="{{ $activeStoreSlug !== null ? '/leidinys/' . $activeStoreSlug : '/leidiniai' }}" class="hidden h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede] sm:inline-flex">
                        <x-app-icon name="bookmark" class="size-5 shrink-0" />
                        <span class="truncate">{{ $activeStoreName ? "{$activeStoreName} leidiniai" : 'Leidiniai' }}{{ $leafletsCount ? ' (' . $leafletsCount . ')' : '' }}</span>
                    </a>
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
                        {{-- flex-1 only when there's a sort button to its
                             right to balance against — on store pages
                             ($showSort false, no sort button at all), this
                             is the bar's only mobile pill, and flex-1 there
                             left the label hugging the left edge with a
                             huge dead gap on the right instead of a normal
                             evenly-padded pill. --}}
                        <button type="button" @click="$wire.openPanel = ($wire.openPanel === 'combined' ? null : 'combined')" class="inline-flex h-full min-w-0 {{ $showSort ? 'flex-1' : 'shrink-0' }} cursor-pointer items-center gap-2 rounded-2xl px-2 text-[19px] {{ $activeCount > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-900' }} hover:bg-[#dedede] sm:hidden">
                            <x-app-icon name="filter" class="size-6 shrink-0" />
                            <span class="truncate">{{ $mobileFilterLabel }}</span>
                            @if ($activeCount > 0)
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green text-sm font-bold text-white">{{ $activeCount }}</span>
                            @endif
                            <x-app-icon name="chevron-down" class="size-5 shrink-0 text-gray-500 transition-transform sm:size-4" x-bind:class="$wire.openPanel === 'combined' ? 'rotate-180' : ''" />
                        </button>
                    @endif
                </div>
                @if ($showSort)
                    <div class="relative shrink-0">
                        <button type="button" @click="sortOpen = !sortOpen" class="inline-flex h-full cursor-pointer items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]" aria-haspopup="listbox" :aria-expanded="sortOpen">
                            <x-app-icon name="arrow-down-up" class="size-6 shrink-0 sm:size-5" />
                            <span class="hidden max-w-[140px] truncate sm:inline">{{ $orderOptions[$order] }}</span>
                            <x-app-icon name="chevron-down" class="size-6 shrink-0 opacity-70 sm:size-5" />
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
                @endif
            </div>

            {{-- Centered modal on desktop (not a right-docked drawer) — per
                 explicit product decision. Mobile keeps the bottom-sheet feel
                 (items-end), desktop centers it (sm:items-center) with margin
                 on every side and rounded corners all around. Up to two
                 independent instances now (store + category can both be
                 available at once), sharing the exact same shell — only one
                 is ever open at a time ($wire.openPanel). --}}
            @if ($showStoreFilter)
                <div x-show="$wire.openPanel === 'store'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @click.self="$wire.openPanel = null">
                    <div class="max-h-[82vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 sm:max-h-[80vh] sm:w-full sm:max-w-[420px] sm:rounded-2xl">
                        <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Parduotuvės</div>
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'stores',
                            'items' => $allStores,
                            'activeSlug' => $activeStoreSlug,
                            'hrefFor' => $storeHrefFor,
                            'allHref' => $storeAllHref,
                            'rowClass' => $rowClass,
                        ])
                        {{-- Only the store+category combo lacks any link to
                             this store's leaflets — <x-store-nav-tabs> (with
                             its own Leidiniai tab) only ever renders on the
                             plain store page. A distinct bordered row, not
                             another $rowClass list item — it's a navigation
                             shortcut, not a facet choice. --}}
                        @if ($activeStoreSlug !== null)
                            <a href="/leidinys/{{ $activeStoreSlug }}" class="mt-2 flex w-full items-center gap-2 rounded-2xl border border-gray-200 px-3 min-h-[40px] text-[16px] font-semibold text-green transition-colors hover:bg-gray-50">
                                <x-app-icon name="bookmark" class="size-5 shrink-0" />
                                <span class="min-w-0 flex-1 truncate">{{ $activeStoreName }} savaitės leidiniai</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
            @if ($showCategoryFilter)
                <div x-show="$wire.openPanel === 'category'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @click.self="$wire.openPanel = null">
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
            {{-- Mobile-only "Filtrai" modal — full-screen takeover with an
                 accordion (one section open at a time) and a sticky footer,
                 matching a reference filter-modal's structure 1:1 (our own
                 colors, not theirs). Store+category selection here is
                 STAGED: tapping a row just marks it selected locally
                 (Alpine state, not a real link) — nothing navigates until
                 "Filtruoti" is tapped, so both facets can be changed
                 together and applied as one redirect. Contrast with every
                 other facet list on this page (desktop pills' modals,
                 site-header's Kategorijos modal): those still navigate
                 immediately per tap, unchanged — this staged behavior is
                 unique to this one mobile modal. --}}
            @if ($showStoreFilter || $showCategoryFilter)
                @php $defaultOpenSection = $showStoreFilter ? 'store' : 'category'; @endphp
                <div
                    x-show="$wire.openPanel === 'combined'"
                    x-cloak
                    x-data="{
                        stagedStore: @js($activeStoreSlug),
                        stagedCategory: @js($activeCategorySlug),
                        openSection: @js($defaultOpenSection),
                        applyFilters() {
                            const mode = @js($mode), primarySlug = @js($primarySlug),
                                  orderSuffix = @js($orderSuffix), order = @js($order);
                            let url;
                            if (mode === 'keyword') {
                                const params = new URLSearchParams();
                                if (this.stagedStore) { params.set('store', this.stagedStore); }
                                if (order !== 'popular') { params.set('order', order); }
                                const query = params.toString();
                                url = '/akcijos/' + primarySlug + (query ? '?' + query : '');
                            } else if (this.stagedStore && this.stagedCategory) {
                                url = '/akcijos/' + this.stagedStore + '/' + this.stagedCategory + orderSuffix;
                            } else if (this.stagedStore) {
                                url = '/akcijos/' + this.stagedStore + orderSuffix;
                            } else if (this.stagedCategory) {
                                url = '/akcijos/' + this.stagedCategory + orderSuffix;
                            } else {
                                url = '/akcijos' + orderSuffix;
                            }
                            window.location.href = url;
                        },
                    }"
                    class="fixed inset-0 z-[70] flex flex-col bg-white sm:hidden"
                >
                    <div class="relative flex shrink-0 items-center justify-center border-b border-gray-200 px-4 py-3">
                        <h2 class="text-base font-bold text-gray-900">Filtrai</h2>
                        <button type="button" @click="$wire.openPanel = null" class="absolute right-4 top-1/2 -translate-y-1/2" aria-label="Uždaryti">
                            <x-app-icon name="x" class="size-5" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4">
                        @if ($showStoreFilter)
                            <section class="py-3">
                                <button type="button" @click="openSection = openSection === 'store' ? null : 'store'" class="flex w-full items-center justify-between text-left text-base font-bold text-gray-900">
                                    Parduotuvė
                                    <x-app-icon name="chevron-down" class="size-5 shrink-0 text-gray-500 transition-transform" x-bind:class="openSection === 'store' ? 'rotate-180' : ''" />
                                </button>
                                <div x-show="openSection === 'store'" class="mt-3">
                                    @include('components.partials.discount-filter-sections', [
                                        'facet' => 'stores',
                                        'items' => $allStores,
                                        'activeSlug' => $activeStoreSlug,
                                        'hrefFor' => $storeHrefFor,
                                        'allHref' => $storeAllHref,
                                        'rowClass' => $rowClass,
                                        'stagedModel' => 'stagedStore',
                                    ])
                                </div>
                            </section>
                        @endif
                        @if ($showCategoryFilter)
                            <section class="{{ $showStoreFilter ? 'border-t border-gray-200' : '' }} py-3">
                                <button type="button" @click="openSection = openSection === 'category' ? null : 'category'" class="flex w-full items-center justify-between text-left text-base font-bold text-gray-900">
                                    Kategorija
                                    <x-app-icon name="chevron-down" class="size-5 shrink-0 text-gray-500 transition-transform" x-bind:class="openSection === 'category' ? 'rotate-180' : ''" />
                                </button>
                                <div x-show="openSection === 'category'" class="mt-3">
                                    @include('components.partials.discount-filter-sections', [
                                        'facet' => 'categories',
                                        'items' => $allCategories,
                                        'activeSlug' => $activeCategorySlug,
                                        'hrefFor' => $categoryHrefFor,
                                        'allHref' => $categoryAllHref,
                                        'rowClass' => $rowClass,
                                        'stagedModel' => 'stagedCategory',
                                    ])
                                </div>
                            </section>
                        @endif
                        @if ($activeStoreSlug !== null)
                            <a href="/leidinys/{{ $activeStoreSlug }}" class="mb-3 mt-3 flex w-full items-center gap-2 rounded-2xl border border-gray-200 px-3 min-h-[40px] text-[16px] font-semibold text-green transition-colors hover:bg-gray-50">
                                <x-app-icon name="bookmark" class="size-5 shrink-0" />
                                <span class="min-w-0 flex-1 truncate">{{ $activeStoreName }} savaitės leidiniai</span>
                            </a>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2 border-t border-gray-200 px-4 py-3">
                        <button type="button" @click="stagedStore = null; stagedCategory = null" class="flex-1 rounded-2xl border border-gray-300 px-4 py-3 text-[16px] font-semibold text-gray-900">Išvalyti viską</button>
                        <button type="button" @click="applyFilters()" class="flex-1 rounded-2xl bg-green px-4 py-3 text-[16px] font-bold text-white">Filtruoti</button>
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
