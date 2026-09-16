{{-- Persistent category/store list — used inside discount-filters'
     centered modal, once per open facet ($facet: 'categories' | 'stores').
     Explicit props (not ambient $sidebarMode/$primarySlug/$secondarySlug)
     so the same partial renders either facet independently on pages that
     now show both filters at once (category-only, store+category) — an
     $hrefFor closure avoids re-deriving URL shape from ambient variables
     whose meaning differs by page type (a bug found while unifying these:
     $primarySlug means a store on one page type and a category on another).

     Plain navigation links, not wire:click toggles — confirmed against
     production: selecting a row does a full page nav to the dedicated URL
     and highlights only that one row, it doesn't accumulate a multi-select
     query-string filter.

     Standard one-row-per-item list (not a chip-wrap grid) — explicit
     product decision, reversing this session's earlier chip-grid rewrite.
     $rowClass is the same row style closure the sort dropdown in
     discount-filters.blade.php already uses (ported from
     product-filter-controls.tsx's row constants) — reused here instead of
     a third bespoke row style. --}}
@php
    // Cap the always-visible list at 5 rows — on a store/category-heavy page
    // this list can run past 20 entries, which on a phone means a lot of
    // scrolling inside the sheet just to find the sort button below it. The
    // active slug (if any) is always pinned into the visible head, even if
    // it would otherwise fall past row 5, so re-opening the sheet never
    // hides the user's own current selection behind "Rodyti daugiau".
    $visibleLimit = 5;
    $headItems = collect($items)->slice(0, $visibleLimit)->values();
    $tailItems = collect($items)->slice($visibleLimit)->values();
    if ($activeSlug !== null && !$headItems->contains('slug', $activeSlug)) {
        $activeItem = $tailItems->firstWhere('slug', $activeSlug);
        if ($activeItem) {
            $tailItems = $tailItems->reject(fn ($item) => $item['slug'] === $activeSlug)->values();
            $headItems->push($activeItem);
        }
    }
@endphp
@if ($facet === 'categories')
    <section class="flex flex-col gap-0.5" x-data="{ expanded: {{ $tailItems->isEmpty() ? 'true' : 'false' }} }">
        @if ($allHref ?? null)
            <a href="{{ $allHref }}" class="{{ $rowClass($activeSlug === null) }}">
                <x-app-icon name="layout-grid" class="size-5 shrink-0 opacity-70" />
                <span class="min-w-0 flex-1 truncate">Visos kategorijos</span>
            </a>
        @endif
        @foreach ($headItems as $category)
            <a href="{{ $hrefFor($category['slug']) }}" class="{{ $rowClass($category['slug'] === $activeSlug) }}">
                <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="size-5 shrink-0 opacity-70" onerror="this.style.display='none'">
                <span class="min-w-0 flex-1 truncate text-lg">{{ $category['name'] }}</span>
                @if (isset($category['offers_count']))
                    <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($category['offers_count'], 0, ',', ' ') }}</span>
                @endif
            </a>
        @endforeach
        <template x-if="expanded">
            @foreach ($tailItems as $category)
                <a href="{{ $hrefFor($category['slug']) }}" class="{{ $rowClass($category['slug'] === $activeSlug) }}">
                    <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="size-5 shrink-0 opacity-70" onerror="this.style.display='none'">
                    <span class="min-w-0 flex-1 truncate text-lg">{{ $category['name'] }}</span>
                    @if (isset($category['offers_count']))
                        <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($category['offers_count'], 0, ',', ' ') }}</span>
                    @endif
                </a>
            @endforeach
        </template>
        @if ($tailItems->isNotEmpty())
            <button type="button" @click="expanded = !expanded" class="flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-[18px] font-semibold text-green text-left transition-colors hover:bg-[#f2f2f2]">
                <x-app-icon name="chevron-down" class="size-5 shrink-0 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                <span x-text="expanded ? 'Rodyti mažiau' : 'Rodyti daugiau ({{ $tailItems->count() }})'"></span>
            </button>
        @endif
    </section>
@else
    <section class="flex flex-col gap-0.5" x-data="{ expanded: {{ $tailItems->isEmpty() ? 'true' : 'false' }} }">
        @if ($allHref ?? null)
            <a href="{{ $allHref }}" class="{{ $rowClass($activeSlug === null) }}">
                <x-app-icon name="store" class="size-5 shrink-0 opacity-70" />
                <span class="min-w-0 flex-1 truncate">Visos parduotuvės</span>
            </a>
        @endif
        @foreach ($headItems as $store)
            <a href="{{ $hrefFor($store['slug']) }}" class="{{ $rowClass($store['slug'] === $activeSlug) }}">
                <span class="min-w-0 flex-1 truncate text-lg">{{ $store['name'] }}</span>
                @if (isset($store['offers_count']))
                    <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($store['offers_count'], 0, ',', ' ') }}</span>
                @endif
            </a>
        @endforeach
        <template x-if="expanded">
            @foreach ($tailItems as $store)
                <a href="{{ $hrefFor($store['slug']) }}" class="{{ $rowClass($store['slug'] === $activeSlug) }}">
                    <span class="min-w-0 flex-1 truncate text-lg">{{ $store['name'] }}</span>
                    @if (isset($store['offers_count']))
                        <span class="shrink-0 text-base font-normal text-gray-400">{{ number_format($store['offers_count'], 0, ',', ' ') }}</span>
                    @endif
                </a>
            @endforeach
        </template>
        @if ($tailItems->isNotEmpty())
            <button type="button" @click="expanded = !expanded" class="flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[48px] text-[18px] font-semibold text-green text-left transition-colors hover:bg-[#f2f2f2]">
                <x-app-icon name="chevron-down" class="size-5 shrink-0 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                <span x-text="expanded ? 'Rodyti mažiau' : 'Rodyti daugiau ({{ $tailItems->count() }})'"></span>
            </button>
        @endif
    </section>
@endif
