import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

const BASE_URL = 'https://www.thomas-philipps.lt';
const STORE = 'Thomas Philipps';
const CATEGORY_PATHS = [
    'svaros-prekes',
    'maisto-prekes',
    'kuno-prieziuros-ir-higienos-prekes',
    'sunu-maistas',
    'maistas-katems',
    'kitos-kaciu-prekes',
];

const MAX_CONCURRENT_REQUESTS = 12;

const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (2000 - 1000 + 1)) + 1000));

const cleanPrice = (value) => {
    if (!value) {
        return '';
    }
    return value.replace(/ /g, ' ').replace(/€/g, '').trim();
};

// The listing pages only expose a coarse top-level category (e.g. "Maisto prekės").
// Each product's own page has a breadcrumb with the real subcategory path
// (Pagrindinis > Maisto prekės > Šokoladai > Product name) - take the two levels
// right before the product name itself for a specific category like "Maisto prekės/Šokoladai".
const extractCategoryFromProductPage = (html) => {
    const breadcrumbMatch = html.match(/<div class="breadcrumbs">([\s\S]*?)<\/div>/);
    if (!breadcrumbMatch) {
        return null;
    }

    const titles = [...breadcrumbMatch[1].matchAll(/title="([^"]+)"/g)].map(m => m[1]);
    if (titles.length < 2) {
        return null;
    }

    const withoutProductName = titles.slice(0, -1);
    const lastTwo = withoutProductName.slice(-2);

    return lastTwo.length > 0 ? lastTwo.join('/') : null;
};

const enrichCategory = async (product, fallbackCategory) => {
    try {
        const response = await fetch(product.product_url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            },
        });

        if (!response.ok) {
            return { ...product, category: fallbackCategory };
        }

        const html = await response.text();
        const category = extractCategoryFromProductPage(html);

        return { ...product, category: category ?? fallbackCategory };
    } catch (error) {
        return { ...product, category: fallbackCategory };
    }
};

const enrichCategories = async (products, fallbackCategory) => {
    const enriched = [];

    for (let i = 0; i < products.length; i += MAX_CONCURRENT_REQUESTS) {
        const batch = products.slice(i, i + MAX_CONCURRENT_REQUESTS);
        const results = await Promise.all(batch.map(product => enrichCategory(product, fallbackCategory)));
        enriched.push(...results);
    }

    return enriched;
};

(async () => {
    const chromePath = `${process.env.HOME}/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome`;
    const launchOptions = {
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
    };

    // /usr/bin/chromium-browser is Ubuntu's transitional snap-stub package —
    // it exists on disk but just errors telling you to install the snap, so
    // it must never be used as executablePath. Prefer puppeteer's own managed
    // Chrome, falling back to puppeteer's dynamic resolution if that specific
    // cached version isn't present (e.g. after a puppeteer upgrade).
    if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    } else {
        launchOptions.executablePath = puppeteer.executablePath();
    }

    const browser = await puppeteer.launch(launchOptions);
    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    console.log('Reading current leaflet validity period.');
    let start_at = null;
    let end_at = null;

    try {
        await page.goto(`${BASE_URL}/leidinys`, { waitUntil: 'domcontentloaded' });
        const validityText = await page.$eval('#pdf .content p', el => el.textContent).catch(() => null);

        if (validityText) {
            const match = validityText.match(/(\d{4})\.(\d{2})\.(\d{2})[–-](\d{2})\.(\d{2})/);
            if (match) {
                const [, year, startMonth, startDay, endMonth, endDay] = match;
                start_at = `${year}-${startMonth}-${startDay}`;
                end_at = `${year}-${endMonth}-${endDay}`;
            }
        }
    } catch (error) {
        console.log('Could not read leaflet page:', error.message);
    }

    if (!start_at && !end_at) {
        console.log('Could not determine current price validity period, stopping scrape.');
        await browser.close();
        process.exit(1);
    }

    console.log(`Current price validity: ${start_at} - ${end_at}`);

    let totalProducts = 0;

    for (const categoryPath of CATEGORY_PATHS) {
        let currentPage = 1;
        let fallbackCategory = categoryPath;

        while (true) {
            const pageUrl = currentPage === 1
                ? `${BASE_URL}/${categoryPath}`
                : `${BASE_URL}/${categoryPath}/page/${currentPage}`;

            console.log(`Scraping ${categoryPath} page: ${currentPage}`);

            // A transient load hiccup shouldn't be mistaken for the real end of pagination,
            // so retry a couple of times before giving up on this page.
            let hasProducts = false;
            for (let attempt = 1; attempt <= 3; attempt++) {
                await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });
                try {
                    await page.waitForSelector('.product', { timeout: 15000 });
                    hasProducts = true;
                    break;
                } catch (error) {
                    console.log(`Attempt ${attempt} failed to load ${categoryPath} page ${currentPage}${attempt < 3 ? ', retrying...' : '.'}`);
                }
            }

            if (!hasProducts) {
                console.log(`No products on ${categoryPath} page ${currentPage}, moving to next category.`);
                break;
            }

            if (currentPage === 1) {
                fallbackCategory = await page.title().catch(() => categoryPath);
            }

            await sleep();

            const rawBlocks = await page.$$eval('.product', (blocks) => {
                return blocks.map(block => {
                    const nameEl = block.querySelector('.title');
                    const name = nameEl?.textContent.trim() ?? '';
                    const product_url = nameEl?.href ?? '';
                    const image_url = block.querySelector('.img img')?.src ?? '';
                    const info = block.querySelector('p')?.textContent.trim() ?? '';

                    const priceBlock = block.querySelector('.price');

                    if (!name || !product_url || !priceBlock) {
                        return null;
                    }

                    const oldEl = priceBlock.querySelector('.old');
                    const original_price = oldEl?.textContent.trim() ?? '';

                    const priceClone = priceBlock.cloneNode(true);
                    priceClone.querySelector('.old')?.remove();
                    const discounted_price = priceClone.textContent.trim();

                    const discount_percent = block.querySelector('.labels .label')?.textContent.trim() ?? '';

                    return {
                        name,
                        product_url,
                        image_url,
                        info,
                        original_price,
                        discounted_price,
                        discount_percent,
                    };
                });
            });

            if (rawBlocks.length === 0) {
                console.log(`No product cards on ${categoryPath} page ${currentPage}, moving to next category.`);
                break;
            }

            const productBlocks = rawBlocks.filter(Boolean);

            if (productBlocks.length === 0) {
                console.log(`No usable product cards on ${categoryPath} page ${currentPage}, continuing.`);
                currentPage++;
                continue;
            }

            const cleanedProducts = productBlocks.map(product => ({
                ...product,
                discounted_price: cleanPrice(product.discounted_price),
                original_price: cleanPrice(product.original_price),
            }));

            console.log(`Enriching ${cleanedProducts.length} products with their real subcategory`);
            const enrichedProducts = await enrichCategories(cleanedProducts, fallbackCategory);

            totalProducts += enrichedProducts.length;
            console.log(`Scraped ${enrichedProducts.length} products from ${categoryPath} page ${currentPage}`);
            console.log(`Total ${totalProducts}`);

            try {
                const data = enrichedProducts.map(product => ({
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
                console.log(`Posted ${data.length} products from ${categoryPath} page ${currentPage} to API`);
            } catch (error) {
                console.error(`Error posting products from ${categoryPath} page ${currentPage}:`, error.message);
            }

            currentPage++;
        }
    }

    console.log(`Scraped total ${totalProducts} products across ${CATEGORY_PATHS.length} categories.`);
    console.log('Scraping completed.');

    await browser.close();
})();
