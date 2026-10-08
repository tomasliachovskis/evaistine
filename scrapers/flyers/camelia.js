import { fetchBuffer, submitFlyer, extractCoverInfo, fetchYumpuDocument, renderPdfBufferFirstPageToJpeg, monthRangeFromText } from './_shared.js';

// camelia.lt/akciju-leidinys is plain server-rendered HTML (a Nuxt SSR page)
// embedding a Yumpu-hosted document, with a real "Atsisiųsti akcijų
// leidinį" download link straight to the source PDF — no flipbook scraping
// and no Puppeteer needed for the PDF itself. No validity dates appear on
// the page, only on the leaflet's own cover, read via the backend's Gemini
// cover-OCR endpoint. The cover image is rendered from the PDF itself
// with Ghostscript (Chrome only downloads a Yumpu download link): Yumpu's
// document JSON (which also has the title) sits behind AWS WAF since
// 2026-10 and is only used when it answers.
const LISTING_URL = 'https://camelia.lt/akciju-leidinys';

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const downloadMatch = html.match(/https:\/\/www\.yumpu\.com\/lt\/document\/download\/(\d+)\/[a-zA-Z0-9-]+/);
    if (!downloadMatch) {
        console.log('No Yumpu download link found on', LISTING_URL);
        return;
    }

    const [downloadUrl, docId] = downloadMatch;
    console.log(`Found Yumpu document ${docId}, download URL: ${downloadUrl}`);

    try {
        const pdfBuffer = await fetchBuffer(downloadUrl);
        const doc = await fetchYumpuDocument(docId);

        const coverImage = renderPdfBufferFirstPageToJpeg(pdfBuffer);
        const coverInfo = await extractCoverInfo({ store: 'Camelia', imageBuffer: coverImage, filename: 'camelia-cover.jpg' });
        const title = coverInfo?.title || doc?.title || 'Camelia akcijų leidinys';
        const dates = coverInfo?.validFrom ? coverInfo : monthRangeFromText(title);
        if (!dates) {
            console.log('No dates via cover OCR or the title — submitting without dates');
        }

        await submitFlyer({
            store: 'Camelia',
            title,
            catalogName: 'Camelia',
            validFrom: dates?.validFrom ?? null,
            validTo: dates?.validTo ?? null,
            pdfBuffer,
            sourcePdfUrl: downloadUrl,
            filename: `camelia-${docId}.pdf`,
            sourceId: docId,
        });
    } catch (error) {
        console.error(`Failed to process leaflet ${docId}:`, error.message);
    }
})();
