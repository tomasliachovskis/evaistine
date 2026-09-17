import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import axios from "axios";

// Apply Stealth plugin
puppeteer.use(StealthPlugin());

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Iki prints its own real per-unit price inline in the same `info` string
// already scraped, e.g. "500 g, 5,98 Eur/kg" or "10 vnt., skirtingų dydžių,
// 0,18 Eur/vnt." — pull the "X Eur/unit" part out of it.
const parseUnitPrice = (text) => {
    if (!text) return null;
    const match = text.replace(/\s+/g, ' ').match(/([\d.,]+)\s*Eur\s*\/\s*(kg|l|vnt)\.?/i);
    if (!match) return null;
    const price = parseFloat(match[1].replace(',', '.'));
    if (!price || price <= 0) return null;
    return { price, basis: match[2].toLowerCase() };
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

    const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (3000 - 1000 + 1)) + 1000));

    let allProducts = [];

    console.log(`Opening main category page.`);
    await page.goto('https://iki.lt/akcijos/savaites-akcijos/', { waitUntil: 'domcontentloaded' });

    // Handle cookies
    try {
        await page.waitForSelector('#onetrust-accept-btn-handler', { timeout: 5000 });
        await page.click('#onetrust-accept-btn-handler');
        console.log('Accepted cookies.');
    } catch (error) {
        console.log('No cookie popup detected or already accepted.');
    }

    const categoryLinks = await page.$$eval(' div.offers-categories > div.items.d-flex.flex-column > a', anchors =>
        anchors.map(anchor => anchor.href)
    );

    console.log(categoryLinks);
    console.log(`Found ${categoryLinks.length} category links.`);

    for (const link of categoryLinks) {
        if (link === 'https://iki.lt/akcijos/savaites-akcijos/') {
            continue;
        }

        const fullUrl = link;
        console.log(`Visiting category: ${fullUrl}`);
        await page.goto(fullUrl, { waitUntil: 'domcontentloaded' });

        await page.evaluate(async () => {
            const distance = 500;
            const scrollDelay = 500;
            let scrollPosition = 0;

            while (scrollPosition < document.body.scrollHeight) {
                window.scrollTo(0, scrollPosition);
                scrollPosition += distance;
                await new Promise(resolve => setTimeout(resolve, scrollDelay));
            }
        });

        try {
            await page.waitForSelector('div.product.border-radius-20', { timeout: 5000 });
        } catch (error) {
            console.log(`Failed to load products in ${fullUrl}, skipping.`);
            continue;
        }

        await sleep();

        const productBlocks = await page.$$eval('div.product.border-radius-20', (blocks, link) => {
            function parseDate(dateStr) {
                const [month, day] = dateStr.split('.').map(Number);
                const year = new Date().getFullYear();
                return new Date(year, month - 1, day).toLocaleDateString('en-CA');
            }

            const cleanPrice = (str) => {
                if (typeof str !== 'string') return str;
                return str.replace(/\s+/g, '').replace(/(\d)(?=\d)/, '$1.');
            };

            return blocks.map(block => {
                const name = block.querySelector('h2.font-family-proxima-soft-condensed.text-px-20')?.textContent.trim();

                let price1 = block.querySelector('.card_tag .price_int')?.textContent.trim() ?? '';
                let price2 = block.querySelector('.card_tag .sub')?.textContent.trim() ?? '';
                let discounted_price = price1 + '.' + price2;

                if (discounted_price === '.') {
                    price1 = block.querySelector('.price_block_wrapper .price_int')?.textContent.trim() ?? '';
                    price2 = block.querySelector('.price_block_wrapper .price_cents')?.textContent.trim() ?? '';
                    discounted_price = price1 + '.' + price2;
                }

                if (discounted_price === '.') {
                    discounted_price = null;
                }

                if (discounted_price) {
                    discounted_price = discounted_price
                        .split(/\r?\n/)
                        .map(line => line.trim())
                        .find(line => line !== '');
                }

                // let original_price = block.querySelector('.price_old_block')?.textContent.trim() ?? '';
                let discount_percent = block.querySelector('.percentage_tag .main')?.textContent.trim() ?? '-';

                if (discount_percent === '-') {
                    discount_percent = block.querySelector('.price_block_rounded_red_wrapper .main')?.textContent.trim() ?? '-';
                }
                if (discount_percent === '-') {
                    discount_percent = block.querySelector('.price_block_red_wrapper .main')?.textContent.trim() ?? '-';
                }

                const priceInt = block.querySelector('.price_old_block .price_int')?.textContent.trim();
                const priceCents = block.querySelector('.price_old_block .price_cents')?.textContent.trim();

                let original_price = (priceInt && priceCents)
                    ? `${priceInt}.${priceCents}`
                    : '';

                if ((parseFloat(discounted_price) > 0 && parseFloat(original_price) > 0)
                    && parseFloat(discounted_price) > parseFloat(original_price)) {
                    let tmp = original_price;
                    original_price = discounted_price;
                    discounted_price = tmp;
                }

                const info = block.querySelector('p.font-family-proxima-soft-condensed.text-px-16.text-dark-grey-new.line-height-100.fw-light.mt-1.m-0.max-w-75')?.textContent.trim();
                let valid = block.querySelector('p.font-family-proxima-soft-condensed.text-px-14.text-dark-grey-new.line-height-100.fw-light.mt-2.m-0.max-w-75')?.textContent.trim();
                const product_url = block.querySelector('a')?.href;
                const image_url = block.querySelector('img.object-fit-cover.object-position-center.border-radius-20')?.src;
                const card = block.querySelector('.card') !== null;

                let start_at = '';
                let end_at = '';

                if (valid && typeof valid === 'string' && valid.includes(' - ')) {
                    valid = valid.replace('Galioja: ', '').trim();
                    const [start_atStr, end_atStr] = valid.split('-').map(s => s.trim());
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
                    info,
                    discount_percent,
                    start_at,
                    end_at,
                    card,
                    product_url,
                    image_url,
                    link, // <-- link is now included here
                };
            });
        }, link); // <-- passing link as argument

        console.log(`Scraped ${productBlocks.length} products from category.`);

        allProducts = allProducts.concat(productBlocks);
        await sleep();
    }

    // Save and send
    try {
        const data = allProducts.map(product => {
            const unit = parseUnitPrice(product.info);
            return {
                name: product.name,
                brand: product.brand ?? '',
                discounted_price: product.discounted_price,
                original_price: product.original_price,
                card: product.card,
                info: (product.info),
                unit_price: unit?.price ?? null,
                unit_price_basis: unit?.basis ?? null,
                discount_percent: product.discount_percent,
                start_at: product.start_at,
                end_at: product.end_at,
                product_url: product.product_url,
                image_url: product.image_url,
                category: product.link,
                store: 'iki'
            };
        });
        await axios.post('https://superakcijos.lt/api/scrapers', data);
    } catch (error) {
        console.error('Error posting products:', error.message);
    }

    // fs.writeFileSync('scrapers/iki.json', JSON.stringify(allProducts, null, 2));
    console.log('Scraping completed.');

    await browser.close();
})();
