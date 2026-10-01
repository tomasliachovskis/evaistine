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

    <main class="base-container pb-6 pt-3 sm:pb-8 sm:pt-4">
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
                        const top = this.$refs.viewerFrame.getBoundingClientRect().top;
                        this.viewerHeight = Math.max(320, window.innerHeight - top - 16);
                    },
                    init() {
                        const wideQuery = window.matchMedia('(min-width: 1024px)');
                        this.wide = wideQuery.matches;
                        wideQuery.addEventListener('change', (e) => { this.wide = e.matches; });
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
                @leaflet-goto.window="currentPage = $event.detail; $refs.viewerFrame.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                @fullscreenchange.window="fullscreen = !!document.fullscreenElement"
                @webkitfullscreenchange.window="fullscreen = !!document.webkitFullscreenElement"
            @endif
        >
            @if (!empty($pages))
                <div class="order-1 min-w-0 lg:order-2">
                    @if ($betaConfig)
                        <div class="mb-3 flex flex-col gap-2">
                            <div x-show="showHint" x-cloak class="flex items-start gap-2 rounded-lg bg-green/10 px-3 py-2 text-sm text-dark-green">
                                <x-app-icon name="info" class="mt-0.5 size-4 shrink-0" />
                                <p class="min-w-0 flex-1"><span class="font-bold">Naujiena:</span> spauskite ant prekės leidinyje – pamatysite kainas kitose parduotuvėse ir galėsite ją įsidėti į pirkinių sąrašą.</p>
                                <button type="button" @click="dismissHint()" aria-label="Uždaryti" class="shrink-0 text-dark-green/70 hover:text-dark-green"><x-app-icon name="x" class="size-4" /></button>
                            </div>
                            <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                                <label class="relative block shrink-0 sm:w-64">
                                    <x-app-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="search"
                                        x-model="query"
                                        @input.debounce.250ms="onSearch()"
                                        placeholder="Ieškoti leidinyje, pvz. sviestas"
                                        class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-green focus:outline-none"
                                    >
                                </label>
                                <div class="scroll-cards-x -my-2 flex min-w-0 gap-1.5">
                                    <template x-for="lens in lenses" :key="lens.key">
                                        <button
                                            type="button"
                                            @click="setLens(lens.key)"
                                            class="shrink-0 whitespace-nowrap rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                                            :class="activeLens === lens.key ? 'border-green bg-green text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-green/40'"
                                        >
                                            <span x-text="lens.label"></span>
                                            <span class="ml-0.5 opacity-70" x-text="lens.count"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div x-show="filtering()" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                                <span x-show="matchCount() > 0"><span class="font-bold text-gray-900" x-text="matchCount()"></span> prekės, <span x-text="matchPages().length"></span> psl.</span>
                                <span x-show="matchCount() === 0">Nieko nerasta šiame leidinyje.</span>
                                <button type="button" x-show="matchPages().length > 1" @click="gotoNextMatch()" class="section-link">
                                    Kitas puslapis su atitikmenimis <x-app-icon name="chevron-right" class="size-3.5" />
                                </button>
                                <button type="button" @click="activeLens = null; query = ''" class="text-sm font-medium text-gray-500 hover:text-gray-900">Išvalyti</button>
                            </div>
                        </div>
                    @endif
                    <div
                        x-ref="viewerFrame"
                        class="relative flex h-[75vh] items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                        :style="fullscreen ? 'height: 100dvh' : (viewerHeight ? `height: ${viewerHeight}px` : '')"
                        {{-- Important modifiers: the static 'relative' otherwise
                             wins over 'fixed' in the compiled CSS order, so the
                             pseudo-fullscreen overlay never left the page flow. --}}
                        :class="fullscreen && 'fixed! inset-0 z-[9999] rounded-none! border-none! bg-black/95! p-4'"
                        @if ($betaConfig) @click="selectedId = null" @endif
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
                                        @load="loadedPages[{{ $pageNumber }}] = true{{ $pageNumber === 1 ? '; portrait = $el.naturalHeight >= $el.naturalWidth' : '' }}; $nextTick(() => measure())"
                                        x-on:error="loadedPages[{{ $pageNumber }}] = true"
                                    >
                                    <div class="absolute" :style="overlayStyle({{ $pageNumber }})">
                                        <template x-for="h in hotspotsOn({{ $pageNumber }})" :key="h.id">
                                            <button
                                                type="button"
                                                class="absolute rounded-md transition-[box-shadow,background-color] duration-150"
                                                :style="boxStyle(h)"
                                                :class="hotspotClass(h)"
                                                :aria-label="h.name + ', ' + euro(h.price)"
                                                @click.stop="select(h)"
                                                @mouseenter="hoveredId = h.id"
                                                @mouseleave="hoveredId = null"
                                            >
                                                <span
                                                    x-show="h.comparison && h.comparison.cheapest && !(filtering() && !isMatch(h))"
                                                    class="pointer-events-none absolute -top-2 left-1 whitespace-nowrap rounded-full bg-green px-1.5 py-0.5 text-xs font-bold leading-none text-white shadow-sm"
                                                >Pigiausia</span>
                                                <span
                                                    x-show="inList(h.id)"
                                                    class="pointer-events-none absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-green text-white shadow-sm"
                                                ><x-app-icon name="check" class="size-3" /></span>
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
                                    @load="loadedPages[{{ $page['page_number'] }}] = true{{ $page['page_number'] === 1 ? '; portrait = $el.naturalHeight >= $el.naturalWidth' : '' }}"
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
                            class="absolute left-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-700 shadow-sm hover:bg-white"
                        >
                            <x-app-icon name="chevron-right" class="size-4 rotate-180" />
                        </button>
                        <button
                            type="button"
                            @click="next()"
                            x-show="hasNext()"
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

                        @if ($betaConfig)
                            <template x-if="selected()">
                                <div
                                    @click.stop
                                    class="fixed inset-x-2 bottom-20 z-[55] rounded-xl border border-gray-200 bg-white p-4 shadow-xl lg:absolute lg:inset-x-auto lg:bottom-auto lg:right-3 lg:top-12 lg:z-30 lg:w-80"
                                >
                                    <button type="button" @click="selectedId = null" aria-label="Uždaryti" class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-lg text-gray-400 hover:text-gray-700">
                                        <x-app-icon name="x" class="size-4" />
                                    </button>
                                    <div class="flex gap-3 pr-6">
                                        <img x-show="selected().image" :src="selected().image" alt="" class="size-16 shrink-0 rounded-md bg-gray-50 object-contain">
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 text-sm font-semibold text-gray-900" x-text="selected().name"></p>
                                            <p class="mt-1 flex items-baseline gap-1.5">
                                                <span class="text-xl font-bold tabular-nums text-gray-900" x-text="euro(selected().price)"></span>
                                                <del x-show="selected().original > selected().price" class="text-sm tabular-nums text-gray-400" x-text="euro(selected().original)"></del>
                                                <span x-show="selected().percent" class="rounded bg-[#ffdb4d] px-1.5 py-0.5 text-xs font-bold text-gray-900" x-text="'-' + Math.abs(selected().percent) + '%'"></span>
                                            </p>
                                            <p x-show="selected().unit" class="text-xs text-gray-500" x-text="selected().unit"></p>
                                        </div>
                                    </div>

                                    <div x-show="selected().comparison" class="mt-3 rounded-lg bg-gray-50 px-3 py-2">
                                        <p class="text-sm font-semibold" :class="selected().comparison?.cheapest ? 'text-green' : 'text-gray-900'" x-text="selected().comparison?.label"></p>
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-600">
                                            <template x-for="other in (selected().comparison?.others || []).slice(0, 4)" :key="other.slug">
                                                <span><span x-text="other.store"></span> <span class="font-semibold tabular-nums" x-text="euro(other.price)"></span></span>
                                            </template>
                                        </div>
                                    </div>
                                    <p
                                        x-show="selected().signal"
                                        class="mt-2 text-xs font-medium"
                                        :class="selected().signal?.tone === 'good' ? 'text-green' : (selected().signal?.tone === 'bad' ? 'text-red-600' : 'text-gray-600')"
                                        x-text="selected().signal ? selected().signal.label + ' ' + selected().signal.description : ''"
                                    ></p>

                                    <div class="mt-3 flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="toggleList(selected())"
                                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors"
                                            :class="inList(selected().id) ? 'bg-green/10 text-dark-green' : 'bg-green text-white hover:bg-dark-green'"
                                        >
                                            <x-app-icon x-show="!inList(selected().id)" name="plus" class="size-4" />
                                            <x-app-icon x-show="inList(selected().id)" name="check" class="size-4" />
                                            <span x-text="inList(selected().id) ? 'Sąraše' : 'Į sąrašą'"></span>
                                        </button>
                                        <template x-for="h in [selected()]" :key="h.id">
                                            <button
                                                type="button"
                                                x-data="favoriteButton(h.product_id, false, h.name, h.image)"
                                                @click.stop.prevent="toggle()"
                                                class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 hover:border-gray-300"
                                                x-bind:aria-label="favorited ? 'Nebesekti kainos' : 'Sekti kainą'"
                                            >
                                                <x-app-icon name="heart" class="size-5" x-bind:class="favorited ? 'fill-red-500 text-red-500' : 'fill-none text-gray-500'" />
                                            </button>
                                        </template>
                                        <a :href="selected().href" class="flex h-10 shrink-0 items-center gap-0.5 rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-700 hover:border-gray-300">
                                            Prekė <x-app-icon name="chevron-right" class="size-3.5" />
                                        </a>
                                    </div>
                                </div>
                            </template>
                        @endif
                    </div>

                    @if ($betaConfig)
                        <div x-show="spreadHotspots().length > 0" x-cloak class="mt-3">
                            <p class="mb-1 text-sm font-semibold text-gray-900">Prekės šiame <span x-text="spread().length > 1 ? 'atvertime' : 'puslapyje'"></span> <span class="font-normal text-gray-500" x-text="'(' + spreadHotspots().length + ')'"></span></p>
                            <div class="scroll-cards-x flex gap-2">
                                <template x-for="h in spreadHotspots()" :key="h.id">
                                    <button
                                        type="button"
                                        @click="selectedId = h.id; $refs.viewerFrame.scrollIntoView({ behavior: 'smooth', block: 'nearest' })"
                                        @mouseenter="hoveredId = h.id"
                                        @mouseleave="hoveredId = null"
                                        class="relative flex w-32 shrink-0 flex-col rounded-lg border bg-white p-2 text-left transition-colors"
                                        :class="selectedId === h.id || hoveredId === h.id ? 'border-green' : 'border-gray-200'"
                                    >
                                        <img x-show="h.image" :src="h.image" alt="" loading="lazy" class="h-20 w-full object-contain">
                                        <span class="mt-1 text-sm font-bold tabular-nums text-gray-900" x-text="euro(h.price)"></span>
                                        <span class="line-clamp-2 text-xs leading-snug text-gray-700" x-text="h.name"></span>
                                        <span x-show="inList(h.id)" class="absolute right-1.5 top-1.5 flex size-5 items-center justify-center rounded-full bg-green text-white"><x-app-icon name="check" class="size-3" /></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    @endif

                    {{-- Mobile only — desktop shows this same pager in the
                         rail, right under the date range, instead (see
                         below). --}}
                    <div class="mt-3 lg:hidden">
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
                    <nav class="flex flex-wrap items-center gap-1 text-xs text-gray-500" aria-label="Naršymo kelias">
                        @foreach ($breadcrumbs as $index => $crumb)
                            @if ($index > 0)<x-app-icon name="arrow-right" class="size-3 text-gray-300" />@endif
                            <a href="{{ $crumb['href'] }}" class="transition-colors hover:text-green {{ $canonical === $crumb['href'] ? 'font-medium text-green' : '' }}">{{ $crumb['name'] }}</a>
                        @endforeach
                    </nav>
                    <h1 class="mt-2 text-xl font-bold leading-tight">{{ $flyer['title'] }}</h1>
                    @if ($dateRange)
                        <p class="mt-1 text-sm text-gray-600">{{ $dateRange }}</p>
                    @endif
                </div>

                {{-- Desktop only: mobile shows this same pager just below
                     the image instead (see the viewer column). --}}
                @if (!empty($pages))
                    <div class="hidden shrink-0 lg:block">
                        @include('leaflets.partials.leaflet-pager', ['pages' => $pages, 'beta' => (bool) $betaConfig])
                    </div>
                @endif
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
                <button
                    type="button"
                    x-show="list.length > 0 && !selectedId"
                    x-cloak
                    @click="listOpen = true"
                    class="fixed bottom-20 right-4 z-50 flex items-center gap-2 rounded-full bg-green px-4 py-3 text-sm font-bold text-white shadow-lg hover:bg-dark-green lg:bottom-6"
                >
                    <x-app-icon name="shopping-basket" class="size-5" />
                    Sąrašas · <span x-text="list.length"></span> · <span class="tabular-nums" x-text="euro(listTotal())"></span>
                </button>

                <div x-show="toast" x-cloak x-transition.opacity class="fixed bottom-36 left-1/2 z-[70] -translate-x-1/2 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-lg lg:bottom-24" x-text="toast"></div>

                <div
                    x-show="listOpen"
                    x-cloak
                    x-transition.opacity
                    @click.self="listOpen = false"
                    @keydown.escape.window="listOpen = false"
                    class="fixed inset-0 z-[60] flex justify-end bg-black/40"
                >
                    <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                            <h2 class="text-lg font-bold text-gray-900">Pirkinių sąrašas</h2>
                            <button type="button" @click="listOpen = false" aria-label="Uždaryti" class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:text-gray-900"><x-app-icon name="x" class="size-5" /></button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                            <p x-show="list.length === 0" class="text-sm text-gray-500">Sąrašas tuščias. Spauskite ant prekės leidinyje ir „Į sąrašą“.</p>
                            <template x-for="group in listByStore()" :key="group.store">
                                <div class="mb-4">
                                    <h3 class="mb-1 text-sm font-bold uppercase tracking-wide text-gray-500" x-text="group.store"></h3>
                                    <template x-for="item in group.items" :key="item.id">
                                        <div class="flex items-start gap-3 border-b border-gray-100 py-2">
                                            <button
                                                type="button"
                                                @click="toggleChecked(item.id)"
                                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border"
                                                :class="item.checked ? 'border-green bg-green text-white' : 'border-gray-300'"
                                                :aria-label="item.checked ? 'Pažymėti kaip nenupirktą' : 'Pažymėti kaip nupirktą'"
                                            ><x-app-icon x-show="item.checked" name="check" class="size-3.5" /></button>
                                            <div class="min-w-0 flex-1">
                                                <a :href="item.href" class="line-clamp-2 text-sm text-gray-900 hover:text-green" :class="item.checked && 'text-gray-400 line-through'" x-text="item.name"></a>
                                                <div class="mt-0.5 flex flex-wrap gap-x-2 text-xs text-gray-500">
                                                    <a :href="item.flyer_href" class="hover:text-green" x-text="item.page + ' psl.'"></a>
                                                    <span x-show="item.cheaper" class="text-gray-600" x-text="item.cheaper"></span>
                                                </div>
                                            </div>
                                            <span class="shrink-0 text-sm font-bold tabular-nums text-gray-900" x-text="euro(item.price)"></span>
                                            <button type="button" @click="removeFromList(item.id)" aria-label="Pašalinti" class="shrink-0 text-gray-400 hover:text-gray-700"><x-app-icon name="x" class="size-4" /></button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div x-show="list.length > 0" class="border-t border-gray-200 px-4 py-3">
                            <div class="mb-3 flex items-baseline justify-between">
                                <span class="text-sm text-gray-600">Iš viso</span>
                                <span class="text-xl font-bold tabular-nums text-gray-900" x-text="euro(listTotal())"></span>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="shareList()" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-green px-3 py-2.5 text-sm font-semibold text-white hover:bg-dark-green"><x-app-icon name="share-2" class="size-4" /> Dalintis</button>
                                <button type="button" @click="printList()" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2.5 text-sm font-semibold text-gray-700 hover:border-gray-300"><x-app-icon name="printer" class="size-4" /> Spausdinti</button>
                                <button type="button" @click="clearList()" class="rounded-lg px-3 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-900">Išvalyti</button>
                            </div>
                        </div>
                    </div>
                </div>
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
