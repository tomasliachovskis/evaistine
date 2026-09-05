import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer } from './_shared.js';

// avon.lt closed its online shop ("MES UŽDARĖME AVON INTERNETINĘ
// PARDUOTUVĘ...") and now only sells through its digital catalog, linked
// from the homepage's "APSIPIRKTI DABAR" button to lt.avon-brochure.com —
// a white-labelled flipbook SaaS (digital-catalogue.com, same family of
// platform used by many Avon markets worldwide). Plain `fetch` (no
// Puppeteer needed) on the bare root URL gets a clean 302 redirect to the
// current campaign's slug (e.g. /c09_lt_2026/) and a plain server-rendered
// page — the only part of this platform that needs a real browser is its
// deeper /campaign/ sub-path, which isn't needed here. That landing page
// embeds a `window.main_brochure = {...}` JSON blob with everything:
// campaign name, exact validity dates (top-level `config.template.
// campaignDate.{start,end}`, "DD.MM.YYYY"), the campaign number
// (`config.template.orderCampaignNr`, e.g. "C09"), and the page count
// (top-level `personalized_cover.pagesNumber`). Each page is a numbered image at
// `{main_brochure.url}common/data/{NNNN}.jpg` (also available as .webp,
// but pdf-lib/imagesToPdf only supports jpg/png) — pages past the real
// last one return a ~150 byte placeholder instead of 404, so pagesNumber
// from the JSON (not probing for a 404) is the reliable page count.
//
// At ~219 pages, downloading the site's own full-resolution (1541x2000)
// per-page JPEGs straight into imagesToPdf blows well past the backend's
// 60MB cap (~74MB) — there's no server-side resize param (`?w=`/`?q=`
// etc are all ignored) and the only smaller variant the site itself
// serves is a blurry 385x500 nav-thumbnail. So each page is downscaled
// and re-encoded locally via a Chromium canvas (same "render then
// screenshot smaller" idea as _shared.js's own compressPdfByRasterizing,
// just done directly on images instead of PDF pages) before assembly —
// brings the whole leaflet down to ~25-30MB.
const ROOT_URL = 'https://lt.avon-brochure.com/';

function parseDate(ddmmyyyy) {
    const match = ddmmyyyy?.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    return match ? `${match[3]}-${match[2]}-${match[1]}` : null;
}

async function downscaleImages(buffers, { maxWidth = 1000, quality = 0.72 } = {}) {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        const results = [];

        for (const buffer of buffers) {
            const base64 = buffer.toString('base64');
            const outBase64 = await page.evaluate(async (b64, maxWidth, quality) => {
                const img = new Image();
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                    img.src = `data:image/jpeg;base64,${b64}`;
                });
                const scale = Math.min(1, maxWidth / img.width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', quality).split(',')[1];
            }, base64, maxWidth, quality);

            results.push(Buffer.from(outBase64, 'base64'));
        }

        return results;
    } finally {
        await browser.close();
    }
}

(async () => {
    const html = await (await fetch(ROOT_URL)).text();

    const match = html.match(/window\.main_brochure\s*=\s*(\{.*?\});/s);
    if (!match) {
        console.log('No main_brochure config found on', ROOT_URL);
        return;
    }

    const brochure = JSON.parse(match[1]);
    const template = brochure?.config?.template ?? {};
    const pagesNumber = brochure?.personalized_cover?.pagesNumber;
    const baseUrl = brochure?.url;

    if (!baseUrl || !pagesNumber) {
        console.log('Missing base URL or page count in main_brochure config');
        return;
    }

    const validFrom = parseDate(template.campaignDate?.start);
    const validTo = parseDate(template.campaignDate?.end);
    const issueNumber = template.orderCampaignNr || null;

    console.log(`Found "${brochure.name}" (${issueNumber}), ${pagesNumber} pages, valid ${validFrom} - ${validTo}`);

    const buffers = [];
    for (let n = 1; n <= pagesNumber; n++) {
        const pageUrl = `${baseUrl}common/data/${String(n).padStart(4, '0')}.jpg`;
        try {
            buffers.push(await fetchBuffer(pageUrl));
        } catch (error) {
            console.error(`Failed to fetch page ${n} (${pageUrl}):`, error.message);
        }
    }

    if (buffers.length === 0) {
        console.log('No pages downloaded — aborting');
        return;
    }

    try {
        const smallBuffers = await downscaleImages(buffers);
        const pdfBuffer = await imagesToPdf(smallBuffers);

        await submitFlyer({
            store: 'Avon',
            title: brochure.name || 'Avon katalogas',
            catalogName: 'Avon',
            issueNumber,
            validFrom,
            validTo,
            pdfBuffer,
            filename: `avon-${issueNumber || 'katalogas'}.pdf`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
