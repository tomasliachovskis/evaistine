@props(['categories' => [], 'stores' => []])

@php
    use App\Support\StoreDisplayMeta;

    $activeTab = null;
    if (request()->is('/')) {
        $activeTab = 'home';
    } elseif (request()->is('favorites')) {
        $activeTab = 'favorites';
    } elseif (request()->is('parduotuves') || request()->is('parduotuves/*') || request()->is('leidinys/*')) {
        $activeTab = 'stores';
    } elseif (request()->is('akcijos') || request()->is('akcijos/paieska*')) {
        $activeTab = 'products';
    } elseif (request()->is('akcijos/*')) {
        $segment = explode('/', trim(request()->path(), '/'))[1] ?? null;
        $activeTab = ($segment && StoreDisplayMeta::isStoreSlug($segment)) ? 'stores' : 'categories';
    }

    $itemClass = fn (bool $active) => 'relative flex min-w-0 flex-col items-center justify-center gap-1 px-1 py-2.5 text-[11px] font-medium leading-none transition-colors '
        . ($active ? 'text-green' : 'text-[#4b5563]');
@endphp

{{-- Ported from discount/src/components/common/mobile-bottom-nav.tsx --}}
<nav
    x-data="{
        navVisible: true,
        lastScrollY: 0,
        categoriesOpen: false,
        storesOpen: false,
        init() {
            this.lastScrollY = window.scrollY;
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (this.categoriesOpen || this.storesOpen) { return; }
                if (ticking) { return; }
                ticking = true;
                requestAnimationFrame(() => {
                    const currentY = window.scrollY;
                    const delta = currentY - this.lastScrollY;
                    if (currentY <= 12) { this.navVisible = true; }
                    else if (delta > 10) { this.navVisible = false; }
                    else if (delta < -10) { this.navVisible = true; }
                    this.lastScrollY = currentY;
                    ticking = false;
                });
            }, { passive: true });
        },
    }"
    x-cloak
    :class="!navVisible && 'pointer-events-none translate-y-full'"
    class="fixed inset-x-0 bottom-0 z-40 rounded-t-2xl border-t border-gray-200 bg-white shadow-[0_-4px_20px_rgba(15,23,42,0.08)] transition-transform duration-300 ease-out will-change-transform sm:hidden"
    aria-label="Mobili navigacija"
>
    <div class="grid grid-cols-5 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
        <a href="/" class="{{ $itemClass($activeTab === 'home') }}">
            <span class="relative inline-flex"><x-app-icon name="home" class="size-[22px]" style="{{ $activeTab === 'home' ? 'stroke-width:2.25' : '' }}" /></span>
            <span class="truncate">Pagrindinis</span>
        </a>
        <button type="button" @click="categoriesOpen = true; storesOpen = false" class="{{ $itemClass(false) }}" :class="(categoriesOpen || '{{ $activeTab }}' === 'categories') && 'text-green'">
            <span class="relative inline-flex"><x-app-icon name="layout-grid" class="size-[22px]" /></span>
            <span class="truncate">Kategorijos</span>
        </button>
        @auth
            <a href="/favorites" class="{{ $itemClass($activeTab === 'favorites') }}">
                <span class="relative inline-flex">
                    <x-app-icon name="heart" class="size-[22px]" style="{{ $activeTab === 'favorites' ? 'stroke-width:2.25' : '' }}" />
                    <livewire:favorites-badge />
                </span>
                <span class="truncate">Stebimos</span>
            </a>
        @else
            <button type="button" @click="$store.authModal.open = true" class="{{ $itemClass(false) }}">
                <span class="relative inline-flex"><x-app-icon name="heart" class="size-[22px]" /></span>
                <span class="truncate">Stebimos</span>
            </button>
        @endauth
        <button type="button" @click="storesOpen = true; categoriesOpen = false" class="{{ $itemClass(false) }}" :class="(storesOpen || '{{ $activeTab }}' === 'stores') && 'text-green'">
            <span class="relative inline-flex"><x-app-icon name="store" class="size-[22px]" /></span>
            <span class="truncate">Parduotuvės</span>
        </button>
        <a href="/akcijos" class="{{ $itemClass($activeTab === 'products') }}">
            <span class="relative inline-flex"><x-app-icon name="package" class="size-[22px]" style="{{ $activeTab === 'products' ? 'stroke-width:2.25' : '' }}" /></span>
            <span class="truncate">Produktai</span>
        </a>
    </div>

    {{-- Bottom sheets, ported from mobile-bottom-sheet.tsx --}}
    <div x-show="categoriesOpen" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="categoriesOpen = false"></button>
        <div
            x-show="categoriesOpen"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[82vh] flex-col overflow-hidden rounded-t-[22px] bg-white shadow-xl"
        >
            <div class="flex shrink-0 justify-center pb-1 pt-2"><div class="h-1 w-12 rounded-full bg-gray-300"></div></div>
            <div class="flex shrink-0 items-center justify-between border-b px-4 py-2.5">
                <h2 class="text-base font-bold leading-tight">Kategorijos</h2>
                <button type="button" class="cursor-pointer rounded-full p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Uždaryti" @click="categoriesOpen = false">
                    <x-app-icon name="x" class="size-5" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 max-h-[calc(82vh-68px)]">
                <div class="grid grid-cols-1 gap-2.5">
                    @forelse ($categories as $category)
                        <a href="/akcijos/{{ $category['slug'] }}" class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <img src="/assets/products/{{ $category['slug'] }}.png" alt="" width="48" height="44" class="size-12 shrink-0 object-contain" onerror="this.style.visibility='hidden'">
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-3">
                                <span class="line-clamp-2 text-[15px] font-semibold leading-snug text-gray-900">{{ $category['name'] }}</span>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-gray-700">{{ number_format($category['discounts_count'] ?? 0, 0, ',', ' ') }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Kategorijos bus rodomos čia.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div x-show="storesOpen" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="storesOpen = false"></button>
        <div
            x-show="storesOpen"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[82vh] flex-col overflow-hidden rounded-t-[22px] bg-white shadow-xl"
        >
            <div class="flex shrink-0 justify-center pb-1 pt-2"><div class="h-1 w-12 rounded-full bg-gray-300"></div></div>
            <div class="flex shrink-0 items-center justify-between border-b px-4 py-2.5">
                <h2 class="text-base font-bold leading-tight">Parduotuvės</h2>
                <button type="button" class="cursor-pointer rounded-full p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Uždaryti" @click="storesOpen = false">
                    <x-app-icon name="x" class="size-5" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 max-h-[calc(82vh-68px)]">
                <div class="grid grid-cols-2 gap-2">
                    @forelse ($stores as $store)
                        <a href="/akcijos/{{ $store['slug'] }}" class="flex flex-col items-center justify-center gap-2 rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <img src="/assets/stores/{{ $store['slug'] }}.svg" alt="{{ $store['name'] }}" class="h-12 w-full max-w-[100px] object-contain">
                            <span class="text-sm font-bold tabular-nums text-gray-700">{{ number_format($store['discounts_count'] ?? 0, 0, ',', ' ') }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Parduotuvės bus rodomos čia.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</nav>
