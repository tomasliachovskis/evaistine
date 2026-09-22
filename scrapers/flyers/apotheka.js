import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// apotheka.lt/leidinys is plain server-rendered HTML (no trailing slash —
// the trailing-slash variant 301-redirects and comes back empty) embedding
// a Yumpu flipbook iframe, same platform as camelia.js. Unlike Camelia,
// there's no direct PDF download link anywhere on the page or in Yumpu's
// document JSON, so the leaflet is rebuilt from Yumpu's own per-page images
// (same technique as eurokos.js/techasas.js). Yumpu's document JSON also
// carries a real `validity.from`/`validity.until` range for this leaflet —
// no cover OCR needed at all.
const LISTING_URL = 'https://www.apotheka.lt/leidinys';

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const embedMatch = html.match(/https:\/\/www\.yumpu\.com\/lt\/embed\/view\/([a-zA-Z0-9]+)/);
    if (!embedMatch) {
        console.log('No Yumpu embed found on', LISTING_URL);
        return;
    }

    const embedHtml = await (await fetch(embedMatch[0])).text();
    const docIdMatch = embedHtml.match(/\/document\/view\/(\d+)\//);
    if (!docIdMatch) {
        console.log('No Yumpu document id found in embed page for', embedMatch[0]);
        return;
    }

    const docId = docIdMatch[1];
    console.log(`Found Yumpu document ${docId}`);

    const doc = await (await fetch(`https://www.yumpu.com/lt/document/json/${docId}`)).json();
    const { title, base_path: basePath, pages, validity } = doc.document;

    let validFrom = validity?.from ? validity.from.slice(0, 10) : null;
    let validTo = validity?.until ? validity.until.slice(0, 10) : null;

    if (!pages?.length) {
        console.log('No pages found for', title);
        return;
    }

    try {
        const buffers = [];
        for (const page of pages) {
            buffers.push(await fetchBuffer(basePath + page.images.large + '?' + page.qss.large));
        }

        if (!validFrom) {
            const coverInfo = await extractCoverInfo({ store: 'Apotheka', imageBuffer: buffers[0], filename: 'apotheka-cover.jpg' });
            if (coverInfo?.validFrom) {
                validFrom = coverInfo.validFrom;
                validTo = coverInfo.validTo;
            } else {
                console.log('No validity range in Yumpu document JSON or cover OCR — submitting without dates');
            }
        }

        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Apotheka',
            title,
            catalogName: 'Apotheka',
            validFrom,
            validTo,
            pdfBuffer,
            filename: `apotheka-${docId}.pdf`,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
