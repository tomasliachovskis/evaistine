@php
    $flyer = $listingMeta['flyer'];
    $pages = $listingMeta['pages'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $leafletEnded = ! empty($flyer['valid_to']) && \Illuminate\Support\Carbon::parse($flyer['valid_to'])->endOfDay()->isPast();
    // In words ("rugsėjo 8–14 d."), same as components/leaflet-card.blade.php.
    $dateRange = ($flyer['valid_from'] ?? null) && ($flyer['valid_to'] ?? null)
        ? \App\Support\LithuanianDate::range(\Illuminate\Support\Carbon::parse($flyer['valid_from']), \Illuminate\Support\Carbon::parse($flyer['valid_to']))
        : null;
    // Real cover image (confirmed live, e.g. /storage/flyers/pages/15/page-1.webp)
    // was previously in no structured data anywhere on this page, despite
    // being the whole point of the page.
    // First few product names printed on each page, for the page image's
    // alt text (image search), from the offers extracted from this flyer.
    $pageProductNames = collect($flyerOffers ?? [])
        ->filter(fn ($d) => ! empty($d['flyer_page']))
        ->groupBy('flyer_page')
        ->map(fn ($offers) => $offers->take(3)->pluck('product.name')->implode(', '));
    $imageObjectSchema = ! empty($flyer['image_url']) ? array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'ImageObject',
        'contentUrl' => url($flyer['image_url']),
        'name' => $flyer['title'],
        'representativeOfPage' => true,
    ]) : null;
@endphp

