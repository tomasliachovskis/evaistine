import { fetchBuffer, submitFlyer, extractCoverInfo } from './_shared.js';

// camelia.lt/akciju-leidinys is plain server-rendered HTML (a Nuxt SSR page)
// embedding a Yumpu-hosted document, with a real "Atsisiųsti akcijų
// leidinį" download link straight to the source PDF — no flipbook scraping
// needed at all, and no Puppeteer either since everything here is a plain
// fetch. No validity dates appear on the page itself, only on the leaflet's
// own cover — read via the backend's Gemini cover-OCR endpoint, using the
// same page's first-page JPEG from Yumpu's public document-JSON API rather
// than rasterizing the (download-only, un-renderable-in-browser) PDF.
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

    const doc = await (await fetch(`https://www.yumpu.com/lt/document/json/${docId}`)).json();
    const { title, base_path: basePath, pages } = doc.document;

    let coverInfo = null;
    if (pages?.[0]) {
        const coverUrl = basePath + pages[0].images.large + '?' + pages[0].qss.large;
        const coverBuffer = await fetchBuffer(coverUrl);
        coverInfo = await extractCoverInfo({ store: 'Camelia', imageBuffer: coverBuffer, filename: 'camelia-cover.jpg' });
    }

    if (!coverInfo?.validFrom) {
        console.log('No reliable dates from cover OCR — submitting without dates');
    }

    try {
        const pdfBuffer = await fetchBuffer(downloadUrl);

        await submitFlyer({
            store: 'Camelia',
            title: coverInfo?.title || title,
            catalogName: 'Camelia',
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            sourcePdfUrl: downloadUrl,
            filename: `camelia-${docId}.pdf`,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
