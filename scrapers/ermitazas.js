import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import axios from 'axios';

puppeteer.use(StealthPlugin());

const BASE_URL = 'https://www.ermitazas.lt';
const STORE = 'Ermitažas';
const API_URL = process.env.SCRAPER_API_URL || 'https://superakcijos.lt/api/scrapers';

// Full catalog of the everyday-goods leaf categories only (household
// chemicals, cleaning supplies, paper goods, pet food, hygiene, food) —
// Ermitažas is mostly a DIY/home store, so durable goods (cookware, tools,
// furniture, toys, perfume, brooms) are deliberately left out.
//
// Only LEAF categories: a parent category page renders subcategory tiles and
// an empty `products` array (e.g. "Asmens higienos priemonės" claims 202
// products but only exposes 11 of them through its two leaf children — the
// rest aren't reachable by category browsing at all).
const CATEGORY_PATHS = [
    'namu-apyvoka-ir-buitis/buitine-chemija/langu-valikliai-chemija',
    'namu-apyvoka-ir-buitis/buitine-chemija/skalbimo-priemones/skalbimo-milteliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/skalbimo-priemones/skystieji-skalbikliai-skalbimo-kapsules',
    'namu-apyvoka-ir-buitis/buitine-chemija/skalbimo-priemones/skalbiniu-minkstikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/skalbimo-priemones/demiu-valikliai-ir-balinimo-priemones',
    'namu-apyvoka-ir-buitis/buitine-chemija/skalbimo-priemones/vandens-minkstikliai-ir-skalbiniu-standikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/indu-plovimo-priemones/indu-plovikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/indu-plovimo-priemones/indaploviu-tabletes-ir-priedai',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/vonios-kambario-valikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/tualeto-valikliai-gaivikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/nutekamuju-vamzdziu-valikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/kilimu-valikliai',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/grindu-valymo-priemones',
    'namu-apyvoka-ir-buitis/buitine-chemija/buitines-valymo-prieziuros-priemones/universalus-ivairiu-pavirsiu-valikliai',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/kempines-sluostes-sveistukai-valymas/indu-valymo-kempineles-reikmenys',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/kempines-sluostes-sveistukai-valymas/metaliniai-ir-plastikiniai-sveistukai-indams-valymas',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/kempines-sluostes-sveistukai-valymas/sluostes-valymas',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/kempines-sluostes-sveistukai-valymas/dulkiu-sluostes-valymas',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/buitines-pirstines',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/langu-valymo-priemones/langu-valikliai',
    'namu-apyvoka-ir-buitis/valymo-reikmenys/avalynes-prieziura/batu-tepalai-ir-gaivikliai',
    'namu-apyvoka-ir-buitis/vienkartiniai-popieriaus-gaminiai/tualetinis-popierius',
    'namu-apyvoka-ir-buitis/vienkartiniai-popieriaus-gaminiai/popieriniai-ranksluosciai',
    'namu-apyvoka-ir-buitis/vienkartiniai-popieriaus-gaminiai/vienkartines-nosinaites-ir-serveteles',
    'namu-apyvoka-ir-buitis/siuksliadezes-namams/siuksliu-maisai',
    'namu-apyvoka-ir-buitis/virtuves-ir-stalo-reikmenys/kepimo-popierius-folija-plevele/kepimo-popierius-rankoves',
    'namu-apyvoka-ir-buitis/virtuves-ir-stalo-reikmenys/kepimo-popierius-folija-plevele/maistines-pleveles',
    'namu-apyvoka-ir-buitis/virtuves-ir-stalo-reikmenys/kepimo-popierius-folija-plevele/folijos',
    'gyvunu-prekes/sunims/maistas-skanestai-ir-vitaminai-sunims/sausas-maistas-sunims',
    'gyvunu-prekes/sunims/maistas-skanestai-ir-vitaminai-sunims/konservai-sunims',
    'gyvunu-prekes/sunims/maistas-skanestai-ir-vitaminai-sunims/skanestai-sunims',
    'gyvunu-prekes/sunims/maistas-skanestai-ir-vitaminai-sunims/vitaminai-ir-maisto-papildai-sunims',
    'gyvunu-prekes/sunims/higienos-ir-prieziuros-priemones-sunims',
    'gyvunu-prekes/katems/maistas-skanestai-ir-vitaminai-katems/sausas-maistas-katems',
    'gyvunu-prekes/katems/maistas-skanestai-ir-vitaminai-katems/konservai-katems',
    'gyvunu-prekes/katems/maistas-skanestai-ir-vitaminai-katems/skanestai-katems',
    'gyvunu-prekes/katems/maistas-skanestai-ir-vitaminai-katems/vitaminai-ir-maisto-papildai-katems',
    'gyvunu-prekes/katems/kaciu-kraikas',
    'gyvunu-prekes/katems/higienos-ir-prieziuros-priemones-katems',
    'gyvunu-prekes/grauzikams/maistas-skanestai-ir-vitaminai-grauzikams/maistas-grauzikams',
    'gyvunu-prekes/pauksciams/maistas-skanestai-ir-vitaminai-pauksciams/pauksciu-lesalas',
    'kosmetika-kvepalai-ir-higiena/asmens-higienos-priemones/duso-zele-aliejai',
    'kosmetika-kvepalai-ir-higiena/asmens-higienos-priemones/dezinfekciniai-skysciai-antibakterines-serveteles',
    'kosmetika-kvepalai-ir-higiena/kosmetika-kunui/duso-zele-aliejai-kosmetika',
    'kosmetika-kvepalai-ir-higiena/kosmetika-kunui/muilas-higiena',
    'kosmetika-kvepalai-ir-higiena/kosmetika-kunui/kuno-kremai-losjonai',
    'kosmetika-kvepalai-ir-higiena/kosmetika-kunui/ranku-kremai',
    'kosmetika-kvepalai-ir-higiena/plauku-prieziuros-priemones/plauku-sampunai',
    'kosmetika-kvepalai-ir-higiena/plauku-prieziuros-priemones/balzamai-kondicionieriai',
    'kosmetika-kvepalai-ir-higiena/plauku-prieziuros-priemones/plauku-formavimo-priemones',
    'zaislai-prekes-vaikams-ir-kudikiams/kudikio-higienos-ir-sveikatos-priemones/sauskelnes-dregnos-serveteles-vystyklai/sauskelnes',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/bakaleja/traskuciai-trapuciai-ir-kiti-uzkandziai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/bakaleja/aliejus-ir-actas',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/saldumynai/saldainiai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/saldumynai/sausainiai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/saldumynai/draze',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/saldumynai/batoneliai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/gerimai/gaivieji-gerimai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/gerimai/sultys-nektarai-ir-sulciu-gerimai',
    'maistas-maisto-papildai-ir-sveikata/maisto-prekes-ir-gerimai/gerimai/vanduo',
];

