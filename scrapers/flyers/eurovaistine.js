import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// eurovaistine.lt/menesio-leidinys embeds a Yumpu document (same platform
// as Camelia — see camelia.js) but, unlike Camelia's, this one has no plain
// download link anywhere on the page, so the PDF is rebuilt from Yumpu's
// public per-page JPEGs instead (base_path + each page's images.large,
// signed by its own qss.large query string). No validity dates appear on
// the page either — only on the leaflet's own cover — read via the
// backend's Gemini cover-OCR endpoint.
const LISTING_URL = 'https://www.eurovaistine.lt/menesio-leidinys';

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const embedMatch = html.match(/https:\/\/www\.yumpu\.com\/lt\/embed\/view\/[a-zA-Z0-9]+/);
    if (!embedMatch) {
        console.log('No Yumpu embed found on', LISTING_URL);
        return;
    }

    // The page only embeds Yumpu's short hash-id form
    // ("embed/view/{hash}") — the numeric document id (needed for the
    // public document-JSON API) only appears in that embed page's own
    // canonical link.
    const embedHtml = await (await fetch(embedMatch[0])).text();
    const docIdMatch = embedHtml.match(/document\/view\/(\d+)\//);
    if (!docIdMatch) {
        console.log('Could not resolve numeric doc id from', embedMatch[0]);
        return;
    }

    const docId = docIdMatch[1];
    console.log(`Found Yumpu document ${docId}`);

    const doc = await (await fetch(`https://www.yumpu.com/lt/document/json/${docId}`)).json();
    const { title, base_path: basePath, pages } = doc.document;

    if (!pages?.length) {
        console.log('No pages found in Yumpu document JSON');
        return;
    }

    const imageUrls = pages.map(p => basePath + p.images.large + '?' + p.qss.large);
    const buffers = [];
    for (const url of imageUrls) {
        buffers.push(await fetchBuffer(url));
    }

    const coverInfo = await extractCoverInfo({ store: 'Eurovaistinė', imageBuffer: buffers[0], filename: 'eurovaistine-cover.jpg' });
    if (!coverInfo?.validFrom) {
        console.log('No reliable dates from cover OCR — submitting without dates');
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Eurovaistinė',
            title: coverInfo?.title || title,
            catalogName: 'Eurovaistinė',
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            filename: `eurovaistine-${docId}.pdf`,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
