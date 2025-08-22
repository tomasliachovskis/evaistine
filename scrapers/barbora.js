import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from "axios";

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

        const productBlocks = await page.$$eval('li[data-testid^="product-card-"]', (blocks, baseUrl) => {
            return blocks.map(block => {
                let json = block.querySelector('.tw-h-full.tw-w-full').getAttribute('data-b-for-cart');
                json = JSON.parse(json);

                const category = json ? json.category_name_full_path : null;
                const valid = json ? json.ShowInOffersTo : null;
                let start_at = valid.split('T')[0];
                let end_at = start_at;

                const discounted_price = json ? json.price : null;
                const original_price = json ? json.promotion.oldPrice : null;

                const name = json ? json.title : null;
                const brand = json ? json.brand_name : null;

                const discount_percent = json && json.promotion ? `${json.promotion.percentage}%` : '';
                const product_url = json ? `${baseUrl}/produktai/${json.Url}` : '';
                const image_url = json ? json.image : '';
                const card = json && json.extra && json.extra.is_with_card ? true : false;
                const info = json ? `${json.comparative_unit_price}€/${json.comparative_unit}` : '';

                if (card) {
                    console.log('Card require ' + name);
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
                    category,
                    product_url,
                    card,
                    image_url
                };
            });
        }, baseUrl);

        console.log(productBlocks);

        allProducts = allProducts.concat(productBlocks);
        console.log(`Scraped ${productBlocks.length} products from page ${currentPage}`);
        console.log(`Total  ${allProducts.length}`);

        currentPage++;
    }

    console.log(`Scraped total ${allProducts.length} products from ${currentPage} pages`);

    try {
        const data = allProducts.map(product => ({
            name: product.name,
            brand: product.brand,
            discounted_price: product.discounted_price,
            original_price: product.original_price,
            card: product.card,
            discount_percent: product.discount_percent,
            start_at: product.start_at,
            end_at: product.end_at,
            product_url: product.product_url,
            image_url: product.image_url,
            category: product.category,
            store: 'barbora'
        }));
        await axios.post('http://127.0.0.1/api/scrapers', data);
    } catch (error) {
        console.error('Error posting products:', error.message);
    }

    fs.writeFileSync('barbora.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed. Data saved to barbora.json');

    await browser.close();
})();
