// Shared pieces of the pharmacy e-shop scrapers (scrapers/{store}.js).
//
// Environment, for every scraper:
//   SCRAPER_API_URL     where rows are POSTed (default: this app's local API)
//   SCRAPER_DRY_RUN=1   print a sample of the rows instead of POSTing them
//   SCRAPER_ONLY=a,b    only these top-level categories (scraper-specific keys)
//   SCRAPER_MAX_PAGES=1 at most this many listing pages per category
// A test run that proves the parser works:
//   ./vendor/bin/sail bash -c 'SCRAPER_DRY_RUN=1 SCRAPER_MAX_PAGES=1 node scrapers/{store}.js'
import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

export const API_URL = process.env.SCRAPER_API_URL || 'http://localhost/api/scrapers';
export const DRY_RUN = Boolean(process.env.SCRAPER_DRY_RUN);
export const ONLY = (process.env.SCRAPER_ONLY || '').split(',').map(s => s.trim()).filter(Boolean);
export const MAX_PAGES = Number(process.env.SCRAPER_MAX_PAGES) || Infinity;

export const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

const MAX_ATTEMPTS = 3;

export const sleep = (min = 1000, max = 2000) =>
    new Promise(res => setTimeout(res, Math.floor(Math.random() * (max - min + 1)) + min));

// Keeps only the categories named in SCRAPER_ONLY (by `key`), or all of them.
export const onlySelected = (items, key = item => item) =>
    ONLY.length ? items.filter(item => ONLY.includes(key(item))) : items;

// No pharmacy publishes validity dates for its everyday e-shop prices, and
// discounts:process drops rows where both dates are null. Use the current
// Monday–Sunday week (as ermitazas.js does on superakcijos): daily runs within
// one week dedupe onto the same Discount rows, and last week's rows expire on
// their own.
export const currentWeek = () => {
    const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Vilnius' }));
    const monday = new Date(today);
    monday.setDate(today.getDate() - ((today.getDay() + 6) % 7));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return { start_at: fmt(monday), end_at: fmt(sunday) };
};

// GS1 check digit for the digits of a GTIN without its last one.
export const gtinCheckDigit = (body) => {
    const sum = [...String(body)].reverse().reduce((acc, digit, i) => acc + Number(digit) * (i % 2 === 0 ? 3 : 1), 0);
    return String((10 - (sum % 10)) % 10);
};

// A retail barcode another pharmacy can match on, or null. Rejects bad check
// digits and in-store EAN-13s (prefix 2), the same rule as
// ProcessDiscounts::normalizeEan(); a 12-digit UPC-A becomes its EAN-13.
export const validEan = (value) => {
    const code = String(value ?? '').trim();
    if (!/^(\d{8}|\d{12,14})$/.test(code) || /^0+$/.test(code)) {
        return null;
    }
    if (gtinCheckDigit(code.slice(0, -1)) !== code.slice(-1)) {
        return null;
    }
    const ean = code.length === 12 ? `0${code}` : code;
    return ean.length === 13 && ean.startsWith('2') ? null : ean;
};

// "12,55 €", "12.55", "1 234,50€" -> 12.55 (number) or null.
export const parsePrice = (text) => {
    const cleaned = String(text ?? '').replace(/\s| |€|eur/gi, '').replace(',', '.');
    const value = parseFloat(cleaned);
    return Number.isFinite(value) && value > 0 ? value : null;
};

const money = (value) => value.toFixed(2);

// The DiscountTemp row for one product. `price` is what the shopper pays now
// (the promo or member price when there is one), `regular` the price without
// it. Rows without a lower promo price are plain catalog prices: only
// `discounted_price` is set, as DefaultRules::validate() expects.
export const buildRow = ({ store, name, url, category, price, regular = null, card = false, condition = null, brand = null, ean = null, image = '', dates }) => {
    const hasDiscount = Boolean(price && regular && price < regular);
    return {
        name: String(name ?? '').replace(/\s+/g, ' ').trim(),
        category,
        product_url: url,
        store,
        original_price: hasDiscount ? money(regular) : '',
        discounted_price: price ? money(price) : (regular ? money(regular) : ''),
        discount_percent: hasDiscount ? String(Math.round((1 - price / regular) * 100)) : '',
        card: Boolean(hasDiscount && card),
        condition: hasDiscount && condition ? condition : null,
        start_at: dates.start_at,
        end_at: dates.end_at,
        brand: brand || null,
        ean: ean || null,
        image_url: image || '',
    };
};

