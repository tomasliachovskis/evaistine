import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';

puppeteer.use(StealthPlugin());

(async () => {
    const browser = await puppeteer.launch({headless: false });
    const page = await browser.newPage();
    const sleep = ms => new Promise(res => setTimeout(res, ms));

    const filePath = 'https://www.norfa.lt/akciju-puslapiai/praktiski-pasiulymai';
    await page.goto(filePath, { waitUntil: 'domcontentloaded' });

    await page.waitForSelector('#gdpr-cookie-accept', { timeout: 5000 });
    await page.click('#gdpr-cookie-accept'); // Click the button

    await page.waitForSelector('div.c-discount-item-list', { timeout: 5000 });
    await sleep(5000);

    const productBlocks = await page.$$eval('.c-discount-item-list .c-product', async blocks => {
        return Promise.all(blocks.map(async block => {
            const title = block.querySelector('.c-product__name')?.textContent.trim() ?? '';
            const price = block.querySelector('.c-product__price')?.textContent.trim() ?? '';
            const price_before = block.querySelector('.c-product__old-price')?.textContent.trim() ?? '';
            let valid = block.querySelector('.c-more-info__content')?.textContent.trim() ?? '';

            if (valid.includes("\n")) {
                const parts = valid.split("\n");
                valid = parts[parts.length - 1].trim();
            }

            const discount = block.querySelector('.c-product__discount')?.textContent.trim() ?? '-';
            const link = block.querySelector('a')?.href ?? '';
            const imageSrc = block.querySelector('div.c-product__media > img')?.src ?? '';

            // Check if the block contains the shop images (shop-h, shop-xxl, shop-xl)
            const shopH = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-h.svg"]') !== null;
            const shopXxl = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-xxl.svg"]') !== null;
            const shopXl = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-xl.svg"]') !== null;

            const shop = {};
            if (shopH) shop.shopH = 'shopH';
            if (shopXxl) shop.shopXxl = 'shopXxl';
            if (shopXl) shop.shopXl = 'shopXl';

            return {
                title,
                price,
                price_before,
                discount,
                valid,
                link,
                imageSrc,
                shop,
            };
        }));
    });
    // Save to JSON file
    fs.writeFileSync('norfa.json', JSON.stringify(productBlocks, null, 2));

    await browser.close();
})();
