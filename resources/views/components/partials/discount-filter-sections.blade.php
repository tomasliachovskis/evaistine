{{-- Persistent category/store nav — discount-filters' desktop sidebar AND
     mobile "Filtrai" sheet both include this. Verified against production
     (not the local .tsx checkout, which builds a different filter UI):
     the sidebar shows ONLY the facet not already fixed by the URL —
     categories when browsing a store, stores when browsing a category or
     keyword page — ProductFilterSidebar/FilterListItem's row styling.

     These are plain navigation links to the dedicated /akcijos/{store}/{category}
     URL, not wire:click filter toggles — confirmed against production by
     clicking: selecting a category on a store page (or a store on a category
     page) does a full page nav to the combined URL and highlights only that
     one row, it doesn't accumulate a multi-select query-string filter. --}}
@if ($sidebarMode === 'categories')
    <section>
        <div class="flex flex-col gap-0.5">
            @if ($allHref)
                <a href="{{ $allHref }}" class="{{ $rowClass(empty($selectedCategories) && $mode === 'discounts' && $secondarySlug === null) }}">Visos</a>
            @endif
            @foreach ($allCategories as $category)
                <a href="{{ $primarySlug !== null ? '/akcijos/'.$primarySlug.'/'.$category['slug'] : '/akcijos/'.$category['slug'] }}" class="{{ $rowClass($category['slug'] === $secondarySlug) }}">
                    <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="h-5 w-5 shrink-0 opacity-70" onerror="this.style.display='none'">
                    <span class="truncate">{{ $category['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@else
    <section>
        <div class="flex flex-col gap-0.5">
            @foreach ($allStores as $store)
                <a href="{{ $mode !== 'keyword' && $primarySlug !== null ? '/akcijos/'.$store['slug'].'/'.$primarySlug : '/akcijos/'.$store['slug'] }}" class="{{ $rowClass($store['slug'] === $primarySlug) }}">
                    {{-- Fixed-width icon column (unlike <x-store-logo>'s auto-width
                         tiers) so every row's name starts at the same x position
                         regardless of that store's own logo aspect ratio. --}}
                    <span class="flex h-5 w-6 shrink-0 items-center justify-center">
                        <img src="/assets/stores/{{ $store['slug'] }}.svg?v=2" alt="" class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">
                    </span>
                    <span class="truncate">{{ $store['name'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
