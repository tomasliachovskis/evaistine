import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

const MAX_CONCURRENT_REQUESTS = 12;
const BASE_URL = 'https://gulbele.lt';
const LISTING_URL = `${BASE_URL}/sumazinta-kaina?resultsPerPage=40`;
const STORE = 'Gulbelė';

const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (2000 - 1000 + 1)) + 1000));

const decodeHtmlEntities = (text) => {
    return text
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&#039;/g, "'");
};

const formatDate = (value) => {
    if (!value || value === '0000-00-00 00:00:00') {
        return null;
    }
    return value.split(' ')[0];
};

const parseProductPage = (html) => {
    let start_at = null;
    let end_at = null;
    let brand = null;
    let category = null;

    const dataProductMatch = html.match(/data-product="([^"]+)"/);
    if (dataProductMatch) {
        try {
            const productData = JSON.parse(decodeHtmlEntities(dataProductMatch[1]));
            if (productData.specific_prices) {
                start_at = formatDate(productData.specific_prices.from);
                end_at = formatDate(productData.specific_prices.to);
            }
            if (productData.manufacturer_name) {
                brand = productData.manufacturer_name;
            }
            if (!category && productData.category_name) {
                category = productData.category_name;
            }
        } catch (error) {
        }
    }

    const datalayerMatch = html.match(/let cdcDatalayer = (\{.*?\});/);
    if (datalayerMatch) {
        try {
            const datalayer = JSON.parse(datalayerMatch[1]);
            const item = datalayer?.ecommerce?.items?.[0];
            if (item) {
                if (!brand && item.item_brand) {
                    brand = item.item_brand;
                }
                const categories = [item.item_category, item.item_category2, item.item_category3]
                    .filter(Boolean);
                if (categories.length > 0) {
                    category = categories.join(' > ');
                }
            }
        } catch (error) {
        }
    }

    return { start_at, end_at, brand, category };
};

const enrichProduct = async (product) => {
    try {
        const response = await fetch(product.product_url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            },
        });

        if (!response.ok) {
            return null;
        }

        const html = await response.text();
        const enriched = parseProductPage(html);
        if (!enriched.start_at && !enriched.end_at) {
            return null;
        }

        return {
            ...product,
            brand: enriched.brand || product.brand,
            category: enriched.category || product.category,
            start_at: enriched.start_at,
            end_at: enriched.end_at,
        };
    } catch (error) {
        return null;
    }
};

const enrichProducts = async (products) => {
    const enriched = [];

    for (let i = 0; i < products.length; i += MAX_CONCURRENT_REQUESTS) {
        const batch = products.slice(i, i + MAX_CONCURRENT_REQUESTS);
        const results = await Promise.all(batch.map(enrichProduct));
        enriched.push(...results.filter(Boolean));
    }

    return enriched;
};

const cleanPrice = (value) => {
    if (!value) {
        return '';
    }
    return value.replace(/\u00a0/g, ' ').replace(/€/g, '').trim();
};

