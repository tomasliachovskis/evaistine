@props(['storeSlug', 'storeName', 'totalOffers', 'showLeafletsLink' => false, 'leafletsCount' => null, 'compact' => false, 'showsDiscountsPage' => true])

@php
    // "leidinys" -> "leidiniai" is a stem swap (drop "ys", add "iai"), not a
    // plain suffix append — naively concatenating 'iai' onto the singular
    // produced "leidinysiai" instead of "leidiniai" for every non-iki store.
    // Iki's own leaflets are called "leidynys/leidyniai" instead.
    $leafletNoun = $storeSlug === 'iki' ? 'leidynys' : 'leidinys';
    $leafletNounPlural = substr($leafletNoun, 0, -2) . 'iai';

    $wrapperClass = $compact
        ? 'flex w-full flex-col gap-1'
        : 'sticky top-[calc(var(--header-h)+env(safe-area-inset-top,0px))] z-[60] mb-4 flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-2xl border border-gray-300 bg-[#e8e8e8] px-4 py-1 min-h-14 sm:mb-[17px] sm:px-[20px]';
    // Same links either way — only the wrapper (sticky pill bar vs. a plain
    // compact list) and each link's own classes differ. The sticky bar is
    // sized for docking full-width under the header on a listing page; the
    // compact variant is for a narrow side rail (leaflets/show.blade.php),
    // where that same shape wrapped into oversized bordered rows instead of
    // reading as a normal link list (confirmed live 2026-09-18).
    $linkClass = $compact
        ? 'flex min-h-12 items-center gap-2 rounded-xl px-3 text-base font-semibold text-gray-900 transition-colors hover:bg-green-soft'
        : 'inline-flex min-h-12 shrink-0 items-center gap-2 rounded-xl px-3 text-base font-semibold text-gray-900 hover:bg-[#dedede]';
    $iconSizeClass = 'size-5 shrink-0';
@endphp

{{-- Same pill-bar language as discount-filters.blade.php's Parduotuvė/
     Kategorija/Leidiniai bar — plain link pills only here (no dropdowns,
     no facets to pick), so no mobile/desktop split is needed. $compact
     swaps that sticky bar for a plain vertical link list instead, for the
     narrow side-rail context. --}}
<div @if (!$compact) data-sticky-filter-bar @endif class="{{ $wrapperClass }}">
    <a href="/leidiniai" data-ga-event="filter_select" data-ga-item="leidiniai:all" data-ga-source="leaflet_quick_links" class="{{ $linkClass }}">
        <x-app-icon name="layout-grid" class="{{ $iconSizeClass }}" />
        <span class="truncate">Visi leidiniai</span>
    </a>
    @if ($showLeafletsLink)
        <a href="/leidinys/{{ $storeSlug }}" data-ga-event="filter_select" data-ga-item="leidiniai:{{ $storeSlug }}" data-ga-source="leaflet_quick_links" class="{{ $linkClass }}">
            <x-app-icon name="bookmark" class="{{ $iconSizeClass }}" />
            <span class="truncate">{{ $storeName }} {{ $leafletNounPlural }}{{ $leafletsCount !== null ? ' (' . number_format($leafletsCount, 0, ',', ' ') . ')' : '' }}</span>
        </a>
    @endif
    {{-- Leaflet-only stores have no offers page (/akcijos/{slug} 301s back here). --}}
    @if ($showsDiscountsPage)
        <a href="/akcijos/{{ $storeSlug }}" data-ga-event="filter_select" data-ga-item="akcijos:{{ $storeSlug }}" data-ga-source="leaflet_quick_links" class="{{ $linkClass }}">
            <x-app-icon name="tag" class="{{ $iconSizeClass }}" />
            <span class="truncate">{{ $storeName }} akcijos ({{ number_format($totalOffers, 0, ',', ' ') }})</span>
        </a>
    @endif
</div>
