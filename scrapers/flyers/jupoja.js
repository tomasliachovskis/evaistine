import { fetchBuffer, imagesToPdf, submitFlyer } from './_shared.js';

// jupoja.lt and jsm.lt ("Jupojos statybinės medžiagos") share the same
// nopCommerce install — the leaflet listing lives at
// jsm.lt/pasiulymu-leidiniai, plain server-rendered HTML with a single
// current brochure linking to a self-hosted FlowPaper flipbook viewer
// (leidiniai/index.html). FlowPaper's inline JS config gives a PDF
// filename that itself 404s (blocked from direct access), but the same
// filename's per-page JPEGs (`{pdf}_${page}.jpg`) are servable — so pages
// are fetched as images and reassembled rather than downloading the PDF.
// No total page count is exposed anywhere, so pages are probed
// sequentially until one 404s. The only validity date is a partial
// "(iki YYYY MM DD)" end date in the listing page's own heading — no
// start date is given anywhere.
const LISTING_URL = 'https://www.jsm.lt/pasiulymu-leidiniai';
const VIEWER_URL = 'https://www.jsm.lt/leidiniai/index.html';

function parseEndDate(text) {
    const match = text.match(/iki\s+(\d{4})\s+(\d{2})\s+(\d{2})/i);
    if (!match) return null;
    const [, year, month, day] = match;
    return `${year}-${month}-${day}`;
}

async function collectPageUrls(baseUrl) {
    const urls = [];
    for (let n = 1; ; n++) {
        const url = `${baseUrl}_${n}.jpg`;
        const response = await fetch(url, { method: 'HEAD' }).catch(() => null);
        if (!response || !response.ok) break;
        urls.push(url);
    }
    return urls;
}

(async () => {
    const listingHtml = await (await fetch(LISTING_URL)).text();

    const brochureMatch = listingHtml.match(/<div class=brochure-item>[\s\S]*?<\/div>/);
    if (!brochureMatch) {
        console.log('No brochure item found on', LISTING_URL);
        return;
    }

    const block = brochureMatch[0];
    const titleMatch = block.match(/<h2>([^<]+)/);
    const title = titleMatch ? titleMatch[1].replace(/\s+/g, ' ').trim() : 'Jupoja pasiūlymų leidinys';
    const validTo = parseEndDate(block);

    if (!validTo) {
        console.log('No end date found in listing heading — submitting without dates');
    }

    const viewerHtml = await (await fetch(VIEWER_URL)).text();
    const pdfMatch = viewerHtml.match(/"PDFFile"\s*:\s*"(docs\/[^"?]+\.pdf)/);
    if (!pdfMatch) {
        console.log('No FlowPaper PDF config found on', VIEWER_URL);
        return;
    }

    const baseUrl = `https://www.jsm.lt/leidiniai/${pdfMatch[1]}`;
    const imageUrls = await collectPageUrls(baseUrl);

    if (imageUrls.length === 0) {
        console.log('No page images found for', baseUrl);
        return;
    }

    console.log(`Found leaflet "${title}" with ${imageUrls.length} page(s)`);

    try {
        const buffers = [];
        for (const url of imageUrls) {
            buffers.push(await fetchBuffer(url));
        }

        const pdfBuffer = await imagesToPdf(buffers);
        const issueMatch = title.match(/Nr\.?\s*(\d+)/i);

        await submitFlyer({
            store: 'Jupoja',
            title,
            catalogName: 'Jupoja',
            issueNumber: issueMatch ? issueMatch[1] : null,
            validFrom: null,
            validTo,
            pdfBuffer,
            filename: `jupoja-${issueMatch ? issueMatch[1] : 'leidinys'}.pdf`,
        });
    } catch (error) {
        console.error(`Failed to process leaflet "${title}":`, error.message);
    }
})();
