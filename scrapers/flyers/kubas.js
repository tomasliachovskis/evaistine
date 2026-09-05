import { launchBrowser, fetchBuffer, submitFlyer, extractCoverInfo } from './_shared.js';

// The "Kubas" store's real domain is pckubas.lt (kubas.lt belongs to an
// unrelated company, "Baltasis Kubas" — confirmed via web search).
// pckubas.lt/leidiniai/ links straight to a real PDF
// (pckubas.lt/image/leidiniai/Leidinys.pdf), always at the same URL for
// whatever the current leaflet is — no per-issue slug to key on. No
// validity dates appear on the listing page itself, only printed on the
// leaflet's own cover, so those are read via the backend's Gemini
// cover-OCR endpoint. The cover image is produced by navigating Chromium
// directly to the PDF (which renders it with its own built-in viewer,
// since the file has no Content-Disposition:attachment) and screenshotting
// — `networkidle2` never resolves against the PDF viewer's own background
// requests, so `domcontentloaded` + a fixed wait is used instead.
const LISTING_URL = 'https://pckubas.lt/leidiniai/';

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const pdfMatch = html.match(/href="(https:\/\/(?:www\.)?pckubas\.lt\/image\/leidiniai\/[^"]+\.pdf)"/);
    if (!pdfMatch) {
        console.log('No PDF link found on', LISTING_URL);
        return;
    }

    const pdfUrl = pdfMatch[1];
    console.log('Found leaflet PDF:', pdfUrl);

    const browser = await launchBrowser();
    let coverInfo = null;

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1400, height: 1800, deviceScaleFactor: 2 });
        await page.goto(pdfUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await new Promise(resolve => setTimeout(resolve, 5000));
        const coverBuffer = await page.screenshot({ type: 'jpeg', quality: 90 });

        coverInfo = await extractCoverInfo({ store: 'Kubas', imageBuffer: coverBuffer, filename: 'kubas-cover.jpg' });
    } finally {
        await browser.close();
    }

    if (!coverInfo?.validFrom) {
        console.log('No reliable dates from cover OCR — submitting without dates');
    }

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        await submitFlyer({
            store: 'Kubas',
            title: coverInfo?.title || 'Kubas akcijų leidinys',
            catalogName: 'Kubas',
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: 'kubas-leidinys.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
