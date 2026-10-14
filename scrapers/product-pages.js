import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { DRY_RUN, loadEanCache, loadUrlList, sleep, USER_AGENT } from './lib/pharmacy.js';
import { PRODUCT_PAGE_EXTRACTORS } from './lib/product-page.js';

// One pharmacy's product pages, fetched slowly on their own nightly run
// (scrapers:fetch-product-pages, from 00:30, done before the 03:00 main
// scrape). Each page gives:
// - the barcode, into the EAN cache the main scrape reads (Gintarinė's main
//   scrape fetches no product pages at all: 429s, 2026-10-13);
// - the description text, labelled attributes, name and breadcrumb, posted
//   to this machine's own API (never production): the facts are extracted
//   here and product-facts:push sends them on (docs/product-facts.md).
//
// Which pages: production says which of its active offers still need facts
// ({SCRAPER_PROD_API_URL}/product-facts/pending/{store}), so the work follows
// what the live site lists, and only products with at least 2 price history
// rows (production decides). The rest of the time budget goes to barcodes
// only: pages from the main scrape's list (urls-{slug}.json) without a
// cached EAN, no text posted (Gintarinė's EANs come only from this run).
// When production can't be reached, only that EAN pass runs.
//
// Every page is tried once (owner, 2026-10-14): a page fetched here is never
// fetched again, also when it had no text or was gone (404). Those are
// posted too, empty, so production knows they were tried. Only a page that
// failed to load (timeout, 5xx) is tried again on a later night. One page at a time,
// a pause between; a 429 waits (Retry-After, else 2, 4, 8 min) and five in a
// row end the night.
//
// Environment: SCRAPER_STORE (slug: gintarine-vaistine, apotheka,
// mano-vaistine), SCRAPER_PAGES_MINUTES (time budget, default 140),
// SCRAPER_PAGES_LIMIT (at most this many pages, for tests), SCRAPER_DRY_RUN=1
// (print, post and save nothing).

const SLUG = process.env.SCRAPER_STORE;
const config = PRODUCT_PAGE_EXTRACTORS[SLUG];
if (!config) {
    console.error(`SCRAPER_STORE must be one of: ${Object.keys(PRODUCT_PAGE_EXTRACTORS).join(', ')}`);
    process.exit(1);
}

const BUDGET_MS = Number(process.env.SCRAPER_PAGES_MINUTES ?? 140) * 60 * 1000;
const LIMIT = Number(process.env.SCRAPER_PAGES_LIMIT) || Infinity;
const PROD_API = process.env.SCRAPER_PROD_API_URL || null;
const MAX_429_IN_A_ROW = 5;
const POST_EVERY = 25;
const SAVE_EVERY = 50;
// Gintarinė answered parallel or quick requests with 429s; the others are
// fine with a shorter pause.
const DELAY = SLUG === 'gintarine-vaistine' ? [4000, 7000] : [2000, 4000];

// Always the local app, even when SCRAPER_API_URL points the scrapers at
// production: extraction runs here (owner, 2026-10-14).
const LOCAL_API = process.env.SCRAPER_LOCAL_API_URL || 'http://localhost/api/scrapers';

const STORAGE = path.resolve('storage/app/scrapers');
const pagesFile = path.join(STORAGE, `pages-${SLUG}.json`);

// url -> 'YYYY-MM-DD' the page was last fetched.
const loadFetched = () => {
    try {
        return JSON.parse(fs.readFileSync(pagesFile, 'utf8'));
    } catch (error) {
        return {};
    }
};
const saveFetched = (map) => {
    if (DRY_RUN) {
        return;
    }
    fs.mkdirSync(STORAGE, { recursive: true });
    fs.writeFileSync(pagesFile, JSON.stringify(map));
};

// { html } or { status: 429, retryAfter } or { gone: true } (404) or
// { failed: true } for any other error (tried again on a later night).
const fetchPage = async (url) => {
    try {
        const { data } = await axios.get(url, {
            headers: { 'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml', 'Accept-Language': 'lt-LT,lt;q=0.9' },
            timeout: 30000,
            responseType: 'text',
            transformResponse: [d => d],
        });
        return { html: data };
    } catch (error) {
        const status = error.response?.status;
        if (status === 404 || status === 410) {
            return { gone: true };
        }
        if (status === 429) {
            return { status, retryAfter: Number(error.response.headers['retry-after']) || null };
        }
        console.log(`${url}: ${error.message}`);
        return { failed: true };
    }
};

