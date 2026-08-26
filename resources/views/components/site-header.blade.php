@props(['categories' => [], 'stores' => []])

@php
    // Mirrors discount/src/lib/header-nav.ts's isHeaderNavItemActive() family.
    $path = request()->path();
    $order = request()->query('order');
    $akcijosSegment = str_starts_with($path, 'akcijos/') ? explode('/', $path)[1] ?? null : null;
    $keywordSlugs = array_column(config('header_nav.product_keyword_items'), 'slug');
    $isProductKeywordPath = $akcijosSegment && $akcijosSegment !== 'paieska' && in_array($akcijosSegment, $keywordSlugs, true);
    $isCategoryPath = $akcijosSegment
        && $akcijosSegment !== 'paieska'
        && !\App\Support\StoreDisplayMeta::isStoreSlug($akcijosSegment)
        && !$isProductKeywordPath;
    $storesActive = $path === 'parduotuves' || str_starts_with($path, 'leidinys/');
    $topTodayActive = $path === 'akcijos' && $order === 'price_discount_proc_max';
    $leafletsActive = $path === 'leidiniai';

    $navLinkClass = fn (bool $active) => 'inline-flex h-11 items-center border-b-2 px-3 text-sm transition-colors hover:text-dark-green '
        . ($active ? 'border-green font-bold text-gray-900' : 'border-transparent font-medium text-gray-700');
@endphp

