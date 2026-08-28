@props(['storeSlug', 'leafletsCount', 'totalOffers', 'categories' => [], 'featuredCategory' => null, 'allCategoriesCount' => null, 'ariaLabel', 'active' => 'leidiniai'])

@php
    $filledClass = 'inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white';
    $outlineClass = 'inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green px-4 py-2 text-base font-bold text-green transition-colors hover:bg-green/5';
    $shownCategoriesCount = count(array_slice($categories, 0, 5)) + ($featuredCategory ? 1 : 0);
    $hasMoreCategories = $allCategoriesCount !== null && $allCategoriesCount > $shownCategoriesCount;
@endphp

{{-- Ported from store-listing-header.tsx's nav row — plain links to the store's
     leaflet hub / full discount listing / top categories, not tabs that swap
     content in place. Always a single scrollable row (never wraps), on mobile
     and desktop alike. The current page's own tab renders filled; the other
     renders as an outline link, so it reads as an actual tab switcher on
     whichever of the two pages it's placed on. --}}
<div class="relative">
    <nav aria-label="{{ $ariaLabel }}" class="scroll-cards-x flex flex-nowrap items-center gap-2.5">
        <a href="/leidinys/{{ $storeSlug }}" class="{{ $active === 'leidiniai' ? $filledClass : $outlineClass }}">
            Leidiniai
            <x-count-pill :count="$leafletsCount" :color="$active === 'leidiniai' ? 'white' : 'green'" />
        </a>
        <a href="/akcijos/{{ $storeSlug }}" class="{{ $active === 'akcijos' ? $filledClass : $outlineClass }}">
            Akcijos
            <x-count-pill :count="$totalOffers" :color="$active === 'akcijos' ? 'white' : 'green'" />
        </a>
        @if ($featuredCategory)
            {{-- Food discounts live under several small root categories (pieno
                 produktai, bakalėja, mėsa ir žuvis, ...) that individually never
                 crack the top-5 below, so getFeaturedFoodCategoryForStore()'s
                 combined total is surfaced here instead of not at all. --}}
            <a href="{{ $featuredCategory['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-dark-green bg-dark-green/5 px-4 py-2 text-base font-bold text-dark-green transition-colors hover:bg-dark-green/10">
                {{ $featuredCategory['name'] }}
                <x-count-pill :count="$featuredCategory['offers_count'] ?? 0" color="green" />
            </a>
        @endif
        @if (!empty($categories))
            <span class="mx-0.5 h-6 w-px shrink-0 bg-gray-300" aria-hidden="true"></span>
            @foreach (array_slice($categories, 0, 5) as $category)
                <a href="{{ $category['href'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-base font-semibold text-gray-700 transition-colors hover:border-green hover:text-dark-green">
                    {{ $category['name'] }}
                    <x-count-pill :count="$category['offers_count'] ?? 0" color="gray" />
                </a>
            @endforeach
        @endif
        @if ($hasMoreCategories)
            <a href="/akcijos/{{ $storeSlug }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-dashed border-gray-400 px-4 py-2 text-base font-semibold text-gray-500 transition-colors hover:border-green hover:text-dark-green">
                Visos kategorijos ({{ $allCategoriesCount }})
                <x-app-icon name="chevron-right" class="size-3.5 shrink-0" />
            </a>
        @endif
    </nav>
    <span class="pointer-events-none absolute inset-y-0 right-0 w-14 bg-gradient-to-r from-transparent to-background" aria-hidden="true"></span>
</div>
