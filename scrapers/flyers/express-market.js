import { fetchBuffer, submitFlyer } from './_shared.js';

// expressmarket.lt/akcijos/ is plain server-rendered WordPress HTML with a
// single current leaflet: a direct PDF download link (`download` anchor,
// same file also embedded in a self-hosted pdf.js viewer modal for
// in-page preview) and plain validity text right on the page: "Pasiūlymai
// galioja:<br />2026.08.23 - 2026.09.12".
const LISTING_URL = 'https://expressmarket.lt/akcijos/';

function parseDateRange(text) {
    const match = text.match(/(\d{4})\.(\d{2})\.(\d{2})\s*-\s*(\d{4})\.(\d{2})\.(\d{2})/);
    if (!match) return { validFrom: null, validTo: null };

    const [, y1, m1, d1, y2, m2, d2] = match;
    return { validFrom: `${y1}-${m1}-${d1}`, validTo: `${y2}-${m2}-${d2}` };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const pdfMatch = html.match(/href="(https:\/\/expressmarket\.lt\/wp-content\/uploads\/[^"]+\.pdf)"/);
    if (!pdfMatch) {
        console.log('No PDF link found on', LISTING_URL);
        return;
    }

    const dateMatch = html.match(/Pasiūlymai galioja:<br\s*\/?>\s*([^<]+)/);
    const { validFrom, validTo } = dateMatch ? parseDateRange(dateMatch[1]) : { validFrom: null, validTo: null };

    if (!validFrom) {
        console.log('No date range found — submitting without dates');
    }

    try {
        const pdfBuffer = await fetchBuffer(pdfMatch[1]);

        await submitFlyer({
            store: 'Express Market',
            title: 'Naujausias akcijų leidinys',
            catalogName: 'Express Market',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfMatch[1],
            filename: `express-market-${pdfMatch[1].split('/').pop()}`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
