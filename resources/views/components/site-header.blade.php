@php
    // Mirrors discount/src/lib/header-nav.ts's isHeaderNavItemActive() family.
    $path = request()->path();
    $akcijosSegment = str_starts_with($path, 'akcijos/') ? explode('/', $path)[1] ?? null : null;
    $keywordSlugs = array_column(config('header_nav.product_keyword_items'), 'slug');
    $isProductKeywordPath = $akcijosSegment && $akcijosSegment !== 'paieska' && in_array($akcijosSegment, $keywordSlugs, true);
    $akcijosActive = $path === 'akcijos' || str_starts_with($path, 'akcijos/');
    $storesActive = $path === 'parduotuves' || str_starts_with($path, 'leidinys/');
    $leafletsActive = $path === 'leidiniai';
    $cheapestActive = $path === 'pigiausios-prekes';

    $navLinkClass = fn (bool $active) => 'inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-[11px] px-4 py-2.5 my-[7px] text-[0.95rem] font-bold transition-colors '
        . ($active ? 'bg-green text-white' : 'text-gray-600 hover:bg-gray-100');
    $menuItemClass = fn (bool $active) => 'flex min-h-[56px] w-full items-center gap-3.5 rounded-xl px-2.5 py-4 text-[1.05rem] font-bold transition-colors '
        . ($active ? 'bg-green/10 text-dark-green' : 'text-gray-900 hover:bg-gray-100');
    $headerIconClass = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-600 transition-colors hover:bg-gray-200';

    // Same row style discount-filters.blade.php's store/category panels use
    // (ported from product-filter-controls.tsx's row constants) — reused
    // here so the desktop nav's Kategorijos click opens the identical modal
    // instead of its own smaller bespoke dropdown.
    $categoryRowClass = fn (bool $active) => 'flex w-full cursor-pointer items-center gap-2 rounded-2xl px-3 min-h-[40px] text-[16px] leading-snug text-left transition-colors '
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

    // Experiment (leaflets/show.blade.php only, not the /leidinys/{store}
    // hub): row2 nav links already eat vertical space above a page whose
    // whole point is showing the leaflet image itself, even with the
    // hide-on-scroll behavior — try dropping it entirely on this page
    // instead of just hiding it on scroll.
    $hideNavRow = preg_match('#^leidinys/[^/]+/[^/]+#', $path) === 1;
@endphp

{{-- x-data="{}" required for @click bindings here to survive Livewire's
     initial-page morph — see auth-modal.blade.php's comment.

     One shared slide-in menu panel (menuOpen), opened by the same hamburger
     icon at every viewport width — this replaces the old split between
     desktop-only inline dropdowns (categoriesOpen/productsOpen) and a
     separate mobile-only bottom sheet; there is now only one menu
     implementation, not two. --}}
<header
    x-data="{
        menuOpen: false, keywordsOpen: false, categoriesMenuOpen: false, categoriesNavOpen: false, accountOpen: false,
        lastScrollY: 0,
        init() {
            this.lastScrollY = window.scrollY;
            // Row2 only ever hides to make room for a page's own sticky
            // filter/pill bar to dock in its place — on a page with no such
            // bar (homepage, plain /akcijos hub) there's nothing to dock
            // there, so hiding it just left dead space. Checked once here
            // (not reactively) since these are full server-rendered page
            // loads — the marker, when present, is already in the initial
            // HTML by the time this runs.
            const hasStickyFilterBar = document.querySelector('[data-sticky-filter-bar]') !== null;
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!hasStickyFilterBar) { return; }
                if (this.menuOpen) { return; }
                if (ticking) { return; }
                ticking = true;
                requestAnimationFrame(() => {
                    const currentY = window.scrollY;
                    const delta = currentY - this.lastScrollY;
                    if (currentY <= 12) { $store.siteHeader.visible = true; }
                    else if (delta > 10) { $store.siteHeader.visible = false; }
                    else if (delta < -10) { $store.siteHeader.visible = true; }
                    this.lastScrollY = currentY;
                    ticking = false;
                });
            }, { passive: true });
        },
    }"
    @keydown.escape.window="menuOpen = false; accountOpen = false; categoriesNavOpen = false"
    class="fixed top-0 z-50 w-full bg-white pt-[env(safe-area-inset-top,0px)] shadow-[0_1px_0_rgba(15,23,42,0.06),0_4px_16px_rgba(15,23,42,0.08)]"
