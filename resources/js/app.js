// Livewire ships and initializes its own Alpine.js instance (see @livewireScripts
// in resources/views/components/layouts/app.blade.php) — don't import/start a
// separate Alpine here, it would double-initialize. Register data/plugins on
// the 'alpine:init' event instead, same as the layout's inline authModal store.
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet's default marker icon paths assume a non-bundled <script> tag next
// to its CSS file — under Vite they 404 unless re-pointed at the bundled URLs.
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Ported from discount/src/lib/google-analytics.ts's sendGaEvent() — same
// silent no-op if gtag hasn't loaded (ad blockers, consent tooling) instead
// of throwing. Exposed globally so Blade/Alpine call sites (@click="...",
// Livewire component methods via $dispatch) can fire events without each
// needing its own <script> module.
window.trackGaEvent = function (eventName, params) {
    if (typeof window.gtag !== 'function') {
        return;
    }

    window.gtag('event', eventName, params);
};

document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-ga-event]');
    if (!target) {
        return;
    }

    const params = {};
    const { gaProductId, gaProductName, gaSource, gaItem } = target.dataset;

    if (gaProductId) {
        params.product_id = Number(gaProductId);
    }
    if (gaProductName) {
        params.product_name = gaProductName;
    }
    if (gaSource) {
        params.source = gaSource;
    }
    if (gaItem) {
        params.item = gaItem;
    }

    window.trackGaEvent(target.dataset.gaEvent, params);
});

