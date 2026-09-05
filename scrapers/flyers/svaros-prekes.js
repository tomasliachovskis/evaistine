import { launchBrowser, fetchBuffer, submitFlyer, extractCoverInfo } from './_shared.js';

// svarosprekes.lt/pdfviewer/leidinys/ is a WordPress page built with the
// "pdf-viewer-for-wordpress" plugin — it 302-redirects (no Puppeteer needed
// for this step, a plain fetch with redirect:'manual' sees it) to
// .../pdf-viewer-for-wordpress/web/pdf-viewer-x.php?tnc_pvfw=<base64>, whose
// base64 payload is itself a query string containing `file=<direct PDF
// URL>` — no flipbook/embed scraping needed, just decode and fetch that PDF
// straight from wp-content/uploads. Neither the redirect target nor the PDF
// filename (e.g. "..._2026_09_su_nuorodomis.pdf", just a month stamp) carry
// an exact day-level validity range, and there's no other date text
// anywhere on the site for this leaflet — so the cover page is rendered to
// an image with Puppeteer (single #page=1 screenshot, same technique as
// _shared.js's compressPdfByRasterizing) and read via the backend's cover-OCR
// endpoint, same as Camelia/Aibė.
const LISTING_URL = 'https://svarosprekes.lt/pdfviewer/leidinys/';

async function resolvePdfUrl() {
    const response = await fetch(LISTING_URL, { redirect: 'manual' });
    const location = response.headers.get('location');
    if (!location) return null;

    const tncMatch = location.match(/tnc_pvfw=([^&#]+)/);
    if (!tncMatch) return null;

    const decoded = Buffer.from(decodeURIComponent(tncMatch[1]), 'base64').toString('utf8');
    const fileMatch = decoded.match(/file=([^&]+)/);
    return fileMatch ? decodeURIComponent(fileMatch[1]) : null;
}

async function screenshotCover(pdfUrl) {
    const browser = await launchBrowser();
    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1200, height: 1600, deviceScaleFactor: 2 });
        await page.goto(`${pdfUrl}#page=1`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 4000));
        return await page.screenshot({ type: 'jpeg', quality: 80 });
    } finally {
        await browser.close();
    }
}

(async () => {
    const pdfUrl = await resolvePdfUrl();
    if (!pdfUrl) {
        console.log('No PDF URL resolved from', LISTING_URL);
        return;
    }

    console.log('Found PDF URL:', pdfUrl);

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        let coverInfo = null;
        try {
            const coverBuffer = await screenshotCover(pdfUrl);
            coverInfo = await extractCoverInfo({ store: 'Švaros Prekės', imageBuffer: coverBuffer, filename: 'svaros-prekes-cover.jpg' });
        } catch (error) {
            console.error('Cover screenshot/OCR failed:', error.message);
        }

        if (!coverInfo?.validFrom) {
            console.log('No reliable dates from cover OCR — submitting without dates');
        }

        await submitFlyer({
            store: 'Švaros Prekės',
            title: coverInfo?.title || 'Švaros Prekės leidinys',
            catalogName: 'Švaros Prekės',
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: `svaros-prekes-${pdfUrl.split('/').pop()}`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
