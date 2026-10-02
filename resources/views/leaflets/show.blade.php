@php
    $flyer = $listingMeta['flyer'];
    $pages = $listingMeta['pages'] ?? [];
    $storeName = $listingMeta['store_name'] ?? $storeSlug;
    $otherLeaflets = collect($listingMeta['leaflets'] ?? [])->filter(fn ($l) => ($l['slug'] ?? null) !== $flyer['slug'])->values();
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

    <main class="base-container pt-0 pb-6 sm:pb-8 lg:pt-4">
        {{-- The leaflet page image is the whole point of this page — it used
             to render after a full-width breadcrumb/H1/dates/pill-bar stack,
             squeezed into a 7fr/3fr column beside a "Kiti leidiniai"
             sidebar, with no height cap on the image. Net effect: on a
             normal screen, most of the leaflet page needed scrolling just to
             see it once, let alone at a readable size (confirmed live
             2026-09-18). Flipped here: the image is sized to fill the
             viewport height available to it. On desktop a slim title row
             (breadcrumb, title, dates, pager) sits above it and the
             quick links and "Kiti leidiniai" go below, so the viewer gets
             the full container width for the two-page spread. A 320px side
             rail used to take that width, which left a single portrait page
             at about half the screen. On mobile the viewer still comes
             first, then the pager, the title and the rest. --}}
        <div
            class="flex flex-col gap-6"
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
                    // Desktop shows two pages side by side like an opened
                    // magazine (1, 2-3, 4-5, ...): a portrait page fitted to
                    // the viewer's height only filled ~half of a wide screen.
                    // Only for portrait pages (a landscape spread would come
                    // out tiny), detected from page 1's real size.
                    wide: false,
                    portrait: true,
                    spread() {
                        if (!this.wide || !this.portrait || this.currentPage === 1) return [this.currentPage];
                        const left = this.currentPage % 2 === 0 ? this.currentPage : this.currentPage - 1;
                        return left + 1 <= this.totalPages ? [left, left + 1] : [left];
                    },
                    isShown(page) { return this.spread().includes(page); },
                    hasNext() { return Math.max(...this.spread()) < this.totalPages; },
                    hasPrev() { return Math.min(...this.spread()) > 1; },
                    next() { this.currentPage = Math.min(this.totalPages, Math.max(...this.spread()) + 1); },
                    prev() { this.currentPage = Math.max(1, Math.min(...this.spread()) - 1); },
                    // Height was a guessed calc(100vh - Nrem) constant, tied to
                    // this page's current header/padding sizes — any future
                    // change to those (or a device's own chrome/safe-area
                    // quirks) would silently reopen the dead-space gap this was
                    // meant to fix. Measure the real remaining space instead:
                    // however tall the frame's own top offset actually renders,
                    // fill everything below it down to a small bottom margin.
                    sizeViewer() {
                        if (this.fullscreen || !this.$refs.viewerFrame) return;
                        // Phones and tablets: the viewer sits flush under the
                        // header and, with the page bar under it, fills the
                        // screen exactly down to the bottom nav (like
                        // manoakcijos.lt), so the flyer page never has to be
                        // scrolled into view. clientHeight, not innerHeight
                        // or visualViewport: those shrink while pinch-zoomed
                        // or as the iOS URL bar moves, resizing the flyer.
                        if (!this.wide) {
                            const frame = this.$refs.viewerFrame;
                            const top = frame.getBoundingClientRect().top + window.scrollY;
                            const nav = document.querySelector('[data-bottom-nav]');
                            const navHeight = nav && getComputedStyle(nav).display !== 'none' ? nav.getBoundingClientRect().height : 0;
                            const bar = this.$refs.pagerBar;
                            const barHeight = bar ? bar.getBoundingClientRect().height + parseFloat(getComputedStyle(bar).marginTop || 0) : 0;
                            const available = Math.max(320, Math.floor(document.documentElement.clientHeight - top - navHeight - barHeight));
                            // The page is always fitted to the full screen
                            // width (like manoakcijos.lt): the frame takes
                            // the shown page's own height, so there are no
                            // gray bands beside or above it. Until the image
                            // has loaded its size is unknown; fill the screen.
                            const img = frame.querySelector(`[data-page-img='${this.currentPage}']`);
                            const pageHeight = img && img.naturalWidth ? Math.round(frame.clientWidth * img.naturalHeight / img.naturalWidth) : null;
                            this.viewerHeight = pageHeight || available;
                            // Pinned (no vertical drag) when the whole page
                            // fits on screen. On a short phone a page taller
                            // than the screen has to scroll, or its bottom
                            // could never be seen.
                            this.viewerFitsScreen = !pageHeight || pageHeight <= available;
                            return;
                        }
                        // Beta puts the instruction, search and filters
                        // above the flyer, so 'space left below the frame'
                        // shrank it to the 320px floor. Size it to the full
                        // screen below the sticky header instead, as if it
                        // were scrolled to the top.
                        if (this.beta) {
                            const header = document.querySelector('header');
                            const headerHeight = header ? header.getBoundingClientRect().height : 64;
                            this.viewerHeight = Math.max(420, window.innerHeight - headerHeight - 24);
                            return;
                        }
                        const top = this.$refs.viewerFrame.getBoundingClientRect().top;
                        this.viewerHeight = Math.max(320, window.innerHeight - top - 16);
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
                    if ($event.key === 'ArrowRight') next();
                    if ($event.key === 'ArrowLeft') prev();
                    if ($event.key === 'Escape' && fullscreen && !document.fullscreenElement && !document.webkitFullscreenElement) fullscreen = false;
                "
                @leaflet-goto.window="currentPage = $event.detail; $refs.viewerFrame.scrollIntoView({ behavior: 'smooth', block: wide ? 'center' : 'start' })"
                @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
                @webkitfullscreenchange.window="fullscreen = !!document.webkitFullscreenElement"
            @endif
        >
            @if (!empty($pages))
                {{-- Phones and tablets: the flyer comes first, flush under
                     the header and edge to edge, then the page bar, then
                     whose leaflet it is, the search and the filters
                     (order-* below). Desktop keeps the DOM order
                     (lg:order-none): toolbar, viewer, page bar. --}}
                <div class="order-1 flex min-w-0 flex-col lg:order-2 lg:block">
                    <div class="order-3 mt-3 flex min-h-12 items-center gap-3 lg:hidden">
                        <x-store-logo :slug="$storeSlug" :name="$storeName" size="sm" class="shrink-0" />
                        <span class="h-6 w-px shrink-0 bg-gray-300"></span>
                        @if ($dateRange)
                            <span class="min-w-0 text-base leading-snug {{ $leafletEnded ? 'font-semibold text-amber-800' : 'text-gray-700' }}">{{ $leafletEnded ? 'Galiojo' : 'Galioja' }} {{ $dateRange }}</span>
                        @else
                            <span class="min-w-0 text-base font-semibold leading-snug text-gray-900">{{ $flyer['title'] }}</span>
                        @endif
                    </div>
                    {{-- Instruction, search and filters only when this
                         leaflet has clickable products. --}}
                    @if ($betaConfig && ! empty($betaConfig['hotspots']))
                        <div class="order-4 mt-3 lg:mt-0">
                            @include('leaflets.partials.beta.toolbar')
                        </div>
                    @endif
                    {{-- touch-action: pinch-zoom on phones, so a drag on the
                         flyer no longer scrolls the page up and down under
                         the finger (it is pinned in place); the swipe
                         handlers below still flip pages and pinch-zoom still
                         works. Only when the page fits on screen (see
                         sizeViewer()). --}}
                    <div
                        x-ref="viewerFrame"
                        class="relative order-1 -mx-4 flex h-[75vh] scroll-mt-[var(--header-h)] items-center justify-center overflow-hidden overscroll-contain bg-gray-100 sm:-mx-5 lg:mx-0 lg:rounded-xl lg:border lg:border-gray-200 lg:bg-gray-50"
                        :style="(fullscreen ? 'height: 100dvh' : (viewerHeight ? `height: ${viewerHeight}px` : '')) + (wide ? '' : (viewerFitsScreen ? ';touch-action: pinch-zoom' : ';touch-action: pan-y pinch-zoom'))"
                        {{-- Important modifiers: the static 'relative' otherwise
                             wins over 'fixed' in the compiled CSS order, so the
                             pseudo-fullscreen overlay never left the page flow. --}}
                        :class="fullscreen && 'fixed! inset-0 z-[9999] rounded-none! border-none! bg-black/95! p-4'"
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
                                    class="relative h-full min-w-0"
                                    :class="spread().length > 1 ? 'w-1/2' : 'w-full'"
                                >
                                    <img
                                        src="{{ $page['image_url'] }}"
                                        alt="{{ $flyer['title'] }} – {{ $pageNumber }} puslapis{{ $pageProductNames->has($pageNumber) ? ': ' . $pageProductNames[$pageNumber] : '' }}"
                                        class="block h-full w-full object-contain"
                                        :class="{ 'object-right': pageAlign({{ $pageNumber }}) === 'right', 'object-left': pageAlign({{ $pageNumber }}) === 'left' }"
                                        loading="{{ $pageNumber <= 3 ? 'eager' : 'lazy' }}"
                                        data-page-img="{{ $pageNumber }}"
                                        @load="loadedPages[{{ $pageNumber }}] = true{{ $pageNumber === 1 ? '; portrait = $el.naturalHeight >= $el.naturalWidth' : '' }}; $nextTick(() => { sizeViewer(); measure(); })"
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
                                    class="block h-full w-auto object-contain"
                                    :class="[spread().length > 1 ? 'max-w-[50%]' : 'max-w-full', fullscreen && 'max-h-full', fullscreen && spread().length === 1 && 'mx-auto']"
                                    loading="{{ $page['page_number'] <= 3 ? 'eager' : 'lazy' }}"
                                    data-page-img="{{ $page['page_number'] }}"
                                    @load="loadedPages[{{ $page['page_number'] }}] = true{{ $page['page_number'] === 1 ? '; portrait = $el.naturalHeight >= $el.naturalWidth' : '' }}; $nextTick(() => sizeViewer())"
                                    x-on:error="loadedPages[{{ $page['page_number'] }}] = true"
                                >
                            @endforeach
                        @endif

                        <div
                            x-show="!spread().every((page) => pageLoaded(page))"
                            x-cloak
                            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-gray-50"
                        >
                            <div class="size-8 animate-spin rounded-full border-[3px] border-gray-200 border-t-green"></div>
                        </div>

                        <button
                            type="button"
                            @click="prev()"
                            x-show="hasPrev()"
                            aria-label="Ankstesnis puslapis"
                            class="absolute left-2 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon name="chevron-right" class="size-4 rotate-180" />
                        </button>
                        <button
                            type="button"
                            @click="next()"
                            x-show="hasNext()"
                            aria-label="Kitas puslapis"
                            class="absolute right-2 top-1/2 flex size-12 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon name="chevron-right" class="size-4" />
                        </button>
                        <button
                            type="button"
                            @click="toggleFullscreen()"
                            :aria-label="fullscreen ? 'Uždaryti pilną ekraną' : 'Pilnas ekranas'"
                            class="absolute right-2 top-2 flex size-12 items-center justify-center rounded-lg border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon x-show="!fullscreen" name="maximize" class="size-4" />
                            <x-app-icon x-show="fullscreen" name="x" class="size-4" />
                        </button>

                    </div>

                    @if ($betaConfig)
                        @include('leaflets.partials.beta.below')
                    @endif

                    {{-- Mobile only — desktop shows this same pager in the
                         rail, right under the date range, instead (see
                         below). Without the beta page bar this is the bar
                         sizeViewer() leaves room for under the flyer. --}}
                    <div class="{{ $betaConfig ? 'order-6' : 'order-2' }} mt-3 lg:hidden" @unless ($betaConfig) x-ref="pagerBar" @endunless>
                        @include('leaflets.partials.leaflet-pager', ['pages' => $pages, 'beta' => (bool) $betaConfig])
                    </div>
                </div>
            @elseif (!empty($flyer['image_url']))
                <div class="order-1 min-w-0 overflow-hidden rounded-xl lg:order-2 border border-gray-200 bg-white">
                    <img src="{{ $flyer['image_url'] }}" alt="{{ $flyer['title'] }}" class="block h-auto w-full">
                </div>
            @else
                <div class="order-1 min-w-0 rounded-xl border lg:order-2 border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                    Leidinio puslapiai dar ruošiami.
                    <a href="/leidinys/{{ $storeSlug }}" class="font-semibold text-dark-green">Grįžti į {{ $storeName }} leidinius</a>
                </div>
            @endif

            {{-- Title row: above the viewer on desktop (lg:order-1), below
                 it on mobile, where the flyer page comes first. The
                 viewer used to sit beside a 320px rail; it now gets the
                 full container width so the two-page spread (see spread())
                 shows both pages at a readable size. --}}
            <div class="order-2 flex min-w-0 flex-col gap-3 lg:order-1 lg:flex-row lg:items-end lg:justify-between lg:gap-6">
                <div class="min-w-0">
                    <nav class="flex flex-wrap items-center gap-x-2" aria-label="Naršymo kelias">
                        @foreach ($breadcrumbs as $index => $crumb)
                            @if ($index > 0)<x-app-icon name="arrow-right" class="crumb-sep" />@endif
                            <a href="{{ $crumb['href'] }}" class="crumb-link {{ $canonical === $crumb['href'] ? 'crumb-current' : '' }}">{{ $crumb['name'] }}</a>
                        @endforeach
                    </nav>
                    <h1 class="mt-2 text-xl font-bold leading-tight">{{ $flyer['title'] }}</h1>
                    @if ($dateRange)
                        <p class="mt-1 {{ $betaConfig ? 'text-base' : 'text-sm' }} {{ $leafletEnded ? 'font-semibold text-amber-800' : 'text-gray-600' }}">{{ $leafletEnded ? 'Galiojo' : 'Galioja' }} {{ $dateRange }}</p>
                    @endif
                </div>

                {{-- Desktop only: mobile shows this same pager just below
                     the image instead (see the viewer column). --}}
                <div class="flex shrink-0 flex-wrap items-center gap-4">
                @if ($betaConfig)
                    <button
                        type="button"
                        @click="openList()"
                        class="hidden min-h-12 shrink-0 items-center gap-2 rounded-xl bg-green-soft px-4 text-lg font-bold text-dark-green hover:bg-green-soft-border focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30 lg:inline-flex"
                    >
                        <x-app-icon name="shopping-basket" class="size-6" />
                        Pirkinių sąrašas
                        <span class="tabular-nums" x-text="'(' + list.length + ')'"></span>
                    </button>
                @endif
                @if (!empty($pages))
                    <div class="hidden shrink-0 lg:block">
                        @include('leaflets.partials.leaflet-pager', ['pages' => $pages, 'beta' => (bool) $betaConfig])
                    </div>
                @endif
                </div>
            </div>

            {{-- data-sticky-filter-bar: gates the header's row2 (nav links)
                 scroll-hide behavior — this page previously had none of the
                 sticky bars that mechanism looks for, so row2 stayed
                 permanently visible here, wasting space. Presence-only, read
                 once by site-header.blade.php's scroll listener; doesn't
                 need to sit on the thing that visually docks in that space
                 (there isn't one on this page), just needs to exist. --}}
            <aside data-sticky-filter-bar class="order-3 flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
                <div class="flex flex-col gap-4 lg:w-80 lg:shrink-0">
                    <x-leaflet-quick-links :store-slug="$storeSlug" :store-name="$storeName" :total-offers="$totalOffers" :shows-discounts-page="$showsDiscountsPage" :show-leaflets-link="true" :leaflets-count="$listingMeta['leaflets_count'] ?? 0" :compact="true" />

                    @if ($showsDiscountsPage)
                        <a href="/akcijos/{{ $storeSlug }}" class="section-link">
                            Visos {{ $storeName }} akcijos
                            <x-app-icon name="chevron-right" class="size-3.5" />
                        </a>
                    @endif
                </div>

                <div class="section-card min-w-0 lg:flex-1">
                    <h2 class="section-heading mb-3">Kiti {{ $storeName }} leidiniai</h2>
                    @if ($otherLeaflets->isEmpty())
                        <p class="text-sm text-gray-500">Kitų leidinių nėra.</p>
                    @else
                        <div class="flex flex-col gap-2 lg:flex-row lg:flex-wrap">
                            @foreach ($otherLeaflets->take(6) as $other)
                                <a href="{{ $other['view_url'] ?? "/leidinys/{$storeSlug}" }}" class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-green/5 lg:w-[calc(33.333%-0.375rem)]">
                                    @if (!empty($other['image_url']))
                                        <img src="{{ $other['image_url'] }}" alt="" class="h-20 w-16 shrink-0 rounded-md object-cover">
                                    @endif
                                    <span class="line-clamp-2 text-sm font-medium text-gray-900">{{ $other['title'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </aside>

            @if ($betaConfig)
                @include('leaflets.partials.beta.overlays')
            @endif
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
    </main>
</x-layouts.app>
