@props(['appTitle' => null, 'backHref' => null]) {{-- accepted, unused since the app bar was dropped --}}

@php
    // Mirrors discount/src/lib/header-nav.ts's isHeaderNavItemActive() family.
    $path = request()->path();
    $akcijosSegment = str_starts_with($path, 'akcijos/') ? explode('/', $path)[1] ?? null : null;
    $keywordSlugs = array_column(config('header_nav.product_keyword_items'), 'slug');
    $isProductKeywordPath = $akcijosSegment && $akcijosSegment !== 'paieska' && in_array($akcijosSegment, $keywordSlugs, true);
    $akcijosActive = $path === 'akcijos' || str_starts_with($path, 'akcijos/');
    $storesActive = $path === 'parduotuves' || str_starts_with($path, 'parduotuves/');
    $leafletsActive = $path === 'leidiniai' || str_starts_with($path, 'leidinys/');
    $cheapestActive = $path === 'pigiausios-prekes';

    // One calm row (2026-10 redesign): plain text links, no icons, the
    // active one marked by a green underline instead of a filled pill.
    $navLinkClass = fn (bool $active) => 'relative inline-flex min-h-12 shrink-0 items-center gap-1 whitespace-nowrap px-3 text-lg font-semibold transition-colors '
        . ($active ? 'text-dark-green after:absolute after:inset-x-3 after:bottom-0 after:h-[3px] after:rounded-full after:bg-action' : 'text-gray-800 hover:text-dark-green');
    $menuItemClass = fn (bool $active) => 'flex min-h-14 w-full items-center gap-3.5 rounded-xl px-3 py-2.5 text-lg font-bold transition-colors '
        . ($active ? 'bg-green-soft text-dark-green' : 'text-gray-900 hover:bg-gray-100');
    // Icon-only, borderless (explicit product decision): the title
    // attribute and aria-label name each one.
    $headerButtonClass = 'relative inline-flex size-12 shrink-0 items-center justify-center rounded-xl text-gray-900 transition-colors hover:bg-gray-100 hover:text-dark-green';
    $isHome = $path === '/' || $path === '';

    // Counts next to the nav links ("Akcijos 17 366"), from the same
    // cached composer data the bottom-nav sheets use, so no extra queries.
    $storeList = collect($stores ?? []);
    $navCounts = [
        'akcijos' => (int) $storeList->sum('discounts_count'),
        'leidiniai' => (int) $storeList->sum('leaflets_count'),
        'parduotuves' => $storeList->filter(fn ($st) => ($st['discounts_count'] ?? 0) > 0 || ($st['leaflets_count'] ?? 0) > 0)->count(),
        'kategorijos' => count($categories ?? []),
    ];
    $navCount = fn (string $key) => $navCounts[$key] > 0
        ? '<span class="ml-1.5 text-base font-medium tabular-nums text-gray-500">('.\App\Support\LithuanianPlural::formatCount($navCounts[$key]).')</span>'
        : '';

    // Same row style discount-filters.blade.php's store/category panels use
    // (ported from product-filter-controls.tsx's row constants) — reused
    // here so the desktop nav's Kategorijos click opens the identical modal
    // instead of its own smaller bespoke dropdown.
    $categoryRowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-12 text-base leading-snug text-left transition-colors '
        . ($active ? 'bg-[#e8e8e8] font-bold text-gray-900 hover:bg-[#dedede]' : 'font-semibold text-gray-900 hover:bg-[#f2f2f2]');
    // discount-filter-sections.blade.php's categories branch reads
    // offers_count; MobileNavComposer's $categories carries discounts_count
    // — same figure, different key, since it's built for the different
    // category-links-list caller instead.
    $categoriesForModal = collect($categories)->map(fn ($category) => [
        'slug' => $category['slug'],
        'name' => $category['name'],
        'offers_count' => $category['discounts_count'] ?? 0,
    ])->all();
@endphp

{{-- x-data="{}" required for @click bindings here to survive Livewire's
     initial-page morph — see auth-modal.blade.php's comment.

     One row on every width (2026-10 redesign, was logo/search/icons over a
     separate nav row, 144px in total): logo, categories icon, text links
     with counts (from lg), search, favourites, menu. Everything else
     (account, Didžiausios nuolaidos, popular products) lives in the menu. --}}
<header
    x-data="{ menuOpen: false, keywordsOpen: false, categoriesMenuOpen: false, categoriesNavOpen: false }"
    @keydown.escape.window="menuOpen = false; categoriesNavOpen = false"
    style="view-transition-name: site-header"
    class="fixed top-0 z-50 w-full border-b border-gray-200 bg-white pt-[env(safe-area-inset-top,0px)]"
