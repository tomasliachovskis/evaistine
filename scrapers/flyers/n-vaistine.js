import { launchBrowser, fetchBuffer, submitFlyer, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// nvaistine.lt/leidiniai/ (branded "Norfos vaistinės" in its own copy —
// N vaistinė's real chain name — same platform template as
// gulbele.js/vynoteka.js's parent group) only renders its leaflet
// title/dates/download link via client-side JS — a plain fetch returns an
// empty body, so Puppeteer is needed to read the final DOM. The listing
// page itself gives everything needed directly: a real download link
// (`/leidiniai/download/{id}`, plain-fetchable with no JS afterward) and
// an exact "YYYY.MM.DD - YYYY.MM.DD" validity range as plain text next to
// the leaflet title — no cover OCR needed.
const LISTING_URL = 'https://nvaistine.lt/leidiniai/';

function parseDateRange(text) {
    const match = text.match(/(\d{4})\.(\d{2})\.(\d{2})\s*[-–]\s*(\d{4})\.(\d{2})\.(\d{2})/);
    if (!match) return { validFrom: null, validTo: null };

    const [, y1, m1, d1, y2, m2, d2] = match;
    return { validFrom: `${y1}-${m1}-${d1}`, validTo: `${y2}-${m2}-${d2}` };
}

(async () => {
    const browser = await launchBrowser();
    let title = null;
    let downloadUrl = null;
    let validFrom = null;
    let validTo = null;

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'networkidle2', timeout: 60000 });
        await new Promise(resolve => setTimeout(resolve, 2000));

        const leaflet = await page.evaluate(() => {
            const card = document.querySelector('.c-leaflet--featured') || document.querySelector('.c-leaflet');
            if (!card) return null;

            const link = card.querySelector('a[href*="/leidiniai/download/"]');
            return {
                title: card.querySelector('.c-leaflet__title')?.textContent?.trim() ?? null,
                duration: card.querySelector('.c-leaflet__duration')?.textContent?.trim() ?? null,
                downloadHref: link?.getAttribute('href') ?? null,
            };
        });

        if (!leaflet?.downloadHref) {
            console.log('No leaflet download link found on', LISTING_URL);
            return;
        }

        title = leaflet.title || 'N vaistinė leidinys';
        downloadUrl = new URL(leaflet.downloadHref, LISTING_URL).toString();
        ({ validFrom, validTo } = leaflet.duration ? parseDateRange(leaflet.duration) : { validFrom: null, validTo: null });
    } finally {
        await browser.close();
    }

    try {
        const pdfBuffer = await fetchBuffer(downloadUrl);

        if (!validFrom) {
            const coverImage = await renderFirstPdfPageToJpeg(downloadUrl);
            const coverInfo = await extractCoverInfo({ store: 'N vaistinė', imageBuffer: coverImage, filename: 'n-vaistine-cover.jpg' });
            if (coverInfo?.validFrom) {
                validFrom = coverInfo.validFrom;
                validTo = coverInfo.validTo;
            } else {
                console.log('No date range found on page or via cover OCR — submitting without dates');
            }
        }

        await submitFlyer({
            store: 'N vaistinė',
            title,
            catalogName: 'N vaistinė',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: downloadUrl,
            filename: 'n-vaistine-leidinys.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
