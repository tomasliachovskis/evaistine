import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

(async () => {
    const chromiumPath = '/usr/bin/chromium-browser';
    const chromePath = '/root/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome';
    const launchOptions = {
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
    };

    if (fs.existsSync(chromiumPath)) {
        launchOptions.executablePath = chromiumPath;
    } else if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    }

    const browser = await puppeteer.launch(launchOptions);
    const page = await browser.newPage();
    const sleep = ms => new Promise(res => setTimeout(res, ms));

    // Load the local HTML file
    const filePath = 'https://www.lidl.lt/c/visos-sios-savaites-akcijos';
    await page.goto(filePath, { waitUntil: 'domcontentloaded' });

    await page.waitForSelector('#onetrust-accept-btn-handler', { timeout: 5000 });
    await page.click('#onetrust-accept-btn-handler'); // Click the button

    await sleep(2000);

    // await page.click('#week-panel-0 > div > div > button'); // Click the button
    // await sleep(2000);

    // const offers = await page.evaluate(() => {
    //     const elements = document.querySelectorAll('a.ACategoryOverviewSlider__Link');
    //     const texts = Array.from(elements).map(offer => offer.href);
    //     return [...new Set(texts)];
    // });

    const offers = [
        'https://www.lidl.lt/q/search?q=&offset=10',
        'https://www.lidl.lt/q/search?q=&offset=20',
        'https://www.lidl.lt/q/search?q=&offset=30',
        'https://www.lidl.lt/q/search?q=&offset=40',
        'https://www.lidl.lt/q/search?q=&offset=50',
        'https://www.lidl.lt/q/search?q=&offset=60',
        'https://www.lidl.lt/q/search?q=&offset=70',
        'https://www.lidl.lt/q/search?q=&offset=80',
    ];

    console.log(offers);
    console.log(offers.length);

    let allProducts = new Map();

    for (const offerLink of offers) {
        await page.goto(offerLink, { waitUntil: 'domcontentloaded' });

        await sleep(2000);

        // Scroll to .s-load-more__text before checking for products
        let loadMoreTextElement = await page.$('.s-load-more__text');
        if (loadMoreTextElement) {
            await page.evaluate((element) => {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, loadMoreTextElement);
            await sleep(1000);
        }

        try {
            await page.waitForSelector('div.product-grid-box', { timeout: 5000 });
        } catch (error) {
            console.log(`No products found on ${offerLink}, continuing...`);
            continue;
        }

        // Function to extract product details
        const extractProducts = async () => {
            return await page.$$eval('.odsc-tile--label-.product-grid-box, .odsc-tile--label-red.product-grid-box', blocks => {
                function parseDate(dateStr) {
                    const [month, day] = dateStr.split(' ').map(Number);
                    const year = new Date().getFullYear();
                    return new Date(year, month - 1, day).toLocaleDateString('en-CA');
                }

                function extractCategory(block) {
                    try {
                        const impressionData = block.getAttribute('data-gridbox-impression');
                        if (impressionData) {
                            const decodedData = decodeURIComponent(impressionData);
                            const parsedData = JSON.parse(decodedData);
                            return parsedData.wonCategoryPrimary || '';
                        }
                    } catch (error) {
                        console.log('Error parsing category data:', error);
                    }
                    return '';
                }

                return blocks.map(block => {
                    const brand = block.querySelector('.product-grid-box__brand')?.textContent.trim() ?? '';
                    let name = block.querySelector('.odsc-tile__link')?.textContent.trim();
                    const discounted_price = block.querySelector('.ods-price__value')?.textContent.trim();
                    const original_price = block.querySelector('.ods-price__stroke-price')?.textContent.trim();
                    const info = block.querySelector('.ods-price__footer')?.textContent.trim();
                    const valid = block.querySelector('.product-grid-box__availabilities')?.textContent.trim();
                    let discount_percent = block.querySelector('.ods-price__box-content-text-el')?.textContent.trim() ?? '';
                    if (discount_percent && discount_percent.includes('x')) {
                        discount_percent = '';
                    }
                    const product_url = block.querySelector('a')?.href;
                    const image_url = block.querySelector('.odsc-image-gallery__image')?.src;
                    const card = block.querySelector('.seal .seal__badge') !== null;
                    const category = extractCategory(block);

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
                        card,
                        product_url,
                        image_url,
                        category
                    };
                });
            });
        };

        // Scrape products and click load more button until it's no longer available
        while (true) {
            const productBlocks = await extractProducts();
            console.log(`Found ${productBlocks.length} products on ${offerLink}`);

            // Store unique products based on "name + price + valid"
            for (const product of productBlocks) {
                const uniqueKey = `${product.name}-${product.discounted_price}-${product.start_at}-${product.end_at}`;
                if (!allProducts.has(uniqueKey)) {
                    allProducts.set(uniqueKey, product);
                }
            }

            const loadMoreButton = await page.$('.s-load-more__button.s-load-more__button');
            if (!loadMoreButton) {
                break;
            }

            // Scroll to button and click it
            await page.evaluate((button) => {
                button.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, loadMoreButton);

            await sleep(500);

            try {
                await loadMoreButton.click();
                await sleep(1000);
            } catch (error) {
                console.log('Error clicking load more button:', error.message);
                break;
            }
        }

        // When load button is no longer available, scroll to .s-load-more__text and scrape all products
        const loadMoreText = await page.$('.s-load-more__text');
        if (loadMoreText) {
            await page.evaluate((element) => {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, loadMoreText);
            await sleep(1000);
        }

        const finalProductBlocks = await extractProducts();
        console.log(`Found ${finalProductBlocks.length} products on final scrape for ${offerLink}`);

        // Store unique products from final scrape
        for (const product of finalProductBlocks) {
            const uniqueKey = `${product.name}-${product.discounted_price}-${product.start_at}-${product.end_at}`;
            if (!allProducts.has(uniqueKey)) {
                allProducts.set(uniqueKey, product);
            }
        }
    }

    const uniqueProducts = Array.from(allProducts.values());

    try {
        const data = uniqueProducts.map(product => ({
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
            image_url: product.image_url,
            category: product.category,
            store: 'lidl'
        }));
        await axios.post('http://127.0.0.1/api/scrapers', data);
    } catch (error) {
        console.error('Error posting products:', error.message);
    }

    await browser.close();
})();