{{-- x-data="{}" required for @click bindings here to survive Livewire's
     initial-page morph — see auth-modal.blade.php's comment. --}}
<header x-data="{ menuOpen: false, categoriesOpen: false, productsOpen: false }" @keydown.escape.window="categoriesOpen = false; productsOpen = false; menuOpen = false" class="fixed top-0 z-50 w-full bg-white pt-[env(safe-area-inset-top,0px)] shadow-[0_1px_0_rgba(15,23,42,0.06),0_4px_16px_rgba(15,23,42,0.08)]">
    <div class="bg-green">
        <div class="base-container flex h-14 items-center gap-3 py-2 sm:gap-6 lg:gap-8">
            <a href="/" class="flex min-w-0 shrink-0 items-center no-underline outline-offset-2 hover:opacity-90">
                <img src="/assets/logo-white.svg" alt="SuperAkcijos.lt" class="h-7 w-auto max-w-[min(178px,48vw)] object-contain object-left sm:h-8">
            </a>

            <div class="hidden min-w-0 flex-1 sm:ml-2 sm:block lg:ml-4">
                <livewire:site-search mode="desktop" />
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
                {{-- Mobile-only cluster --}}
                <div class="flex items-center gap-1 sm:hidden">
                    <livewire:site-search mode="mobile" />
                    @auth
                        <a href="/favorites" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-white hover:bg-white/10" aria-label="Mano favoritai">
                            <x-app-icon name="heart" class="size-6" />
                            <livewire:favorites-badge />
                        </a>
                    @else
                        <button type="button" @click="$store.authModal.open = true" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-white hover:bg-white/10" aria-label="Mano favoritai">
                            <x-app-icon name="heart" class="size-6" />
                        </button>
                    @endauth
                    <button
                        type="button"
                        @click="menuOpen = !menuOpen"
                        :class="menuOpen && 'bg-white/10'"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/35 bg-transparent text-white hover:bg-white/10"
                        aria-label="Meniu"
                    >
                        <x-app-icon x-show="!menuOpen" name="equal" class="size-6" style="stroke-width:1.75" />
                        <x-app-icon x-show="menuOpen" x-cloak name="x" class="size-6" style="stroke-width:1.75" />
                    </button>
                </div>

                {{-- Desktop-only cluster --}}
                <div class="hidden items-center gap-2 sm:flex">
                    @auth
                        <div class="relative" @click.outside="accountOpen = false" x-data="{ accountOpen: false }">
                            <button type="button" @click="accountOpen = !accountOpen" class="flex h-auto items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold text-white hover:bg-white/10">
                                <x-app-icon name="user" class="size-5" />
                                <span>Paskyra</span>
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
                        </div>
                    @else
                        <button type="button" @click="$store.authModal.open = true" class="flex h-auto items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-semibold text-white hover:bg-white/10">
                            <x-app-icon name="user" class="size-5" />
                            <span>Prisijungti</span>
                        </button>
                    @endauth

                    @auth
                        <a href="/favorites" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-white hover:bg-white/10" aria-label="Mano favoritai">
                            <x-app-icon name="heart" class="size-6" />
                            <livewire:favorites-badge />
                        </a>
                    @else
                        <button type="button" @click="$store.authModal.open = true" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-white hover:bg-white/10" aria-label="Mano favoritai">
                            <x-app-icon name="heart" class="size-6" />
                        </button>
                    @endauth
                </div>
            </div>
        </div>
    </div>

    <div class="hidden border-b border-gray-300 bg-white sm:block">
        {{-- overflow-x-auto here used to clip the categories/products dropdown
             panels — a non-'visible' overflow-x forces overflow-y to 'auto'
             too per the CSS overflow spec, so any absolutely-positioned child
             (the dropdown panels below) got clipped invisible even though
             Alpine was correctly toggling them to display:block. Confirmed at
             640px (the narrowest width this row is shown at, sm:block) that
             the items just wrap rather than actually needing horizontal
             scroll, so dropping it entirely is safe. --}}
        <nav class="base-container flex h-11 items-stretch gap-1" aria-label="Pagrindinė navigacija">
            <a href="/parduotuves" class="{{ $navLinkClass($storesActive) }}">Parduotuvės</a>

            <div class="relative" @click.outside="categoriesOpen = false">
                <button type="button" @click="categoriesOpen = !categoriesOpen; productsOpen = false" class="inline-flex h-11 shrink-0 cursor-pointer items-center gap-1 border-b-2 px-3 text-sm transition-colors hover:text-dark-green {{ $isCategoryPath ? 'border-green font-bold text-gray-900' : 'border-transparent font-medium text-gray-700' }}">
                    Kategorijos
                    <span :class="categoriesOpen && 'rotate-180'" class="transition-transform"><x-app-icon name="chevron-down" class="size-4" /></span>
                </button>
                <div x-show="categoriesOpen" x-cloak class="absolute left-0 top-full z-50 max-h-[70vh] w-[320px] overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
                    <x-category-links-list :categories="$categories" />
                </div>
            </div>

            <div class="relative" @click.outside="productsOpen = false">
                <button type="button" @click="productsOpen = !productsOpen; categoriesOpen = false" class="inline-flex h-11 shrink-0 cursor-pointer items-center gap-1 border-b-2 px-3 text-sm transition-colors hover:text-dark-green {{ $isProductKeywordPath ? 'border-green font-bold text-gray-900' : 'border-transparent font-medium text-gray-700' }}">
                    Populiarios akcijos
                    <span :class="productsOpen && 'rotate-180'" class="transition-transform"><x-app-icon name="chevron-down" class="size-4" /></span>
                </button>
                <div x-show="productsOpen" x-cloak class="absolute left-0 top-full z-50 max-h-[70vh] w-[320px] overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 shadow-lg">
                    <x-product-keyword-links-list />
                </div>
            </div>

            <a href="/leidiniai" class="{{ $navLinkClass($leafletsActive) }}">Leidiniai</a>
            <a href="/akcijos?order=price_discount_proc_max" class="{{ $navLinkClass($topTodayActive) }}">Top akcijos</a>
        </nav>
    </div>

    {{-- Mobile "Meniu" bottom sheet, ported from categories-popup.tsx --}}
    <div x-show="menuOpen" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="menuOpen = false"></button>
        <div
            x-show="menuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[82vh] flex-col overflow-hidden rounded-t-[22px] bg-white shadow-xl"
        >
            <div class="flex shrink-0 justify-center pb-1 pt-2">
                <div class="h-1 w-12 rounded-full bg-gray-300"></div>
            </div>
            <div class="flex shrink-0 items-center justify-between border-b px-4 py-2.5">
                <h2 class="text-base font-bold leading-tight">Meniu</h2>
                <button type="button" @click="menuOpen = false" class="cursor-pointer rounded-full p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Uždaryti">
                    <x-app-icon name="x" class="size-5" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                <div class="space-y-5">
                    <div class="space-y-1">
                        <a href="/parduotuves" @click="menuOpen = false" class="flex w-full rounded-lg px-1 py-2.5 text-sm font-semibold transition-colors hover:bg-gray-50 {{ $storesActive ? 'text-green' : 'text-gray-900' }}">Parduotuvės</a>
                        <a href="/leidiniai" @click="menuOpen = false" class="flex w-full rounded-lg px-1 py-2.5 text-sm font-semibold transition-colors hover:bg-gray-50 {{ $leafletsActive ? 'text-green' : 'text-gray-900' }}">Leidiniai</a>
                        <a href="/akcijos?order=price_discount_proc_max" @click="menuOpen = false" class="flex w-full rounded-lg px-1 py-2.5 text-sm font-semibold transition-colors hover:bg-gray-50 {{ $topTodayActive ? 'text-green' : 'text-gray-900' }}">Top akcijos</a>
                    </div>
                    <div class="space-y-1 border-t pt-4">
                        @auth
                            <a href="/favorites" @click="menuOpen = false" class="flex w-full items-center gap-3 rounded-lg px-1 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                <x-app-icon name="heart" class="size-5 shrink-0" />
                                <span class="flex-1 text-left">Mano favoritai</span>
                            </a>
                            <form method="POST" action="/logout" class="rounded-lg border border-gray-200 px-3 py-3">
                                @csrf
                                <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->name ?? auth()->user()->email }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                <button type="submit" class="mt-2 flex h-9 w-full items-center gap-2 text-sm text-gray-700">
                                    <x-app-icon name="log-out" class="size-4" />
                                    Atsijungti
                                </button>
                            </form>
                        @else
                            <button type="button" @click="menuOpen = false; $store.authModal.open = true" class="flex w-full items-center gap-3 rounded-lg px-1 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                <x-app-icon name="heart" class="size-5 shrink-0" />
                                <span>Mano favoritai</span>
                            </button>
                            <button type="button" @click="menuOpen = false; $store.authModal.open = true" class="flex w-full items-center gap-3 rounded-lg px-1 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                <x-app-icon name="user" class="size-5 shrink-0" />
                                <span>Prisijungti</span>
                            </button>
                        @endauth
                    </div>
                    <div class="border-t pt-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Kategorijos</p>
                        <x-category-links-list :categories="$categories" />
                    </div>
                    <div class="border-t pt-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Populiarios akcijos</p>
                        <x-product-keyword-links-list />
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
