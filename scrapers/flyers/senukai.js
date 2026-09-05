import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, sleep } from './_shared.js';

// senukai.lt/leidiniai lists every active physical-store leaflet as a card,
// each embedding a dcatalog.com flipbook (either a raw
// `html5.dcatalog.com/?docid={guid}` iframe or a friendly
// `Senukai.dcatalog.com/v/Leidinys-Nr-N-(id)/` one — the guid is only
// discoverable for the latter by loading it and sniffing its own page-image
// requests). Once the guid is known, `dc-docs.dcatalog.com/Senukai/
// katalogai/{guid}/ZPage_{n}.jpg` serves every page as a plain public JPEG,
// no auth needed. Only an end date is shown ("Leidinys galioja iki:
// YYYY-MM-DD") — no start date anywhere on the listing or card.
const LISTING_URL = 'https://www.senukai.lt/leidiniai';

async function findLeaflets(page) {
    return page.$$eval('.brochure-item', cards => cards.map(card => ({
        title: card.querySelector('.name')?.textContent.trim() ?? '',
        validTo: card.querySelector('p.duration')?.textContent.match(/(\d{4}-\d{2}-\d{2})/)?.[1] ?? null,
        iframeSrc: card.querySelector('iframe[src*="dcatalog.com"]')?.getAttribute('src') ?? null,
    })));
}

async function resolveGuid(browser, iframeSrc) {
    const directMatch = iframeSrc.match(/docid=([0-9a-f-]+)/);
    if (directMatch) return directMatch[1];

    const page = await browser.newPage();
    let guid = null;

    page.on('response', response => {
        if (guid) return;
        const match = response.url().match(/dc-docs\.dcatalog\.com\/Senukai\/katalogai\/([0-9a-f-]+)\/ZPage_1\.jpg/);
        if (match) guid = match[1];
    });

    try {
        await page.goto(iframeSrc.replace(/^\/\//, 'https://'), { waitUntil: 'networkidle2', timeout: 60000 });
        for (let waited = 0; waited < 15 && !guid; waited++) await sleep(1000);
        return guid;
    } finally {
        await page.close();
    }
}

async function fetchLeafletPdf(guid) {
    const buffers = [];

    for (let n = 1; ; n++) {
        try {
            buffers.push(await fetchBuffer(`https://dc-docs.dcatalog.com/Senukai/katalogai/${guid}/ZPage_${n}.jpg`));
        } catch (error) {
            break;
        }
    }

    if (buffers.length === 0) throw new Error('No page images fetched');

    return imagesToPdf(buffers);
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });

        const leaflets = await findLeaflets(page);
        console.log(`Found ${leaflets.length} leaflet(s) on ${LISTING_URL}`);

        for (const leaflet of leaflets) {
            if (!leaflet.iframeSrc || !leaflet.title) {
                console.log('Skipping leaflet with missing data:', leaflet);
                continue;
            }

            if (!leaflet.validTo) {
                console.log(`No end date for "${leaflet.title}" — submitting without dates`);
            }

            try {
                const guid = await resolveGuid(browser, leaflet.iframeSrc);
                if (!guid) throw new Error('Could not resolve dcatalog guid');

                const pdfBuffer = await fetchLeafletPdf(guid);

                await submitFlyer({
                    store: 'Senukai',
                    title: leaflet.title,
                    catalogName: 'Senukai',
                    validFrom: null,
                    validTo: leaflet.validTo,
                    pdfBuffer,
                    filename: `senukai-${guid}.pdf`,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
