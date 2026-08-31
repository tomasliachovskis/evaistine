import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

const BASE_URL = 'https://vynoteka.lt';
const LISTING_URL = `${BASE_URL}/maistas`;
const STORE = 'Vynoteka';

const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (2000 - 1000 + 1)) + 1000));

// Product cards below the fold are lazy-loaded on scroll, so the listing must be
// scrolled to the bottom repeatedly until the rendered card count stops growing.
const scrollUntilStable = async (page, selector) => {
    let count = -1;
    let stableStreak = 0;
    for (let i = 0; i < 15; i++) {
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await new Promise(res => setTimeout(res, 1200));
        const next = await page.$$eval(selector, els => els.length);
        if (next === count) {
            stableStreak++;
            if (stableStreak >= 2) {
                break;
            }
        } else {
            stableStreak = 0;
        }
        count = next;
    }
};

const cleanPrice = (value) => {
    if (!value) {
        return '';
    }
    return value.replace(/ /g, ' ').replace(/€/g, '').trim();
};

(async () => {
    const systemChromePath = '/usr/bin/google-chrome';
    const chromePath = `${process.env.HOME}/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome`;
    const launchOptions = {
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
    };

    // Prefer the system Chrome baked into the Sail image's Dockerfile
    // (permanent, survives container recreation) over puppeteer's own
    // downloaded copy under ~/.cache/puppeteer — that path lives in the
    // container's ephemeral home directory (not the bind-mounted project
    // dir, not a named Docker volume), so it silently disappears every
    // time the container is recreated (sail up / sail build), breaking
    // every scraper until someone remembers to re-run
    // `npx puppeteer browsers install chrome`. /usr/bin/chromium-browser
    // is NOT an equivalent fallback — on a stale image (Dockerfile changed
    // but the image wasn't rebuilt) it's Ubuntu's transitional snap-stub,
    // which exists on disk but just errors telling you to install the
    // snap — only safe once the Dockerfile's own
    // `ln -sf /usr/bin/google-chrome /usr/bin/chromium-browser` step has
    // actually run, hence checking google-chrome directly instead.
    if (fs.existsSync(systemChromePath)) {
        launchOptions.executablePath = systemChromePath;
    } else if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    } else {
        launchOptions.executablePath = puppeteer.executablePath();
    }

    const browser = await puppeteer.launch(launchOptions);
    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    console.log('Opening initial page to handle cookies.');
    await page.goto(`${LISTING_URL}?page=1`, { waitUntil: 'domcontentloaded' });

    try {
        await page.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
        await page.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    try {
        await page.waitForSelector('.product-card__wrapper', { timeout: 20000 });
    } catch (error) {
        console.log('Failed to load products on first page, stopping scrape.');
        await browser.close();
        process.exit(1);
    }

    const validityLink = await page.$$eval('a[href*="kainos-galioja-"]', (links) => {
        const link = links.find(el => /maisto leidinys/i.test(el.textContent || ''));
        return (link || links[0])?.getAttribute('href') || null;
    });

    let start_at = null;
    let end_at = null;
    if (validityLink) {
        const match = validityLink.match(/kainos-galioja-(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})/);
        if (match) {
            [, start_at, end_at] = match;
        }
    }

    if (!start_at && !end_at) {
        // No "kainos galioja" leaflet link found at all (e.g. between leaflet
        // cycles) — rather than dropping the whole run, fall back to today as
        // the start date with no end date. discounts:process only requires
        // one of the two to be set, and "valid from today, no known end" is a
        // safe default when the site gives us nothing else to go on.
        start_at = new Date().toLocaleDateString('en-CA');
        end_at = null;
        console.log(`Could not determine current price validity period, defaulting to start_at=${start_at}.`);
    }

    console.log(`Current price validity: ${start_at} - ${end_at}`);

    let currentPage = 1;
    let totalProducts = 0;
    let firstPageCardUrls = null;

    while (true) {
        const pageUrl = `${LISTING_URL}?page=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);
        await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });

        try {
            await page.waitForSelector('.product-card__wrapper', { timeout: 20000 });
        } catch (error) {
            console.log(`Failed to load products on page ${currentPage}, stopping scrape.`);
            break;
        }

        await sleep();

        // Product cards below the fold only render once the list is scrolled into view.
        await scrollUntilStable(page, '.product-card__wrapper');

        // Requesting a page number beyond the last one silently re-renders page 1
        // instead of returning an empty result, so detect that repeat and stop.
        const cardUrls = await page.$$eval('.product-card__title', links => links.map(l => l.href));
        if (currentPage === 1) {
            firstPageCardUrls = cardUrls;
        } else if (JSON.stringify(cardUrls) === JSON.stringify(firstPageCardUrls)) {
            console.log(`Page ${currentPage} repeats page 1's content, reached the end. Stopping.`);
            break;
        }

        const rawBlocks = await page.$$eval('.product-card__wrapper', (blocks) => {
            return blocks.map(block => {
                const link = block.querySelector('.product-card__title');
                const product_url = link?.href ?? '';
                const img = block.querySelector('.product-card__image img');
                const name = img?.getAttribute('alt')?.trim() ?? '';
                const image_url = img?.src ?? '';

                if (!name || !product_url) {
                    return null;
                }

                const priceBlock = block.querySelector('.product-price--product-card');
                const oldPriceEl = priceBlock?.querySelector('.product-price__old');

                const intPart = priceBlock?.querySelector('.product-price__int')?.textContent.trim() ?? '';
                const decimalPart = priceBlock?.querySelector('.product-price__decimal')?.textContent.trim() ?? '';
                const discounted_price = decimalPart ? `${intPart}.${decimalPart}` : intPart;
                const original_price = oldPriceEl?.textContent.trim() ?? '';
                const discount_percent = block.querySelector('.product-badges__item.bottom .badge--primary')?.textContent.trim() ?? '';
                const category = block.querySelector('.caption span')?.textContent.trim() ?? '';
                const info = block.querySelector('.product-price--extra-small .product-price__int')?.textContent.trim() ?? '';

                return {
                    name,
                    product_url,
                    image_url,
                    discounted_price,
                    original_price,
                    discount_percent,
                    category,
                    info,
                };
            });
        });

        if (rawBlocks.length === 0) {
            console.log(`No product cards on page ${currentPage}, stopping scrape.`);
            break;
        }

        const productBlocks = rawBlocks.filter(Boolean);

        if (productBlocks.length === 0) {
            console.log(`No usable product cards on page ${currentPage}, continuing.`);
            currentPage++;
            continue;
        }

        const cleanedProducts = productBlocks.map(product => ({
            ...product,
            discounted_price: cleanPrice(product.discounted_price),
            original_price: cleanPrice(product.original_price),
        }));

        totalProducts += cleanedProducts.length;
        console.log(`Scraped ${cleanedProducts.length} products from page ${currentPage}`);
        console.log(`Total ${totalProducts}`);

        try {
            const data = cleanedProducts.map(product => ({
                name: product.name,
                discounted_price: product.discounted_price,
                original_price: product.original_price,
                discount_percent: product.discount_percent,
                info: product.info,
                start_at,
                end_at,
                product_url: product.product_url,
                image_url: product.image_url,
                category: product.category,
                store: STORE,
            }));
            await axios.post('http://127.0.0.1/api/scrapers', data);
            console.log(`Posted ${data.length} products from page ${currentPage} to API`);
        } catch (error) {
            console.error(`Error posting products from page ${currentPage}:`, error.message);
        }

        currentPage++;
    }

    console.log(`Scraped total ${totalProducts} products from ${currentPage} page(s)`);
    console.log('Scraping completed.');

    await browser.close();
})();
