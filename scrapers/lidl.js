import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

// Lidl prints its own real per-unit price in the same footer element already
// scraped into `info`, e.g. "400 g pakuotė / 1 kg = 9,98 €" or
// "0,75 l / 1 l = 8,33 €" — parse the "1 <unit> = X €" part out. Loose-weight
// items (already priced per kg) just show "1 kg" or "1 vnt." with no "=",
// which correctly yields no match here.
const parseUnitPrice = (text) => {
    if (!text) return null;
    const match = text.replace(/\s+/g, ' ').match(/1\s*(kg|l|vnt)\.?\s*=\s*([\d.,]+)\s*€/i);
    if (!match) return null;
    const price = parseFloat(match[2].replace(',', '.'));
    if (!price || price <= 0) return null;
    return { price, basis: match[1].toLowerCase() };
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
    const sleep = ms => new Promise(res => setTimeout(res, ms));

    // Used to carry an &offset=90 param to pre-seed virtualized placeholder
    // slots without clicking "Daugiau produktų" (which triggers anti-bot rate
    // limiting after a couple of quick clicks). That param now makes Lidl's
    // search backend return "Atsiprašome, pagal jūsų užklausą nieko nerasta"
    // (nothing found) — reproduced live 2026-09-07, site-side change, not
    // something on our end. Confirmed live that the plain empty-query URL
    // still reports the real total via .s-load-more__text ("48/48 produktas")
    // and that the existing scroll-until-stable loop below (checked against
    // that same counter) fills in every virtualized card without needing the
    // offset trick or ever hitting the load-more button.
    const offers = [
        'https://www.lidl.lt/q/search?q=',
        'https://www.lidl.lt/q/search?q=&offset=48',
    ];

    let allProducts = new Map();

    for (const offerLink of offers) {
        await page.goto(offerLink, { waitUntil: 'domcontentloaded' });

        await sleep(2000);

        // Cookie consent is domain-wide, so it's simplest to accept it directly
        // on the search page rather than detouring through another page first.
        // Detouring through /c/visos-sios-savaites-akcijos to accept it there,
        // then navigating here, reliably hung the very next page interaction
        // with a "Runtime.callFunctionOn timed out" ProtocolError — reproduced
        // 5/5 times with the detour, 0/3 times going here directly.
        const cookieButton = await page.$('#onetrust-accept-btn-handler');
        if (cookieButton) {
            await cookieButton.click();
            await sleep(1000);
        }

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

                // "SUPERKAINA!"/flat-price tiles (no % discount to compute,
                // just a slogan badge like "SUPERKAINA!", "5už1€") render
                // with NO .product-grid-box__availabilities element at all —
                // verified live on lidl.lt, unlike every regular discount
                // tile around them which has one. There's no page-level
                // validity banner to fall back to either, so carry the last
                // real validity window seen among surrounding cards instead
                // — without this, both start_at/end_at end up blank and
                // discounts:process silently drops the row entirely (9 of 52
                // Lidl products a week were vanishing this way). Stored on
                // `window` because $$eval's callback runs fresh each call
                // (once per scroll step, see the caller's loop below) with
                // no closure over Node-side state.
                window.__lidlLastValidity = window.__lidlLastValidity || { start_at: null, end_at: null };

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
                    let name = block.querySelector('.product-grid-box__title')?.textContent.trim();
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
                        window.__lidlLastValidity = { start_at, end_at };
                    } else if (typeof valid === 'string' && valid.includes('Nuo ')) {
                        start_atStr = valid.replace('Nuo ', '');
                        start_at = parseDate(start_atStr);
                        end_at = null;
                        window.__lidlLastValidity = { start_at, end_at };
                    } else if (valid) {
                        // Some other non-empty, unrecognized format — keep
                        // the previous behavior of using it as-is rather
                        // than silently discarding it.
                        start_at = valid;
                        end_at = null;
                    } else {
                        // No availabilities element at all — fall back to
                        // the last real validity window from a nearby card
                        // instead of leaving both dates blank.
                        start_at = window.__lidlLastValidity.start_at;
                        end_at = window.__lidlLastValidity.end_at;
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

        const mergeProducts = (productBlocks) => {
            for (const product of productBlocks) {
                const uniqueKey = `${product.name}-${product.discounted_price}-${product.start_at}-${product.end_at}`;
                const existing = allProducts.get(uniqueKey);
                // A card's data-gridbox-impression (category) attribute can
                // still be empty on the read that happens right as a tile
                // mounts during scrolling — keeping only the FIRST read of
                // each product silently locked in that empty category
                // forever (69 real Lidl products landed with category=NULL
                // this way, which discounts:process excludes permanently).
                // Prefer whichever read actually has a category.
                if (!existing || (!existing.category && product.category)) {
                    allProducts.set(uniqueKey, product);
                }
            }
        };

        // The list is virtualized (only a window of items exists in the DOM at
        // once), so scroll down slowly in small steps, extracting at every step —
        // reading only at the end would miss items that scrolled back out of the
        // window. Stop once we've stayed at the bottom for a few checks in a row.
        let stableAtBottom = 0;
        while (stableAtBottom < 3) {
            mergeProducts(await extractProducts());

            // Smaller steps (was 350px) — fewer new tiles trigger to mount per
            // step, and the 300ms gap has an easier time keeping up with them.
            const atBottom = await page.evaluate(() => {
                window.scrollBy(0, 150);
                return window.scrollY + window.innerHeight >= document.body.scrollHeight;
            });
            await sleep(300);

            stableAtBottom = atBottom ? stableAtBottom + 1 : 0;
        }

        console.log(`Found ${allProducts.size} unique products after scrolling ${offerLink}`);

        // The initial scrollIntoView(.s-load-more__text) above jumps almost to
        // the bottom in one move (that element sits right above the load-more
        // button), which can trigger many lazy-loaded tiles to start mounting
        // at once — the stableAtBottom loop's 300ms gap between checks isn't
        // always enough for all of them to finish before three consecutive
        // "still at the bottom" reads conclude we're done. Reproduced locally:
        // same page, same code, 35/55 one run and 51/55 the next. The site's
        // own "Rodomi produktai X / Y produktas" counter (.s-load-more__text)
        // is a reliable total to check against — if we're short, give the
        // virtualized list more time/scroll cycles instead of trusting the
        // geometry-only stability check alone.
        const totalText = await page.$eval('.s-load-more__text', el => el.textContent).catch(() => null);
        const totalMatch = totalText && totalText.match(/(\d+)\s*\/\s*(\d+)/);
        const expectedTotal = totalMatch ? parseInt(totalMatch[2], 10) : null;

        if (expectedTotal) {
            let stallRounds = 0;
            let lastCount = allProducts.size;

            while (allProducts.size < expectedTotal && stallRounds < 10) {
                await page.evaluate(() => window.scrollBy(0, 150));
                await sleep(700);
                mergeProducts(await extractProducts());

                stallRounds = allProducts.size > lastCount ? 0 : stallRounds + 1;
                lastCount = allProducts.size;
            }

            // Still short after nudging from the bottom? Scroll back to the
            // top and do a full fresh pass down — a completely new traversal
            // gives the virtualized list another chance to mount whatever
            // didn't load the first time, instead of only ever nudging from
            // wherever the previous pass stalled out.
            for (let fullRetry = 0; allProducts.size < expectedTotal && fullRetry < 5; fullRetry++) {
                console.log(`Still ${allProducts.size}/${expectedTotal}, full re-scroll pass ${fullRetry + 1}/5...`);

                await page.evaluate(() => window.scrollTo(0, 0));
                await sleep(1000);

                let passStableAtBottom = 0;
                while (passStableAtBottom < 3 && allProducts.size < expectedTotal) {
                    mergeProducts(await extractProducts());

                    const atBottom = await page.evaluate(() => {
                        window.scrollBy(0, 150);
                        return window.scrollY + window.innerHeight >= document.body.scrollHeight;
                    });
                    await sleep(500);

                    passStableAtBottom = atBottom ? passStableAtBottom + 1 : 0;
                }
            }

            if (allProducts.size < expectedTotal) {
                console.log(`Warning: only found ${allProducts.size}/${expectedTotal} products after retrying, site may be rate-limiting.`);
            } else {
                console.log(`Found all ${allProducts.size}/${expectedTotal} products after retry pass.`);
            }
        }

        // Fallback: if a "load more" button is still present (offset=90 didn't
        // seed quite enough placeholders to cover every item), click it a few
        // times, gently, to pick up whatever's left.
        for (let clicks = 0; clicks < 3; clicks++) {
            const loadMoreButton = await page.$('.s-load-more__button.s-load-more__button');
            if (!loadMoreButton) {
                break;
            }

            await page.evaluate(async () => {
                const step = 400;
                while (window.scrollY + window.innerHeight < document.body.scrollHeight) {
                    window.scrollBy(0, step);
                    await new Promise(r => setTimeout(r, 150));
                }
            });
            await sleep(2500);

            try {
                await loadMoreButton.click();
                await sleep(2000);
            } catch (error) {
                console.log('Error clicking load more button:', error.message);
                break;
            }

            mergeProducts(await extractProducts());
        }

        console.log(`Found ${allProducts.size} unique products total on ${offerLink}`);
    }

    const uniqueProducts = Array.from(allProducts.values());

    try {
        const data = uniqueProducts.map(product => {
            const unit = parseUnitPrice(product.info);
            return {
                name: product.name,
                brand: product.brand,
                discounted_price: product.discounted_price,
                original_price: product.original_price,
                info: product.info,
                unit_price: unit?.price ?? null,
                unit_price_basis: unit?.basis ?? null,
                discount_percent: product.discount_percent,
                start_at: product.start_at,
                end_at: product.end_at,
                valid: product.valid,
                card: product.card,
                product_url: product.product_url,
                image_url: product.image_url,
                category: product.category,
                store: 'lidl'
            };
        });
        await axios.post('https://superakcijos.lt/api/scrapers', data);
    } catch (error) {
        console.error('Error posting products:', error.message);
    }

    await browser.close();
})();
