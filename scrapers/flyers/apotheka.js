import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo, fetchYumpuDocumentViaPage, monthRangeFromText } from './_shared.js';

// apotheka.lt/leidinys (no trailing slash — the trailing-slash variant
// 301-redirects and comes back empty) embeds a Yumpu flipbook iframe, same
// platform as camelia.js. Unlike Camelia, there's no direct PDF download
// link anywhere on the page or in Yumpu's document JSON, so the leaflet is
// rebuilt from Yumpu's own per-page images. The document JSON is behind AWS
// WAF since 2026-10, so it's read through the pharmacy's own page in stealth
// Chrome (fetchYumpuDocumentViaPage). Dates: the JSON's validity range when
// it has one, else the month in the title ("Apotheka vaistinės spalio akcijų
// leidinys"), else the cover OCR.
const LISTING_URL = 'https://www.apotheka.lt/leidinys';

(async () => {
    const result = await fetchYumpuDocumentViaPage(LISTING_URL);
    if (!result) {
        console.log('Apotheka: Yumpu document JSON unavailable, leaflet not collected.');
        return;
    }

    const { docId, doc } = result;
    const { title, base_path: basePath, pages, validity } = doc;
    console.log(`Found Yumpu document ${docId}: "${title}", ${pages.length} pages`);

    let validFrom = validity?.from ? validity.from.slice(0, 10) : null;
    let validTo = validity?.until ? validity.until.slice(0, 10) : null;

    try {
        const buffers = [];
        for (const page of pages) {
            buffers.push(await fetchBuffer(basePath + page.images.large + '?' + page.qss.large));
        }

        if (!validFrom) {
            const fromTitle = monthRangeFromText(title);
            const dates = fromTitle ?? await extractCoverInfo({ store: 'Apotheka', imageBuffer: buffers[0], filename: 'apotheka-cover.jpg' });
            if (dates?.validFrom) {
                validFrom = dates.validFrom;
                validTo = dates.validTo;
            } else {
                console.log('No validity range in Yumpu document JSON, title or cover OCR — submitting without dates');
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
            sourceId: docId,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
