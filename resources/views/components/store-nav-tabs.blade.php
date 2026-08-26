@props(['storeSlug', 'leafletsCount', 'totalOffers', 'categories' => [], 'ariaLabel'])

{{-- Ported from store-listing-header.tsx's nav row — plain links to the store's
     leaflet hub / full discount listing / top categories, not tabs that swap
     content in place. Always a single scrollable row (never wraps), on mobile
     and desktop alike. --}}
<nav aria-label="{{ $ariaLabel }}" class="scroll-cards-x flex flex-nowrap items-center gap-2.5">
    <a href="/leidinys/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white">
        Leidiniai
        <x-count-pill :count="$leafletsCount" color="white" />
    </a>
    <a href="/akcijos/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green px-4 py-2 text-base font-bold text-green transition-colors hover:bg-green/5">
        Akcijos
        <x-count-pill :count="$totalOffers" color="green" />
    </a>
    @if (!empty($categories))
        <span class="mx-0.5 h-6 w-px shrink-0 bg-gray-300" aria-hidden="true"></span>
        @foreach (array_slice($categories, 0, 5) as $category)
            <a href="{{ $category['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-base font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                {{ $category['name'] }}
                <x-count-pill :count="$category['offers_count'] ?? 0" color="gray" />
            </a>
        @endforeach
    @endif
</nav>
