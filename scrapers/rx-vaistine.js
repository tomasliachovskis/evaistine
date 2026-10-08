import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, MAX_PAGES, onlySelected, parsePrice, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://rx-vaistine.lt';
const STORE = 'Rx vaistinė';

// PrestaShop. A category page takes ?resultsPerPage=500, so most categories
// fit on one page (?page=N for the rest). Product URLs end in the barcode
// (".../7101-acc-200mg-...-n20-4030855000036.html").
//
// Second-level categories from the site's menu, by top-level category.
// Prescription medicines (28) are not scraped. After the subcategories, each
// top-level category is read once more for products filed under none of
// them, so a subcategory added later is still covered.
const ROOTS = [
    {
        key: '20-nereceptiniai-vaistai',
        name: 'Nereceptiniai vaistai',
        subs: ['21-persalimui', '23-skausmui', '22-sirdziai', '61-alergijai', '63-nervu-sistetami', '64-lytiniai-sveikatai', '66-krautotakai-gerinti', '67-odos-negalavimai', '24-virskinimo-sistemai', '68-salpimo-sistemai', '65-kiti-nereceptiniai-vaistai'],
    },
    {
        key: '3-vitaminai',
        name: 'Vitaminai | Maisto papildai',
        subs: ['44-vitaminai-ir-mineralai', '45-sportuojantiems', '46-maisto-papildai'],
    },
    {
        key: '43-medicinos-pagalbos-priemones',
        name: 'Medicinos pagalbos priemonės',
        subs: ['69-suaugusiuju-slauga', '70-zaizdu-prieziura', '103-iklotai-paklotai-sauskelnes-ir-kelnaites', '107-priemones-slapimo-ir-ismatu-surinkimui', '74-diabeto-prieziura', '71-diagnostikos-ir-matavimo-priemones', '72-specialios-paskirties-maistas', '73-kateteriai', '106-astmos-tarpines', '105-kitos-prekes'],
    },
    { key: '29-Kitos-prekes', name: 'Kosmetika', subs: [] },
];

const PAGE_SIZE = 500;

const heading = (page) => page.querySelector('h1')?.text.replace(/\s+/g, ' ').trim() ?? '';

// The current price is in .normal-price-container; a struck-through regular
// price, when there is one, in the (otherwise empty) price container next
// to it.
const readCard = (item) => {
    const url = item.querySelector('.product-title a')?.getAttribute('href') ?? '';
    const regularText = item.querySelector('.no-discount-price-container')?.text.trim();
    return {
        id: item.getAttribute('data-id-product'),
        url,
        name: item.querySelector('.product-name-container')?.text.trim(),
        image: item.querySelector('.product-image img')?.getAttribute('data-full-size-image-url') ?? item.querySelector('.product-image img')?.getAttribute('src') ?? '',
        price: parsePrice(item.querySelector('meta[itemprop="price"]')?.getAttribute('content')) ?? parsePrice(item.querySelector('.normal-price-container')?.text),
        regular: regularText ? parsePrice(regularText) : null,
        ean: validEan(url.match(/-(\d{8,14})\.html$/)?.[1]),
    };
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const seen = new Set();

    const scrapeListing = async (slug, prefix) => {
        let currentPage = 1;
        let pageCount = 1;
        let category = prefix;

        do {
            const html = await fetchText(`${BASE_URL}/${slug}?resultsPerPage=${PAGE_SIZE}${currentPage > 1 ? `&page=${currentPage}` : ''}`);
            if (!html) {
                console.log(`Giving up on ${slug} page ${currentPage}.`);
                return;
            }
            const page = parse(html);
            if (currentPage === 1) {
                const name = heading(page);
                category = prefix && name && name !== prefix ? `${prefix}/${name}` : (prefix || name);
                const pages = page.querySelectorAll('.pagination a.js-search-link')
                    .map(a => Number(new URL(a.getAttribute('href'), BASE_URL).searchParams.get('page')) || 1);
                pageCount = Math.min(Math.max(1, ...pages), MAX_PAGES);
            }

            const rows = [];
            for (const card of page.querySelectorAll('li.js-product-miniature').map(readCard)) {
                if (!card.id || seen.has(card.id)) {
                    continue;
                }
                seen.add(card.id);
                rows.push(buildRow({ store: STORE, ...card, category, dates }));
            }
            await poster.post(rows, `${category} page ${currentPage}/${pageCount}`);

            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    };

    for (const root of onlySelected(ROOTS, r => r.key)) {
        for (const sub of root.subs) {
            await scrapeListing(sub, root.name);
        }
        await scrapeListing(root.key, root.name);
    }

    poster.summary();
})();
