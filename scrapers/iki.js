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

    let allProducts = [];

    console.log(`Opening initial page to handle cookies.`);
    await page.goto('https://iki.lt/akcijos/savaites-akcijos/', { waitUntil: 'domcontentloaded' });

    try {
        await page.waitForSelector('#onetrust-accept-btn-handler', { timeout: 5000 });
        await page.click('#onetrust-accept-btn-handler');
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    await page.evaluate(async () => {
        const distance = 500; // Set the distance to scroll each time (in pixels)
        const scrollDelay = 500; // Delay between scrolls (in milliseconds)

        let scrollPosition = 0;

        while (scrollPosition < document.body.scrollHeight) {
            window.scrollTo(0, scrollPosition);
            scrollPosition += distance; // Increase the scroll position by the increment
            await new Promise(resolve => setTimeout(resolve, scrollDelay)); // Wait before scrolling again
        }
    });

    try {
        await page.waitForSelector('.akcija-loyalty', { timeout: 5000 });
    } catch (error) {
        console.log(`Failed to load products, stopping scrape.`);
    }

    await sleep();

    const productBlocks = await page.$$eval('.akcija-loyalty', blocks => {
        return blocks.map(block => {
            const cleanPrice = (str) => {
                console.log(str);
                if (typeof str !== 'string') {
                    return str;
                }

                return str.replace(/\s+/g, '').replace(/(\d)(?=\d)/, '$1.');
            };

            const title = block.querySelector('.akcija_title')?.textContent.trim();

            let price1 = block.querySelector('.card_tag .price_int')?.textContent.trim() ?? '';
            let price2 = block.querySelector('.card_tag .sub')?.textContent.trim() ?? '';

            let price = price1 + '.' + price2;

            if (price === '.') {
                // 2+1 pasiulymai
                price1 = block.querySelector('.price_block_wrapper .price_int')?.textContent.trim() ?? '';
                price2 = block.querySelector('.price_block_wrapper .price_cents')?.textContent.trim() ?? '';

                price = price1 + '.' + price2;
            }

            let price_before = block.querySelector('.price_old_block')?.textContent.trim() ?? '';

            let discount = block.querySelector('.percentage_tag .main')?.textContent.trim() ?? '-';

            if (discount === '-') {
                discount = block.querySelector('.price_block_rounded_red_wrapper .main')?.textContent.trim() ?? '-';
            }

            if (discount === '-') {
                discount = block.querySelector('.price_block_red_wrapper .main')?.textContent.trim() ?? '-';
            }

            price_before = cleanPrice(price_before);

            const info = block.querySelector('.akcija_description')?.textContent.trim();
            const valid = block.querySelector('.m-0.w-100.akcija_description.text-center')?.textContent.trim();
            const link = block.querySelector('a')?.href;
            const imageSrc = block.querySelector('.card-img-top')?.src;
            const card_required = block.querySelector('.card') !== null;

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

    allProducts = allProducts.concat(productBlocks);
    console.log(`Scraped ${productBlocks.length} products from`);

    fs.writeFileSync('iki.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed. Data saved to iki.json');

    await browser.close();
})();