// A 404 is final; anything else (timeouts, 5xx, 429) is retried, and a 429
// ("too many requests") waits long enough for the site's limit to reset.
const withRetry = async (label, fn) => {
    for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
        try {
            return await fn();
        } catch (error) {
            const status = error.response?.status;
            console.log(`Attempt ${attempt} failed for ${label}: ${error.message}`);
            if (status === 404) {
                return null;
            }
            if (attempt < MAX_ATTEMPTS) {
                await (status === 429 ? sleep(20000, 40000) : sleep(2000, 4000));
            }
        }
    }
    return null;
};

// GET with retries; a transient failure must not silently cut a category's
// pagination short. Returns null after the last failed attempt.
export const fetchText = (url, headers = {}) =>
    withRetry(url, async () => {
        const { data } = await axios.get(url, {
            // axios asks for JSON by default, and some shops (Laravel) answer
            // that with a JSON fragment instead of the page.
            headers: { 'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml', 'Accept-Language': 'lt-LT,lt;q=0.9', ...headers },
            timeout: 30000,
            responseType: 'text',
            transformResponse: [d => d],
        });
        return data;
    });

export const fetchJson = (url, params = {}, headers = {}) =>
    withRetry(url, async () => {
        const { data } = await axios.get(url, {
            params,
            headers: { 'User-Agent': USER_AGENT, Accept: 'application/json', ...headers },
            timeout: 30000,
        });
        return data;
    });

// POSTs rows to the scraper API (or prints them on a dry run) and keeps the
// totals the scraper prints at the end.
export const createPoster = (store) => {
    const stats = { posted: 0, failed: 0, withEan: 0, discounted: 0 };
    let printed = 0;

    const post = async (rows, label) => {
        const valid = rows.filter(row => row.name && row.discounted_price);
        if (!valid.length) {
            return;
        }
        stats.withEan += valid.filter(row => row.ean).length;
        stats.discounted += valid.filter(row => row.original_price).length;

        if (DRY_RUN) {
            if (printed < 3) {
                console.log(JSON.stringify(valid.slice(0, 3 - printed), null, 1));
                printed += Math.min(valid.length, 3 - printed);
            }
            stats.posted += valid.length;
            console.log(`${label}: ${valid.length} rows (dry run, total ${stats.posted})`);
            return;
        }

        try {
            await axios.post(API_URL, valid);
            stats.posted += valid.length;
            console.log(`${label}: posted ${valid.length} (total ${stats.posted})`);
        } catch (error) {
            stats.failed += valid.length;
            console.error(`Error posting ${label}:`, error.message);
        }
    };

    const summary = (extra = '') =>
        console.log(`${store}: ${stats.posted} rows${DRY_RUN ? ' (dry run)' : ''}, ${stats.discounted} discounted, ${stats.withEan} with EAN, ${stats.failed} failed to post. ${extra}`.trim());

    return { post, stats, summary };
};

// product URL -> EAN, kept between runs in storage/app/scrapers/, for stores
// whose listing pages carry no barcode: each product page is fetched once,
// not on every run. A null value means "page fetched, no barcode on it"; a
// failed fetch is not stored, so it's tried again next run.
//
// Product pages are fetched one at a time with a pause (pharmacies rate-limit
// parallel requests with 429s), at most SCRAPER_EAN_LIMIT new ones per run
// (default 1500): a large catalog fills its cache over a few nightly runs, and
// rows posted before then go out without an EAN and match by name.
export const EAN_LIMIT = Number(process.env.SCRAPER_EAN_LIMIT ?? 1500);
export const loadEanCache = (slug) => {
    const dir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../storage/app/scrapers');
    const file = path.join(dir, `ean-${slug}.json`);
    let map = {};
    try {
        map = JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (error) {
        // First run, or an unreadable cache: start empty.
    }
    let fetched = 0;
    const has = (url) => Object.prototype.hasOwnProperty.call(map, url);

    return {
        has,
        // Fills in the EAN of each url not cached yet, with `fetchEan(url)`
        // returning the EAN, null (no barcode on the page) or undefined
        // (fetch failed, try again next run).
        fill: async (urls, fetchEan) => {
            for (const url of urls) {
                if (has(url) || fetched >= EAN_LIMIT) {
                    continue;
                }
                fetched++;
                const ean = await fetchEan(url);
                if (ean !== undefined) {
                    map[url] = ean;
                }
                await sleep(500, 1500);
            }
        },
        get: (url) => map[url] ?? null,
        save: () => {
            if (DRY_RUN) {
                return;
            }
            fs.mkdirSync(dir, { recursive: true });
            fs.writeFileSync(file, JSON.stringify(map));
        },
        size: () => Object.keys(map).length,
        fetched: () => fetched,
    };
};
