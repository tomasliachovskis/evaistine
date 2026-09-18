@php
    $flyer = $listingMeta['flyer'];
    $pages = $listingMeta['pages'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $otherLeaflets = collect($listingMeta['leaflets'] ?? [])->filter(fn ($l) => ($l['slug'] ?? null) !== $flyer['slug'])->values();
    // Y.m.d (dots), not Y-m-d — matches every listing card's date range
    // format (leaflets/hub.blade.php, components/leaflet-card.blade.php).
    $dateRange = ($flyer['valid_from'] ?? null) && ($flyer['valid_to'] ?? null)
        ? \Illuminate\Support\Carbon::parse($flyer['valid_from'])->format('Y.m.d') . ' – ' . \Illuminate\Support\Carbon::parse($flyer['valid_to'])->format('Y.m.d')
        : null;
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? $flyer['title'])"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <main class="base-container pb-6 pt-3 sm:pb-8 sm:pt-4">
        {{-- The leaflet page image is the whole point of this page — it used
             to render after a full-width breadcrumb/H1/dates/pill-bar stack,
             squeezed into a 7fr/3fr column beside a "Kiti leidiniai"
             sidebar, with no height cap on the image. Net effect: on a
             normal screen, most of the leaflet page needed scrolling just to
             see it once, let alone at a readable size (confirmed live
             2026-09-18). Flipped here: the image is the hero column, sized
             to fill the viewport height available to it; everything that
             used to sit above it (breadcrumb, title, dates, follow CTA, the
             quick-links pill bar, "Kiti leidiniai") moves into a narrow side
             rail instead. Below lg, the rail collapses to a single column
             below the viewer — same relative order mobile already had. --}}
        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
            @if (!empty($pages))
                x-data="{ currentPage: 1, totalPages: {{ count($pages) }}, fullscreen: false, touchStartX: null }"
                @keydown.window="
                    if ($event.key === 'ArrowRight') currentPage = Math.min(totalPages, currentPage + 1);
                    if ($event.key === 'ArrowLeft') currentPage = Math.max(1, currentPage - 1);
                "
                @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
                @webkitfullscreenchange.window="fullscreen = !!document.webkitFullscreenElement"
            @endif
        >
            @if (!empty($pages))
                <div class="order-1 min-w-0">
                    <div
                        x-ref="viewerFrame"
                        class="relative flex h-[75vh] items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50 lg:h-[calc(100vh-5.5rem)]"
                        :class="fullscreen && 'fixed inset-0 z-[9999] h-auto rounded-none border-none bg-black/95 p-4'"
                        @touchstart="touchStartX = $event.touches[0].clientX"
                        @touchend="
                            if (touchStartX === null) return;
                            const dx = $event.changedTouches[0].clientX - touchStartX;
                            if (Math.abs(dx) > 50) {
                                if (dx < 0) currentPage = Math.min(totalPages, currentPage + 1);
                                else currentPage = Math.max(1, currentPage - 1);
                            }
                            touchStartX = null;
                        "
                    >
                        @foreach ($pages as $page)
                            <img
                                x-show="currentPage === {{ $page['page_number'] }}"
                                x-cloak
                                src="{{ $page['image_url'] }}"
                                alt="{{ $flyer['title'] }} – {{ $page['page_number'] }} puslapis"
                                class="block h-full w-auto max-w-full object-contain"
                                :class="fullscreen && 'mx-auto max-h-full'"
                                loading="{{ $page['page_number'] <= 2 ? 'eager' : 'lazy' }}"
                            >
                        @endforeach

                        <button
                            type="button"
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            x-show="currentPage > 1"
                            aria-label="Ankstesnis puslapis"
                            class="absolute left-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon name="chevron-right" class="size-4 rotate-180" />
                        </button>
                        <button
                            type="button"
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            x-show="currentPage < totalPages"
                            aria-label="Kitas puslapis"
                            class="absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon name="chevron-right" class="size-4" />
                        </button>
                        <button
                            type="button"
                            @click="
                                if (fullscreen) {
                                    (document.exitFullscreen && document.exitFullscreen()) || (document.webkitExitFullscreen && document.webkitExitFullscreen());
                                } else {
                                    const el = $refs.viewerFrame;
                                    (el.requestFullscreen && el.requestFullscreen()) || (el.webkitRequestFullscreen && el.webkitRequestFullscreen());
                                }
                            "
                            :aria-label="fullscreen ? 'Uždaryti pilną ekraną' : 'Pilnas ekranas'"
                            class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon x-show="!fullscreen" name="maximize" class="size-4" />
                            <x-app-icon x-show="fullscreen" name="x" class="size-4" />
                        </button>
                    </div>

                    {{-- Mobile only — desktop shows this same pager in the
                         rail, right under the date range, instead (see
                         below). --}}
                    <div class="mt-3 lg:hidden">
                        @include('leaflets.partials.leaflet-pager', ['pages' => $pages])
                    </div>
                </div>
            @elseif (!empty($flyer['image_url']))
                <div class="order-1 min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <img src="{{ $flyer['image_url'] }}" alt="{{ $flyer['title'] }}" class="block h-auto w-full">
                </div>
            @else
                <div class="order-1 min-w-0 rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                    Leidinio puslapiai dar ruošiami.
                    <a href="/leidinys/{{ $storeSlug }}" class="font-semibold text-dark-green">Grįžti į {{ $storeName }} leidinius</a>
                </div>
            @endif

            {{-- data-sticky-filter-bar: gates the header's row2 (nav links)
                 scroll-hide behavior — this page previously had none of the
                 sticky bars that mechanism looks for, so row2 stayed
                 permanently visible here, wasting space. Presence-only, read
                 once by site-header.blade.php's scroll listener; doesn't
                 need to sit on the thing that visually docks in that space
                 (there isn't one on this page), just needs to exist. --}}
            <aside data-sticky-filter-bar class="order-2 flex min-w-0 flex-col gap-4">
                {{-- Compact breadcrumb + title instead of <x-breadcrumb-trail>
                     (full-width, its own row) and <x-type-hero> (h1 with no
                     size class — inherits the global, large h1 rule meant
                     for full-width headers, which ballooned to 4+ wrapped
                     lines at this rail's ~320px width, confirmed live
                     2026-09-18). Both moved/rebuilt here instead, sized for
                     the rail. --}}
                <nav class="flex flex-wrap items-center gap-1 text-xs text-gray-500" aria-label="Naršymo kelias">
                    @foreach ($breadcrumbs as $index => $crumb)
                        @if ($index > 0)<x-app-icon name="arrow-right" class="size-3 text-gray-300" />@endif
                        <a href="{{ $crumb['href'] }}" class="transition-colors hover:text-green {{ $canonical === $crumb['href'] ? 'font-medium text-green' : '' }}">{{ $crumb['name'] }}</a>
                    @endforeach
                </nav>
                <div>
                    <h1 class="text-xl font-bold leading-tight">{{ $flyer['title'] }}</h1>
                    @if ($dateRange)
                        <p class="mt-1 text-sm text-gray-600">{{ $dateRange }}</p>
                    @endif
                </div>

                {{-- Desktop only — mobile shows this same pager just below
                     the image instead (see the viewer column above). --}}
                @if (!empty($pages))
                    <div class="hidden lg:block">
                        @include('leaflets.partials.leaflet-pager', ['pages' => $pages])
                    </div>
                @endif

                <x-leaflet-quick-links :store-slug="$storeSlug" :store-name="$storeName" :total-offers="$totalOffers" :show-leaflets-link="true" :leaflets-count="$listingMeta['leaflets_count'] ?? 0" :compact="true" />

                <div class="section-card">
                    <h2 class="section-heading mb-3">Kiti {{ $storeName }} leidiniai</h2>
                    @if ($otherLeaflets->isEmpty())
                        <p class="text-sm text-gray-500">Kitų leidinių nėra.</p>
                    @else
                        <div class="flex flex-col gap-2">
                            @foreach ($otherLeaflets->take(6) as $other)
                                <a href="{{ $other['view_url'] ?? "/leidinys/{$storeSlug}" }}" class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-green/5">
                                    @if (!empty($other['image_url']))
                                        <img src="{{ $other['image_url'] }}" alt="" class="h-20 w-16 shrink-0 rounded-md object-cover">
                                    @endif
                                    <span class="line-clamp-2 text-sm font-medium text-gray-900">{{ $other['title'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="/akcijos/{{ $storeSlug }}" class="section-link">
                    Visos {{ $storeName }} akcijos
                    <x-app-icon name="chevron-right" class="size-3.5" />
                </a>
            </aside>
        </div>
    </main>
</x-layouts.app>
