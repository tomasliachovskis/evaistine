import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Apply Stealth plugin
puppeteer.use(StealthPlugin());

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const browser = await puppeteer.launch({
        headless: false,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled'
        ]
    });
    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000));

    const baseUrl = 'https://www.rimi.lt/';
    let currentPage = 1;
    let allProducts = [];

    console.log(`Opening initial page to handle cookies.`);
    await page.goto(`${baseUrl}e-parduotuve/lt/akcijos?currentPage=1&pageSize=80&currentPage=1`, { waitUntil: 'domcontentloaded' });

    try {
        await page.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
        await page.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    while (true) {
        const pageUrl = `${baseUrl}e-parduotuve/lt/akcijos?pageSize=80&currentPage=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);
        await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });

        try {
            await page.waitForSelector('.js-product-container', { timeout: 5000 });
        } catch (error) {
            console.log(`Failed to load products on page ${currentPage}, stopping scrape.`);
            break;
        }

        await sleep();

        const productBlocks = await page.$$eval('.js-product-container', blocks => {
            return blocks.map(block => {
                const title = block.querySelector('.card__name')?.textContent.trim();
                const price = block.querySelector('.card__price')?.textContent.trim();
                const price_before = block.querySelector('.card__old-price')?.textContent.trim();
                const info = block.querySelector('.card__price-per')?.textContent.trim();
                const valid = block.querySelector('.ods-badge__label')?.textContent.trim();
                const discount = block.querySelector('.m-price__label')?.textContent.trim() ?? '-';
                const link = block.querySelector('.card__url')?.href;
                const imageSrc = block.querySelector('.card__image-wrapper img')?.src;

                return { title, price, price_before, info, discount, valid, link, imageSrc };
            });
        });

        for (const product of productBlocks) {
            if (product.link) {
                await page.goto(product.link, { waitUntil: 'domcontentloaded' });

                await sleep();

                // console.log(product.link);

                const priceLabel = await page.$('.price-label__price') !== null;

                if (priceLabel) {
                    const price_before = await page.$eval('.price-wrapper .price', priceElem => {
                        const mainPrice = priceElem.querySelector('span').textContent.trim();
                        const decimal = priceElem.querySelector('sup') ? priceElem.querySelector('sup').textContent.trim() : '';
                        return `${mainPrice}.${decimal}`;
                    });

                    const price = await page.$eval('.price-label__price', priceElem => {
                        const major = priceElem.querySelector('.major').textContent.trim();
                        const cents = priceElem.querySelector('.cents').textContent.trim();
                        return `${major}.${cents}`;
                    });

                    // console.log(price, price_before);

                    product.price = price;
                    product.price_before = price_before;
                } else {
                    const price = await page.$eval('.price-wrapper .price', priceElem => {
                        const mainPrice = priceElem.querySelector('span').textContent.trim();
                        const decimal = priceElem.querySelector('sup') ? priceElem.querySelector('sup').textContent.trim() : '';
                        return `${mainPrice}.${decimal}`;
                    });

                    const price_before = await page.$eval('.price__old-price', priceElem => {
                        return  priceElem.textContent.trim();
                    });

                    // console.log(price, price_before);

                    product.price = price;
                    product.price_before = price_before;
                }

                const categories = await page.$$eval('.section-header__container a', links => {
                    return links.map(link => link.textContent.trim());
                });

                const categoryPath = categories.join('/');

                // console.log(categoryPath); // Log the product data with the additional image

                console.log(product);
            }
        }

        allProducts = allProducts.concat(productBlocks);
        console.log(`Scraped ${productBlocks.length} products from page ${currentPage}`);

        currentPage++;
    }

    fs.writeFileSync('rimi.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed. Data saved to barbora.json');

    await browser.close();
})();
