{{-- Interactive flyer layer (?beta=1): mixed into the viewer's x-data in
     leaflets/show.blade.php via `...leafletBeta(config)`. Plain methods
     only, no getters: object spread would evaluate a getter once and copy
     the value. The viewer's own init() calls betaInit(). --}}
<script>
    window.leafletBeta = function (config) {
        const LIST_KEY = 'superakcijos_sarasas_v1';
        const HINT_KEY = 'superakcijos_leidinys_hint_v1';

        const readList = () => {
            try {
                const items = JSON.parse(localStorage.getItem(LIST_KEY) || '[]');
                return Array.isArray(items) ? items : [];
            } catch (e) {
                return [];
            }
        };

        const euro = (amount) => Number(amount || 0).toFixed(2).replace('.', ',') + ' €';

        return {
            beta: true,
            hotspots: config.hotspots || [],
            lenses: config.lenses || [],
            activeLens: null,
            query: '',
            selectedId: null,
            hoveredId: null,
            rects: {},
            list: readList(),
            listOpen: false,
            toast: null,
            showHint: false,

            betaInit() {
                try { this.showHint = !localStorage.getItem(HINT_KEY); } catch (e) {}
                const remeasure = () => this.$nextTick(() => requestAnimationFrame(() => this.measure()));
                ['currentPage', 'wide', 'fullscreen', 'viewerHeight', 'portrait'].forEach((key) => this.$watch(key, remeasure));
                window.addEventListener('resize', remeasure);
                this.$watch('currentPage', () => { this.selectedId = null; });
                // Another tab changed the list.
                window.addEventListener('storage', (e) => { if (e.key === LIST_KEY) this.list = readList(); });
                remeasure();
            },

            euro,

            dismissHint() {
                this.showHint = false;
                try { localStorage.setItem(HINT_KEY, '1'); } catch (e) {}
            },

            // Where the page image's content actually renders inside its
            // wrapper: object-contain letterboxes it, differently in the
            // spread (left page pushed right, right page pushed left) and on
            // phones (fitted by width). Hotspots are positioned inside it.
            measure() {
                const rects = {};
                this.$root.querySelectorAll('[data-page-wrap]').forEach((wrap) => {
                    const page = Number(wrap.dataset.pageWrap);
                    const img = wrap.querySelector('img');
                    if (!this.isShown(page) || !img || !img.naturalWidth) return;
                    const W = wrap.clientWidth;
                    const H = wrap.clientHeight;
                    const scale = Math.min(W / img.naturalWidth, H / img.naturalHeight);
                    const w = img.naturalWidth * scale;
                    const h = img.naturalHeight * scale;
                    const align = this.pageAlign(page);
                    const x = align === 'right' ? W - w : (align === 'left' ? 0 : (W - w) / 2);
                    rects[page] = { x, y: (H - h) / 2, w, h };
                });
                this.rects = rects;
            },

            pageAlign(page) {
                if (this.spread().length < 2) return 'center';
                return page === this.spread()[0] ? 'right' : 'left';
            },

            overlayStyle(page) {
                const r = this.rects[page];
                return r ? `left:${r.x}px;top:${r.y}px;width:${r.w}px;height:${r.h}px` : 'display:none';
            },

            boxStyle(h) {
                const [ymin, xmin, ymax, xmax] = h.box;
                return `top:${ymin / 10}%;left:${xmin / 10}%;width:${(xmax - xmin) / 10}%;height:${(ymax - ymin) / 10}%`;
            },

            hotspotsOn(page) {
                return this.hotspots.filter((h) => h.page === page);
            },

            // Products on the current spread, in reading order.
            spreadHotspots() {
                const pages = this.spread();
                return this.hotspots
                    .filter((h) => pages.includes(h.page))
                    .sort((a, b) => (a.page - b.page) || (a.box[0] - b.box[0]) || (a.box[1] - b.box[1]));
            },

            filtering() {
                return this.activeLens !== null || this.query.trim().length >= 2;
            },

            isMatch(h) {
                const q = this.query.trim().toLowerCase();
                if (q.length >= 2 && !h.search.includes(q)) return false;
                if (this.activeLens === 'cheapest') return !!(h.comparison && h.comparison.cheapest);
                if (this.activeLens === 'big') return (h.percent || 0) >= 40;
                if (this.activeLens && this.activeLens.startsWith('cat:')) return h.category === this.activeLens.slice(4);
                return true;
            },

            matchCount() {
                return this.hotspots.filter((h) => this.isMatch(h)).length;
            },

            pageMatchCount(page) {
                return this.hotspots.filter((h) => h.page === page && this.isMatch(h)).length;
            },

            matchPages() {
                return [...new Set(this.hotspots.filter((h) => this.isMatch(h)).map((h) => h.page))].sort((a, b) => a - b);
            },

            gotoNextMatch() {
                const pages = this.matchPages();
                if (pages.length === 0) return;
                const last = Math.max(...this.spread());
                this.currentPage = pages.find((p) => p > last) ?? pages[0];
            },

            setLens(key) {
                this.activeLens = this.activeLens === key ? null : key;
                if (this.filtering() && this.spread().every((p) => this.pageMatchCount(p) === 0)) this.gotoNextMatch();
            },

            onSearch() {
                if (this.query.trim().length >= 2 && this.spread().every((p) => this.pageMatchCount(p) === 0)) this.gotoNextMatch();
            },

            hotspotClass(h) {
                if (this.selectedId === h.id) return 'ring-4 ring-green bg-green/10';
                if (this.hoveredId === h.id) return 'ring-2 ring-green bg-green/10';
                if (this.filtering()) return this.isMatch(h) ? 'ring-2 ring-green' : 'bg-white/70';
                if (this.inList(h.id)) return 'ring-2 ring-green/70';
                return 'hover:ring-2 hover:ring-green hover:bg-green/10';
            },

            selected() {
                return this.hotspots.find((h) => h.id === this.selectedId) || null;
            },

            select(h) {
                this.selectedId = this.selectedId === h.id ? null : h.id;
                if (this.showHint) this.dismissHint();
            },

            focusOnPage(h) {
                this.currentPage = h.page;
                this.$nextTick(() => { this.selectedId = h.id; });
            },

            // Shopping list (guest, this browser only).
            inList(id) {
                return this.list.some((item) => item.id === id);
            },

            toggleList(h) {
                if (this.inList(h.id)) {
                    this.list = this.list.filter((item) => item.id !== h.id);
                } else {
                    this.list = [...this.list, {
                        id: h.id,
                        product_id: h.product_id,
                        name: h.name,
                        price: h.price,
                        store: h.store,
                        store_slug: h.store_slug,
                        page: h.page,
                        href: h.href,
                        flyer_href: h.flyer_href,
                        image: h.image,
                        cheaper: h.comparison && !h.comparison.cheapest ? h.comparison.label : null,
                        checked: false,
                    }];
                    this.flash('Pridėta į sąrašą');
                }
                this.saveList();
            },

            toggleChecked(id) {
                this.list = this.list.map((item) => item.id === id ? { ...item, checked: !item.checked } : item);
                this.saveList();
            },

            removeFromList(id) {
                this.list = this.list.filter((item) => item.id !== id);
                this.saveList();
            },

            clearList() {
                this.list = [];
                this.saveList();
            },

            saveList() {
                try { localStorage.setItem(LIST_KEY, JSON.stringify(this.list)); } catch (e) {}
            },

            listTotal() {
                return this.list.filter((item) => !item.checked).reduce((sum, item) => sum + (Number(item.price) || 0), 0);
            },

            listByStore() {
                const groups = {};
                this.list.forEach((item) => {
                    (groups[item.store] = groups[item.store] || []).push(item);
                });
                return Object.entries(groups).map(([store, items]) => ({ store, items }));
            },

            listText() {
                return this.listByStore().map((group) =>
                    group.store.toUpperCase() + '\n' + group.items.map((item) => `- ${item.name} – ${euro(item.price)}`).join('\n')
                ).join('\n\n') + `\n\nIš viso: ${euro(this.listTotal())}`;
            },

            async shareList() {
                const text = this.listText();
                try {
                    if (navigator.share) {
                        await navigator.share({ title: 'Pirkinių sąrašas', text });
                        return;
                    }
                    await navigator.clipboard.writeText(text);
                    this.flash('Sąrašas nukopijuotas');
                } catch (e) {}
            },

            printList() {
                const win = window.open('', '_blank');
                if (!win) return;
                const escape = (value) => String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
                const groups = this.listByStore().map((group) =>
                    `<h2>${escape(group.store)}</h2><ul>` + group.items.map((item) =>
                        `<li>&#9744; ${escape(item.name)} <b>${euro(item.price)}</b> <small>(${item.page} psl.)</small></li>`
                    ).join('') + '</ul>'
                ).join('');
                win.document.write(`<!doctype html><meta charset="utf-8"><title>Pirkinių sąrašas</title><style>body{font-family:system-ui,sans-serif;padding:24px}h2{margin:20px 0 6px;font-size:16px}li{margin:4px 0;list-style:none}small{color:#666}</style><h1>Pirkinių sąrašas</h1>${groups}<p><b>Iš viso: ${euro(this.listTotal())}</b></p>`);
                win.document.close();
                win.focus();
                win.print();
            },

            flash(message) {
                this.toast = message;
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(() => { this.toast = null; }, 1800);
            },
        };
    };
</script>
