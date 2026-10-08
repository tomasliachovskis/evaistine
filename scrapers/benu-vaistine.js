import { buildRow, createPoster, currentWeek, fetchJson, MAX_PAGES, onlySelected, sleep, validEan } from './lib/pharmacy.js';

const STORE = 'Benu vaistinė';

// benu.lt's category pages load their products from Luigi's Box, through a
// public search endpoint keyed by the shop's tracker id (it's in the page
// source as `nextEndpoint`). Every hit carries the EAN, price, old price,
// brand, main category and drug type, so no product page is fetched.
const SEARCH_URL = 'https://live.luigisbox.com/search';
const TRACKER_ID = '232949-269214';
const PAGE_SIZE = 200;

// One search returns at most 10 000 hits, so the catalog is read one main
// category at a time (the largest has ~4 800). The categories come from the
// search's own facet, so a new one is picked up automatically.
const baseParams = () => {
    const params = new URLSearchParams({ tracker_id: TRACKER_ID });
    params.append('f[]', 'type:item');
    // Items hidden on the site (delisted) are still in the index.
    params.append('f[]', 'isHidden:no');
    return params;
};

const search = (extra) => {
    const params = baseParams();
    for (const [key, value] of extra) {
        params.append(key, value);
    }
    return fetchJson(`${SEARCH_URL}?${params}`);
};

const first = (value) => (Array.isArray(value) ? value[0] : value) ?? null;

// "Specialūs pasiūlymai ir akcijos/Sezono svarbiausi" is a seasonal promo
// shelf with no real category (all ~660 items were filed as vitamins, 360 of
// them sun creams). Its items still carry a third level ("Apsauga nuo
// saulės", "Imunitetui ir gerai savijautai", "Alergijai slopinti") and
// Benu's own productType (Kosmetika, Maisto papildas, Nereceptinis vaistas,
// Medicinos prekė...), which together say what the product is.
const PROMO_ROOT = 'Specialūs pasiūlymai ir akcijos';

const categoryOf = (a) => {
    const top = first(a.main_category_lvl_1);
    const sub = first(a.main_category_lvl_2);

    if (top === PROMO_ROOT) {
        const topic = first(a.main_category_lvl_3);
        const type = first(a.productType);
        // Unknown shape: empty, so categories:bulk-map classifies it by name.
        return topic && type ? `${sub ?? 'Sezono svarbiausi'}/${topic}/${type}` : '';
    }

    return sub ? `${top}/${sub}` : top;
};

// `price_amount` is the current price; `price_old_amount` is set only while
// the product is discounted. `drugType` is basic, OTC or RX.
const toRow = (hit, dates) => {
    const a = hit.attributes;

    return buildRow({
        store: STORE,
        name: first(a.title),
        url: a.original_url || hit.url,
        category: categoryOf(a),
        price: Number(a.price_amount) || null,
        regular: Number(a.price_old_amount) || null,
        condition: first(a.labels),
        brand: first(a.brand),
        ean: validEan(first(a.ean)),
        image: a.image_link,
        dates,
    });
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const facets = await search([['size', '0'], ['facets', 'main_category_lvl_1:100']]);
    const categories = facets?.results?.facets?.find(f => f.name === 'main_category_lvl_1')?.values?.map(v => v.value) ?? [];
    if (!categories.length) {
        throw new Error('Could not read the category list from Luigi\'s Box.');
    }
    console.log(`Categories: ${categories.join(', ')}`);

    const poster = createPoster(STORE);
    const seen = new Set();
    let skippedPrescription = 0;
    let expectedTotal = 0;

    for (const category of onlySelected(categories)) {
        let currentPage = 1;
        let pageCount = 1;

        do {
            const response = await search([
                ['f[]', `main_category_lvl_1:${category}`],
                ['size', String(PAGE_SIZE)],
                ['page', String(currentPage)],
            ]);
            const results = response?.results;
            if (!Array.isArray(results?.hits)) {
                console.log(`Giving up on ${category} page ${currentPage}.`);
                break;
            }
            if (currentPage === 1) {
                expectedTotal += results.total_hits ?? 0;
            }
            pageCount = Math.min(Math.ceil((results.total_hits ?? 0) / PAGE_SIZE) || 1, MAX_PAGES);

            const rows = [];
            for (const hit of results.hits) {
                const id = hit.attributes?.item_id ?? hit.url;
                if (seen.has(id)) {
                    continue;
                }
                seen.add(id);
                // productType "Receptinis vaistas" also shows up on a few
                // items whose drugType isn't RX.
                if (first(hit.attributes?.drugType) === 'RX' || first(hit.attributes?.productType) === 'Receptinis vaistas') {
                    skippedPrescription++;
                    continue;
                }
                rows.push(toRow(hit, dates));
            }

            await poster.post(rows, `${category} page ${currentPage}/${pageCount}`);
            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    }

    poster.summary(`Luigi's Box reports ${expectedTotal} visible items; skipped ${skippedPrescription} prescription.`);
})();
