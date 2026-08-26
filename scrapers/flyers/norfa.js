import { launchBrowser, fetchBuffer, submitFlyer } from './_shared.js';

// Norfa's /lt/leidiniai/ page is plain server-rendered HTML with a real,
// unauthenticated, static PDF download link — no JS rendering is actually
// required to read it, but we still launch Puppeteer the same way every
// other scraper in this repo does, for consistency, and only skip the
// scroll/wait tricks that a JS-rendered site would need.
const LISTING_URL = 'https://www.norfa.lt/lt/leidiniai/';

function parseDuration(text) {
    const [fromRaw, toRaw] = text.split('-').map(s => s.trim());
    const toIso = s => s.replace(/\./g, '-');

    return { validFrom: toIso(fromRaw), validTo: toIso(toRaw) };
}

function parseTitle(title) {
    const match = title.match(/^(.*?)\s*Nr\.\s*(\d+)\s*(?:\((\d+)\))?/i);

    if (!match) {
        return { catalogName: title, issueNumber: null };
    }

    return {
        catalogName: match[1].trim(),
        issueNumber: match[3] || match[2],
    };
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });

        const leaflets = await page.$$eval('.c-leaflet__details', cards => cards.map(card => ({
            title: card.querySelector('.c-leaflet__title')?.textContent.trim() ?? '',
            duration: card.querySelector('.c-leaflet__duration')?.textContent.trim() ?? '',
            href: card.querySelector('.download-pdf')?.getAttribute('href') ?? null,
        })));

        console.log(`Found ${leaflets.length} leaflet(s) on ${LISTING_URL}`);

        for (const leaflet of leaflets) {
            if (!leaflet.href || !leaflet.duration || !leaflet.title) {
                console.log('Skipping leaflet with missing data:', leaflet);
                continue;
            }

            const { validFrom, validTo } = parseDuration(leaflet.duration);
            const { catalogName, issueNumber } = parseTitle(leaflet.title);
            const downloadUrl = new URL(leaflet.href, LISTING_URL).toString();

            try {
                const pdfBuffer = await fetchBuffer(downloadUrl);

                await submitFlyer({
                    store: 'Norfa',
                    title: leaflet.title,
                    catalogName,
                    issueNumber,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    filename: `norfa-${issueNumber || 'leidinys'}.pdf`,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
