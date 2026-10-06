@props(['storeSlug', 'leafletsCount', 'totalOffers', 'categories' => [], 'featuredCategory' => null, 'allCategoriesCount' => null, 'ariaLabel', 'active' => 'leidiniai', 'currentCategory' => null])

@php
    // Matches the mockup's .nav-tabs-row exactly: always exactly 3 items —
    // Leidiniai / Akcijos (real page-switch buttons) then a divider and one
    // "Kategorijos ▾" popup — instead of one chip per popular category plus
    // a separate "Visos kategorijos" pill. A per-category chip row grows
    // with however many categories a store happens to have; this row never
    // does, regardless of store size.
    $activeClass = 'inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-green bg-action px-2.5 py-1.5 text-xs font-bold text-white sm:gap-2 sm:rounded-[10px] sm:px-4 sm:py-2.5 sm:text-sm';
    $mutedClass = 'inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-bold text-gray-500 transition-colors hover:border-gray-300 sm:gap-2 sm:rounded-[10px] sm:px-4 sm:py-2.5 sm:text-sm';
    $pillClass = fn (bool $onActive) => 'rounded-full px-1.5 py-0.5 text-xs font-extrabold sm:px-2 sm:text-xs ' . ($onActive ? 'bg-white/25' : 'bg-gray-100 text-gray-500');
    $totalCategoriesCount = $allCategoriesCount ?? count($categories);
@endphp

<div class="relative" x-data="{ categoriesOpen: false }" @click.outside="categoriesOpen = false">
    <nav aria-label="{{ $ariaLabel }}" class="scroll-cards-x flex flex-nowrap items-center gap-2.5 sm:flex-wrap">
        <a href="/leidinys/{{ $storeSlug }}" class="{{ $active === 'leidiniai' ? $activeClass : $mutedClass }}">
            Leidiniai
            <span class="{{ $pillClass($active === 'leidiniai') }}">{{ number_format($leafletsCount, 0, ',', ' ') }}</span>
        </a>
        <a href="/{{ $storeSlug }}" class="{{ $active === 'akcijos' ? $activeClass : $mutedClass }}">
            Akcijos
            <span class="{{ $pillClass($active === 'akcijos') }}">{{ number_format($totalOffers, 0, ',', ' ') }}</span>
        </a>

        @if ($totalCategoriesCount > 0)
            <span class="mx-0.5 h-5 w-px shrink-0 bg-gray-200 sm:h-6" aria-hidden="true"></span>

            <button type="button" @click="categoriesOpen = !categoriesOpen" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-bold text-gray-900 sm:gap-2 sm:rounded-[10px] sm:px-4 sm:py-2.5 sm:text-sm min-h-12">
                @if ($currentCategory)
                    Kategorija: {{ $currentCategory['name'] }}
                    <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-extrabold text-gray-500 sm:px-2 sm:text-xs">{{ number_format($currentCategory['offers_count'] ?? $totalOffers, 0, ',', ' ') }}</span>
                @else
                    Kategorijos
                    <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-extrabold text-gray-500 sm:px-2 sm:text-xs">{{ $totalCategoriesCount }}</span>
                @endif
                <span :class="categoriesOpen && 'rotate-180'" class="transition-transform">
                    <x-app-icon name="chevron-down" class="size-3 sm:size-3.5" />
                </span>
            </button>

            <div
                x-show="categoriesOpen"
                x-cloak
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
                @click.self="categoriesOpen = false"
            >
                <div @click.outside="categoriesOpen = false" class="flex w-full max-w-[420px] max-h-[80vh] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
                        <h2 class="text-base font-bold text-gray-900">Kategorijos</h2>
                        <button type="button" @click="categoriesOpen = false" class="flex size-12 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" aria-label="Uždaryti">
                            <x-app-icon name="x" class="size-4.5" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        @foreach ($categories as $category)
                            <a href="{{ $category['href'] ?? '/' . $storeSlug . '/' . $category['slug'] }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 min-h-12">
                                <span class="min-w-0 flex-1 truncate">{{ $category['name'] }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ number_format($category['offers_count'] ?? 0, 0, ',', ' ') }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </nav>
</div>