(async () => {
    const systemChromePath = '/usr/bin/google-chrome';
    const chromePath = `${process.env.HOME}/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome`;
    const launchOptions = {
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
    };

    // Prefer the system Chrome baked into the Sail image's Dockerfile
    // (permanent, survives container recreation) over puppeteer's own
    // downloaded copy under ~/.cache/puppeteer — that path lives in the
    // container's ephemeral home directory (not the bind-mounted project
    // dir, not a named Docker volume), so it silently disappears every
    // time the container is recreated (sail up / sail build), breaking
    // every scraper until someone remembers to re-run
    // `npx puppeteer browsers install chrome`. /usr/bin/chromium-browser
    // is NOT an equivalent fallback — on a stale image (Dockerfile changed
    // but the image wasn't rebuilt) it's Ubuntu's transitional snap-stub,
    // which exists on disk but just errors telling you to install the
    // snap — only safe once the Dockerfile's own
    // `ln -sf /usr/bin/google-chrome /usr/bin/chromium-browser` step has
    // actually run, hence checking google-chrome directly instead.
    if (fs.existsSync(systemChromePath)) {
        launchOptions.executablePath = systemChromePath;
    } else if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    } else {
        launchOptions.executablePath = puppeteer.executablePath();
    }

    const browser = await puppeteer.launch(launchOptions);
    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36'
    );

    let currentPage = 1;
    let totalProducts = 0;

    console.log('Opening initial page to handle cookies.');
    await page.goto(`${LISTING_URL}&page=1`, { waitUntil: 'domcontentloaded' });

    try {
        await page.waitForSelector('button, a', { timeout: 5000 });
        await page.evaluate(() => {
            const elements = [...document.querySelectorAll('button, a')];
            const accept = elements.find(el => el.textContent.trim() === 'Priimti viską');
            if (accept) {
                accept.click();
            }
        });
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    while (true) {
        const pageUrl = `${LISTING_URL}&page=${currentPage}`;
        console.log(`Scraping page: ${currentPage}`);
        await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });

        try {
            await page.waitForSelector('article.product-miniature', { timeout: 20000 });
        } catch (error) {
            console.log(`Failed to load products on page ${currentPage}, stopping scrape.`);
            break;
        }

        await sleep();

        const productBlocks = await page.$$eval('article.product-miniature', (blocks) => {
            return blocks.map(block => {
                const name = block.querySelector('.product-title a')?.textContent.trim() ?? '';
                const product_url = block.querySelector('.product-title a')?.href ?? '';
                const discounted_price = block.querySelector('.price')?.textContent.trim() ?? '';
                const original_price = block.querySelector('.regular-price')?.textContent.trim() ?? '';
                const image_url = block.querySelector('.product-thumbnail img')?.src ?? '';
                const fractPrice = block.querySelector('.fract_price')?.textContent.trim() ?? '';
                const info = fractPrice && fractPrice !== '\u00a0' ? fractPrice : '';

                if (!name || !product_url) {
                    return null;
                }

                return {
                    name,
                    product_url,
                    discounted_price,
                    original_price,
                    image_url,
                    info,
                    category: 'Sumažinta kaina',
                };
            }).filter(Boolean);
        });

        if (productBlocks.length === 0) {
            console.log(`No products on page ${currentPage}, stopping scrape.`);
            break;
        }

        const cleanedProducts = productBlocks.map(product => ({
            ...product,
            discounted_price: cleanPrice(product.discounted_price),
            original_price: cleanPrice(product.original_price),
        }));

        console.log(`Enriching ${cleanedProducts.length} products from page ${currentPage}`);
        const enrichedProducts = await enrichProducts(cleanedProducts);
        totalProducts += enrichedProducts.length;
        console.log(`Enriched ${enrichedProducts.length} products from page ${currentPage}`);
        console.log(`Total ${totalProducts}`);

        if (enrichedProducts.length > 0) {
            try {
                const data = enrichedProducts.map(product => ({
                    name: product.name,
                    brand: product.brand,
                    discounted_price: product.discounted_price,
                    original_price: product.original_price,
                    info: product.info,
                    start_at: product.start_at,
                    end_at: product.end_at,
                    product_url: product.product_url,
                    image_url: product.image_url,
                    category: product.category,
                    store: STORE,
                }));
                await axios.post('http://127.0.0.1/api/scrapers', data);
                console.log(`Posted ${data.length} products from page ${currentPage} to API`);
            } catch (error) {
                console.error(`Error posting products from page ${currentPage}:`, error.message);
            }
        }

        const hasNextPage = await page.$('link[rel="next"]');
        if (!hasNextPage) {
            break;
        }

        currentPage++;
    }

    console.log(`Scraped total ${totalProducts} products from ${currentPage} pages`);
    console.log('Scraping completed.');

    await browser.close();
})();
