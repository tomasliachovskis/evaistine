import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo, fetchYumpuDocumentViaPage, monthRangeFromText } from './_shared.js';

// eurovaistine.lt/menesio-leidinys embeds a Yumpu document (same platform
// as Camelia — see camelia.js) but, unlike Camelia's, this one has no plain
// download link anywhere on the page, so the PDF is rebuilt from Yumpu's
// public per-page JPEGs instead (base_path + each page's images.large,
// signed by its own qss.large query string). Yumpu's document JSON is behind
// AWS WAF since 2026-10, so it's read through the pharmacy's own page in
// stealth Chrome (fetchYumpuDocumentViaPage). No validity dates appear on
// the page; the title names the month ("Eurovaistinės leidinys SPALIS"),
// and the cover OCR is the fallback when it doesn't.
const LISTING_URL = 'https://www.eurovaistine.lt/menesio-leidinys';

(async () => {
    const result = await fetchYumpuDocumentViaPage(LISTING_URL);
    if (!result) {
        console.log('Eurovaistinė: Yumpu document JSON unavailable, leaflet not collected.');
        return;
    }

    const { docId, doc } = result;
    const { title, base_path: basePath, pages } = doc;
    console.log(`Found Yumpu document ${docId}: "${title}", ${pages.length} pages`);

    const imageUrls = pages.map(p => basePath + p.images.large + '?' + p.qss.large);
    const buffers = [];
    for (const url of imageUrls) {
        buffers.push(await fetchBuffer(url));
    }

    let dates = monthRangeFromText(title);
    if (!dates) {
        const coverInfo = await extractCoverInfo({ store: 'Eurovaistinė', imageBuffer: buffers[0], filename: 'eurovaistine-cover.jpg' });
        dates = coverInfo?.validFrom ? { validFrom: coverInfo.validFrom, validTo: coverInfo.validTo } : null;
    }
    if (!dates) {
        console.log('No month in the title and no reliable dates from cover OCR — submitting without dates');
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Eurovaistinė',
            title,
            catalogName: 'Eurovaistinė',
            validFrom: dates?.validFrom ?? null,
            validTo: dates?.validTo ?? null,
            pdfBuffer,
            filename: `eurovaistine-${docId}.pdf`,
            sourceId: docId,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
