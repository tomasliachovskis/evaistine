@props(['categories' => [], 'stores' => []])

@php
    use App\Support\StoreDisplayMeta;

    $activeTab = null;
    if (request()->is('/')) {
        $activeTab = 'home';
    } elseif (request()->is('favorites')) {
        $activeTab = 'favorites';
    } elseif (request()->is('leidiniai') || request()->is('leidinys/*')) {
        $activeTab = 'leaflets';
    } elseif (request()->is('vaistines') || request()->is('vaistines/*')) {
        $activeTab = 'stores';
    } elseif (request()->is('akcijos') || request()->is('akcijos/paieska*')) {
        $activeTab = 'products';
    } elseif (request()->is('akcijos/*')) {
        $segment = explode('/', trim(request()->path(), '/'))[1] ?? null;
        $activeTab = ($segment && StoreDisplayMeta::isStoreSlug($segment)) ? 'stores' : 'categories';
    }

    // Four worded tabs (16px labels fit four across even at 360px; five
    // did not). Always visible: a menu that slides away on scroll confuses
    // older readers.
    $itemClass = fn (bool $active) => 'relative mx-1 my-1.5 flex min-h-16 min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 text-xs font-semibold leading-none transition-colors '
        . ($active ? 'bg-green-soft text-dark-green' : 'text-gray-800');
@endphp

{{-- Ported from discount/src/components/common/mobile-bottom-nav.tsx --}}
<nav
    @open-categories-sheet.window="categoriesOpen = true"
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
    data-bottom-nav
    style="view-transition-name: bottom-nav"
    class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-gray-200 bg-white shadow-[0_-4px_20px_rgba(15,23,42,0.08)] sm:hidden"
    aria-label="Mobili navigacija"
