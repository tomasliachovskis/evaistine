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
@if ($facet === 'categories')
    <section class="flex flex-col gap-0.5">
        @if ($allHref ?? null)
            <a href="{{ $allHref }}" class="{{ $rowClass($activeSlug === null) }}">
                <x-app-icon name="layout-grid" class="size-5 shrink-0 opacity-70" />
                <span class="min-w-0 flex-1 truncate">Visos</span>
            </a>
        @endif
        @foreach ($items as $category)
            <a href="{{ $hrefFor($category['slug']) }}" class="{{ $rowClass($category['slug'] === $activeSlug) }}">
                <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="size-5 shrink-0 opacity-70" onerror="this.style.display='none'">
                <span class="min-w-0 flex-1 truncate">{{ $category['name'] }}</span>
                @if (isset($category['offers_count']))
                    <span class="shrink-0 text-sm font-normal text-gray-400">{{ number_format($category['offers_count'], 0, ',', ' ') }}</span>
                @endif
            </a>
        @endforeach
    </section>
@else
    <section class="flex flex-col gap-0.5">
        @foreach ($items as $store)
            <a href="{{ $hrefFor($store['slug']) }}" class="{{ $rowClass($store['slug'] === $activeSlug) }}">
                <x-store-logo :slug="$store['slug']" :name="$store['name']" size="xs" />
                <span class="min-w-0 flex-1 truncate">{{ $store['name'] }}</span>
                @if (isset($store['offers_count']))
                    <span class="shrink-0 text-sm font-normal text-gray-400">{{ number_format($store['offers_count'], 0, ',', ' ') }}</span>
                @endif
            </a>
        @endforeach
    </section>
@endif
