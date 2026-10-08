import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, loadEanCache, MAX_PAGES, onlySelected, parsePrice, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://www.apotheka.lt';
const STORE = 'Apotheka';

// Magento 2 (GraphQL is off). Category pages are server-rendered, 34 products
// a page (?p=N, the page size is fixed), and filter by subcategory with
// ?cat={id}. The subcategory tree is in the page's filter JSON
// ({"selectedFilters":..., "filters":[{"attributeCode":"cat", ...}]}).
// Apotheka sells no prescription medicines online.
const ROOTS = [
    { key: 'nereceptiniai-vaistai', name: 'Nereceptiniai vaistai' },
    { key: 'vitaminai-ir-maisto-papildai', name: 'Vitaminai ir maisto papildai' },
    { key: 'odos-ir-plauku-kosmetika', name: 'Odos ir plaukų kosmetika' },
    { key: 'medicinos-priemones-ir-kita', name: 'Medicinos priemonės ir kita' },
];

// Filter entries that aren't product types: brand lists, sales and themed
// collections. They're scraped after the real subcategories (or not at all
// for brands), so a product keeps its product-type category.
const SKIPPED_SUBCATEGORIES = ['Prekių ženklai'];
const THEME_SUBCATEGORIES = ['IŠPARDAVIMAS', 'Sveika, Saule!', 'Jūsų sveikam grožiui', 'Jūsų vidiniam grožiui'];

const readSubcategories = (html) => {
    const start = html.indexOf('{"selectedFilters"');
    if (start < 0) {
        return [];
    }
    try {
        // The JSON sits in an HTML attribute or script; read just that object.
        const text = parse(`<p>${html.slice(start, start + 400000)}</p>`).text;
        const [json] = [...JSONSplitter(text)];
        const filters = JSON.parse(json).filters ?? [];
        const categories = filters.find(f => f.attributeCode === 'cat')?.items ?? [];
        return categories
            .map(item => ({ id: item.value, name: item.label.trim() }))
            .filter(item => !SKIPPED_SUBCATEGORIES.includes(item.name))
            .sort((a, b) => THEME_SUBCATEGORIES.includes(a.name) - THEME_SUBCATEGORIES.includes(b.name));
    } catch (error) {
        console.log(`Could not read the subcategory filter: ${error.message}`);
        return [];
    }
};

// Yields the first balanced {...} object of a string (the filter JSON is
// followed by more markup, so JSON.parse can't take the rest as is).
function* JSONSplitter(text) {
    let depth = 0;
    let inString = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inString) {
            if (c === '\\') {
                i++;
            } else if (c === '"') {
                inString = false;
            }
        } else if (c === '"') {
            inString = true;
        } else if (c === '{') {
            depth++;
        } else if (c === '}' && --depth === 0) {
            yield text.slice(0, i + 1);
            return;
        }
    }
}

const totalFound = (page) => {
    const match = page.text.match(/Rasta\s+(\d+)/);
    return match ? Number(match[1]) : 0;
};

// A card shows either one price (<ins>) or the regular price struck through
// (<del>) plus the discounted one (<ins><data value>).
const readCard = (card) => {
    const link = card.querySelector('a.product-card__link')?.getAttribute('href') ?? '';
    const special = card.querySelector('.price-item.special data')?.getAttribute('value');
    const plain = card.querySelector('.price-item ins data')?.getAttribute('value');
    const crossed = card.querySelector('.price-item del')?.text;

    return {
        url: link.split('?')[0],
        name: card.querySelector('.product-card__title')?.text,
        image: card.querySelector('.product-card__image img')?.getAttribute('src') ?? '',
        price: parsePrice(special ?? plain),
        regular: crossed ? parsePrice(crossed) : null,
    };
};

// The barcode and brand are in the product page's JSON-LD ("gtin").
// undefined = the page didn't load (see loadEanCache).
const fetchEan = async (url) => {
    const html = await fetchText(url);
    if (html === null) {
        return undefined;
    }
    const match = html.match(/"gtin1?3?":"(\d+)"/);
    return match ? validEan(match[1]) : null;
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const eans = loadEanCache('apotheka');
    const seen = new Set();
    let expectedTotal = 0;

    for (const root of onlySelected(ROOTS, r => r.key)) {
        const rootUrl = `${BASE_URL}/prekes/${root.key}`;
        const rootHtml = await fetchText(rootUrl);
        if (!rootHtml) {
            console.log(`Giving up on ${root.key}.`);
            continue;
        }
        expectedTotal += totalFound(parse(rootHtml));
        await sleep();

        // Subcategories first, then the root itself for anything filed under
        // none of them (already-seen products are skipped).
        const listings = readSubcategories(rootHtml).map(sub => ({ url: `${rootUrl}?cat=${sub.id}`, category: `${root.name}/${sub.name}` }));
        listings.push({ url: rootUrl, category: root.name });

        for (const listing of listings) {
            let currentPage = 1;
            let pageCount = 1;

            do {
                const html = await fetchText(`${listing.url}${currentPage > 1 ? `${listing.url.includes('?') ? '&' : '?'}p=${currentPage}` : ''}`);
                if (!html) {
                    console.log(`Giving up on ${listing.url} page ${currentPage}.`);
                    break;
                }
                const page = parse(html);
                const cards = page.querySelectorAll('article.product-card').map(readCard).filter(card => card.url && card.price);
                if (currentPage === 1) {
                    pageCount = Math.min(Math.ceil(totalFound(page) / Math.max(cards.length, 1)) || 1, MAX_PAGES);
                }

                const fresh = cards.filter(card => !seen.has(card.url));
                fresh.forEach(card => seen.add(card.url));
                await eans.fill(fresh.map(card => card.url), fetchEan);

                const rows = fresh.map(card => buildRow({ store: STORE, ...card, category: listing.category, ean: eans.get(card.url), dates }));
                await poster.post(rows, `${listing.category} page ${currentPage}/${pageCount}`);

                currentPage++;
                await sleep();
            } while (currentPage <= pageCount);

            eans.save();
        }
    }

    eans.save();
    poster.summary(`Site reports ${expectedTotal} across the roots; ${eans.fetched()} product pages fetched for EANs, ${eans.size()} in the cache.`);
})();
