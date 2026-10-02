{{-- Interactive flyer layer (?beta=1): mixed into the viewer's x-data in
     leaflets/show.blade.php via `...leafletBeta(config)`. Plain methods
     only, no getters: object spread would evaluate a getter once and copy
     the value. The viewer's own init() calls betaInit().

     Built for older readers: nothing happens on its own (search and
     filters never flip the page; they offer a button instead), every
     action is confirmed with an undo, and the phone's Back button closes
     the product card or the list instead of leaving the page. --}}
<script>
    window.leafletBeta = function (config) {
        const LIST_KEY = 'superakcijos_sarasas_v1';

        const readList = () => {
            try {
                const items = JSON.parse(localStorage.getItem(LIST_KEY) || '[]');
                return Array.isArray(items) ? items : [];
            } catch (e) {
                return [];
            }
        };

        const euro = (amount) => Number(amount || 0).toFixed(2).replace('.', ',') + ' €';

        // 1 prekė, 2 prekės, 10 prekių, 21 prekė.
        const productWord = (n) => {
            if (n % 100 >= 11 && n % 100 <= 19) return 'prekių';
            if (n % 10 === 1) return 'prekė';
            if (n % 10 >= 2) return 'prekės';
            return 'prekių';
        };

        return {
            beta: true,
            storeName: config.store || '',
            hotspots: (config.hotspots || []).map((h) => ({ ...h, search: h.name.toLowerCase() })),
            lenses: config.lenses || [],
            activeLens: null,
            query: '',
            activeQuery: '',
            selectedId: null,
            hoveredId: null,
            rects: {},
            pageRatios: {},
            list: readList(),
            listOpen: false,
            toast: null,
            undoSnapshot: null,

            betaInit() {
                const remeasure = () => this.$nextTick(() => requestAnimationFrame(() => this.measure()));
                ['currentPage', 'wide', 'fullscreen', 'viewerHeight', 'portrait'].forEach((key) => this.$watch(key, remeasure));
                window.addEventListener('resize', remeasure);
                this.$watch('currentPage', () => { if (this.selectedId) this.closeCard(); });
                // The list button in the phone page bar comes and goes,
                // changing the bar's height the viewer leaves room for.
                this.$watch('list', () => this.$nextTick(() => this.sizeViewer()));
                window.addEventListener('storage', (e) => { if (e.key === LIST_KEY) this.list = readList(); });
                // Back button closes whatever overlay is open.
                window.addEventListener('popstate', () => {
                    this.selectedId = null;
                    this.listOpen = false;
                });
                remeasure();
            },

            euro,
            productWord,

            // ---- Page geometry ---------------------------------------

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
                    this.pageRatios[page] = img.naturalHeight / img.naturalWidth;
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

            // The card's magnifier: the product's own area of the flyer
            // page, cut out with a little margin and scaled up to fill the
            // card width, so the small print on the price tag is readable.
            magnifierStyle(h) {
                if (!h || !h.page_image) return 'display:none';
                const pad = 15;
                const [ymin, xmin, ymax, xmax] = [
                    Math.max(0, h.box[0] - pad), Math.max(0, h.box[1] - pad),
                    Math.min(1000, h.box[2] + pad), Math.min(1000, h.box[3] + pad),
                ];
                const bw = (xmax - xmin) / 1000;
                const bh = (ymax - ymin) / 1000;
                const pageRatio = this.pageRatios[h.page] || (1754 / 1240);
                const ratio = (bh * pageRatio) / bw;
                const maxWidth = Math.min(window.innerWidth - 48, 440);
                const maxHeight = Math.min(window.innerHeight * 0.38, 340);
                let width = maxWidth;
                let height = width * ratio;
                if (height > maxHeight) {
                    height = maxHeight;
                    width = height / ratio;
                }
                const posX = bw >= 1 ? 0 : (xmin / 1000) / (1 - bw) * 100;
                const posY = bh >= 1 ? 0 : (ymin / 1000) / (1 - bh) * 100;
                return `width:${width}px;height:${height}px;background-image:url('${h.page_image}');`
                    + `background-size:${100 / bw}% ${100 / bh}%;background-position:${posX}% ${posY}%;background-repeat:no-repeat`;
            },

            hotspotsOn(page) {
                return this.hotspots.filter((h) => h.page === page);
            },

            // Products on the current page(s), in reading order.
            spreadHotspots() {
                const pages = this.spread();
                return this.hotspots
                    .filter((h) => pages.includes(h.page))
                    .sort((a, b) => (a.page - b.page) || (a.box[0] - b.box[0]) || (a.box[1] - b.box[1]));
            },

            pagesLabel() {
                const pages = this.spread();
                return (pages.length > 1 ? `${pages[0]} ir ${pages[1]} puslapis` : `${pages[0]} puslapis`) + ` iš ${this.totalPages}`;
            },

            // ---- Search and filters ----------------------------------

            runSearch() {
                this.activeQuery = this.query.trim().toLowerCase();
            },

            clearFilters() {
                this.activeLens = null;
                this.query = '';
                this.activeQuery = '';
            },

            filtering() {
                return this.activeLens !== null || this.activeQuery.length >= 2;
            },

            isMatch(h) {
                if (this.activeQuery.length >= 2 && !h.search.includes(this.activeQuery)) return false;
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

            spreadMatchCount() {
                return this.spread().reduce((sum, page) => sum + this.pageMatchCount(page), 0);
            },

            // The next page with results after the current one (wrapping),
            // offered as a button rather than jumped to.
            nextMatchPage() {
                const pages = this.matchPages();
                if (pages.length === 0) return null;
                const shown = this.spread();
                const last = Math.max(...shown);
                const next = pages.find((p) => p > last) ?? pages[0];
                return shown.includes(next) ? null : next;
            },

            resultSentence() {
                const n = this.matchCount();
                if (n === 0) return 'Šiame leidinyje tokių prekių nerasta.';
                const pages = this.matchPages().length;
                return `Rasta ${n} ${productWord(n)} ${pages === 1 ? '1 puslapyje' : pages + ' puslapiuose'}.`;
            },

            setLens(key) {
                this.activeLens = this.activeLens === key ? null : key;
            },

            hotspotClass(h) {
                if (this.selectedId === h.id) return 'ring-4 ring-dark-green bg-green/15';
                if (this.filtering()) return this.isMatch(h) ? 'ring-4 ring-green' : 'bg-white/75';
                if (this.hoveredId === h.id) return 'ring-4 ring-green bg-green/10';
                return 'ring-2 ring-green/70';
            },

            // ---- Product card ----------------------------------------

            selected() {
                return this.hotspots.find((h) => h.id === this.selectedId) || null;
            },

            openCard(h) {
                if (!this.selectedId) history.pushState({ leafletOverlay: 'card' }, '');
                this.selectedId = h.id;
                this.$nextTick(() => this.$refs.cardClose && this.$refs.cardClose.focus());
            },

            closeCard() {
                if (history.state && history.state.leafletOverlay === 'card') {
                    history.back();
                } else {
                    this.selectedId = null;
                }
            },

            comparisonSentence(h) {
                if (!h.comparison) return '';
                return h.comparison.cheapest
                    ? `${this.storeName} kaina mažiausia.`
                    : h.comparison.label + '.';
            },

            // ---- Shopping list (this browser only) -------------------

            inList(id) {
                return this.list.some((item) => item.id === id);
            },

            toggleList(h) {
                if (this.inList(h.id)) {
                    this.changeList(this.list.filter((item) => item.id !== h.id), 'Išimta iš sąrašo.');
                    return;
                }
                this.changeList([...this.list, {
                    id: h.id,
                    product_id: h.product_id,
                    name: h.name,
                    price: h.price,
                    store: h.store,
                    page: h.page,
                    href: h.href,
                    flyer_href: h.flyer_href,
                    cheaper: h.comparison && !h.comparison.cheapest ? h.comparison.label : null,
                    checked: false,
                }], 'Įdėta į pirkinių sąrašą.');
            },

            toggleChecked(id) {
                this.list = this.list.map((item) => item.id === id ? { ...item, checked: !item.checked } : item);
                this.saveList();
            },

            removeFromList(id) {
                this.changeList(this.list.filter((item) => item.id !== id), 'Išimta iš sąrašo.');
            },

            clearList() {
                this.changeList([], 'Sąrašas išvalytas.');
            },

            // Every list change can be undone from the confirmation.
            changeList(next, message) {
                this.undoSnapshot = this.list;
                this.list = next;
                this.saveList();
                this.flash(message);
            },

            undo() {
                if (this.undoSnapshot === null) return;
                this.list = this.undoSnapshot;
                this.undoSnapshot = null;
                this.saveList();
                this.toast = null;
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

            openList() {
                if (this.selectedId) this.selectedId = null;
                history.pushState({ leafletOverlay: 'list' }, '');
                this.listOpen = true;
            },

            closeList() {
                if (history.state && history.state.leafletOverlay === 'list') {
                    history.back();
                } else {
                    this.listOpen = false;
                }
            },

            listText() {
                return this.listByStore().map((group) =>
                    group.store + ':\n' + group.items.map((item) => `- ${item.name}, ${euro(item.price)}`).join('\n')
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
                    this.flash('Sąrašas nukopijuotas. Galite jį įklijuoti į žinutę.', false);
                } catch (e) {}
            },

            printList() {
                const win = window.open('', '_blank');
                if (!win) return;
                const escape = (value) => String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
                const groups = this.listByStore().map((group) =>
                    `<h2>${escape(group.store)}</h2><ul>` + group.items.map((item) =>
                        `<li><span class="box"></span>${escape(item.name)} <b>${euro(item.price)}</b></li>`
                    ).join('') + '</ul>'
                ).join('');
                win.document.write(`<!doctype html><meta charset="utf-8"><title>Pirkinių sąrašas</title><style>body{font-family:system-ui,sans-serif;font-size:20px;line-height:1.5;padding:24px;color:#000}h1{font-size:28px}h2{font-size:22px;margin:24px 0 8px}ul{padding:0}li{list-style:none;margin:10px 0;display:flex;gap:12px;align-items:center}.box{display:inline-block;width:22px;height:22px;border:2px solid #000;flex:none}b{margin-left:auto}</style><h1>Pirkinių sąrašas</h1>${groups}<p><b>Iš viso: ${euro(this.listTotal())}</b></p>`);
                win.document.close();
                win.focus();
                win.print();
            },

            flash(message, undoable = true) {
                this.toast = { message, undoable: undoable && this.undoSnapshot !== null };
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(() => { this.toast = null; }, 6000);
            },
        };
    };
</script>
