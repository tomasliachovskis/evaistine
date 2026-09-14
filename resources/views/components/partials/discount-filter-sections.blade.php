{{-- Persistent category/store row list — used inside discount-filters'
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
     query-string filter. --}}
@if ($facet === 'categories')
    <section>
        <div class="flex flex-col gap-0.5">
            @if ($allHref ?? null)
                <a href="{{ $allHref }}" class="{{ $rowClass($activeSlug === null) }}">Visos</a>
            @endif
            @foreach ($items as $category)
                <a href="{{ $hrefFor($category['slug']) }}" class="{{ $rowClass($category['slug'] === $activeSlug) }}">
                    <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="h-5 w-5 shrink-0 opacity-70" onerror="this.style.display='none'">
                    <span class="truncate">{{ $category['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@else
    <section>
        <div class="flex flex-col gap-0.5">
            @foreach ($items as $store)
                <a href="{{ $hrefFor($store['slug']) }}" class="{{ $rowClass($store['slug'] === $activeSlug) }}">
                    {{-- Fixed-width icon column (unlike <x-store-logo>'s auto-width
                         tiers) so every row's name starts at the same x position
                         regardless of that store's own logo aspect ratio. --}}
                    <span class="flex h-8 w-10 shrink-0 items-center justify-center">
                        <img src="/assets/stores/{{ $store['slug'] }}.svg?v=2" alt="" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">
                    </span>
                    <span class="truncate">{{ $store['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
