// Shared scraper for pharmacies on the Verskis e-shop platform (Piliulė,
// Universiteto vaistinė). Category pages are server-rendered; each lists
// its subcategories as links under its own path, and pages by offset
// (/page/{offset}, the ">>" link carries the last one). The cards only show
// the current price, and neither the card nor the product page has a
// barcode, so these rows match other pharmacies by name only.
import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, MAX_PAGES, onlySelected, parsePrice, sleep } from './pharmacy.js';

// Subcategory slugs that are brand lists, not product types.
const BRAND_LIST = /^gamintojai/;

const heading = (page) => page.querySelector('h1')?.text.replace(/\s+/g, ' ').trim() ?? '';

const lastOffset = (page) => {
    const offsets = page.querySelectorAll('.pagination-links a[data-offset]').map(a => Number(a.getAttribute('data-offset')) || 0);
    return offsets.length ? Math.max(...offsets) : 0;
};

const subcategoryUrls = (page, url) => {
    const prefix = `${url}/`;
    const urls = page.querySelectorAll('a[href]')
        .map(a => a.getAttribute('href'))
        .filter(href => href.startsWith(prefix) && !href.slice(prefix.length).includes('/') && !BRAND_LIST.test(href.slice(prefix.length)));
    return [...new Set(urls)];
};

// "9<sup>60</sup> €" (Piliulė) or "10.29 €" (Universiteto), after an
// optional "Kaina" label.
const readPrice = (strong) => {
    if (!strong) {
        return null;
    }
    const cents = strong.querySelector('sup')?.text.trim();
    strong.querySelectorAll('span, sup').forEach(el => el.remove());
    const whole = strong.text.replace(/\u00a0/g, ' ').trim();
    return parsePrice(cents ? `${whole}.${cents}` : whole);
};

// Sold-out cards (class "soldout") are skipped: they can't be bought, so a
// price comparison shouldn't offer them.
const readCards = (page) => page.querySelectorAll('.product-item').map(card => {
    const link = card.querySelector('a.item-title');
    return {
        id: card.getAttribute('data-pid'),
        soldOut: card.classList.contains('soldout'),
        name: link?.getAttribute('title') ?? link?.text,
        url: link?.getAttribute('href') ?? '',
        // Listing thumbnails are 164px on some shops; the 384px size exists for all.
        image: (card.querySelector('.item-image-wrapper img')?.getAttribute('src') ?? '').replace(/\/\d+x\d+\.g\//, '/384x384.g/'),
        price: parsePrice(card.querySelector('strong.price')?.childNodes.filter(n => n.nodeType === 3).map(n => n.text).join(' ').replace(/ /g, ' ').trim().replace(/\s+/, '.')),
    };
});

export const runVerskis = async ({ store, baseUrl, roots }) => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(store);
    const seen = new Set();
    let soldOut = 0;

    const scrapeListing = async (url, category) => {
        let offset = 0;
        let last = 0;
        let pageNumber = 1;
        do {
            const html = await fetchText(offset ? `${url}/page/${offset}` : url);
            if (!html) {
                console.log(`Giving up on ${url} offset ${offset}.`);
                return;
            }
            const page = parse(html);
            const cards = readCards(page);
            if (offset === 0) {
                last = lastOffset(page);
            }

            const rows = [];
            for (const card of cards) {
                if (!card.id || seen.has(card.id)) {
                    continue;
                }
                seen.add(card.id);
                if (card.soldOut) {
                    soldOut++;
                    continue;
                }
                rows.push(buildRow({ store, ...card, category, dates }));
            }
            await poster.post(rows, `${category} page ${pageNumber}`);

            offset += cards.length || 1;
            pageNumber++;
            await sleep();
        } while (offset <= last && pageNumber <= MAX_PAGES);
    };

    for (const root of onlySelected(roots)) {
        const rootUrl = `${baseUrl}/${root}`;
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
        // Then the root itself, for products filed under no subcategory.
        await scrapeListing(rootUrl, rootName);
    }

    poster.summary(`Skipped ${soldOut} sold-out products.`);
};
