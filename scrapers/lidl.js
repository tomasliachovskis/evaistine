import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';

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

    const offers = await page.evaluate(() => {
        const elements = document.querySelectorAll('nav a.ATheHeroStage__OfferAnchor');
        const texts = Array.from(elements).map(offer => offer.href);
        return [...new Set(texts)];
    });


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
            return blocks.map(block => {
                const title = block.querySelector('.product-grid-box__title')?.textContent.trim();
                const price = block.querySelector('.m-price__price')?.textContent.trim();
                const price_before = block.querySelector('.m-price__top')?.textContent.trim();
                const info = block.querySelector('.price-footer')?.textContent.trim();
                const valid = block.querySelector('.product-grid-box__availabilities')?.textContent.trim();
                const discount = block.querySelector('.m-price__label')?.textContent.trim() ?? '-';
                const link = block.querySelector('a')?.href;
                const imageSrc = block.querySelector('.odsc-image-gallery__image')?.src;
                const card_required = block.querySelector('.seal .seal__badge') !== null;

                return {
                    title,
                    price,
                    price_before,
                    info,
                    discount,
                    valid,
                    card_required,
                    link,
                    imageSrc
                };
            });
        });

        console.log(`Found ${productBlocks.length} products on ${offerLink}`);

        // Store unique products based on "title + price + valid"
        for (const product of productBlocks) {
            const uniqueKey = `${product.title}-${product.price}-${product.valid}`;
            if (!allProducts.has(uniqueKey)) {
                allProducts.set(uniqueKey, product);
            }
        }
    }

    const uniqueProducts = Array.from(allProducts.values());

    // Save to JSON file
    fs.writeFileSync('lidl.json', JSON.stringify(uniqueProducts, null, 2));

    await browser.close();
})();
