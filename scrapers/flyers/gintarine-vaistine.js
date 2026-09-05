import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, sleep } from './_shared.js';

// gintarine.lt/leidiniai lists a single current leaflet as a direct link to
// an Issuu embed (`e.issuu.com/embed.html?d={docname}&u={username}`) — same
// platform and page-fetching approach as Iki (see iki.js for the full
// rationale: loading the embed sniffs a `{revisionId}-{publicationId}`
// slug from its `svg.issuu.com` requests, which also serves plain JPEGs at
// `image.isu.pub/{slug}/jpg/page_{n}.jpg` with no auth needed). The title
// only names the month ("...rugsėjo mėnesio leidinys"), no exact validity
// dates anywhere on the page.
const LISTING_URL = 'https://www.gintarine.lt/leidiniai';

async function findLeaflet(page) {
    return page.$eval('a.publications-item[href*="issuu.com/embed.html"]', el => ({
        href: el.getAttribute('href'),
        title: el.querySelector('.publications-item__title')?.textContent.trim() ?? '',
    })).catch(() => null);
}

async function fetchLeafletPdf(browser, embedUrl) {
    const embedPage = await browser.newPage();
    let slug = null;

    embedPage.on('response', response => {
        if (slug) return;
        const match = response.url().match(/svg\.issuu\.com\/([^/]+)\/page_\d+\.svg/);
        if (match) slug = match[1];
    });

    try {
        await embedPage.goto(embedUrl, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
        for (let waited = 0; waited < 20 && !slug; waited++) await sleep(1000);
    } finally {
        await embedPage.close();
    }

    if (!slug) throw new Error('Could not determine Issuu page-image slug');

    const buffers = [];
    for (let n = 1; ; n++) {
        try {
            buffers.push(await fetchBuffer(`https://image.isu.pub/${slug}/jpg/page_${n}.jpg`));
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

        const leaflet = await findLeaflet(page);
        if (!leaflet?.href) {
            console.log('No Issuu embed found on', LISTING_URL);
            return;
        }

        console.log(`Found leaflet "${leaflet.title}" at ${leaflet.href}`);

        try {
            const pdfBuffer = await fetchLeafletPdf(browser, leaflet.href);

            await submitFlyer({
                store: 'Gintarinė vaistinė',
                title: leaflet.title,
                catalogName: 'Gintarinė vaistinė',
                validFrom: null,
                validTo: null,
                pdfBuffer,
                filename: 'gintarine-vaistine-leidinys.pdf',
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
        }
    } finally {
        await browser.close();
    }
})();
