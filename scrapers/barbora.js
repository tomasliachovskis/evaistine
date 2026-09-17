import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from "axios";

// Apply Stealth plugin
puppeteer.use(StealthPlugin());

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

    const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (2000 - 1000 + 1)) + 1000));

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
            await page.waitForSelector('.tw-flex-shrink-0.tw-list-none.tw-w-full', { timeout: 20000 });
        } catch (error) {
            console.log(`Failed to load products on page ${currentPage}, stopping scrape.`);
            break;
        }

        await sleep();

        const productBlocks = await page.$$eval(
            'li[data-testid^="product-card-"]',
            (blocks, baseUrl) => {
                return blocks.map(block => {
                    // Parse JSON data
                    let jsonEl = block.querySelector('.tw-h-full.tw-w-full');
                    if (!jsonEl) return null;

                    let json = JSON.parse(jsonEl.getAttribute('data-b-for-cart'));
                    if (!json) return null;

                    const category = json.category_name_full_path;
                    const valid = json.ShowInOffersTo;
                    let start_at= null;
                    let end_at = valid ? valid.split('T')[0] : null;

                    const discounted_price = json.price;
                    const original_price = json.promotion?.oldPrice || null;
                    const name = json.title;
                    const brand = json.brand_name;
                    const discount_percent = json.promotion?.percentage ? `${json.promotion.percentage}%` : '';
                    const product_url = `${baseUrl}/produktai/${json.Url}`;
                    const image_url = json.image;
                    const card = json.extra?.is_with_card || false;
                    const info = `${json.comparative_unit_price}€/${json.comparative_unit}`;

                    // Barbora's own real per-unit price is already structured
                    // data in this same JSON blob (comparative_unit is "kg",
                    // "l", or "vnt." with a trailing dot) — no text parsing
                    // needed, just clean the unit string.
                    const unitPrice = typeof json.comparative_unit_price === 'number' && json.comparative_unit_price > 0
                        ? json.comparative_unit_price
                        : null;
                    const rawUnitBasis = json.comparative_unit
                        ? String(json.comparative_unit).toLowerCase().replace(/\.$/, '')
                        : null;
                    const unitPriceBasis = ['kg', 'l', 'vnt'].includes(rawUnitBasis) ? rawUnitBasis : null;

                    // --- Shadow DOM logic ---
                    let conditionText = null;
                    const host = block.querySelector('.tw-h-full.tw-w-full > div.tw-h-full');
                    if (host?.shadowRoot) {
                        const condition = host.shadowRoot.querySelector(
                            '.bg-primary-500.text-white.rounded-lg.px-2.font-semibold.text-2xs.leading-3');
                        if (condition) conditionText = condition.textContent.trim();
                    }

                    if (card) {
                        console.log('Card requires: ' + name);
                    }

                    return {
                        name,
                        brand,
                        discounted_price,
                        original_price,
                        info,
                        unitPrice,
                        unitPriceBasis,
                        discount_percent,
                        start_at,
                        end_at,
                        category,
                        product_url,
                        card,
                        image_url,
                        condition: conditionText
                    };
                }).filter(Boolean); // remove nulls
            },
            baseUrl
        );

        console.log(productBlocks);

        allProducts = allProducts.concat(productBlocks);
        console.log(`Scraped ${productBlocks.length} products from page ${currentPage}`);
        console.log(`Total  ${allProducts.length}`);

        try {
            const data = productBlocks.map(product => ({
                name: product.name,
                brand: product.brand,
                discounted_price: product.discounted_price,
                original_price: product.original_price,
                card: product.card,
                unit_price: product.unitPrice,
                unit_price_basis: product.unitPriceBasis,
                discount_percent: product.discount_percent,
                start_at: product.start_at,
                end_at: product.end_at,
                product_url: product.product_url,
                image_url: product.image_url,
                category: product.category,
                condition: product.condition,
                store: 'maxima'
            }));
            await axios.post('https://superakcijos.lt/api/scrapers', data);
            console.log(`Posted ${data.length} products from page ${currentPage} to API`);
        } catch (error) {
            console.error(`Error posting products from page ${currentPage}:`, error.message);
        }

        currentPage++;
    }

    console.log(`Scraped total ${allProducts.length} products from ${currentPage} pages`);

    // fs.writeFileSync('barbora.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed.');

    await browser.close();
})();