const MAX_PAGE_ATTEMPTS = 3;

const sleep = () => new Promise(res => setTimeout(res, Math.floor(Math.random() * (2000 - 1000 + 1)) + 1000));

// Ermitažas publishes no validity dates at all (not on the card, not on the
// product page), and discounts:process drops rows where both dates are null.
// Use the current Monday–Sunday week, same shape as a weekly leaflet: daily
// runs within one week dedupe onto the same Discount rows, and last week's
// rows expire via discounts:archive-expired on their own.
const currentWeek = () => {
    const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Vilnius' }));
    const monday = new Date(today);
    monday.setDate(today.getDate() - ((today.getDay() + 6) % 7));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return { start_at: fmt(monday), end_at: fmt(sunday) };
};

// `barcode[]` mixes real EANs with store-internal codes: in-store EAN-13s
// (GS1 prefix 20–29, e.g. "2000000051499") and multipack codes with a
// suffix (e.g. "4779017040533BLK" — the 6-pack, not the single bottle).
// Only a real, digits-only retail barcode may be used for cross-store
// product matching, so pick the first one of those or nothing.
const pickEan = (codes) => (codes ?? []).find(code =>
    /^(\d{8}|\d{12,14})$/.test(code) && !(code.length === 13 && code.startsWith('2'))
) ?? null;

const attribute = (product, slug) =>
    product.attributes?.additionalAttributes?.find(a => a.labelSlug === slug)?.value ?? null;

