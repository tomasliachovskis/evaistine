import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import {fileURLToPath} from 'url';
import axios from 'axios';

// Apply Stealth plugin
puppeteer.use(StealthPlugin());

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
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

    const retryNavigation = async (page, url, maxRetries = 3) => {
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 10000 });
                return true;
            } catch (error) {
                console.log(`Navigation attempt ${attempt} failed for ${url}: ${error.message}`);
                if (attempt === maxRetries) {
                    console.log(`All ${maxRetries} navigation attempts failed for ${url}`);
                    return false;
                }
                await new Promise(res => setTimeout(res, 5000));
            }
        }
    };

    const baseUrl = 'https://www.rimi.lt/';
    let currentPage = 1;
    let allProducts = [];

    console.log(`Opening initial page to handle cookies.`);
    await retryNavigation(page, `${baseUrl}e-parduotuve/lt/akcijos?currentPage=1&pageSize=80&currentPage=1`);

    try {
        await page.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
        await page.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        // console.log('Accepted cookies.');
    } catch (error) {
        // console.log('No cookie popup detected or already accepted.');
    }

    while (true) {
        const pageUrl = `${baseUrl}e-parduotuve/lt/akcijos?pageSize=80&currentPage=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);
        if (!await retryNavigation(page, pageUrl, 10)) {
            console.log(`Failed to load page ${currentPage} after retries, stopping scrape.`);
            break;
        }

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
                const valid = block.querySelector('.ods-badge__label')?.textContent.trim();
                const discount = block.querySelector('.m-price__label')?.textContent.trim() ?? '-';
                const link = block.querySelector('.card__url')?.href;
                const imageSrc = block.querySelector('.card__image-wrapper img')?.src;

                let info = block.querySelector('.card__price-per')?.textContent.trim();
                info = info.replace(/\s+/g, ' ').trim();

                return { title, price, price_before, info, discount, valid, link, imageSrc };
            });
        });

        for (const product of productBlocks) {
            if (product.link) {
                // console.log('Opening product: ' + product.link);

                try {
                    await retryNavigation(page, product.link);
                    console.log('Opened product: ' + product.link);
                } catch (error) {
                    console.error('Failed to open product:', product.link, error.message);
                    continue; // continue to the next product
                }

                await sleep();

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
                        [start_at, end_at] = matches.map(date => date.replace(/\./g, '-'));
                    }
                }

                if (priceLabel) {
                    const priceWrapper = await page.$('.price-wrapper .price');
                    if (priceWrapper) {
                        const price_before = await page.$eval('.price-wrapper .price', priceElem => {
                            const mainPrice = priceElem.querySelector('span')?.textContent.trim();
                            const decimal = priceElem.querySelector('sup')?.textContent.trim() || '';
                            return `${mainPrice}.${decimal}`;
                        });

                        const priceLabelElem = await page.$('.price-label__price');
                        if (priceLabelElem) {
                            product.price = await page.$eval('.price-label__price', priceElem => {
                                const major = priceElem.querySelector('.major')?.textContent.trim();
                                const cents = priceElem.querySelector('.cents')?.textContent.trim();
                                return `${major}.${cents}`;
                            });
                            product.price_before = price_before;
                        }
                    }
                } else {
                    const priceWrapper = await page.$('.price-wrapper .price');
                    if (priceWrapper) {
                        const price = await page.$eval('.price-wrapper .price', priceElem => {
                            const mainPrice = priceElem.querySelector('span')?.textContent.trim();
                            const decimal = priceElem.querySelector('sup')?.textContent.trim() || '';
                            return `${mainPrice}.${decimal}`;
                        });

                        const priceElem = await page.$('.price__old-price');
                        let price_before = null;
                        if (priceElem) {
                            price_before = await page.evaluate(el => el.textContent.trim(), priceElem);
                        }

                        product.price = price;
                        product.price_before = price_before;
                    }
                }

                const categories = await page.$$eval('.section-header__container a', links => {
                    return links.map(link => link.textContent.trim());
                }).catch(() => []);

                const card = await page.$('img[src*="rimi-card-slanted-right@2x"]') !== null;

                product.category = categories.join('/');
                product.start_at = start_at;
                product.end_at = end_at;
                product.card = card;

                // console.log(product);
            }
        }

        allProducts = allProducts.concat(productBlocks);
        console.log(`Scraped ${productBlocks.length} products from page ${currentPage}`);

        try {
            const data = productBlocks.map(product => ({
                name: product.title,
                category: product.category,
                image_url: product.imageSrc,
                product_url: product.link,
                store: 'rimi',
                original_price: product.price_before,
                discounted_price: product.price,
                discount_percent: product.discount,
                start_at: product.start_at,
                end_at: product.end_at,
                info: product.info,
                card: product.card
            }));
            await axios.post('http://127.0.0.1/api/scrapers', data);
            console.log(`Posted ${productBlocks.length} products to API from page ${currentPage}`);
        } catch (error) {
            console.error('Error posting products:', error.message);
        }

        currentPage++;
    }

    fs.writeFileSync('rimi.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed. Data saved to rimi.json');

    await browser.close();
})();
