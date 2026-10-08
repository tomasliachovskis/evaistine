import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, loadEanCache, MAX_PAGES, onlySelected, parsePrice, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://www.manovaistine.lt';
const STORE = 'Mano vaistinė';

// Server-rendered shop, 63 products a page at /{category}/{n}-psl. Each
// category links its subcategories as /{category}/{sub}. The barcode is only
// on the product page (itemprop="gtin13"), so it goes through the EAN cache.
//
// Medicines: /visi-vaistai lists prescription ones too; the shop's own
// filter attrib[8]=2 keeps only non-prescription medicines.
//
// Categories (fixed 2026-10-08): each root's subcategories come from the
// header menu (li.hassub for the root -> .nav--sub ul.links--two), because
// most live under other URL prefixes (/dermatologine-kosmetika,
// /kosmetika-ir-higiena/..., /matuokliai-ir-kiti-elektroniniai-prietaisai).
// The old /{root}/ prefix rule found only one cosmetics subcategory, so ~600
// products fell into "Kosmetika ir higiena" and were all filed as Higiena.
// Mixed buckets ("Kelionėms", "Dovanos", and whatever only the root listing
// of a mixed root shows) are sent with an empty category: discounts:process
// then runs categories:bulk-map, which classifies each product by name.
// Mixed subcategories are read after the specific ones, so a product listed
// in both keeps its specific category.
const ROOTS = [
    { key: 'visi-vaistai', name: 'Nereceptiniai vaistai', query: '?attrib[8]=2' },
    { key: 'vitaminai-maisto-papildai', name: 'Vitaminai ir maisto papildai' },
    { key: 'kosmetika-higiena', name: 'Kosmetika ir higiena', mixed: true },
    { key: 'medicinos-prekes', name: 'Medicinos prekės' },
    { key: 'arabatos-ir-vaistazoles', name: 'Arbatos ir vaistažolės' },
    { key: 'meiles-prekes', name: 'Meilės prekės' },
    { key: 'kitos-prekes', name: 'Kitos prekės', mixed: true },
];

// Subcategories that mix product types; their products get an empty category.
const MIXED_SUBCATEGORIES = new Set(['kitos-prekes/kelionems', 'dovanos']);
// Not a category: the shop's whole "new arrivals" list.
const SKIP_SUBCATEGORIES = new Set(['prekes']);

