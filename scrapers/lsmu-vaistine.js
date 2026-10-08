import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, MAX_PAGES, onlySelected, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://eshop.lsmu.lt/vaistine';
const STORE = 'LSMU vaistinė';

// eshoprent. Category pages are server-rendered with a schema.org ItemList
// (name, url, image, price, gtin13) and page with ?page=N.
//
// Only what other pharmacies could also sell is scraped: cosmetics, herbal
// teas and aromatherapy. Left out: compounded medicines ("Ekstemporalūs
// vaistai", information for doctors), the pharmacy's own homeopathic and
// endobiogenic preparations, and gift vouchers.
const ROOTS = ['kosmetika', 'vaistazoles', 'aromaterapijos-produktai'];

const heading = (page) =>
    (page.querySelector('.breadcrumb__item--last')?.text ?? page.querySelector('h1')?.text ?? '').replace(/\s+/g, ' ').trim();

const subcategoryUrls = (page, url) => {
    const prefix = `${url}/`;
    const urls = page.querySelectorAll('a[href]')
        .map(a => a.getAttribute('href').split('?')[0])
        .filter(href => href.startsWith(prefix) && !href.slice(prefix.length).includes('/'));
    return [...new Set(urls)];
};

const lastPage = (page) => {
    const pages = page.querySelectorAll('a[href*="?page="]').map(a => Number(a.getAttribute('href').match(/[?&]page=(\d+)/)?.[1]) || 1);
    return pages.length ? Math.max(...pages) : 1;
};

const readItems = (html) => {
    const items = [];
    for (const match of html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
        try {
            const data = JSON.parse(match[1]);
            for (const node of data['@graph'] ?? [data]) {
                if (node['@type'] === 'ItemList') {
                    items.push(...(node.itemListElement ?? []).map(element => element.item).filter(Boolean));
                }
            }
        } catch (error) {
            // Not the product list.
        }
    }
    return items;
};

const toRow = (item, category, dates) => {
    const offer = Array.isArray(item.offers) ? item.offers[0] : item.offers;
    return buildRow({
        store: STORE,
        name: item.name,
        url: item.url,
        category,
        price: Number(offer?.price) || null,
        brand: item.brand?.name ?? null,
        ean: validEan(item.gtin13 ?? item.gtin),
        image: Array.isArray(item.image) ? item.image[0] : item.image,
        dates,
    });
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const seen = new Set();
    let outOfStock = 0;

    const scrapeListing = async (url, category) => {
        let currentPage = 1;
        let pageCount = 1;
        do {
            const html = await fetchText(currentPage > 1 ? `${url}?page=${currentPage}` : url);
            if (!html) {
                console.log(`Giving up on ${url} page ${currentPage}.`);
                return;
            }
            if (currentPage === 1) {
                pageCount = Math.min(lastPage(parse(html)), MAX_PAGES);
            }

            const rows = [];
            for (const item of readItems(html)) {
                if (!item.url || seen.has(item.url)) {
                    continue;
                }
                seen.add(item.url);
                const offer = Array.isArray(item.offers) ? item.offers[0] : item.offers;
                if (offer?.availability && !/InStock/.test(offer.availability)) {
                    outOfStock++;
                    continue;
                }
                rows.push(toRow(item, category, dates));
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

        for (const subUrl of subcategoryUrls(page, rootUrl)) {
            const subHtml = await fetchText(subUrl);
            const subName = subHtml ? heading(parse(subHtml)) : '';
            await sleep();
            await scrapeListing(subUrl, subName ? `${rootName}/${subName}` : rootName);
        }
        await scrapeListing(rootUrl, rootName);
    }

    poster.summary(`Skipped ${outOfStock} out-of-stock products.`);
})();
