import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import axios from 'axios';

puppeteer.use(StealthPlugin());

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const progressFile = path.join(__dirname, 'progress.json');

const BATCH_SIZE = 100;
const MAX_CONCURRENT_REQUESTS = 25;
const REQUEST_DELAY = 300;
const NAVIGATION_TIMEOUT = 10000;

const saveProgress = (pageNumber) => {
    // fs.writeFileSync(progressFile, JSON.stringify({ currentPage: pageNumber }, null, 2));
};

const loadProgress = () => {
    try {
        const data = fs.readFileSync(progressFile);
        return JSON.parse(data).currentPage || 1;
    } catch (err) {
        return 1;
    }
};

const clearProgress = () => {
    // if (fs.existsSync(progressFile)) {
    //     fs.writeFileSync(progressFile, JSON.stringify({ currentPage: 1 }, null, 2));
    // }
};

const delay = (ms) => new Promise(res => setTimeout(res, ms));

const axiosInstance = axios.create({
    baseURL: 'http://127.0.0.1/api',
    timeout: 30000,
    maxRedirects: 5,
    headers: {
        'Content-Type': 'application/json',
        'Connection': 'keep-alive'
    }
});

const createPage = async (browser) => {
    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    await page.setViewport({ width: 1280, height: 720 });

    // Block images, CSS, and fonts for faster loading
    await page.setRequestInterception(true);
    page.on('request', (req) => {
        const resourceType = req.resourceType();
        if (['image', 'stylesheet', 'font', 'media'].includes(resourceType)) {
            req.abort();
        } else {
            req.continue();
        }
    });

    await page.setExtraHTTPHeaders({
        'Accept-Language': 'en-US,en;q=0.9',
        'Accept-Encoding': 'gzip, deflate, br',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
    });

    return page;
};

const scrapeProductDetails = async (browser, product, retries = 1) => {
    if (!product.link) return product;

    let page = null;

    for (let attempt = 0; attempt <= retries; attempt++) {
        try {
            page = await createPage(browser);

            await page.goto(product.link, {
                waitUntil: 'domcontentloaded',
                timeout: NAVIGATION_TIMEOUT
            });

            await delay(REQUEST_DELAY);

            const priceLabel = await page.$('.price-label__price').then(el => !!el).catch(() => false);
            const dateValid = await page.$('.product__main-info > p.notice').then(el => el ? page.evaluate(el => el.textContent.trim(), el) : null).catch(() => null);
            const priceWrapper = await page.$('.price-wrapper .price').catch(() => null);
            const categories = await page.$$eval('.section-header__container a', links => links.map(a => a.textContent.trim())).catch(() => []);
            const card = await page.$('img[src*="rimi-card-slanted-right@2x"]').then(el => !!el).catch(() => false);

            let start_at = null;
            let end_at = null;
            if (dateValid) {
                const matches = dateValid.match(/\d{4}\.\d{2}\.\d{2}/g);
                if (matches && matches.length >= 2) {
                    [start_at, end_at] = matches.map(d => d.replace(/\./g, '-'));
                }
            }

            if (priceLabel && priceWrapper) {
                const price_before = await page.$eval('.price-wrapper .price', el => {
                    const main = el.querySelector('span')?.textContent.trim();
                    const decimal = el.querySelector('sup')?.textContent.trim() || '';
                    return `${main}.${decimal}`;
                }).catch(() => null);

                const price = await page.$eval('.price-label__price', el => {
                    const major = el.querySelector('.major')?.textContent.trim();
                    const cents = el.querySelector('.cents')?.textContent.trim();
                    return `${major}.${cents}`;
                }).catch(() => null);

                product.price = price;
                product.price_before = price_before;
            } else if (priceWrapper) {
                const price = await page.$eval('.price-wrapper .price', el => {
                    const main = el.querySelector('span')?.textContent.trim();
                    const decimal = el.querySelector('sup')?.textContent.trim() || '';
                    return `${main}.${decimal}`;
                }).catch(() => null);

                const price_before = await page.$eval('.price__old-price', el => el?.textContent.trim()).catch(() => null);

                product.price = price;
                product.price_before = price_before;
            }

            const brand = await page.$eval('div.other-from-brand > a', el => el?.textContent.trim()).catch(() => null);

            product.category = categories.join('/');
            product.start_at = start_at;
            product.end_at = end_at;
            product.card = card;
            product.brand = brand;

            await page.close();
            return product;

        } catch (err) {
            console.error(`Attempt ${attempt + 1} failed for product: ${product.link} - ${err.message}`);
            if (page) {
                try {
                    await page.close();
                } catch (closeErr) {
                    console.error('Error closing page:', closeErr.message);
                }
            }

            if (attempt === retries) {
                console.error(`Failed to scrape details for product after ${retries + 1} attempts:`, product.link);
                return product;
            }

            // Small delay only for this product's retry - doesn't block others
            await delay(500 * (attempt + 1));
        }
    }

    return product;
};