const menuSubcategories = (page, root) => {
    const entry = page.querySelectorAll('li.hassub')
        .find(li => li.querySelector('a')?.getAttribute('href') === `/${root}`);
    const unique = new Map();
    entry?.querySelectorAll('.nav--sub ul.links--two a').forEach((a) => {
        const path = (a.getAttribute('href') ?? '').split('?')[0].replace(/^\//, '');
        const name = a.text.replace(/\s+/g, ' ').trim();
        if (path && name && !SKIP_SUBCATEGORIES.has(path) && !unique.has(path)) {
            unique.set(path, name);
        }
    });
    return [...unique].map(([path, name]) => ({ path, name }));
};

// Fallback for roots without a menu entry (Medicinos prekės, Arbatos):
// links under /{root}/ on the root page itself.
const prefixSubcategories = (page, root) => {
    const prefix = `/${root}/`;
    const links = page.querySelectorAll('a[href]')
        .map(a => ({ href: a.getAttribute('href').split('?')[0], name: a.text.replace(/\s+/g, ' ').trim() }))
        .filter(link => link.href.startsWith(prefix) && /^[a-z0-9-]+$/.test(link.href.slice(prefix.length)) && !/^\d+-psl$/.test(link.href.slice(prefix.length)) && link.name);
    const unique = new Map();
    links.forEach(link => unique.has(link.href) || unique.set(link.href, link.name));
    return [...unique].map(([href, name]) => ({ path: href.slice(1), name }));
};

const lastPage = (page, path) => {
    const pages = page.querySelectorAll('.pagination a.page-link')
        .map(a => Number(a.getAttribute('href')?.match(new RegExp(`^/${path}/(\\d+)-psl`))?.[1]) || 1);
    return pages.length ? Math.max(...pages) : 1;
};

// Prices: plain "Kaina 47,92 €", or "Akcinė kaina 8,19 €" with the regular
// price in .price--oldprice. A promo price starting with "*" is the loyalty
// programme price.
const readCard = (card) => {
    const link = card.querySelector('.item-title a');
    const priceText = (selector) => {
        const el = card.querySelector(selector);
        el?.querySelectorAll('small').forEach(small => small.remove());
        return el?.text.replace(/\s+/g, ' ').trim() ?? '';
    };
    const promo = priceText('.price--discount');
    const plain = priceText('.item-price .price:not(.price--oldprice):not(.price--discount)');
    const image = card.querySelector('.item-images img')?.getAttribute('data-src') ?? '';

    return {
        url: link ? `${BASE_URL}${link.getAttribute('href')}` : '',
        name: link?.text.trim(),
        brand: card.querySelector('.product-brand-link')?.text.trim() || null,
        image: image ? `${BASE_URL}${image}` : '',
        price: parsePrice(promo.replace('*', '') || plain),
        regular: promo ? parsePrice(priceText('.price--oldprice')) : null,
        card: promo.startsWith('*'),
    };
};

const fetchEan = async (url) => {
    const html = await fetchText(url);
    if (html === null) {
        return undefined;
    }
    const match = html.match(/itemprop="gtin13" content="(\d+)"/) ?? html.match(/"gtin13":"(\d+)"/);
    return match ? validEan(match[1]) : null;
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const eans = loadEanCache('mano-vaistine');
    const seen = new Set();

    const scrapeListing = async (path, query, category) => {
        let currentPage = 1;
        let pageCount = 1;
        do {
            const html = await fetchText(`${BASE_URL}/${path}${currentPage > 1 ? `/${currentPage}-psl` : ''}${query}`);
            if (!html) {
                console.log(`Giving up on ${path} page ${currentPage}.`);
                return;
            }
            const page = parse(html);
            if (currentPage === 1) {
                pageCount = Math.min(lastPage(page, path), MAX_PAGES);
            }

            const cards = page.querySelectorAll('.custom-grid-item').map(readCard).filter(card => card.url && card.price);
            const fresh = cards.filter(card => !seen.has(card.url));
            fresh.forEach(card => seen.add(card.url));
            await eans.fill(fresh.map(card => card.url), fetchEan);

            const rows = fresh.map(card => buildRow({ store: STORE, ...card, category, ean: eans.get(card.url), dates }));
            await poster.post(rows, `${category} page ${currentPage}/${pageCount}`);

            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    };

    for (const root of onlySelected(ROOTS, r => r.key)) {
        const query = root.query ?? '';
        const html = await fetchText(`${BASE_URL}/${root.key}${query}`);
        if (!html) {
            console.log(`Giving up on ${root.key}.`);
            continue;
        }
        await sleep();

        const page = parse(html);
        // Both lists: the menu misses some (Dekoratyvinė kosmetika lives
        // only under /kosmetika-higiena/), and roots without a menu entry
        // have only the prefix ones.
        const subs = [...new Map([...menuSubcategories(page, root.key), ...prefixSubcategories(page, root.key)]
            .map(sub => [sub.path, sub])).values()];
        const mixed = subs.filter(sub => MIXED_SUBCATEGORIES.has(sub.path));

        for (const sub of subs.filter(sub => !MIXED_SUBCATEGORIES.has(sub.path))) {
            await scrapeListing(sub.path, query, `${root.name}/${sub.name}`);
            eans.save();
        }
        for (const sub of mixed) {
            await scrapeListing(sub.path, query, '');
            eans.save();
        }
        await scrapeListing(root.key, query, root.mixed ? '' : root.name);
        eans.save();
    }

    eans.save();
    poster.summary(`${eans.fetched()} product pages fetched for EANs, ${eans.size()} in the cache.`);
})();