>
    <div class="base-container flex h-14 items-center gap-2 lg:h-[72px] lg:gap-4">
        <a href="/" class="flex min-h-12 min-w-0 shrink-0 items-center no-underline hover:opacity-90">
            <img src="/assets/logo.svg" alt="SuperAkcijos.lt" class="h-7 w-auto max-w-[min(200px,42vw)] object-contain object-left lg:max-w-none">
        </a>

        {{-- Categories as an icon next to the logo (owner's request). --}}
        <div class="relative">
            <button type="button" @click="categoriesNavOpen = !categoriesNavOpen" data-ga-event="desktop_nav_click" data-ga-item="categories" class="{{ $headerButtonClass }}" :aria-expanded="categoriesNavOpen" aria-label="Kategorijos" title="Kategorijos">
                <x-app-icon name="layout-grid" class="size-7" />
            </button>
            <template x-teleport="body">
                <div x-show="categoriesNavOpen" x-cloak x-back-closes="categoriesNavOpen" class="sheet-backdrop z-[70]" @click.self="categoriesNavOpen = false">
                    <div class="sheet-panel px-5 pb-5">
                        <div class="sheet-handle"></div>
                        <div class="mb-3 mt-3 flex items-center justify-between gap-3 sm:mt-5">
                            <h2 class="text-2xl font-bold text-gray-900">Kategorijos</h2>
                            <button type="button" @click="categoriesNavOpen = false" class="sheet-close" aria-label="Uždaryti">
                                <x-app-icon name="x" class="size-7" />
                            </button>
                        </div>
                        @include('components.partials.discount-filter-sections', [
                            'facet' => 'categories',
                            'items' => $categoriesForModal,
                            'activeSlug' => null,
                            'hrefFor' => fn ($slug) => '/akcijos/' . $slug,
                            'rowClass' => $categoryRowClass,
                            'gaSource' => 'header_nav_categories',
                            // This is a full category directory, not a
                            // page-scoped filter — show every category
                            // immediately, no "Rodyti daugiau" step.
                            'visibleLimit' => count($categoriesForModal),
                        ])
                    </div>
                </div>
            </template>
        </div>

        <nav class="hidden items-center lg:flex" aria-label="Pagrindinė navigacija">
            <a href="/akcijos" data-ga-event="desktop_nav_click" data-ga-item="products" class="{{ $navLinkClass($akcijosActive) }}">Akcijos{!! $navCount('akcijos') !!}</a>
            <a href="/leidiniai" data-ga-event="desktop_nav_click" data-ga-item="leaflets" class="{{ $navLinkClass($leafletsActive) }}">Leidiniai{!! $navCount('leidiniai') !!}</a>
            <a href="/parduotuves" data-ga-event="desktop_nav_click" data-ga-item="stores" class="{{ $navLinkClass($storesActive) }}">Parduotuvės{!! $navCount('parduotuves') !!}</a>
        </nav>

        <div class="ml-auto flex min-w-0 items-center gap-1 lg:flex-1 lg:justify-end lg:gap-2">
            {{-- Search field from xl, an icon below that. --}}
            <div class="hidden min-w-0 max-w-[420px] flex-1 xl:block">
                <livewire:site-search mode="desktop" />
            </div>
            <div class="flex items-center xl:hidden">
                <livewire:site-search mode="mobile" />
            </div>

            <div class="hidden sm:block">
                @auth
                    <a href="/favorites" class="{{ $headerButtonClass }}" aria-label="Mėgstami" title="Mėgstami">
                        <span class="relative inline-flex">
                            <x-app-icon name="heart" class="size-7" />
                            <livewire:favorites-badge />
                        </span>
                    </a>
                @else
                    <button type="button" @click="$store.authModal.open = true" class="{{ $headerButtonClass }}" aria-label="Mėgstami" title="Mėgstami">
                        <x-app-icon name="heart" class="size-7" />
                    </button>
                @endauth
            </div>

            <button
                type="button"
                @click="menuOpen = !menuOpen"
                class="{{ $headerButtonClass }}"
                :aria-expanded="menuOpen"
                aria-label="Meniu"
                title="Meniu"
            >
                <x-app-icon x-show="!menuOpen" name="equal" class="size-7" style="stroke-width:2" />
                <x-app-icon x-show="menuOpen" x-cloak name="x" class="size-7" style="stroke-width:2" />
            </button>
        </div>
    </div>

    {{-- x-teleport to <body>: same stacking-context trap as the Kategorijos
         modal above — this menu lives inside <header> (fixed + z-50), so
         its own z-[9999] never actually escapes <header>'s stacking
         context. A page with a z-[60] sticky filter bar would show that
         bar rendering on top of this full-screen menu otherwise. --}}
    <template x-teleport="body">
        <div x-show="menuOpen" x-cloak class="fixed inset-0 z-[9999]">
            <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="menuOpen = false"></button>
            <div
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="absolute inset-y-0 right-0 flex w-full max-w-[420px] flex-col overflow-hidden bg-white shadow-xl"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                    <span class="text-xl font-bold text-gray-900">Meniu</span>
                    <button type="button" @click="menuOpen = false" class="sheet-close" aria-label="Uždaryti">
                        <x-app-icon name="x" class="size-7" />
                    </button>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
                    <a href="/akcijos" @click="menuOpen = false" class="{{ $menuItemClass($akcijosActive) }}">
                        <x-app-icon name="tag" class="size-5 shrink-0" />Visos akcijos
                    </a>
                    <a href="/parduotuves" @click="menuOpen = false" class="{{ $menuItemClass($storesActive) }}">
                        <x-app-icon name="store" class="size-5 shrink-0" />Parduotuvės
                    </a>

                    <div>
                        <button type="button" @click="categoriesMenuOpen = !categoriesMenuOpen" class="{{ $menuItemClass(false) }}">
                            <x-app-icon name="layout-grid" class="size-5 shrink-0" />
                            <span class="flex-1 text-left">Kategorijos</span>
                            <span :class="categoriesMenuOpen && 'rotate-180'" class="transition-transform"><x-app-icon name="chevron-down" class="size-4" /></span>
                        </button>
                        <div x-show="categoriesMenuOpen" x-cloak class="pl-2 pb-1">
                            <x-category-links-list :categories="$categories" />
                        </div>
                    </div>

                    <a href="/leidiniai" @click="menuOpen = false" class="{{ $menuItemClass($leafletsActive) }}">
                        <x-app-icon name="bookmark" class="size-5 shrink-0" />Leidiniai
                    </a>
                    <a href="/pigiausios-prekes" @click="menuOpen = false" class="{{ $menuItemClass($cheapestActive) }}">
                        <x-app-icon name="shopping-bag" class="size-5 shrink-0" />Didžiausios nuolaidos
                    </a>

                    <div>
                        <button type="button" @click="keywordsOpen = !keywordsOpen" class="{{ $menuItemClass($isProductKeywordPath) }}">
                            <x-app-icon name="flame" class="size-5 shrink-0" />
                            <span class="flex-1 text-left">Populiarios prekės</span>
                            <span :class="keywordsOpen && 'rotate-180'" class="transition-transform"><x-app-icon name="chevron-down" class="size-4" /></span>
                        </button>
                        <div x-show="keywordsOpen" x-cloak class="pl-8 pb-1">
                            <x-product-keyword-links-list />
                        </div>
                    </div>

                    <div class="my-2 border-t border-gray-200"></div>

                    @auth
                        <a href="/favorites" @click="menuOpen = false" class="{{ $menuItemClass(false) }}">
                            <x-app-icon name="heart" class="size-5 shrink-0" />Mėgstami
                        </a>
                    @else
                        <button type="button" @click="menuOpen = false; $store.authModal.open = true" class="{{ $menuItemClass(false) }}">
                            <x-app-icon name="heart" class="size-5 shrink-0" />Mėgstami
                        </button>
                    @endauth

                    <a href="/naujienos" @click="menuOpen = false" class="{{ $menuItemClass(false) }}">
                        <x-app-icon name="mail" class="size-5 shrink-0" />Naujienos
                    </a>

                    <div class="my-2 border-t border-gray-200"></div>

                    @auth
                        <p class="px-3 pt-1 text-base text-gray-600">{{ auth()->user()->name ?? auth()->user()->email }}</p>
                        <form method="POST" action="/logout">
                            @csrf
                            <button type="submit" class="{{ $menuItemClass(false) }}">
                                <x-app-icon name="log-out" class="size-5 shrink-0" />Atsijungti
                            </button>
                        </form>
                    @else
                        <button type="button" @click="menuOpen = false; $store.authModal.open = true" class="{{ $menuItemClass(false) }}">
                            <x-app-icon name="user" class="size-5 shrink-0" />Prisijungti
                        </button>
                    @endauth
                </nav>
            </div>
        </div>
    </template>
</header>
