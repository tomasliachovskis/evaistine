import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({headless: false });
    const page = await browser.newPage();
    const sleep = ms => new Promise(res => setTimeout(res, ms));

    // Load the local HTML file
    const filePath = 'https://www.lidl.lt/c/visos-sios-savaites-akcijos/a10025491';
    await page.goto(filePath, { waitUntil: 'domcontentloaded' });

    await page.waitForSelector('#onetrust-accept-btn-handler', { timeout: 5000 });
    await page.click('#onetrust-accept-btn-handler'); // Click the button

    // Wait for a few seconds to see the result
    await sleep(5000);

    // const offers = await page.evaluate(() => {
    //     const elements = document.querySelectorAll('nav a.ATheHeroStage__OfferAnchor');
    //     const texts = Array.from(elements).map(offer => offer.href);
    //     return [...new Set(texts)];
    // });

    const offers = ['https://www.lidl.lt/c/visos-sios-savaites-akcijos/a10023711?channel=store&tabCode=Current_Sales_Week'];

    let allProducts = new Map();

    for (const offerLink of offers) {
        await page.goto(offerLink, { waitUntil: 'domcontentloaded' });

        await sleep(2000);

        // Scroll by little bits incrementally
        await page.evaluate(async () => {
            const distance = 200;
            const scrollDelay = 200;

            let scrollHeight = document.body.scrollHeight;
            let scrollPosition = 0;

            while (scrollPosition < scrollHeight) {
                window.scrollTo(0, scrollPosition);
                scrollPosition += distance;
                await new Promise(resolve => setTimeout(resolve, scrollDelay));
            }
        });

        await page.waitForSelector('div.product-grid-box', { timeout: 5000 });

        // Extract product details
        const productBlocks = await page.$$eval('.odsc-tile--label-.product-grid-box', blocks => {
            function parseDate(dateStr) {
                const [month, day] = dateStr.split(' ').map(Number);
                const year = new Date().getFullYear();
                return new Date(year, month - 1, day).toLocaleDateString('en-CA');
            }

            return blocks.map(block => {
                const brand = block.querySelector('.product-grid-box__brand')?.textContent.trim();
                let name = block.querySelector('.odsc-tile__link')?.textContent.trim();
                const discounted_price = block.querySelector('.m-price__price')?.textContent.trim();
                const original_price = block.querySelector('.m-price__top')?.textContent.trim();
                const info = block.querySelector('.price-footer')?.textContent.trim();
                const valid = block.querySelector('.product-grid-box__availabilities')?.textContent.trim();
                const discount_percent = block.querySelector('.m-price__label')?.textContent.trim() ?? '-';
                const product_url = block.querySelector('a')?.href;
                const image_url = block.querySelector('.odsc-image-gallery__image')?.src;
                const card = block.querySelector('.seal .seal__badge') !== null;

                let { start_atStr, end_atStr } = { start_atStr: '', end_atStr: '' };
                let start_at = '';
                let end_at = '';

                if (valid && typeof valid === 'string' && valid.includes(' - ')) {
                    [start_atStr, end_atStr] = valid.split(' - ');
                    start_at = parseDate(start_atStr);
                    end_at = parseDate(end_atStr);
                } else if (typeof valid === 'string' && valid.includes('Nuo ')) {
                    start_atStr = valid.replace('Nuo ', '');
                    start_at = parseDate(start_atStr);
                } else {
                    start_at = valid;
                    end_at = null;
                }

                return {
                    name,
                    brand,
                    discounted_price,
                    original_price,
                    info,
                    discount_percent,
                    start_at,
                    end_at,
                    valid,
                    card,
                    product_url,
                    image_url
                };
            });
        });

        console.log(`Found ${productBlocks.length} products on ${offerLink}`);

        // Store unique products based on "name + price + valid"
        for (const product of productBlocks) {
            const uniqueKey = `${product.name}-${product.discounted_price}-${product.start_at}-${product.end_at}`;
            if (!allProducts.has(uniqueKey)) {
                allProducts.set(uniqueKey, product);
            }
        }
    }

    const uniqueProducts = Array.from(allProducts.values());

    // Post to API
    for (const product of uniqueProducts) {
        try {
            const response = await axios.post('http://127.0.0.1/api/scrapers', {
                name: product.name,
                brand: product.brand,
                discounted_price: product.discounted_price,
                original_price: product.original_price,
                info: product.info,
                discount_percent: product.discount_percent,
                start_at: product.start_at,
                end_at: product.end_at,
                valid: product.valid,
                card: product.card,
                product_url: product.product_url,
                image_url: product.image_url
            });
            console.log(`Posted product: ${product.name}`);
            await sleep(100);
        } catch (error) {
            console.error(`Error posting product ${product.name}:`, error.message);
            await sleep(200);
        }
    }

    // Save to JSON file
    fs.writeFileSync('scrapers/lidl.json', JSON.stringify(uniqueProducts, null, 2));

    await browser.close();
})();
