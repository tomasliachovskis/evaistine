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

document.addEventListener('alpine:init', () => {
    Alpine.data('storeLocatorMap', (locations) => ({
        map: null,
        init() {
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

    // `points` is [{store_slug, store_name, price, date}], server-sorted
    // ascending by date — the SVG/stat/summary numbers here are purely a
    // client-side progressive enhancement over price-history-chart.blade.php's
    // own server-rendered stat cards/table (the SEO-relevant content), which
    // this only re-derives to update on a store filter click, never to
    // first-paint them.
    const SVG_NS = 'http://www.w3.org/2000/svg';

    Alpine.data('priceHistoryChart', (points) => ({
        allPoints: points,
        activeStore: null,
        tooltip: { visible: false, x: 0, y: 0, price: '', sub: '' },

        W: 760,
        H: 220,
        padL: 48,
        padR: 10,
        padT: 12,
        padB: 22,

        init() {
            this.renderChart();
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
            [...svg.querySelectorAll('[data-dynamic]')].forEach((n) => n.remove());

            const mk = (tag, attrs) => {
                const node = document.createElementNS(SVG_NS, tag);
                node.setAttribute('data-dynamic', '1');
                for (const k in attrs) node.setAttribute(k, attrs[k]);
                return node;
            };

            this.yTicks.forEach((tick) => {
                svg.appendChild(mk('line', { x1: this.padL, y1: tick.y, x2: this.W - this.padR, y2: tick.y, stroke: '#eef0f2', 'stroke-width': 1 }));
                const label = mk('text', { x: this.padL - 8, y: tick.y + 3, 'text-anchor': 'end', class: 'fill-gray-400 text-[10.5px]' });
                label.textContent = this.fmtPrice(tick.price);
                svg.appendChild(label);
            });

            this.monthTicks.forEach((tick) => {
                const label = mk('text', { x: tick.x, y: this.H - 6, 'text-anchor': 'middle', class: 'fill-gray-400 text-[10.5px]' });
                label.textContent = tick.label;
                svg.appendChild(label);
            });

            svg.appendChild(mk('path', { d: this.areaPath, fill: 'url(#priceHistoryFill)' }));
            svg.appendChild(mk('path', { d: this.linePath, fill: 'none', stroke: '#09a952', 'stroke-width': 2.25, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));

            this.points.forEach((pt) => {
                const isMin = pt.price === this.minPrice;
                const circle = mk('circle', {
                    cx: pt.x, cy: pt.y, r: isMin ? 5 : 3.5,
                    fill: isMin ? '#09a952' : '#fff',
                    stroke: isMin ? '#044923' : '#09a952',
                    'stroke-width': 2,
                    class: 'cursor-pointer',
                });
                circle.addEventListener('mouseenter', () => this.showTooltip(pt, svg));
                circle.addEventListener('mouseleave', () => { this.tooltip.visible = false; });
                svg.appendChild(circle);
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
        get monthTicks() {
            const start = new Date(this.t0);
            const end = new Date(this.t1);
            const ticks = [];
            const cursor = new Date(start.getFullYear(), start.getMonth(), 1);

            while (cursor <= end) {
                const x = this.xFor(cursor.toISOString().slice(0, 10));
                if (x >= this.padL && x <= this.W - this.padR) {
                    ticks.push({ label: cursor.toLocaleDateString('lt-LT', { month: 'short' }), x });
                }
                cursor.setMonth(cursor.getMonth() + 1);
            }

            return ticks;
        },

        showTooltip(point, svg) {
            const rect = svg.getBoundingClientRect();
            this.tooltip = {
                visible: true,
                x: (point.x / this.W) * rect.width,
                y: (point.y / this.H) * rect.height,
                price: this.fmtPrice(point.price),
                sub: `${point.store_name} · ${this.fmtDate(point.date)}`,
            };
        },

        fmtPrice(price) {
            return price.toFixed(2).replace('.', ',') + ' €';
        },
        fmtDate(date) {
            return new Date(date).toLocaleDateString('lt-LT', { day: 'numeric', month: 'short' });
        },
    }));
});
