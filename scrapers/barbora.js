import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';

// Apply Stealth plugin
puppeteer.use(StealthPlugin());

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

    const baseUrl = 'https://barbora.lt';
    let currentPage = 1;
    let allProducts = [];

    console.log(`Opening initial page to handle cookies.`);
    await page.goto(`${baseUrl}/akcijos?page=1`, { waitUntil: 'domcontentloaded' });

    try {
        await page.waitForSelector('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', { timeout: 5000 });
        await page.click('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    while (true) {
        const pageUrl = `${baseUrl}/akcijos?page=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);
        await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });

        try {
            await page.waitForSelector('.tw-flex-shrink-0.tw-list-none.tw-w-full', { timeout: 5000 });
        } catch (error) {
            console.log(`Failed to load products on page ${currentPage}, stopping scrape.`);
            break;
        }

        await sleep();

        const productBlocks = await page.$$eval('.tw-flex-shrink-0.tw-list-none.tw-w-full', blocks => {
            return blocks.map(block => {
                let json = block.querySelector('.tw-relative.tw-flex.tw-h-full.tw-w-full.tw-flex-col').getAttribute('data-b-for-cart');
                json = JSON.parse(json);

                const category = json ? json.category_name_full_path : null;
                const valid = json ? json.ShowInOffersTo : null;
                const price = json ? json.price : null;
                const price_before = json ? json.promotion.oldPrice : null;

                const title = block.querySelector('a > span.tw-block')?.textContent.trim();
                // const price = block.querySelector('.tw-align-top')?.textContent.trim();
                // const price_before = block.querySelector('.tw-text-neutral-500 > .tw-relative.tw-flex.tw-align-top')?.textContent.trim() ?? '';
                const info = block.querySelector('.card__price-per')?.textContent.trim() ?? '';
                // const valid = block.querySelector('.ods-badge__label')?.textContent.trim();
                const discount = block.querySelector('span.tw-text-white')?.textContent.trim() ?? '-';
                const link = block.querySelector('.tw-justify-center a')?.href;
                const imageSrc = block.querySelector('.tw-justify-center a img')?.src;
                const card = false;

                return {
                    title,
                    price,
                    price_before,
                    info,
                    discount,
                    valid,
                    category,
                    link,
                    card,
                    imageSrc
                };
            });
        });

        allProducts = allProducts.concat(productBlocks);
        console.log(productBlocks);
        console.log(`Scraped ${productBlocks.length} products from page ${currentPage}`);

        currentPage++;
    }

    console.log(`Scraped total ${allProducts.length} products from ${currentPage} pages`);

    fs.writeFileSync('barbora.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed. Data saved to barbora.json');

    await browser.close();
})();
