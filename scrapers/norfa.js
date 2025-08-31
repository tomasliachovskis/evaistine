import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from "axios";

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
            function parseDate(dateStr) {
                const [month, day] = dateStr.trim().split(' ').map(Number);
                const year = new Date().getFullYear();
                return new Date(year, month - 1, day).toLocaleDateString('en-CA');
            }

            const name = block.querySelector('.c-product__name')?.textContent.trim() ?? '';
            const discounted_price = (block.querySelector('.c-product__price')?.textContent.trim() ?? '').replace('€', '').trim();
            const original_price = (block.querySelector('.c-product__old-price')?.textContent.trim() ?? '').replace('€', '').trim();
            let valid = block.querySelector('.c-more-info__content')?.textContent.trim() ?? '';

            if (valid.includes("\n")) {
                const parts = valid.split("\n");
                valid = parts[parts.length - 1].trim();
            }

            const discount_percent = block.querySelector('.c-product__discount')?.textContent.trim() ?? '-';
            const product_url = block.querySelector('a')?.href ?? '';
            const image_url = block.querySelector('div.c-product__media > img')?.src ?? '';

            // Check if the block contains the shop images (shop-h, shop-xxl, shop-xl)
            const shopH = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-h.svg"]') !== null;
            const shopXxl = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-xxl.svg"]') !== null;
            const shopXl = block.querySelector('img[src*="/assets/Visos-svetaines-foto/Images/shop-xl.svg"]') !== null;

            const info = {};
            if (shopH) info.shopH = 'shopH';
            if (shopXxl) info.shopXxl = 'shopXxl';
            if (shopXl) info.shopXl = 'shopXl';

            let { start_atStr, end_atStr } = { start_atStr: '', end_atStr: '' };
            let start_at = '';
            let end_at = '';

            if (valid && typeof valid === 'string' && valid.includes('-')) {
                valid = valid.replace('Galioja ', '');
                valid = valid.replace(' d.', '');

                [start_atStr, end_atStr] = valid.split('-').map(s => s.trim());
                start_at = parseDate(start_atStr);
                end_at = parseDate(end_atStr);
            } else {
                start_at = valid;
                end_at = null;
            }

            return {
                name,
                discounted_price,
                original_price,
                discount_percent,
                start_at,
                end_at,
                product_url,
                image_url,
                info,
            };
        }));
    });

    try {
        const data = productBlocks.map(product => ({
            name: product.name,
            brand: product.brand,
            discounted_price: product.discounted_price,
            original_price: product.original_price,
            condition: product.info && Object.keys(product.info).length > 0
                ? JSON.stringify(product.info)
                : '',
            discount_percent: product.discount_percent,
            start_at: product.start_at,
            end_at: product.end_at,
            product_url: product.product_url,
            image_url: product.image_url,
            store: 'norfa'
        }));
        await axios.post('http://127.0.0.1/api/scrapers', data);
    } catch (error) {
        console.error('Error posting products:', error.message);
    }

    // Save to JSON file
    fs.writeFileSync('norfa.json', JSON.stringify(productBlocks, null, 2));

    await browser.close();
})();