const processBatch = async (browser, products) => {
    const results = [];
    const chunks = [];
    let failedProducts = 0;
    let skippedProducts = 0;

    for (let i = 0; i < products.length; i += MAX_CONCURRENT_REQUESTS) {
        chunks.push(products.slice(i, i + MAX_CONCURRENT_REQUESTS));
    }

    for (const chunk of chunks) {
        const chunkPromises = chunk.map(async (product) => {
            try {
                const isValidDiscount = await checkValidDiscount(product);

                if (isValidDiscount) {
                    console.log(`Skipping product (discount already exists): ${product.link}`);
                    skippedProducts++;
                    product.skipped = true; // Mark as skipped
                    return product; // Return original product data without scraping
                }

                return await scrapeProductDetails(browser, product);
            } catch (err) {
                console.error(`Failed to process product: ${product.link}`);
                failedProducts++;
                return product; // Return original product data
            }
        });

        const chunkResults = await Promise.all(chunkPromises);
        results.push(...chunkResults);
        await delay(REQUEST_DELAY);
    }

    if (failedProducts > 0) {
        console.log(`Warning: ${failedProducts} products failed to scrape completely`);
    }

    if (skippedProducts > 0) {
        console.log(`Info: ${skippedProducts} products skipped (discounts already exist)`);
    }

    return results;
};

const checkValidDiscount = async (product) => {
    if (!product.link || !product.price) {
        return false;
    }

    // Use GTM data price if available, otherwise fallback to extracted price
    let priceToCheck = product.price;
    let originalPriceToCheck = product.price_before;

    if (product.gtmData && product.gtmData.price) {
        priceToCheck = product.gtmData.price.toString();
    }

    // If we have labelPrice (from price-label), use that as the discounted price
    if (product.labelPrice) {
        priceToCheck = product.labelPrice;
    }

    try {
        const response = await axiosInstance.post('/scrapers/check-discount', {
            store: 'rimi',
            url: product.link,
            discounted_price: parseFloat(priceToCheck)
        });

        return response.data.is_valid;
    } catch (err) {
        console.error('Error checking discount validity:', err.message);
        return false;
    }
};

const postBatchToAPI = async (products) => {
    const data = products.map(p => ({
        name: p.title,
        category: p.category,
        image_url: p.imageSrc,
        product_url: p.link,
        store: 'rimi',
        original_price: p.price_before,
        discounted_price: p.price,
        discount_percent: p.discount,
        start_at: p.start_at,
        end_at: p.end_at,
        info: p.info,
        condition: p.condition,
        card: p.card,
        brand: p.brand
    }));

    try {
        await axiosInstance.post('/scrapers', data);
        console.log(`Posted ${products.length} products to API`);
        return true;
    } catch (err) {
        console.error('Error posting products:', err.message);
        return false;
    }
};

