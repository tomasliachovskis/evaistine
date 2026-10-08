import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, MAX_PAGES, onlySelected, parsePrice, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://internetinevaistine.lt';
const STORE = 'InternetineVaistine.lt';

// Laravel shop, server-rendered, 32 products a page (?page=N). Every
// category's subcategories are linked as /{root}/{sub}. Each listing also has
// a schema.org ItemList with the barcode (gtin13), brand and image, but its
// price is the regular one: a discounted price is only in the card's HTML.
const ROOTS = [
    'nereceptiniai-vaistai',
    'vitaminai-ir-maisto-papildai',
    'kosmetika',
    'higiena',
    'medicinos-priemones',
    'mama-ir-vaikas',
    'maistas',
    'sportas-ir-laisvalaikis',
];

const heading = (page) => page.querySelector('h1')?.text.replace(/\s+/g, ' ').trim() ?? '';

const subcategoryUrls = (page, root) => {
    const prefix = `${BASE_URL}/${root}/`;
    const urls = page.querySelectorAll('a[href]')
        .map(a => a.getAttribute('href').split('?')[0])
        .filter(href => href.startsWith(prefix) && !href.slice(prefix.length).includes('/'));
    return [...new Set(urls)];
};

// url -> { ean, brand, image } from the listing's JSON-LD.
const readStructuredData = (html) => {
    const byUrl = new Map();
    for (const match of html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
        try {
            const data = JSON.parse(match[1]);
            for (const node of Array.isArray(data) ? data : [data]) {
                if (node['@type'] !== 'ItemList') {
                    continue;
                }
                for (const { item } of node.itemListElement ?? []) {
                    if (item?.url) {
                        byUrl.set(item.url, { ean: validEan(item.gtin13), brand: item.brand?.name || null, image: item.image || '' });
                    }
                }
            }
        } catch (error) {
            // Not the product list.
        }
    }
    return byUrl;
};

// .actual is the price to pay (class "discounted" when it's a sale price),
// and a sale also shows the regular price struck through in .discount <s>.
const readCard = (card) => ({
    url: card.querySelector('a.product__img-wrap')?.getAttribute('href') ?? '',
    name: card.querySelector('.product__title')?.text.replace(/\s+/g, ' ').trim(),
    price: parsePrice(card.querySelector('.product__price .actual')?.lastChild?.text ?? card.querySelector('.product__price .actual')?.text),
    regular: parsePrice(card.querySelector('.product__price .discount s')?.text),
});

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const seen = new Set();

    const scrapeListing = async (url, category) => {
        let currentPage = 1;
        let pageCount = 1;
        do {
            const html = await fetchText(currentPage > 1 ? `${url}?page=${currentPage}` : url);
            if (!html) {
                console.log(`Giving up on ${url} page ${currentPage}.`);
                return;
            }
            const page = parse(html);
            const cards = page.querySelectorAll('div.product').map(readCard).filter(card => card.url && card.price);
            if (currentPage === 1) {
                // "Rodoma 32 iš <span>190</span> produktų"
                const total = Number(html.match(/Rodoma \d+ iš <span>(\d+)<\/span>/)?.[1]) || cards.length;
                pageCount = Math.min(Math.ceil(total / Math.max(cards.length, 1)) || 1, MAX_PAGES);
            }
            const structured = readStructuredData(html);

            const rows = [];
            for (const card of cards) {
                if (seen.has(card.url)) {
                    continue;
                }
                seen.add(card.url);
                const extra = structured.get(card.url) ?? {};
                rows.push(buildRow({ store: STORE, ...card, ...extra, category, dates }));
            }
            await poster.post(rows, `${category} page ${currentPage}/${pageCount}`);

            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    };

    for (const root of onlySelected(ROOTS)) {
        const rootUrl = `${BASE_URL}/${root}`;
        const html = await fetchText(rootUrl);
        if (!html) {
            console.log(`Giving up on ${root}.`);
            continue;
        }
        const page = parse(html);
        const rootName = heading(page);
        await sleep();

        for (const subUrl of subcategoryUrls(page, root)) {
            const subHtml = await fetchText(subUrl);
            const subName = subHtml ? heading(parse(subHtml)) : '';
            await sleep();
            await scrapeListing(subUrl, subName ? `${rootName}/${subName}` : rootName);
        }
        await scrapeListing(rootUrl, rootName);
    }

    poster.summary();
})();
