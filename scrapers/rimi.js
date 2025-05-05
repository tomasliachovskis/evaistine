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

const saveProgress = (pageNumber) => {
    fs.writeFileSync(progressFile, JSON.stringify({ currentPage: pageNumber }, null, 2));
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
    if (fs.existsSync(progressFile)) {
        fs.writeFileSync(progressFile, JSON.stringify({ currentPage: 1 }, null, 2));
    }
};

const delay = (ms) => new Promise(res => setTimeout(res, ms));

const scrapeProductDetails = async (page, product) => {
    const retryNavigation = async (page, url, maxRetries = 3) => {
        for (let i = 1; i <= maxRetries; i++) {
            try {
                console.log('Opening url: ' + url);
                await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 5000 });
                return true;
            } catch (err) {
                console.log(`Navigation attempt ${i} failed for ${url}: ${err.message}`);
                if (i === maxRetries) return false;
                await delay(5000);
            }
        }
    };

    try {
        if (!product.link) return;
        // console.log('Opening product ' + product.link);

        await retryNavigation(page, product.link,);

        await delay(1000);

        const priceLabel = await page.$('.price-label__price') !== null;
        const dateValid = await page.$('.product__main-info > p.notice');
        let date = null;

        if (dateValid) {
            date = await page.evaluate(el => el.textContent.trim(), dateValid);
        }

        let start_at = null;
        let end_at = null;
        if (date) {
            const matches = date.match(/\d{4}\.\d{2}\.\d{2}/g);
            if (matches && matches.length >= 2) {
                [start_at, end_at] = matches.map(d => d.replace(/\./g, '-'));
            }
        }

        if (priceLabel) {
            const priceWrapper = await page.$('.price-wrapper .price');
            if (priceWrapper) {
                const price_before = await page.$eval('.price-wrapper .price', el => {
                    const main = el.querySelector('span')?.textContent.trim();
                    const decimal = el.querySelector('sup')?.textContent.trim() || '';
                    return `${main}.${decimal}`;
                });
                const price = await page.$eval('.price-label__price', el => {
                    const major = el.querySelector('.major')?.textContent.trim();
                    const cents = el.querySelector('.cents')?.textContent.trim();
                    return `${major}.${cents}`;
                });
                product.price = price;
                product.price_before = price_before;
            }
        } else {
            const priceWrapper = await page.$('.price-wrapper .price');
            if (priceWrapper) {
                const price = await page.$eval('.price-wrapper .price', el => {
                    const main = el.querySelector('span')?.textContent.trim();
                    const decimal = el.querySelector('sup')?.textContent.trim() || '';
                    return `${main}.${decimal}`;
                });
                const price_before = await page.$eval('.price__old-price', el => el?.textContent.trim()).catch(() => null);
                product.price = price;
                product.price_before = price_before;
            }
        }

        const categories = await page.$$eval('.section-header__container a', links => links.map(a => a.textContent.trim())).catch(() => []);
        const card = await page.$('img[src*="rimi-card-slanted-right@2x"]') !== null;

        product.category = categories.join('/');
        product.start_at = start_at;
        product.end_at = end_at;
        product.card = card;
    } catch (err) {
        console.error('Failed to scrape details for product:', product.link, err.message);
    }
};

const runScraper = async () => {
    const browser = await puppeteer.launch({
        headless: false,
        protocolTimeout: 300000,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-blink-features=AutomationControlled']
    });

    const page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', (req) => {
        const blockedTypes = ['image', 'stylesheet', 'font'];
        if (blockedTypes.includes(req.resourceType())) req.abort();
        else req.continue();
    });

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    const sleep = () => delay(Math.floor(Math.random() * 1000) + 1000);

    const retryNavigation = async (page, url, maxRetries = 3) => {
        for (let i = 1; i <= maxRetries; i++) {
            try {
                await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 5000 });
                return true;
            } catch (err) {
                console.log(`Navigation attempt ${i} failed for ${url}: ${err.message}`);
                if (i === maxRetries) return false;
                await delay(5000);
            }
        }
    };

    const baseUrl = 'https://www.rimi.lt/';
    let currentPage = loadProgress();
    let allProducts = [];

    if (currentPage === 1) {
        console.log(`Opening initial page to handle cookies.`);
        await retryNavigation(page, `${baseUrl}e-parduotuve/lt/akcijos?currentPage=1&pageSize=80`);
        try {
            await page.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
            await page.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        } catch {}
    }

    while (true) {
        const pageUrl = `${baseUrl}e-parduotuve/lt/akcijos?pageSize=80&currentPage=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);

        if (!await retryNavigation(page, pageUrl, 5)) {
            console.log(`Failed to load page ${currentPage}. Retrying will resume from here.`);
            break;
        }

        try {
            await page.waitForSelector('.js-product-container', { timeout: 5000 });
        } catch {
            console.log(`Products not found on page ${currentPage}`);
            break;
        }

        await sleep();

        const productBlocks = await page.$$eval('.js-product-container', blocks => {
            return blocks.map(block => {
                const title = block.querySelector('.card__name')?.textContent.trim();
                const price = block.querySelector('.card__price')?.textContent.trim();
                const price_before = block.querySelector('.card__old-price')?.textContent.trim();
                const valid = block.querySelector('.ods-badge__label')?.textContent.trim();
                const discount = block.querySelector('.m-price__label')?.textContent.trim() ?? '';
                const link = block.querySelector('.card__url')?.href;
                const imageSrc = block.querySelector('.card__image-wrapper img')?.src;
                let info = block.querySelector('.card__price-per')?.textContent.trim();
                let condition = block.querySelector('div.price-label__header.-red')?.textContent.trim();
                info = info?.replace(/\s+/g, ' ').trim();
                return { title, price, price_before, info, discount, valid, link, imageSrc, condition};
            });
        });

        for (const product of productBlocks) {
            await scrapeProductDetails(page, product);
        }

        allProducts.push(...productBlocks);

        try {
            const data = productBlocks.map(p => ({
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
                card: p.card
            }));
            await axios.post('http://127.0.0.1/api/scrapers', data);
            console.log(`Posted ${productBlocks.length} products to API from page ${currentPage}`);
        } catch (err) {
            console.error('Error posting products:', err.message);
        }

        currentPage++;
        saveProgress(currentPage);
    }

    fs.writeFileSync('rimi.json', JSON.stringify(allProducts, null, 2));
    console.log('✅ Scraping completed. Data saved to rimi.json');
    await browser.close();
};

const startWithRetries = async (maxRetries = 5, delayBetweenRetries = 10000) => {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        if (attempt === 1) {
            console.log('🔄 First attempt – resetting progress...');
            clearProgress();
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
