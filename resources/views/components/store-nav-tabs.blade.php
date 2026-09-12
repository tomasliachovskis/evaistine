@props(['storeSlug', 'leafletsCount', 'totalOffers', 'categories' => [], 'featuredCategory' => null, 'allCategoriesCount' => null, 'ariaLabel', 'active' => 'leidiniai', 'currentCategory' => null])

@php
    // Matches the mockup's .nav-tabs-row exactly: always exactly 3 items —
    // Leidiniai / Akcijos (real page-switch buttons) then a divider and one
    // "Kategorijos ▾" popup — instead of one chip per popular category plus
    // a separate "Visos kategorijos" pill. A per-category chip row grows
    // with however many categories a store happens to have; this row never
    // does, regardless of store size.
    $activeClass = 'inline-flex shrink-0 items-center gap-2 rounded-[10px] border-2 border-green bg-green px-4 py-2.5 text-[0.95rem] font-bold text-white';
    $mutedClass = 'inline-flex shrink-0 items-center gap-2 rounded-[10px] border-2 border-gray-200 px-4 py-2.5 text-[0.95rem] font-bold text-gray-500 transition-colors hover:border-gray-300';
    $pillClass = fn (bool $onActive) => 'rounded-full px-2 py-0.5 text-xs font-extrabold ' . ($onActive ? 'bg-white/25' : 'bg-gray-100 text-gray-500');
    $totalCategoriesCount = $allCategoriesCount ?? count($categories);
@endphp

<div class="relative" x-data="{ categoriesOpen: false }" @click.outside="categoriesOpen = false">
    <nav aria-label="{{ $ariaLabel }}" class="flex flex-wrap items-center gap-2.5">
        <a href="/leidinys/{{ $storeSlug }}" class="{{ $active === 'leidiniai' ? $activeClass : $mutedClass }}">
            Leidiniai
            <span class="{{ $pillClass($active === 'leidiniai') }}">{{ number_format($leafletsCount, 0, ',', ' ') }}</span>
        </a>
        <a href="/akcijos/{{ $storeSlug }}" class="{{ $active === 'akcijos' ? $activeClass : $mutedClass }}">
            Akcijos
            <span class="{{ $pillClass($active === 'akcijos') }}">{{ number_format($totalOffers, 0, ',', ' ') }}</span>
        </a>

        @if ($totalCategoriesCount > 0)
            <span class="mx-0.5 h-6 w-px shrink-0 bg-gray-200" aria-hidden="true"></span>

            <button type="button" @click="categoriesOpen = !categoriesOpen" class="inline-flex shrink-0 items-center gap-2 rounded-[10px] border-2 border-gray-200 bg-white px-4 py-2.5 text-[0.95rem] font-bold text-gray-900">
                @if ($currentCategory)
                    Kategorija: {{ $currentCategory['name'] }}
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-extrabold text-gray-500">{{ number_format($currentCategory['offers_count'] ?? $totalOffers, 0, ',', ' ') }}</span>
                @else
                    Kategorijos
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-extrabold text-gray-500">{{ $totalCategoriesCount }}</span>
                @endif
                <span :class="categoriesOpen && 'rotate-180'" class="transition-transform">
                    <x-app-icon name="chevron-down" class="size-3.5" />
                </span>
            </button>

            <div x-show="categoriesOpen" x-cloak class="absolute left-0 top-full z-30 mt-2 max-h-[70vh] w-[300px] overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
                @if ($featuredCategory)
                    <a href="{{ $featuredCategory['href'] }}" class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                        {{ $featuredCategory['name'] }}
                        <span class="text-xs text-gray-400">{{ number_format($featuredCategory['offers_count'] ?? 0, 0, ',', ' ') }}</span>
                    </a>
                @endif
                @foreach ($categories as $category)
                    <a href="{{ $category['href'] ?? '/akcijos/' . $storeSlug . '/' . $category['slug'] }}" class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                        {{ $category['name'] }}
                        <span class="text-xs text-gray-400">{{ number_format($category['offers_count'] ?? 0, 0, ',', ' ') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </nav>
</div>
