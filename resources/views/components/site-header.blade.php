@php
    // Mirrors discount/src/lib/header-nav.ts's isHeaderNavItemActive() family.
    $path = request()->path();
    $order = request()->query('order');
    $akcijosSegment = str_starts_with($path, 'akcijos/') ? explode('/', $path)[1] ?? null : null;
    $keywordSlugs = array_column(config('header_nav.product_keyword_items'), 'slug');
    $isProductKeywordPath = $akcijosSegment && $akcijosSegment !== 'paieska' && in_array($akcijosSegment, $keywordSlugs, true);
    $akcijosActive = $path === 'akcijos' || str_starts_with($path, 'akcijos/');
    $storesActive = $path === 'parduotuves' || str_starts_with($path, 'leidinys/');
    $topTodayActive = $path === 'akcijos' && $order === 'price_discount_proc_max';
    $leafletsActive = $path === 'leidiniai';
    $cheapestActive = $path === 'pigiausios-prekes';

    $navLinkClass = fn (bool $active) => 'inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-[11px] px-4 py-2.5 text-[0.95rem] font-bold transition-colors '
        . ($active ? 'bg-green text-white' : 'text-gray-600 hover:bg-gray-100');
    $menuItemClass = fn (bool $active) => 'flex min-h-[56px] w-full items-center gap-3.5 rounded-xl px-2.5 py-4 text-[1.05rem] font-bold transition-colors '
        . ($active ? 'bg-green/10 text-dark-green' : 'text-gray-900 hover:bg-gray-100');
    $headerIconClass = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-gray-50 text-gray-600 transition-colors hover:bg-gray-200';
@endphp

{{-- x-data="{}" required for @click bindings here to survive Livewire's
     initial-page morph — see auth-modal.blade.php's comment.

     One shared slide-in menu panel (menuOpen), opened by the same hamburger
     icon at every viewport width — this replaces the old split between
     desktop-only inline dropdowns (categoriesOpen/productsOpen) and a
     separate mobile-only bottom sheet; there is now only one menu
     implementation, not two. --}}
<header x-data="{ menuOpen: false, keywordsOpen: false, accountOpen: false }" @keydown.escape.window="menuOpen = false; accountOpen = false" class="fixed top-0 z-50 w-full bg-white pt-[env(safe-area-inset-top,0px)] shadow-[0_1px_0_rgba(15,23,42,0.06),0_4px_16px_rgba(15,23,42,0.08)]">
    <div class="bg-white">
        <div class="base-container flex h-14 items-center gap-3 py-2 sm:gap-4 lg:gap-6">
            <a href="/" class="flex min-w-0 shrink-0 items-center no-underline outline-offset-2 hover:opacity-90">
                <img src="/assets/logo.svg" alt="SuperAkcijos.lt" class="h-7 w-auto max-w-[min(178px,48vw)] object-contain object-left sm:h-8">
            </a>

            {{-- Search box shown ≥1024px (matches the shared mockup's
                 breakpoint); below that, an icon-only trigger opening the
                 same full-screen search overlay. --}}
            <div class="hidden min-w-0 flex-1 lg:ml-4 lg:block">
                <livewire:site-search mode="desktop" />
            </div>
            <div class="flex items-center lg:hidden">
                <livewire:site-search mode="mobile" />
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-2">
                <div class="relative" @click.outside="accountOpen = false">
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

    <div class="hidden border-b border-gray-200 bg-white lg:block">
        <nav class="base-container flex items-center gap-2.5 overflow-x-auto" aria-label="Pagrindinė navigacija">
            <a href="/akcijos" class="{{ $navLinkClass($akcijosActive) }}">
                <x-app-icon name="percent" class="size-4.5" />Akcijos
            </a>
            <a href="/parduotuves" class="{{ $navLinkClass($storesActive) }}">
                <x-app-icon name="store" class="size-4.5" />Parduotuvės
            </a>
            <a href="/leidiniai" class="{{ $navLinkClass($leafletsActive) }}">
                <x-app-icon name="newspaper" class="size-4.5" />Leidiniai
            </a>
            <a href="/pigiausios-prekes" class="{{ $navLinkClass($cheapestActive) }}">
                <x-app-icon name="shopping-basket" class="size-4.5" />Pigiausios prekės
            </a>
        </nav>
    </div>

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
                    <x-app-icon name="percent" class="size-5 shrink-0" />Akcijos
                </a>
                <a href="/parduotuves" @click="menuOpen = false" class="{{ $menuItemClass($storesActive) }}">
                    <x-app-icon name="store" class="size-5 shrink-0" />Parduotuvės
                </a>
                <a href="/leidiniai" @click="menuOpen = false" class="{{ $menuItemClass($leafletsActive) }}">
                    <x-app-icon name="newspaper" class="size-5 shrink-0" />Leidiniai
                </a>
                <a href="/pigiausios-prekes" @click="menuOpen = false" class="{{ $menuItemClass($cheapestActive) }}">
                    <x-app-icon name="shopping-basket" class="size-5 shrink-0" />Pigiausios prekės
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

                <a href="/akcijos?order=price_discount_proc_max" @click="menuOpen = false" class="{{ $menuItemClass($topTodayActive) }}">
                    <x-app-icon name="trending-up" class="size-5 shrink-0" />Didžiausios nuolaidos
                </a>

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
</header>