<x-layouts.app
    :breadcrumbs="$breadcrumbs ?? []"
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
        @if ($betaConfig)
            @include('leaflets.partials.beta-script')
        @endif
    @endpush

    {{-- Laid out like manoakcijos.lt's leaflet page (owner's request,
         2026-10-02): on desktop a 440px column on the left (breadcrumbs,
         store and dates, page buttons, search, this page's products, other
         leaflets) scrolls on its own beside the flyer, which fills the rest
         of the screen under the header, one page at a time, with a progress
         bar across its top. On phones the flyer comes first, flush under
         the header and full width, and the left column's content follows
         it. Sizes stay on this site's older-readers scale. --}}
    <main class="pb-6 sm:pb-8">
        <div
            class="flex flex-col lg:h-[calc(100dvh-var(--header-h))] lg:flex-row"
            @if (!empty($pages))
                x-data="{
                    @if ($betaConfig) ...window.leafletBeta(@js($betaConfig)), @endif
                    currentPage: 1, totalPages: {{ count($pages) }}, fullscreen: false, touchStartX: null,
                    viewerHeight: null,
                    viewerFitsScreen: true,
                    // Pages beyond the first two are loading='lazy' and
                    // hidden (x-show) until picked — the browser only starts
                    // fetching them once shown, so jumping to e.g. page 5
                    // flashed a blank/broken frame for as long as that fetch
                    // took (confirmed live 2026-09-18). Track per-page load
                    // state so a spinner can cover that gap instead.
                    loadedPages: {},
                    pageLoaded(page) { return !!this.loadedPages[page]; },
                    wide: false,
                    // One page at a time on every screen, like the
                    // reference (the desktop two-page spread was dropped).
                    // Kept as a list so the beta layer's per-spread helpers
                    // work unchanged.
                    spread() { return [this.currentPage]; },
                    isShown(page) { return page === this.currentPage; },
                    hasNext() { return this.currentPage < this.totalPages; },
                    hasPrev() { return this.currentPage > 1; },
                    next() { if (this.hasNext()) this.currentPage++; },
                    prev() { if (this.hasPrev()) this.currentPage--; },
                    // Page buttons: first, last and the pages around the
                    // current one, with '…' for the gaps (1 … 4 5 6 … 12).
                    pagerItems() {
                        const last = this.totalPages;
                        const pages = [...new Set([1, this.currentPage - 1, this.currentPage, this.currentPage + 1, last])]
                            .filter((p) => p >= 1 && p <= last)
                            .sort((a, b) => a - b);
                        const items = [];
                        pages.forEach((p, i) => {
                            if (i > 0 && p - pages[i - 1] > 1) items.push({ key: 'gap' + p, page: null });
                            items.push({ key: 'p' + p, page: p });
                        });
                        return items;
                    },
                    // Desktop: the frame fills its column (CSS). Phones: the
                    // page is fitted to the full screen width and the frame
                    // takes that page's height, flush under the header, so
                    // there are no gray bands around it. clientHeight, not
                    // innerHeight or visualViewport: those shrink while
                    // pinch-zoomed or as the iOS URL bar moves.
                    sizeViewer() {
                        if (this.fullscreen || !this.$refs.viewerFrame) return;
                        if (this.wide) {
                            this.viewerHeight = null;
                            return;
                        }
                        const frame = this.$refs.viewerFrame;
                        const top = frame.getBoundingClientRect().top + window.scrollY;
                        const nav = document.querySelector('[data-bottom-nav]');
                        const navHeight = nav && getComputedStyle(nav).display !== 'none' ? nav.getBoundingClientRect().height : 0;
                        const available = Math.max(320, Math.floor(document.documentElement.clientHeight - top - navHeight));
                        // Until the image has loaded its size is unknown;
                        // fill the screen meanwhile.
                        const img = frame.querySelector(`[data-page-img='${this.currentPage}']`);
                        const pageHeight = img && img.naturalWidth ? Math.round(frame.clientWidth * img.naturalHeight / img.naturalWidth) : null;
                        this.viewerHeight = pageHeight || available;
                        // Pinned (no vertical drag) when the whole page fits
                        // on screen. On a short phone a page taller than the
                        // screen has to scroll, or its bottom could never be
                        // seen.
                        this.viewerFitsScreen = !pageHeight || pageHeight <= available;
                    },
                    init() {
                        const wideQuery = window.matchMedia('(min-width: 1024px)');
                        this.wide = wideQuery.matches;
                        wideQuery.addEventListener('change', (e) => { this.wide = e.matches; this.$nextTick(() => this.sizeViewer()); });
                        // #psl-N (product pages link to the page an offer
                        // is printed on) opens that page.
                        const hashPage = parseInt((location.hash.match(/^#psl-(\d+)$/) || [])[1], 10);
                        if (hashPage >= 1 && hashPage <= this.totalPages) this.currentPage = hashPage;
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
                        // Pages can differ in size (a landscape spread among
                        // portrait pages), and on phones the frame takes the
                        // shown page's height.
                        this.$watch('currentPage', () => this.$nextTick(() => this.sizeViewer()));
                        if (this.beta) this.betaInit();
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
                    if ($event.target.closest('input, textarea')) return;
                    if ($event.key === 'ArrowRight') next();
                    if ($event.key === 'ArrowLeft') prev();
                    if ($event.key === 'Escape' && fullscreen && !document.fullscreenElement && !document.webkitFullscreenElement) fullscreen = false;
                "
                @leaflet-goto.window="currentPage = $event.detail; $refs.viewerFrame.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
                @webkitfullscreenchange.window="fullscreen = !!document.webkitFullscreenElement"
            @endif
        >
            {{-- Left column on desktop, below the flyer on phones.
                 data-sticky-filter-bar: gates the header's scroll-hide
                 behavior (presence-only, see site-header.blade.php). --}}
            <aside data-sticky-filter-bar class="order-last flex min-w-0 flex-col gap-6 p-4 lg:order-first lg:w-[440px] lg:shrink-0 lg:overflow-y-auto">
                <div>
                    <nav class="flex flex-wrap items-center gap-x-2" aria-label="Naršymo kelias">
                        @foreach ($breadcrumbs as $index => $crumb)
                            @if ($index > 0)<x-app-icon name="chevron-right" class="crumb-sep" />@endif
                            <a href="{{ $crumb['href'] }}" class="crumb-link {{ $canonical === $crumb['href'] ? 'crumb-current' : '' }}">{{ $crumb['name'] }}</a>
                        @endforeach
                    </nav>
                    <h1 class="sr-only">{{ $flyer['title'] }}</h1>
                    <div class="mt-4">
                        <x-store-logo :slug="$storeSlug" :name="$storeName" size="lg" />
                    </div>
                    @if ($dateRange)
                        <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-lg">
                            <span class="inline-flex items-center gap-1.5 text-font">
                                <x-app-icon name="calendar" class="size-5" />
                                {{ $dateRange }}
                            </span>
                            <span class="font-semibold uppercase {{ $leafletEnded ? 'text-amber-800' : 'text-dark-green' }}">{{ $leafletEnded ? 'Nebegalioja' : 'Pasiūlymas galioja' }}</span>
                        </p>
                    @endif
                </div>

                @if (!empty($pages))
                    @include('leaflets.partials.leaflet-pager', ['beta' => (bool) $betaConfig])
                @endif

                @if ($betaConfig && ! empty($betaConfig['hotspots']))
                    <button
                        type="button"
                        @click="openList()"
                        class="inline-flex min-h-12 items-center justify-center gap-2 self-start rounded-xl bg-green-soft px-4 text-lg font-bold text-dark-green hover:bg-green-soft-border focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                    >
                        <x-app-icon name="shopping-basket" class="size-6" />
                        Pirkinių sąrašas
                        <span class="tabular-nums" x-text="'(' + list.length + ')'"></span>
                    </button>
                    @include('leaflets.partials.beta.toolbar')
                @endif

                @if ($betaConfig)
                    @include('leaflets.partials.beta.below')
                @endif

                @if ($sidebarLeaflets !== [])
                    <section aria-labelledby="other-leaflets-heading">
                        <h2 id="other-leaflets-heading" class="section-heading mb-3">Taip pat žiūrėkite kitus leidinius</h2>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($sidebarLeaflets as $other)
                                <x-leaflet-card :leaflet="$other" class="w-full sm:w-[calc(50%-0.375rem)]" />
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>

            <div class="flex min-w-0 flex-col lg:h-full lg:flex-1">
                @if (!empty($pages))
                    {{-- How far through the leaflet: across the flyer
                         column, right under the header. --}}
                    <div class="h-1 shrink-0 bg-gray-200" role="progressbar" aria-label="Leidinio puslapis" aria-valuemin="1" :aria-valuemax="totalPages" :aria-valuenow="currentPage">
                        <div class="h-full bg-action transition-[width] duration-300" :style="`width: ${currentPage / totalPages * 100}%`"></div>
                    </div>
                    {{-- touch-action: pinch-zoom on phones, so a drag on the
                         flyer doesn't scroll the page up and down under the
                         finger (it is pinned in place); the swipe handlers
                         still flip pages and pinch-zoom still works. Only
                         when the page fits on screen (see sizeViewer()). --}}
                    <div
                        x-ref="viewerFrame"
                        class="relative flex h-[75vh] scroll-mt-[var(--header-h)] items-center justify-center overflow-hidden overscroll-contain bg-gray-100 lg:h-auto lg:min-h-0 lg:flex-1 lg:bg-white lg:p-2"
                        :style="(fullscreen ? 'height: 100dvh' : (viewerHeight ? `height: ${viewerHeight}px` : '')) + (wide ? '' : (viewerFitsScreen ? ';touch-action: pinch-zoom' : ';touch-action: pan-y pinch-zoom'))"
                        {{-- Important modifiers: the static 'relative' otherwise
                             wins over 'fixed' in the compiled CSS order, so the
                             pseudo-fullscreen overlay never left the page flow. --}}
                        :class="fullscreen && 'fixed! inset-0 z-[9999] bg-black/95! p-4'"
                        @touchstart="touchStartX = $event.touches[0].clientX"
                        @touchend="
                            if (touchStartX === null) return;
                            const dx = $event.changedTouches[0].clientX - touchStartX;
                            if (Math.abs(dx) > 50) {
                                if (dx < 0) next();
                                else prev();
                            }
                            touchStartX = null;
                        "
                    >
                        @if ($betaConfig)
                            {{-- Each page in a wrapper with an overlay sized to
                                 the image's real rendered rect (measure()),
                                 holding one clickable zone per product. --}}
                            @foreach ($pages as $page)
                                @php $pageNumber = $page['page_number']; @endphp
                                <div
                                    x-show="isShown({{ $pageNumber }})"
                                    x-cloak
                                    data-page-wrap="{{ $pageNumber }}"
                                    class="relative h-full w-full min-w-0"
                                >
                                    <img
                                        src="{{ $page['image_url'] }}"
                                        alt="{{ $flyer['title'] }} – {{ $pageNumber }} puslapis{{ $pageProductNames->has($pageNumber) ? ': ' . $pageProductNames[$pageNumber] : '' }}"
                                        class="block h-full w-full object-contain"
                                        loading="{{ $pageNumber <= 3 ? 'eager' : 'lazy' }}"
                                        data-page-img="{{ $pageNumber }}"
                                        @load="loadedPages[{{ $pageNumber }}] = true; $nextTick(() => { sizeViewer(); measure(); })"
                                        x-on:error="loadedPages[{{ $pageNumber }}] = true"
                                    >
                                    <div class="absolute" :style="overlayStyle({{ $pageNumber }})">
                                        <template x-for="h in hotspotsOn({{ $pageNumber }})" :key="h.id">
                                            <button
                                                type="button"
                                                class="absolute rounded-md transition-[box-shadow,background-color] duration-150 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green"
                                                :style="boxStyle(h)"
                                                :class="hotspotClass(h)"
                                                :aria-label="h.name + ', ' + euro(h.price)"
                                                @click.stop="openCard(h)"
                                                @mouseenter="hoveredId = h.id"
                                                @mouseleave="hoveredId = null"
                                            >
                                                <span
                                                    x-show="inList(h.id)"
                                                    class="pointer-events-none absolute -right-2 -top-2 flex size-7 items-center justify-center rounded-full border border-white bg-action text-white shadow"
                                                ><x-app-icon name="check" class="size-4" /></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            @foreach ($pages as $page)
                                <img
                                    x-show="isShown({{ $page['page_number'] }})"
                                    x-cloak
                                    src="{{ $page['image_url'] }}"
                                    alt="{{ $flyer['title'] }} – {{ $page['page_number'] }} puslapis{{ $pageProductNames->has($page['page_number']) ? ': ' . $pageProductNames[$page['page_number']] : '' }}"
                                    class="block h-full w-full object-contain"
                                    loading="{{ $page['page_number'] <= 3 ? 'eager' : 'lazy' }}"
                                    data-page-img="{{ $page['page_number'] }}"
                                    @load="loadedPages[{{ $page['page_number'] }}] = true; $nextTick(() => sizeViewer())"
                                    x-on:error="loadedPages[{{ $page['page_number'] }}] = true"
                                >
                            @endforeach
                        @endif

                        <div
                            x-show="!pageLoaded(currentPage)"
                            x-cloak
                            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-gray-50"
                        >
                            <div class="size-8 animate-spin rounded-full border-[3px] border-gray-200 border-t-green"></div>
                        </div>

                        {{-- Dark round arrows at the column edges, like
                             manoakcijos.lt; smaller on phones so they cover
                             less of the page. --}}
                        <button
                            type="button"
                            @click="prev()"
                            x-show="hasPrev()"
                            aria-label="Ankstesnis puslapis"
                            class="absolute left-1 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-gray-900/70 text-white shadow-sm hover:bg-gray-900/85 lg:left-3 lg:size-12"
                        >
                            <x-app-icon name="arrow-right" class="size-5 rotate-180 lg:size-6" />
                        </button>
                        <button
                            type="button"
                            @click="next()"
                            x-show="hasNext()"
                            aria-label="Kitas puslapis"
                            class="absolute right-1 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-gray-900/70 text-white shadow-sm hover:bg-gray-900/85 lg:right-3 lg:size-12"
                        >
                            <x-app-icon name="arrow-right" class="size-5 lg:size-6" />
                        </button>
                        {{-- Desktop only: on phones the flyer already fills
                             the screen width. --}}
                        <button
                            type="button"
                            @click="toggleFullscreen()"
                            :aria-label="fullscreen ? 'Uždaryti pilną ekraną' : 'Pilnas ekranas'"
                            class="absolute right-3 top-3 hidden size-12 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 lg:flex"
                            :class="fullscreen && 'text-white hover:bg-white/10'"
                        >
                            <x-app-icon x-show="!fullscreen" name="maximize" class="size-6" />
                            <x-app-icon x-show="fullscreen" name="x" class="size-6" />
                        </button>
                    </div>
                @elseif (!empty($flyer['image_url']))
                    <div class="min-w-0 overflow-hidden bg-white lg:h-full">
                        <img src="{{ $flyer['image_url'] }}" alt="{{ $flyer['title'] }}" class="block h-auto w-full lg:h-full lg:object-contain">
                    </div>
                @else
                    <div class="m-4 min-w-0 rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                        Leidinio puslapiai dar ruošiami.
                        <a href="/leidinys/{{ $storeSlug }}" class="font-semibold text-dark-green">Grįžti į {{ $storeName }} leidinius</a>
                    </div>
                @endif
            </div>

            @if ($betaConfig)
                @include('leaflets.partials.beta.overlays')
            @endif
        </div>

        <div class="base-container">
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
                @if ($flyerOffersIntro)
                    <p class="mb-4 max-w-3xl text-sm leading-relaxed text-gray-600">{{ $flyerOffersIntro }}</p>
                @endif
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
                                        :compare-stores="true"
                                        source="flyer_offers"
                                        class="deal-card-width"
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
        </div>
    </main>
</x-layouts.app>