>
    <div class="grid grid-cols-4 pb-[env(safe-area-inset-bottom)]">
        <a href="/" data-ga-event="mobile_nav_click" data-ga-item="home" class="{{ $itemClass($activeTab === 'home') }}" @if ($activeTab === 'home') aria-current="page" @endif>
            <x-app-icon name="home" class="size-7" style="{{ $activeTab === 'home' ? 'stroke-width:2.25' : '' }}" />
            <span class="truncate">Pradžia</span>
        </a>
        <a href="/akcijos" data-ga-event="mobile_nav_click" data-ga-item="products" class="{{ $itemClass(in_array($activeTab, ['products', 'categories', 'stores'], true)) }}" @if (in_array($activeTab, ['products', 'categories', 'stores'], true)) aria-current="page" @endif>
            <x-app-icon name="tag" class="size-7" />
            <span class="truncate">Akcijos</span>
        </a>
        <a href="/leidiniai" data-ga-event="mobile_nav_click" data-ga-item="leaflets" class="{{ $itemClass($activeTab === 'leaflets') }}" @if ($activeTab === 'leaflets') aria-current="page" @endif>
            <x-app-icon name="bookmark" class="size-7" />
            <span class="truncate">Leidiniai</span>
        </a>
        @auth
            <a href="/favorites" data-ga-event="mobile_nav_click" data-ga-item="favorites" class="{{ $itemClass($activeTab === 'favorites') }}" @if ($activeTab === 'favorites') aria-current="page" @endif>
                <span class="relative inline-flex">
                    <x-app-icon name="heart" class="size-7" style="{{ $activeTab === 'favorites' ? 'stroke-width:2.25' : '' }}" />
                    <livewire:favorites-badge />
                </span>
                <span class="truncate">Stebimos</span>
            </a>
        @else
            <button type="button" @click="$store.authModal.open = true" data-ga-event="mobile_nav_click" data-ga-item="favorites" class="{{ $itemClass(false) }}">
                <x-app-icon name="heart" class="size-7" />
                <span class="truncate">Stebimos</span>
            </button>
        @endauth
    </div>

    {{-- Bottom sheets, ported from mobile-bottom-sheet.tsx.

         x-teleport to <body>: <nav> above is `fixed` + `will-change-transform`,
         which makes it its own stacking context — same trap already hit and
         documented in site-header.blade.php's menuOpen/categoriesNavOpen
         modals. Without teleporting, these sheets' z-[9999] is only ranked
         against <nav>'s own children, capped at <nav>'s z-40 in the page's
         real stacking order — so any page-level z-[60] sticky bar (e.g.
         <x-leaflet-quick-links> on /leidinys/{store}) paints on top of the
         sheet instead of under it. Confirmed live 2026-09-22 on
         /leidinys/aibe. Teleporting escapes <nav>'s stacking context
         entirely, same fix as the header. --}}
    <template x-teleport="body">
    <div x-show="categoriesOpen" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="categoriesOpen = false"></button>
        <div
            x-show="categoriesOpen"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[82vh] flex-col overflow-hidden rounded-t-3xl bg-white shadow-xl"
        >
            <div class="flex shrink-0 justify-center pb-1 pt-3"><div class="h-1.5 w-14 rounded-full bg-gray-400"></div></div>
            <div class="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-3">
                <h2 class="text-2xl font-bold leading-tight">Kategorijos</h2>
                <button type="button" class="sheet-close" @click="categoriesOpen = false" aria-label="Uždaryti">
                    <x-app-icon name="x" class="size-7" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 max-h-[calc(82vh-68px)]">
                <div class="grid grid-cols-1 gap-2.5">
                    @forelse ($categories as $category)
                        <a href="/akcijos/{{ $category['slug'] }}" class="flex min-h-16 items-center gap-3 rounded-xl border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <div class="flex min-w-0 flex-1 items-center justify-between gap-3">
                                <span class="line-clamp-2 text-lg font-semibold leading-snug text-gray-900">{{ $category['name'] }}</span>
                                <span class="shrink-0 text-base font-bold tabular-nums text-gray-700">{{ number_format($category['discounts_count'] ?? 0, 0, ',', ' ') }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Kategorijos bus rodomos čia.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    </template>

    <template x-teleport="body">
    <div x-show="storesOpen" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="storesOpen = false"></button>
        <div
            x-show="storesOpen"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[82vh] flex-col overflow-hidden rounded-t-3xl bg-white shadow-xl"
        >
            <div class="flex shrink-0 justify-center pb-1 pt-3"><div class="h-1.5 w-14 rounded-full bg-gray-400"></div></div>
            <div class="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-3">
                <h2 class="text-2xl font-bold leading-tight">Vaistinės</h2>
                <button type="button" class="sheet-close" @click="storesOpen = false" aria-label="Uždaryti">
                    <x-app-icon name="x" class="size-7" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 max-h-[calc(82vh-68px)]">
                <div class="grid grid-cols-2 gap-2">
                    @forelse ($stores as $store)
                        @php
                            $discountsCount = $store['discounts_count'] ?? 0;
                            $leafletsCount = $store['leaflets_count'] ?? 0;
                        @endphp
                        @continue($discountsCount === 0 && $leafletsCount === 0)
                        {{-- Leaflet-only stores: link their leaflet hub directly (their /akcijos URL 301s there). --}}
                        <a href="{{ ($store['shows_discounts_page'] ?? true) ? '/akcijos/' . $store['slug'] : '/leidinys/' . $store['slug'] }}" class="flex flex-col items-start gap-2 rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50">
                            <x-store-logo :slug="$store['slug']" :name="$store['name']" size="lg" />
                            {{-- Both rows always render (one `invisible` when its count is 0)
                                 instead of being conditionally omitted — every card in the grid
                                 then reserves the same height regardless of whether a store has
                                 leaflets, so rows don't jump around as the grid lays out. --}}
                            <div class="flex flex-col items-start gap-0.5">
                                <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums text-gray-700 {{ $discountsCount > 0 ? '' : 'invisible' }}">
                                    {{-- x-app-icon's own default class is 'size-5' — since it's merged
                                         AFTER whatever class we pass, a plain smaller size-* class here
                                         loses the cascade (Tailwind emits size-3.5's rule before size-5's,
                                         so size-5 wins despite coming first in the class list). The !
                                         important modifier is the only way to actually shrink it. --}}
                                    <x-app-icon name="tag" class="!size-3.5 shrink-0 text-gray-400" />
                                    {{ \App\Support\LithuanianPlural::formatCount($discountsCount) }} {{ \App\Support\LithuanianPlural::discountWord($discountsCount) }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold tabular-nums text-gray-500 {{ $leafletsCount > 0 ? '' : 'invisible' }}">
                                    <x-app-icon name="bookmark" class="!size-3.5 shrink-0 text-gray-400" />
                                    {{ \App\Support\LithuanianPlural::formatCount($leafletsCount) }} {{ \App\Support\LithuanianPlural::leafletWord($leafletsCount) }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Vaistinės bus rodomos čia.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    </template>
</nav>
