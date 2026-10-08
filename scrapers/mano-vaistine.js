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
const ROOTS = [
    { key: 'visi-vaistai', name: 'Nereceptiniai vaistai', query: '?attrib[8]=2' },
    { key: 'vitaminai-maisto-papildai', name: 'Vitaminai ir maisto papildai' },
    { key: 'kosmetika-higiena', name: 'Kosmetika ir higiena' },
    { key: 'medicinos-prekes', name: 'Medicinos prekės' },
    { key: 'arabatos-ir-vaistazoles', name: 'Arbatos ir vaistažolės' },
    { key: 'meiles-prekes', name: 'Meilės prekės' },
    { key: 'kitos-prekes', name: 'Kitos prekės' },
];

const subcategories = (page, root) => {
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

        for (const sub of subcategories(parse(html), root.key)) {
            await scrapeListing(sub.path, query, `${root.name}/${sub.name}`);
            eans.save();
        }
        await scrapeListing(root.key, query, root.name);
        eans.save();
    }

    eans.save();
    poster.summary(`${eans.fetched()} product pages fetched for EANs, ${eans.size()} in the cache.`);
})();
