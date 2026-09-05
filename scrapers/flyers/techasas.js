// techasas.lt's TLS certificate (CN=*.balt.net) doesn't cover its own
// hostname — a real misconfiguration on their end, not a proxy/MITM
// concern, confirmed by checking the cert directly (openssl/curl -k both
// show a legitimate cert for the wrong CN). Every request needs
// verification disabled to reach the site at all; scoped to this script,
// not a repo-wide setting.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// techasas.lt/lt/leidinys is a very old, plain-HTML Drupal page — no
// flipbook platform, just two full-page leaflet images (front/back) linked
// directly. The cover names an issue number and month ("Leidinio Nr. 318,
// 2026 m. rugpjūtis") but no exact validity date range, so the backend's
// Gemini cover-OCR is used for whatever it can reliably read (likely just
// title/summary, with null dates — the month name alone isn't a real date
// range to submit).
const LISTING_URL = 'https://www.techasas.lt/lt/leidinys';

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const imageUrls = [...html.matchAll(/href="(https:\/\/www\.techasas\.lt\/sites\/default\/files\/pictures\/lankstinukas\/[^"]+\.png)"/g)]
        .map(m => m[1])
        .filter((url, i, arr) => arr.indexOf(url) === i);

    if (imageUrls.length === 0) {
        console.log('No leaflet images found on', LISTING_URL);
        return;
    }

    console.log(`Found ${imageUrls.length} page image(s)`);

    const buffers = [];
    for (const url of imageUrls) {
        buffers.push(await fetchBuffer(url));
    }

    const coverInfo = await extractCoverInfo({ store: 'TECHasas', imageBuffer: buffers[0], filename: 'techasas-cover.png' });
    if (!coverInfo?.validFrom) {
        console.log('No reliable dates from cover OCR — submitting without dates');
    }

    const issueMatch = (coverInfo?.title || '').match(/Nr\.?\s*(\d+)/i);

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'TECHasas',
            title: coverInfo?.title || 'TECHasas leidinys',
            catalogName: 'TECHasas',
            issueNumber: issueMatch ? issueMatch[1] : null,
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            filename: 'techasas-leidinys.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