const runScraper = async () => {
    const chromePath = `${process.env.HOME}/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome`;
    const launchOptions = {
        headless: 'new',
        protocolTimeout: 300000,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ]
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

    const mainPage = await createPage(browser);

    const baseUrl = 'https://www.rimi.lt/';
    // let currentPage = loadProgress();
    let currentPage = 1;
    let allProducts = [];

    if (currentPage === 1) {
        console.log(`Opening initial page to handle cookies.`);
        await mainPage.goto(`${baseUrl}e-parduotuve/lt/akcijos?currentPage=1&pageSize=80`, {
            waitUntil: 'domcontentloaded',
            timeout: NAVIGATION_TIMEOUT
        });

        try {
            await mainPage.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
            await mainPage.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            await delay(1000);
        } catch {}
    }

    while (true) {
        const pageUrl = `${baseUrl}e-parduotuve/lt/akcijos?pageSize=80&currentPage=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);

        let pageLoaded = false;
        for (let retry = 0; retry < 5; retry++) {
            try {
                await mainPage.goto(pageUrl, {
                    waitUntil: 'domcontentloaded',
                    timeout: NAVIGATION_TIMEOUT
                });
                pageLoaded = true;
                break;
            } catch (err) {
                console.log(`Failed to load page ${currentPage} (attempt ${retry + 1}/5): ${err.message}`);
                if (retry === 4) {
                    console.log(`Failed to load page ${currentPage} after 5 attempts. Stopping.`);
                    break;
                }
                await delay(3000 * (retry + 1));
            }
        }

        if (!pageLoaded) {
            break;
        }

        try {
            await mainPage.waitForSelector('.js-product-container', { timeout: 5000 });
        } catch {
            console.log(`Products not found on page ${currentPage}`);
            break;
        }

        await delay(REQUEST_DELAY);

        const productBlocks = await mainPage.$$eval('div.js-product-container', blocks => {
            return blocks.map(block => {
                // Extract from data attributes
                const productId = block.getAttribute('data-gtms-product-id');
                const gtmData = block.getAttribute('data-gtm-eec-product');

                let parsedGtmData = null;
                if (gtmData) {
                    try {
                        parsedGtmData = JSON.parse(gtmData.replace(/&quot;/g, '"'));
                    } catch (e) {
                        console.error('Error parsing GTM data:', e);
                    }
                }

                // Extract from DOM elements
                const title = block.querySelector('.card__name')?.textContent.trim();
                const price = block.querySelector('.card__price')?.textContent.trim();
                const price_before = block.querySelector('.card__old-price')?.textContent.trim();
                const valid = block.querySelector('.ods-badge__label')?.textContent.trim();
                let discount = block.querySelector('.m-price__label')?.textContent.trim() ?? '';
                const link = block.querySelector('.card__url')?.href;
                let imageSrc = block.querySelector('.card__image-wrapper img')?.src;
                let info = block.querySelector('.card__price-per [aria-hidden="true"]')?.textContent.trim();
                let condition = block.querySelector('div.price-label__header.-red')?.textContent.trim();
                info = info?.replace(/\s+/g, ' ').trim();

                // Extract price from price-label if available
                const priceLabelPrice = block.querySelector('.price-label__price');
                let labelPrice = null;
                if (priceLabelPrice) {
                    const major = priceLabelPrice.querySelector('.major')?.textContent.trim();
                    const cents = priceLabelPrice.querySelector('.cents')?.textContent.trim();
                    if (major && cents) {
                        labelPrice = `${major}.${cents}`;
                    }
                }

                // Extract price per unit from price-label
                const pricePerUnit = block.querySelector('.price-per-unit')?.textContent.trim();

                if (imageSrc) {
                    imageSrc = imageSrc.replace(/q_1/g, 'q_auto:low');
                }

                if (condition && condition.includes('%')) {
                    discount = condition;
                    condition = '';
                }

                console.log({
                    productId,
                    gtmData: parsedGtmData,
                    link,
                    price,
                    price_before,
                    labelPrice,
                    pricePerUnit
                });

                return {
                    title,
                    price,
                    price_before,
                    info,
                    discount,
                    valid,
                    link,
                    imageSrc,
                    condition,
                    productId,
                    gtmData: parsedGtmData,
                    labelPrice,
                    pricePerUnit
                };
            });
        });

        if (productBlocks.length === 0) {
            console.log(`No products found on page ${currentPage}`);
            break;
        }

        console.log(`Processing ${productBlocks.length} products from page ${currentPage}`);

        const processedProducts = await processBatch(browser, productBlocks);

        // Filter out products that have valid discounts (were skipped)
        const productsToPost = processedProducts.filter(product => {
            // If product was skipped, it means discount already exists
            return !product.skipped;
        });

        allProducts.push(...processedProducts);

        if (productsToPost.length > 0) {
            await postBatchToAPI(productsToPost);
        } else {
            console.log('No new products to post to API');
        }

        currentPage++;
        // saveProgress(currentPage);
    }

    await mainPage.close();
    await browser.close();

    // fs.writeFileSync('rimi.json', JSON.stringify(allProducts, null, 2));
    console.log('✅ Scraping completed.');
};

const startWithRetries = async (maxRetries = 3, delayBetweenRetries = 5000) => {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        if (attempt === 1) {
            console.log('🔄 First attempt...');
            // clearProgress();
        }

        try {
            console.log(`🚀 Starting scraper (attempt ${attempt})`);
            await runScraper();
            console.log('✅ Scraper finished successfully');
            break;
        } catch (err) {
            console.error(`❌ Attempt ${attempt} failed: ${err.message}`);
            if (attempt === maxRetries) {
                console.error('💀 Max retries reached. Exiting.');
                process.exit(1);
            } else {
                console.log(`🔁 Retrying in ${delayBetweenRetries / 1000} seconds...`);
                await delay(delayBetweenRetries);
            }
        }
    }
};

startWithRetries();