// True when the API took the batch. Pages of a failed batch are not marked
// fetched, so a later night sends them again.
const postPages = async (pages) => {
    if (pages.length === 0 || DRY_RUN) {
        return true;
    }
    try {
        await axios.post(`${LOCAL_API}/product-facts`, { store: config.store, pages }, { timeout: 60000 });
        return true;
    } catch (error) {
        console.log(`POST product-facts failed: ${error.response?.status ?? ''} ${error.message}`);
        return false;
    }
};

(async () => {
    const started = Date.now();
    const today = new Date().toISOString().slice(0, 10);
    const fetchedOn = loadFetched();
    const eans = loadEanCache(SLUG, { limit: 0 });

    let urls = null;
    if (PROD_API) {
        try {
            const { data } = await axios.get(`${PROD_API}/product-facts/pending/${encodeURIComponent(config.store)}`, { timeout: 60000 });
            urls = data.urls.filter(url => !fetchedOn[url]);
            console.log(`${config.store} in production: ${data.active_offers} active offers, ${data.products_with_facts}/${data.products} products with facts, ${data.pending} pages pending, ${urls.length} not tried here yet.`);
        } catch (error) {
            console.log(`Could not ask production which pages are pending (${error.response?.status ?? ''} ${error.message}); using the saved URL list.`);
        }
    }
    // [url, wantsFacts]: production's list first, then EAN-only pages.
    const queue = (urls ?? []).map(url => [url, true]);
    const queued = new Set(urls ?? []);
    const eanOnly = loadUrlList(SLUG).filter(url => !queued.has(url) && !eans.has(url));
    eanOnly.forEach(url => queue.push([url, false]));
    console.log(`${config.store}: ${queue.length - eanOnly.length} pages for facts, then ${eanOnly.length} for EANs only.`);

    let fetched = 0;
    let withText = 0;
    let withEan = 0;
    let failed = 0;
    let in429Row = 0;
    let backoffMinutes = 2;
    let batch = [];
    let postFailures = 0;
    const flush = async () => {
        if (await postPages(batch)) {
            batch.forEach(page => { fetchedOn[page.product_url] = today; });
        } else {
            postFailures++;
        }
        batch = [];
    };

    for (let i = 0; i < queue.length && fetched < LIMIT; i++) {
        if (Date.now() - started > BUDGET_MS) {
            console.log('Time budget used up, stopping for tonight.');
            break;
        }

        const [url, wantsFacts] = queue[i];
        const result = await fetchPage(url);

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
        } else if (result.gone) {
            if (wantsFacts) {
                batch.push({ product_url: url, text: '', attributes: {} });
            } else {
                eans.set(url, null);
            }
        } else {
            const page = config.extract(result.html);
            if (page.ean || !eans.has(url)) {
                eans.set(url, page.ean);
            }
            if (page.ean) {
                withEan++;
            }
            if (wantsFacts) {
                if (page.text || Object.keys(page.attributes).length) {
                    withText++;
                }
                batch.push({ product_url: url, text: page.text, attributes: page.attributes, name: page.name, category: page.category });
            }
            if (DRY_RUN) {
                console.log(`${url}\n  EAN ${page.ean}, ${page.text.length} chars, ${JSON.stringify(page.attributes)}`);
            }
        }

        if (batch.length >= POST_EVERY) {
            await flush();
        }
        if (fetched % SAVE_EVERY === 0) {
            eans.save();
            saveFetched(fetchedOn);
            console.log(`${fetched} pages, ${withText} with text, ${withEan} EANs.`);
        }

        if (postFailures >= 3) {
            console.log('Three failed posts, stopping (is the local app running?).');
            break;
        }

        await sleep(...DELAY);
    }

    await flush();
    eans.save();
    saveFetched(fetchedOn);
    const left = queue.length - fetched + failed;
    console.log(`${config.store} product pages: ${fetched} fetched, ${withText} with text, ${withEan} EANs, ${failed} failed, ${left} left.`);
})();
