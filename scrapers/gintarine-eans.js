import axios from 'axios';
import { DRY_RUN, loadEanCache, loadUrlList, sleep, USER_AGENT, validEan } from './lib/pharmacy.js';

// Gintarinė's barcodes, fetched slowly on their own nightly run
// (scrapers:fetch-eans, 00:30, done before the 03:00 main scrape).
//
// The barcode is only on the product page (`<meta itemprop="gtin">`); the
// listing pages, sitemap and feeds have none. Fetching ~18 000 product pages
// inside the main scrape got 429s that also cost listing pages, so the main
// scrape (gintarine-vaistine.js) only reads this cache and saves the product
// URL list this run works through. One page at a time, 4-7 s apart; a 429
// waits (Retry-After, else 2, 4, 8 min) and five in a row end the night. The
// whole catalog takes about two weeks of nights, then only new products.
//
// Environment: SCRAPER_EAN_MINUTES (time budget, default 140),
// SCRAPER_DRY_RUN=1 (print, don't save the cache).

const SLUG = 'gintarine-vaistine';
const BUDGET_MS = Number(process.env.SCRAPER_EAN_MINUTES ?? 140) * 60 * 1000;
const MAX_429_IN_A_ROW = 5;
const SAVE_EVERY = 50;

// { ean } (null = page has no barcode), { status: 429, retryAfter }, or
// { failed: true } for any other error (tried again on a later night).
const fetchEan = async (url) => {
    try {
        const { data } = await axios.get(url, {
            headers: { 'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml', 'Accept-Language': 'lt-LT,lt;q=0.9' },
            timeout: 30000,
            responseType: 'text',
            transformResponse: [d => d],
        });
        const match = data.match(/itemprop="gtin" content="(\d+)"/);
        return { ean: match ? validEan(match[1]) : null };
    } catch (error) {
        const status = error.response?.status;
        if (status === 404) {
            return { ean: null };
        }
        if (status === 429) {
            return { status, retryAfter: Number(error.response.headers['retry-after']) || null };
        }
        console.log(`${url}: ${error.message}`);
        return { failed: true };
    }
};

(async () => {
    const started = Date.now();
    const eans = loadEanCache(SLUG);
    const urls = loadUrlList(SLUG).filter(url => !eans.has(url));
    console.log(`${urls.length} product pages without a cached EAN.`);

    let fetched = 0;
    let found = 0;
    let failed = 0;
    let in429Row = 0;
    let backoffMinutes = 2;

    for (let i = 0; i < urls.length; i++) {
        if (Date.now() - started > BUDGET_MS) {
            console.log('Time budget used up, stopping for tonight.');
            break;
        }

        const result = await fetchEan(urls[i]);

        if (result.status === 429) {
            in429Row++;
            if (in429Row >= MAX_429_IN_A_ROW) {
                console.log(`${MAX_429_IN_A_ROW} 429s in a row, stopping for tonight.`);
                break;
            }
            const waitSeconds = result.retryAfter ?? backoffMinutes * 60;
            console.log(`429, waiting ${waitSeconds} s.`);
            await sleep(waitSeconds * 1000, waitSeconds * 1000);
            backoffMinutes = Math.min(backoffMinutes * 2, 8);
            i--; // the same page again
            continue;
        }

        in429Row = 0;
        backoffMinutes = 2;
        fetched++;

        if (result.failed) {
            failed++;
        } else {
            eans.set(urls[i], result.ean);
            if (result.ean) {
                found++;
            }
            if (DRY_RUN) {
                console.log(`${urls[i]} -> ${result.ean}`);
            }
        }

        if (fetched % SAVE_EVERY === 0) {
            eans.save();
            console.log(`${fetched} pages, ${found} EANs.`);
        }

        await sleep(4000, 7000);
    }

    eans.save();
    const left = urls.length - fetched + failed;
    console.log(`Gintarinė EANs: ${fetched} pages fetched, ${found} EANs found, ${failed} failed, ${left} left, ${eans.size()} in the cache.`);
})();
