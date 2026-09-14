{{-- Persistent category/store chip grid — used inside discount-filters'
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

     Chip-wrap grid (not a vertical list) — per explicit product decision,
     matches the wrapping-pill pattern used elsewhere on these pages
     (keyword-chips-row) instead of a tall single-column list. --}}
@php
    $chipClass = fn (bool $active) => 'inline-flex items-center gap-1.5 rounded-full border px-3 py-2 text-sm font-semibold transition-colors '
        . ($active ? 'border-green bg-green text-white' : 'border-gray-200 text-gray-700 hover:border-green/40');
@endphp
@if ($facet === 'categories')
    <section>
        <div class="flex flex-wrap gap-2">
            @if ($allHref ?? null)
                <a href="{{ $allHref }}" class="{{ $chipClass($activeSlug === null) }}">
                    <x-app-icon name="layout-grid" class="size-4 shrink-0 opacity-80" />
                    <span>Visos</span>
                </a>
            @endif
            @foreach ($items as $category)
                <a href="{{ $hrefFor($category['slug']) }}" class="{{ $chipClass($category['slug'] === $activeSlug) }}">
                    <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="size-4 shrink-0 opacity-80" onerror="this.style.display='none'">
                    <span>{{ $category['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@else
    <section>
        <div class="flex flex-wrap gap-2">
            @foreach ($items as $store)
                <a href="{{ $hrefFor($store['slug']) }}" class="{{ $chipClass($store['slug'] === $activeSlug) }}">
                    <x-store-logo :slug="$store['slug']" :name="$store['name']" size="xs" />
                    <span>{{ $store['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
