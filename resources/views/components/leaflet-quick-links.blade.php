@props(['storeSlug', 'storeName', 'totalOffers', 'showLeafletsLink' => false, 'leafletsCount' => null])

@php
    // "leidinys" -> "leidiniai" is a stem swap (drop "ys", add "iai"), not a
    // plain suffix append — naively concatenating 'iai' onto the singular
    // produced "leidinysiai" instead of "leidiniai" for every non-iki store.
    // Iki's own leaflets are called "leidynys/leidyniai" instead.
    $leafletNoun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';
    $leafletNounPlural = substr($leafletNoun, 0, -2) . 'iai';
@endphp

{{-- Same sticky pill-bar language as discount-filters.blade.php's
     Parduotuvė/Kategorija/Leidiniai bar — plain link pills only here (no
     dropdowns, no facets to pick), so no mobile/desktop split is needed. --}}
<div data-sticky-filter-bar class="sticky top-[calc(3.5rem+env(safe-area-inset-top,0px))] z-[60] mb-4 flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-4 py-3 min-h-[60px] sm:min-h-[52px] sm:mb-[17px] sm:px-[20px]">
    <a href="/leidiniai" data-ga-event="filter_select" data-ga-item="leidiniai:all" data-ga-source="leaflet_quick_links" class="inline-flex h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
        <x-app-icon name="layout-grid" class="size-5 shrink-0" />
        <span class="truncate">Visi leidiniai</span>
    </a>
    @if ($showLeafletsLink)
        <a href="/leidinys/{{ $storeSlug }}" data-ga-event="filter_select" data-ga-item="leidiniai:{{ $storeSlug }}" data-ga-source="leaflet_quick_links" class="inline-flex h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
            <x-app-icon name="bookmark" class="size-5 shrink-0" />
            <span class="truncate">{{ $storeName }} {{ $leafletNounPlural }}{{ $leafletsCount !== null ? ' (' . number_format($leafletsCount, 0, ',', ' ') . ')' : '' }}</span>
        </a>
    @endif
    <a href="/akcijos/{{ $storeSlug }}" data-ga-event="filter_select" data-ga-item="akcijos:{{ $storeSlug }}" data-ga-source="leaflet_quick_links" class="inline-flex h-full shrink-0 items-center gap-2 rounded-2xl px-2 text-[18px] font-semibold text-gray-900 hover:bg-[#dedede]">
        <x-app-icon name="tag" class="size-5 shrink-0" />
        <span class="truncate">{{ $storeName }} akcijos ({{ number_format($totalOffers, 0, ',', ' ') }})</span>
    </a>
</div>