// `specialPrice` is either the Ermis loyalty-card price (isErmisPrice) or a
// plain sale price everyone gets; with no specialPrice the regular price is
// the only price and there's no discount at all.
// `category` is taken from the LISTING page's breadcrumb, not the product's
// own `category[]`: that array is several category paths flattened into one
// (a product filed under more than one category), so its tail can land on an
// unrelated category we deliberately don't scrape (e.g. hair clippers from
// "Buitinė technika" on a dog-hygiene item).
const toRow = (product, listingCategory, dates) => {
    const variant = product.variants?.[0] ?? {};
    const regular = Number(variant.price) || null;
    const special = Number(product.specialPrice) || null;
    const hasDiscount = special && regular && special < regular;
    const brand = attribute(product, 'prekes-zenklas');

    return {
        name: product.name,
        category: listingCategory,
        product_url: `${BASE_URL}/p/${product.slug}`,
        store: STORE,
        original_price: hasDiscount ? String(regular) : '',
        discounted_price: String(hasDiscount ? special : (regular ?? special ?? '')),
        discount_percent: hasDiscount ? String(Math.round((1 - special / regular) * 100)) : '',
        card: Boolean(hasDiscount && product.isErmisPrice),
        start_at: dates.start_at,
        end_at: dates.end_at,
        brand: brand && brand !== 'Nenurodyta' ? brand : null,
        ean: pickEan(product.barcode) ?? pickEan(variant.barcode),
        image_url: product.images?.[0]?.path ?? '',
    };
};

const readPageProps = (page) => page.evaluate(() => {
    const el = document.getElementById('__NEXT_DATA__');
    return el ? JSON.parse(el.textContent).props.pageProps : null;
});

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

    // Same Chrome resolution order as every other scraper — see
    // thomas-philipps.js for why the system Chrome comes first.
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

    // Prices, barcodes and breadcrumbs are all in the server-rendered
    // __NEXT_DATA__ JSON, so skip images/fonts/css to keep each load cheap.
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        if (['image', 'font', 'stylesheet', 'media'].includes(request.resourceType())) {
            request.abort();
        } else {
            request.continue();
        }
    });

    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    let totalProducts = 0;
    const expectedByCategory = {};

    for (const categoryPath of CATEGORY_PATHS) {
        let currentPage = 1;
        let pageCount = 1;

        do {
            const pageUrl = `${BASE_URL}/c/${categoryPath}${currentPage > 1 ? `?page=${currentPage}` : ''}`;

            // A transient load failure mid-category must not silently cut
            // the rest of its pagination short, so retry before giving up.
            let props = null;
            for (let attempt = 1; attempt <= MAX_PAGE_ATTEMPTS; attempt++) {
                try {
                    await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
                    props = await readPageProps(page);
                    if (props && Array.isArray(props.products)) {
                        break;
                    }
                } catch (error) {
                    console.log(`Attempt ${attempt} failed for ${categoryPath} page ${currentPage}: ${error.message}`);
                }
                props = null;
                await sleep();
            }

            if (!props) {
                console.log(`Giving up on ${categoryPath} page ${currentPage}.`);
                break;
            }

            if (currentPage === 1) {
                pageCount = props.pageCount || 1;
                expectedByCategory[categoryPath] = props.totalProduct ?? 0;
            }

            const listingCategory = (props.breadcrumbs ?? []).map(b => b.title.trim()).slice(-2).join('/');
            const rows = props.products.map(product => toRow(product, listingCategory, dates)).filter(row => row.name && row.discounted_price);

            if (rows.length > 0) {
                try {
                    await axios.post(API_URL, rows);
                    totalProducts += rows.length;
                    console.log(`${categoryPath} page ${currentPage}/${pageCount}: posted ${rows.length} (total ${totalProducts})`);
                } catch (error) {
                    console.error(`Error posting ${categoryPath} page ${currentPage}:`, error.message);
                }
            }

            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    }

    const expectedTotal = Object.values(expectedByCategory).reduce((sum, n) => sum + n, 0);
    console.log(`Scraped total ${totalProducts} products (site reports ${expectedTotal}) across ${CATEGORY_PATHS.length} categories.`);
    console.log('Scraping completed.');

    await browser.close();
})();
