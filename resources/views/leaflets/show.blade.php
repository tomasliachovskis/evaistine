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
    // Real cover image (confirmed live, e.g. /storage/flyers/pages/15/page-1.webp)
    // was previously in no structured data anywhere on this page, despite
    // being the whole point of the page.
    $imageObjectSchema = ! empty($flyer['image_url']) ? array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ImageObject',
        'contentUrl' => url($flyer['image_url']),
        'name' => $flyer['title'],
        'representativeOfPage' => true,
    ]) : null;
@endphp

<x-layouts.app
    :title="$seo['meta_title'] ?? ($seo['seo_title'] ?? $flyer['title'])"
    :description="$seo['meta_description'] ?? ($seo['seo_description'] ?? null)"
    :canonical="$canonical"
    :robots="$robots"
    :og-image="$flyer['image_url'] ?? null"
>
    @push('head')
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @if ($imageObjectSchema)
            <script type="application/ld+json">{!! json_encode($imageObjectSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @if ($flyerOffersSchema)
            <script type="application/ld+json">{!! json_encode($flyerOffersSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
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
                x-data="{
                    currentPage: 1, totalPages: {{ count($pages) }}, fullscreen: false, touchStartX: null,
                    viewerHeight: null,
                    // Pages beyond the first two are loading='lazy' and
                    // hidden (x-show) until picked — the browser only starts
                    // fetching them once shown, so jumping to e.g. page 5
                    // flashed a blank/broken frame for as long as that fetch
                    // took (confirmed live 2026-09-18). Track per-page load
                    // state so a spinner can cover that gap instead.
                    loadedPages: {},
                    pageLoaded(page) { return !!this.loadedPages[page]; },
                    // Height was a guessed calc(100vh - Nrem) constant, tied to
                    // this page's current header/padding sizes — any future
                    // change to those (or a device's own chrome/safe-area
                    // quirks) would silently reopen the dead-space gap this was
                    // meant to fix. Measure the real remaining space instead:
                    // however tall the frame's own top offset actually renders,
                    // fill everything below it down to a small bottom margin.
                    sizeViewer() {
                        if (this.fullscreen || !this.$refs.viewerFrame) return;
                        const top = this.$refs.viewerFrame.getBoundingClientRect().top;
                        this.viewerHeight = Math.max(320, window.innerHeight - top - 16);
                    },
                    init() {
                        this.$nextTick(() => {
                            this.sizeViewer();
                            // Eager-loaded pages (1-2) can finish loading
                            // before this @load listener is even bound —
                            // catch that race so the spinner doesn't get
                            // stuck showing over an already-loaded image.
                            this.$refs.viewerFrame?.querySelectorAll('img').forEach(img => {
                                if (img.complete) img.dispatchEvent(new Event('load'));
                            });
                        });
                        window.addEventListener('resize', () => this.sizeViewer());
                        // Pseudo-fullscreen (see toggleFullscreen) doesn't get
                        // the browser's own scroll lock, so lock the page
                        // behind the fixed overlay ourselves.
                        this.$watch('fullscreen', (on) => {
                            document.documentElement.classList.toggle('overflow-hidden', on);
                            if (!on) this.$nextTick(() => this.sizeViewer());
                        });
                    },
                    // iPhone Safari has no element Fullscreen API at all
                    // (requestFullscreen/webkitRequestFullscreen are both
                    // undefined — only <video> can go fullscreen there), so
                    // the button silently did nothing on iPhones (reported
                    // 2026-09-23, iPhone 11 Pro). Fall back to a CSS-only
                    // fixed overlay whenever the real API is missing or
                    // rejects.
                    toggleFullscreen() {
                        if (this.fullscreen) {
                            if (document.fullscreenElement) document.exitFullscreen();
                            else if (document.webkitFullscreenElement) document.webkitExitFullscreen();
                            else this.fullscreen = false;
                            return;
                        }
                        const el = this.$refs.viewerFrame;
                        const request = el.requestFullscreen || el.webkitRequestFullscreen;
                        if (request) {
                            try {
                                const result = request.call(el);
                                if (result && result.catch) result.catch(() => { this.fullscreen = true; });
                                return;
                            } catch (e) {}
                        }
                        this.fullscreen = true;
                    },
                }"
                @keydown.window="
                    if ($event.key === 'ArrowRight') currentPage = Math.min(totalPages, currentPage + 1);
                    if ($event.key === 'ArrowLeft') currentPage = Math.max(1, currentPage - 1);
                    if ($event.key === 'Escape' && fullscreen && !document.fullscreenElement && !document.webkitFullscreenElement) fullscreen = false;
                "
                @leaflet-goto.window="currentPage = $event.detail; $refs.viewerFrame.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
                @webkitfullscreenchange.window="fullscreen = !!document.webkitFullscreenElement"
            @endif
        >
            @if (!empty($pages))
                <div class="order-1 min-w-0">
                    <div
                        x-ref="viewerFrame"
                        class="relative flex h-[75vh] items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                        :style="fullscreen ? 'height: 100dvh' : (viewerHeight ? `height: ${viewerHeight}px` : '')"
                        {{-- Important modifiers: the static 'relative' otherwise
                             wins over 'fixed' in the compiled CSS order, so the
                             pseudo-fullscreen overlay never left the page flow. --}}
                        :class="fullscreen && 'fixed! inset-0 z-[9999] rounded-none! border-none! bg-black/95! p-4'"
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
                                @load="loadedPages[{{ $page['page_number'] }}] = true"
                                x-on:error="loadedPages[{{ $page['page_number'] }}] = true"
                            >
                        @endforeach

                        <div
                            x-show="currentPage > 0 && !pageLoaded(currentPage)"
                            x-cloak
                            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-gray-50"
                        >
                            <div class="size-8 animate-spin rounded-full border-[3px] border-gray-200 border-t-green"></div>
                        </div>

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
                            @click="toggleFullscreen()"
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

                <x-leaflet-quick-links :store-slug="$storeSlug" :store-name="$storeName" :total-offers="$totalOffers" :shows-discounts-page="$showsDiscountsPage" :show-leaflets-link="true" :leaflets-count="$listingMeta['leaflets_count'] ?? 0" :compact="true" />

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

                @if ($showsDiscountsPage)
                    <a href="/akcijos/{{ $storeSlug }}" class="section-link">
                        Visos {{ $storeName }} akcijos
                        <x-app-icon name="chevron-right" class="size-3.5" />
                    </a>
                @endif
            </aside>
        </div>

        {{-- The pages above are images only; this is the flyer's content as
             text (offers Gemini extracted from this flyer, store_flyer_id),
             each linking to its product page. Flex-wrap, not CSS grid (see
             livewire/discount-filters.blade.php). --}}
        @if (!empty($flyerOffers))
            <section class="mt-8" aria-labelledby="flyer-offers-heading" x-data>
                <h2 id="flyer-offers-heading" class="section-heading mb-3">
                    Šio leidinio akcijos
                    <span class="font-normal text-gray-500">({{ $flyerOffersTotal }})</span>
                </h2>
                {{-- Grouped by the flyer page each offer was printed on, in
                     the flyer's own order (see getStoreLeaflet()). Offers
                     with no known page come last, under no page heading. --}}
                <div class="flex flex-col gap-6">
                    @foreach (collect($flyerOffers)->groupBy(fn ($d) => $d['flyer_page'] ?? 0) as $flyerPage => $pageOffers)
                        <div>
                            @if ($flyerPage > 0)
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $flyerPage }} puslapis</h3>
                                    @if (!empty($pages))
                                        <button
                                            type="button"
                                            @click="$dispatch('leaflet-goto', {{ $flyerPage }})"
                                            class="section-link"
                                        >
                                            Žiūrėti leidinyje
                                            <x-app-icon name="chevron-right" class="size-3.5" />
                                        </button>
                                    @endif
                                </div>
                            @endif
                            <div class="flex w-full flex-wrap gap-2 sm:gap-3">
                                @foreach ($pageOffers as $deal)
                                    <x-deal-card
                                        :deal="$deal"
                                        :stretch="false"
                                        :context-store-slug="$storeSlug"
                                        source="flyer_offers"
                                        class="w-[calc(50%-0.25rem)] sm:w-[calc(33.333%-0.5rem)] lg:w-[calc(25%-0.5625rem)] xl:w-[calc(20%-0.6rem)]"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($flyerOffersTotal > count($flyerOffers) && $showsDiscountsPage)
                    <a href="/akcijos/{{ $storeSlug }}" class="section-link mt-4">
                        Visos {{ $storeName }} akcijos
                        <x-app-icon name="chevron-right" class="size-3.5" />
                    </a>
                @endif
            </section>
        @endif
    </main>
</x-layouts.app>