>
    <div class="border-b border-gray-200 bg-white">
        <div class="base-container flex h-14 items-center gap-4 py-2">
            <a href="/" class="flex min-w-0 shrink-0 items-center no-underline outline-offset-2 hover:opacity-90">
                <img src="/assets/logo.svg" alt="SuperAkcijos.lt" class="h-7 w-auto max-w-[min(178px,48vw)] object-contain object-left sm:h-8">
            </a>

            {{-- Search box shown ≥1024px (matches the shared mockup's
                 breakpoint); below that, an icon-only trigger opening the
                 same full-screen search overlay. --}}
            <div class="hidden min-w-0 flex-1 lg:ml-4 lg:block">
                <livewire:site-search mode="desktop" />
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-4">
                {{-- Grouped with the other icon buttons (favorites, menu)
                     on the right, next to the heart, instead of sitting
                     alone right after the logo. --}}
                <div class="flex items-center lg:hidden">
                    <livewire:site-search mode="mobile" />
                </div>

                {{-- Hidden on mobile per explicit product decision — login/
                     account still reachable via the mobile slide-out menu. --}}
                <div class="relative hidden sm:block" @click.outside="accountOpen = false">
                    @auth
                        <button type="button" @click="accountOpen = !accountOpen" class="{{ $headerIconClass }}" aria-label="Paskyra">
                            <x-app-icon name="user" class="size-5" />
                        </button>
                        <div x-show="accountOpen" x-cloak class="absolute right-0 top-full z-50 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                            <div class="px-2 py-1.5">
                                <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name ?? auth()->user()->email }}</p>
                                <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                            </div>
                            <div class="my-1 border-t border-gray-200"></div>
                            <form method="POST" action="/logout">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                                    <x-app-icon name="log-out" class="size-4" />
                                    Atsijungti
                                </button>
                            </form>
                        </div>
                    @else
                        <button type="button" @click="$store.authModal.open = true" class="{{ $headerIconClass }}" aria-label="Prisijungti">
                            <x-app-icon name="user" class="size-5" />
                        </button>
                    @endauth
                </div>

                {{-- Hidden on mobile — favorites already has its own entry
                     in the mobile bottom nav, so this would be a duplicate. --}}
                <div class="hidden sm:block">
                    @auth
                        <a href="/favorites" class="{{ $headerIconClass }}" aria-label="Mano favoritai">
                            <span class="relative inline-flex">
                                <x-app-icon name="heart" class="size-5" />
                                <livewire:favorites-badge />
                            </span>
                        </a>
                    @else
                        <button type="button" @click="$store.authModal.open = true" class="relative {{ $headerIconClass }}" aria-label="Mano favoritai">
                            <x-app-icon name="heart" class="size-5" />
                        </button>
                    @endauth
                </div>

                <button
                    type="button"
                    @click="menuOpen = !menuOpen"
                    class="{{ $headerIconClass }}"
                    aria-label="Meniu"
                >
                    <x-app-icon x-show="!menuOpen" name="equal" class="size-5" style="stroke-width:1.75" />
                    <x-app-icon x-show="menuOpen" x-cloak name="x" class="size-5" style="stroke-width:1.75" />
                </button>
            </div>
        </div>
    </div>

    @if (!$hideNavRow)
    <div :class="!$store.siteHeader.visible && 'lg:!hidden'" class="hidden border-b border-gray-200 bg-white lg:block">
        <nav class="base-container flex items-center gap-2.5" aria-label="Pagrindinė navigacija">
            <a href="/akcijos" data-ga-event="desktop_nav_click" data-ga-item="products" class="{{ $navLinkClass($akcijosActive) }}">
                <x-app-icon name="tag" class="size-4.5" />Visos akcijos
            </a>
            <a href="/parduotuves" data-ga-event="desktop_nav_click" data-ga-item="stores" class="{{ $navLinkClass($storesActive) }}">
                <x-app-icon name="store" class="size-4.5" />Parduotuvės
            </a>
            <div class="relative">
                <button type="button" @click="categoriesNavOpen = !categoriesNavOpen" data-ga-event="desktop_nav_click" data-ga-item="categories" class="{{ $navLinkClass(false) }}">
                    <x-app-icon name="layout-grid" class="size-4.5" />Kategorijos
                    <span :class="categoriesNavOpen && 'rotate-180'" class="transition-transform"><x-app-icon name="chevron-down" class="size-3.5" /></span>
                </button>
                {{-- Same centered-modal shell as discount-filters.blade.php's
                     category panel (akcijos listing pages) — reused here
                     instead of a second, smaller bespoke dropdown, so
                     "browse categories" looks and behaves the same wherever
                     it's triggered. This button only ever renders at lg+, so
                     the modal always opens in its "desktop" (sm:items-center)
                     shape in practice.

                     x-teleport to <body>: this modal lives inside <header>,
                     which is itself `fixed` + `z-50` — that combination
                     makes <header> its own stacking context, so ANY
                     z-index on a descendant (tried z-[70] first) is capped
                     at that context and never actually out-ranks page-level
                     siblings like a listing/leaflet page's sticky filter
                     bar (z-[60], living outside <header>) — confirmed live
                     2026-09-17, the bar visually cut through the middle of
                     this modal despite the z-[70]. Teleporting to <body>
                     escapes <header>'s stacking context entirely. --}}
                <template x-teleport="body">
                    <div x-show="categoriesNavOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @click.self="categoriesNavOpen = false">
                        <div class="max-h-[80vh] w-full max-w-[420px] overflow-y-auto rounded-2xl bg-white p-4">
                            <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-gray-400">Kategorija</div>
                            @include('components.partials.discount-filter-sections', [
                                'facet' => 'categories',
                                'items' => $categoriesForModal,
                                'activeSlug' => null,
                                'hrefFor' => fn ($slug) => '/akcijos/' . $slug,
                                'rowClass' => $categoryRowClass,
                                'gaSource' => 'header_nav_categories',
                            ])
                        </div>
                    </div>
                </template>
            </div>
            <a href="/leidiniai" data-ga-event="desktop_nav_click" data-ga-item="leaflets" class="{{ $navLinkClass($leafletsActive) }}">
                <x-app-icon name="bookmark" class="size-4.5" />Leidiniai
            </a>
            <a href="/pigiausios-prekes" data-ga-event="desktop_nav_click" data-ga-item="cheapest" class="{{ $navLinkClass($cheapestActive) }}">
                <x-app-icon name="shopping-bag" class="size-4.5" />Didžiausios nuolaidos
            </a>
        </nav>
    </div>
    @endif

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
                class="absolute inset-y-0 right-0 flex w-full max-w-[360px] flex-col overflow-hidden bg-white shadow-xl"
            >
                <div class="flex shrink-0 items-center justify-end p-3.5">
                    <button type="button" @click="menuOpen = false" class="flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-600 hover:bg-gray-100" aria-label="Uždaryti">
                        <x-app-icon name="x" class="size-4.5" />
                    </button>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto px-2.5 pb-4.5">
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
                </nav>
            </div>
        </div>
    </template>
</header>