document.addEventListener('alpine:init', () => {
    // x-back-closes="openExpression": while the sheet/dialog is open, the
    // phone's Back button closes it instead of leaving the page (an extra
    // history entry is pushed on open). Closing it any other way removes
    // that entry again, so Back afterwards still goes to the previous page.
    Alpine.directive('back-closes', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const isOpen = evaluateLater(expression);
        const close = evaluateLater(`${expression} = false`);
        const marker = 'sheet-' + Math.random().toString(36).slice(2);
        let pushed = false;

        const onPop = () => {
            if (pushed && !(history.state && history.state.sheet === marker)) {
                pushed = false;
                close();
            }
        };
        window.addEventListener('popstate', onPop);
        cleanup(() => window.removeEventListener('popstate', onPop));

        effect(() => {
            isOpen((open) => {
                if (open && !pushed) {
                    history.pushState({ sheet: marker }, '');
                    pushed = true;
                } else if (!open && pushed) {
                    pushed = false;
                    if (history.state && history.state.sheet === marker) history.back();
                }
            });
        });
    });

    Alpine.data('listingLoadMore', (config) => ({
        page: config.page,
        lastPage: config.lastPage,
        shown: config.shown,
        total: config.total,
        loading: false,
        observer: null,
        // Absolute, never-reset lifetime cap on loadMore() calls per page
        // view — NOT reset per observer firing (an earlier attempt at this
        // reset the counter every time the observer itself fired, which does
        // nothing if the observer keeps re-firing on its own, e.g. from an
        // unstable/thrashing layout where the sentinel's on-screen position
        // never settles). Found this session: a real card-height bug can
        // make the sentinel never genuinely leave view, and previously that
        // meant this kept fetching pages forever ("the loader just spins").
        // 20 covers every realistic real page (most listings are well under
        // 10 pages); past that this permanently stops and the user would
        // need to reload — better than a truly infinite fetch loop.
        loadMoreCallCount: 0,
        // Infinite scroll re-enabled — the root cause (the main listing
        // grid's CSS Grid row-track-sizing bug on real mobile Safari, which
        // compounded with this into runaway fetch loops) was fixed by
        // switching that grid to flex-wrap (see discount-filters.blade.php).
        // rootMargin '0px' (not the earlier '400px 0px') only fires once the
        // sentinel has genuinely scrolled into view. loadMoreCallCount above
        // is still a hard backstop against any future runaway loop.
        init() {
            // config.manual: a "Rodyti daugiau" button calls loadMore()
            // instead of the auto-loading sentinel — used on listing pages,
            // where the price table/FAQ/related links sit below the grid and
            // were unreachable while it kept growing under the reader.
            if (config.manual || this.page >= this.lastPage || !this.$refs.sentinel) {
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                if (entries[0]?.isIntersecting) {
                    this.loadMore();
                }
            }, { rootMargin: '0px' });
            this.observer.observe(this.$refs.sentinel);
        },
        async loadMore() {
            if (this.loading || this.page >= this.lastPage) {
                if (this.page >= this.lastPage) {
                    this.observer?.disconnect();
                }
                return;
            }

            if (this.loadMoreCallCount >= 20) {
                console.error('listingLoadMore: stopped after 20 loadMore() calls — sentinel likely never left view (unstable layout).');
                this.observer?.disconnect();
                return;
            }
            this.loadMoreCallCount++;

            this.loading = true;

            try {
                const params = new URLSearchParams();
                Object.entries({ ...config.params, page: String(this.page + 1) }).forEach(([key, value]) => {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    params.set(key, String(value));
                });
                const response = await fetch(`${config.endpoint}?${params}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']")?.content ?? '',
                    },
                });

                if (!response.ok) {
                    throw new Error(`load-more failed: ${response.status}`);
                }

                const data = await response.json();
                this.$refs.grid.insertAdjacentHTML('beforeend', data.html);
                this.page = data.page;
                this.lastPage = data.last_page;
                this.shown += data.deals.length;

                // Replaces the old click-triggered load_more_click event —
                // there's no click anymore, but the same analytics event
                // name/shape still matters for tracking how much of the
                // infinite scroll people actually reach.
                if (config.gaSource) {
                    window.trackGaEvent('load_more_click', { source: config.gaSource, page: data.page });
                }

                const wire = config.wireId && window.Livewire?.find?.(config.wireId);
                if (wire) {
                    wire.set('page', data.page);
                    wire.set('pagination', {
                        ...wire.get('pagination'),
                        current_page: data.page,
                        last_page: data.last_page,
                    });
                    wire.set('deals', [...wire.get('deals'), ...data.deals]);
                }
            } catch (error) {
                console.error(error);
            } finally {
                this.loading = false;

                if (this.page >= this.lastPage) {
                    this.observer?.disconnect();
                } else if (!config.manual && this.$refs.sentinel) {
                    // A single loaded page can be short enough that the
                    // sentinel is still on-screen right after insertion (huge
                    // viewport, small per-page count) — IntersectionObserver
                    // only fires on a visibility CHANGE, so without this
                    // check a still-visible sentinel would never trigger the
                    // next load. Matches init()'s 0px rootMargin: only
                    // re-chains if the sentinel is still genuinely on-screen,
                    // not pre-emptively. loadMoreCallCount above is the real
                    // backstop against this recursing forever.
                    const rect = this.$refs.sentinel.getBoundingClientRect();
                    if (rect.top < window.innerHeight) {
                        this.loadMore();
                    }
                }
            }
        },
    }));

    Alpine.data('favoriteButton', (productId, favorited = false, productName = '', productImage = null) => ({
        busy: false,
        favorited: !!favorited,
        toggle() {
            if (this.busy) {
                return;
            }

            this.busy = true;
            const prev = this.favorited;
            this.favorited = !this.favorited;

            fetch(`/favorites/toggle/${productId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content,
                    'Accept': 'application/json',
                },
            })
                .then((r) => {
                    if (r.status === 401) {
                        this.favorited = prev;
                        fetch('/auth/pending-favorite', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ product_id: productId }),
                        }).catch(() => {});
                        window.dispatchEvent(new CustomEvent('open-price-watch-modal', {
                            detail: { productName, productImage },
                        }));

                        return null;
                    }

                    return r.json();
                })
                .then((data) => {
                    if (data) {
                        this.favorited = data.favorited;
                        window.dispatchEvent(new CustomEvent('favorites-changed'));
                    }
                })
                .catch(() => {
                    this.favorited = prev;
                })
                .finally(() => {
                    this.busy = false;
                });
        },
    }));

    // `source` is either the locations array itself (city pages — their
    // addresses are already on the page) or an /api/store-locations URL
    // (chain pages — fetched client-side so the chain page's HTML doesn't
    // carry every city's full address list, which made Google treat each
    // city page as a duplicate of it; /api/ is disallowed in robots.txt).
    const storeLocationRequests = {};
    function loadStoreLocations(source) {
        if (Array.isArray(source)) {
            return Promise.resolve(source);
        }

        storeLocationRequests[source] ??= fetch(source, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : { locations: [] }))
            .then((payload) => (payload.locations || []).map((l) => ({ ...l, citySlug: l.city_slug })))
            .catch(() => []);

        return storeLocationRequests[source];
    }

    Alpine.data('storeLocatorMap', (source) => ({
        map: null,
        async init() {
            const locations = await loadStoreLocations(source);
            if (!locations.length) {
                return;
            }

            this.map = L.map(this.$el).setView([locations[0].lat, locations[0].lng], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.map);

            const markers = locations
                .filter((location) => location.lat && location.lng)
                .map((location) => L.marker([location.lat, location.lng]).bindPopup(
                    `<strong>${location.city}</strong><br>${location.address}`
                ).addTo(this.map));

            if (markers.length > 1) {
                this.map.fitBounds(L.featureGroup(markers).getBounds().pad(0.2));
            }
        },
    }));

    // "Rasti parduotuvę netoliese manęs" — real browser geolocation +
    // client-side haversine against every location's own lat/lng. `source`
    // is [{lat, lng, address, city, citySlug}] or a URL returning them (see
    // loadStoreLocations above).
    Alpine.data('nearestStoreFinder', (source) => ({
        loading: false,
        error: null,
        result: null,
        find() {
            if (!navigator.geolocation) {
                this.error = 'Jūsų naršyklė nepalaiko geolokacijos.';
                return;
            }

            this.loading = true;
            this.error = null;

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const locations = await loadStoreLocations(source);
                    this.loading = false;
                    const { latitude, longitude } = position.coords;
                    const withCoords = locations.filter((l) => l.lat && l.lng);

                    if (!withCoords.length) {
                        this.error = 'Adresų su koordinatėmis nerasta.';
                        return;
                    }

                    let nearest = null;
                    let nearestDistance = Infinity;
                    for (const location of withCoords) {
                        const distance = haversineKm(latitude, longitude, location.lat, location.lng);
                        if (distance < nearestDistance) {
                            nearestDistance = distance;
                            nearest = location;
                        }
                    }

                    this.result = { ...nearest, distanceKm: nearestDistance.toFixed(1) };
                },
                () => {
                    this.loading = false;
                    this.error = 'Nepavyko nustatyti jūsų vietos — patikrinkite naršyklės leidimus.';
                },
                { timeout: 10000 }
            );
        },
    }));

    function haversineKm(lat1, lng1, lat2, lng2) {
        const toRad = (deg) => (deg * Math.PI) / 180;
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // `points` is [{store_slug, store_name, price, date}], server-sorted
    // ascending by date — the SVG/stat/summary numbers here are purely a
    // client-side progressive enhancement over price-history-chart.blade.php's
    // own server-rendered stat cards/table (the SEO-relevant content), which
    // this only re-derives to update on a store filter click, never to
    // first-paint them.
    const SVG_NS = 'http://www.w3.org/2000/svg';
    // lt-LT's Intl 'short' month style renders as a zero-padded number
    // (toLocaleDateString(..., {month:'short'}) => "05", not "geg") in this
    // browser's ICU data, not the abbreviated month name a reader expects —
    // spelling these out by hand is the only reliable way to get "22 geg".
    const LT_MONTHS_SHORT = ['saus', 'vas', 'kov', 'bal', 'geg', 'bir', 'lie', 'rgp', 'rgs', 'spa', 'lap', 'gr'];

    Alpine.data('priceHistoryChart', (points) => ({
        allPoints: points,
        activeStore: null,
        tooltip: { visible: false, x: 0, y: 0, shift: '-50%', price: '', sub: '' },

        // The SVG scales its whole coordinate system (including font-size,
        // which is in user units, not real CSS px) to fill whatever width
        // the box actually renders at (preserveAspectRatio="none"). The
        // viewBox width is set to the SVG's real rendered width, so one user
        // unit is one CSS pixel at every screen size and the 16px axis
        // labels really are 16px (a fixed 380/760 still shrank them to
        // ~11px on a 360px phone).
        get W() {
            return Math.round(this.$refs.svg?.clientWidth || 760);
        },
        H: 220,
        padL: 64,
        padR: 12,
        padT: 12,
        padB: 30,

        init() {
            this.renderChart();
            window.addEventListener('resize', () => this.renderChart());
        },

        setStore(slug) {
            this.activeStore = slug;
            this.renderChart();
        },

        // Alpine's x-for clones <template> content, which the HTML parser
        // always builds in the HTML namespace even when the template sits
        // inside an <svg> — cloned <text>/<circle> elements silently fail to
        // render as real SVG. Building them by hand with createElementNS
        // (same approach already proven in the design mockup) sidesteps that
        // entirely, so the dynamic parts of the chart are imperative while
        // everything else in this component stays plain Alpine bindings.
        renderChart() {
            const svg = this.$refs.svg;
            if (!svg) {
                return;
            }
            // Blade renders a static viewBox="0 0 760 220" for no-JS/SEO
            // visitors — keep it in sync with the (possibly narrower-on-
            // mobile) W computed above so the coordinates below actually
            // line up with what's declared.
            svg.setAttribute('viewBox', `0 0 ${this.W} ${this.H}`);
            [...svg.querySelectorAll('[data-dynamic]')].forEach((n) => n.remove());

            const mk = (tag, attrs) => {
                const node = document.createElementNS(SVG_NS, tag);
                node.setAttribute('data-dynamic', '1');
                for (const k in attrs) node.setAttribute(k, attrs[k]);
                return node;
            };

            this.yTicks.forEach((tick) => {
                svg.appendChild(mk('line', { x1: this.padL, y1: tick.y, x2: this.W - this.padR, y2: tick.y, stroke: '#eef0f2', 'stroke-width': 1 }));
                const label = mk('text', { x: this.padL - 8, y: tick.y + 5, 'text-anchor': 'end', class: 'fill-gray-500 text-xs' });
                label.textContent = this.fmtPrice(tick.price);
                svg.appendChild(label);
            });

            this.dateTicks.forEach((tick) => {
                const label = mk('text', { x: tick.x, y: this.H - 6, 'text-anchor': tick.anchor, class: 'fill-gray-500 text-xs' });
                label.textContent = tick.label;
                svg.appendChild(label);
            });

            svg.appendChild(mk('path', { d: this.areaPath, fill: 'url(#priceHistoryFill)' }));
            svg.appendChild(mk('path', { d: this.linePath, fill: 'none', stroke: '#2b5ba8', 'stroke-width': 2.25, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));

            this.points.forEach((pt) => {
                const isMin = pt.price === this.minPrice;
                const circle = mk('circle', {
                    cx: pt.x, cy: pt.y, r: 3.5,
                    fill: isMin ? '#2b5ba8' : '#fff',
                    stroke: isMin ? '#0f234a' : '#2b5ba8',
                    'stroke-width': 2,
                    class: 'cursor-pointer',
                });
                svg.appendChild(circle);
                // Invisible, finger-sized hit area over each point: hover on
                // a computer, a tap on a phone (the 3.5px dot alone can't be
                // hit with a finger).
                const hit = mk('circle', { cx: pt.x, cy: pt.y, r: 16, fill: 'transparent', class: 'cursor-pointer' });
                hit.addEventListener('mouseenter', () => this.showTooltip(pt, svg));
                hit.addEventListener('mouseleave', () => { this.tooltip.visible = false; });
                hit.addEventListener('click', () => this.showTooltip(pt, svg));
                svg.appendChild(hit);
            });
        },

        get filtered() {
            return this.activeStore
                ? this.allPoints.filter((p) => p.store_slug === this.activeStore)
                : this.allPoints;
        },

        get minPrice() {
            return Math.min(...this.filtered.map((p) => p.price));
        },
        get maxPrice() {
            return Math.max(...this.filtered.map((p) => p.price));
        },
        get avgPrice() {
            return this.filtered.reduce((sum, p) => sum + p.price, 0) / this.filtered.length;
        },
        get lastPoint() {
            return [...this.filtered].sort((a, b) => new Date(b.date) - new Date(a.date))[0];
        },
        get summaryLine() {
            const last = this.lastPoint;
            const scope = this.activeStore
                ? `parduotuvėje ${this.allPoints.find((p) => p.store_slug === this.activeStore)?.store_name}`
                : 'visose parduotuvėse';
            const tail = last.price === this.minPrice
                ? 'tai istorinis minimumas.'
                : `žemiausia buvo ${this.fmtPrice(this.minPrice)}.`;

            return `Paskutinė žinoma kaina ${scope} — ${this.fmtPrice(last.price)} (${this.fmtDate(last.date)}), ${tail}`;
        },

        get t0() {
            return new Date(this.allPoints[0].date).getTime();
        },
        get t1() {
            return new Date(this.allPoints[this.allPoints.length - 1].date).getTime();
        },
        xFor(date) {
            const span = this.t1 - this.t0 || 1;
            return this.padL + ((new Date(date).getTime() - this.t0) / span) * (this.W - this.padL - this.padR);
        },
        yFor(price) {
            const pad = (this.maxPrice - this.minPrice) * 0.2 || 0.5;
            const yMin = this.minPrice - pad;
            const yMax = this.maxPrice + pad;

            return this.padT + (1 - (price - yMin) / (yMax - yMin)) * (this.H - this.padT - this.padB);
        },

        get points() {
            return [...this.filtered]
                .sort((a, b) => new Date(a.date) - new Date(b.date))
                .map((p) => ({ ...p, x: this.xFor(p.date), y: this.yFor(p.price) }));
        },
        get linePath() {
            return this.points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
        },
        get areaPath() {
            if (!this.points.length) {
                return '';
            }
            const first = this.points[0];
            const last = this.points[this.points.length - 1];

            return `${this.linePath} L ${last.x.toFixed(1)} ${this.H - this.padB} L ${first.x.toFixed(1)} ${this.H - this.padB} Z`;
        },
        get yTicks() {
            const pad = (this.maxPrice - this.minPrice) * 0.2 || 0.5;
            const yMin = this.minPrice - pad;
            const yMax = this.maxPrice + pad;

            return Array.from({ length: 5 }, (_, i) => {
                const price = yMin + (i / 4) * (yMax - yMin);

                return { price, y: this.yFor(price) };
            });
        },
        get dateTicks() {
            // Evenly spaced by pixel position (not by fixed calendar interval,
            // like the old month-start ticks were) so a narrow date range
            // still gets several readable "D mon" labels instead of just one.
            const innerW = this.W - this.padL - this.padR;
            // About one 16px "12 geg" label per 110px, so they never touch.
            const count = Math.max(2, Math.min(6, Math.floor(innerW / 110) + 1));
            const span = this.t1 - this.t0 || 1;
            const seen = new Set();
            const ticks = [];

            for (let i = 0; i < count; i++) {
                const x = this.padL + (i / (count - 1)) * innerW;
                const t = this.t0 + (i / (count - 1)) * span;
                const d = new Date(t);
                const label = `${d.getDate()} ${LT_MONTHS_SHORT[d.getMonth()]}`;
                if (seen.has(label)) continue;
                seen.add(label);
                ticks.push({ label, x, anchor: i === 0 ? 'start' : (i === count - 1 ? 'end' : 'middle') });
            }

            return ticks;
        },

        showTooltip(point, svg) {
            const rect = svg.getBoundingClientRect();
            const x = (point.x / this.W) * rect.width;
            this.tooltip = {
                visible: true,
                x,
                // Keep the bubble inside the chart near its edges (centred
                // on a point at the very right ran off a phone screen).
                shift: x > rect.width - 90 ? '-100%' : (x < 90 ? '0%' : '-50%'),
                y: (point.y / this.H) * rect.height,
                price: this.fmtPrice(point.price),
                sub: `${point.store_name} · ${this.fmtDate(point.date)}`,
            };
        },

        fmtPrice(price) {
            return price.toFixed(2).replace('.', ',') + ' €';
        },
        fmtDate(date) {
            const d = new Date(date);
            return `${d.getDate()} ${LT_MONTHS_SHORT[d.getMonth()]}`;
        },
    }));
});
