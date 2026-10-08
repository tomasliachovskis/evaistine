import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchJson, MAX_PAGES, sleep } from './lib/pharmacy.js';

const STORE = 'Ąžuolyno vaistinė';

// WooCommerce: the public Store API lists the whole catalog, 100 a page.
// `sku` is the shop's own code, not a barcode, so there's no EAN.
const PRODUCTS_URL = 'https://azuolynovaistine.lt/wp-json/wc/store/v1/products';
const PAGE_SIZE = 100;

// A product sits in several categories; their links carry the tree
// (/product-category/{parent}/{child}/). Take the deepest one, prefixed with
// its parent's name when the parent is in the list too.
const categoryOf = (categories = []) => {
    const withPath = categories.map(c => ({
        ...c,
        path: decodeURIComponent(new URL(c.link).pathname).split('/').filter(Boolean).slice(1),
    }));
    const deepest = withPath.sort((a, b) => b.path.length - a.path.length)[0];
    if (!deepest) {
        return null;
    }
    const parentSlug = deepest.path[deepest.path.length - 2];
    const parent = withPath.find(c => c.slug === parentSlug);
    return parent ? `${parent.name}/${deepest.name}` : deepest.name;
};

// Prices are in minor units; `sale_price` equals `regular_price` when the
// product isn't on sale.
const toRow = (product, dates) => {
    const minor = 10 ** (product.prices?.currency_minor_unit ?? 2);
    const price = Number(product.prices?.price) / minor || null;
    const regular = Number(product.prices?.regular_price) / minor || null;

    return buildRow({
        store: STORE,
        // Names come HTML-escaped (&#8211;, &amp;).
        name: parse(`<p>${product.name}</p>`).text,
        url: product.permalink,
        category: categoryOf(product.categories),
        price,
        regular: product.on_sale ? regular : null,
        image: product.images?.[0]?.src ?? '',
        dates,
    });
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    let currentPage = 1;
    let fetched = 0;

    while (currentPage <= MAX_PAGES) {
        const products = await fetchJson(PRODUCTS_URL, { per_page: PAGE_SIZE, page: currentPage });
        if (!Array.isArray(products) || products.length === 0) {
            break;
        }
        fetched += products.length;

        const rows = products
            .filter(product => product.is_purchasable && product.is_in_stock)
            .map(product => toRow(product, dates));
        await poster.post(rows, `page ${currentPage}`);

        if (products.length < PAGE_SIZE) {
            break;
        }
        currentPage++;
        await sleep();
    }

    poster.summary(`${fetched} products listed by the Store API.`);
})();
