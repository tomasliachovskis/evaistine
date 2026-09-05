import { fetchBuffer, imagesToPdf, submitFlyer } from './_shared.js';

// jysk.lt links out to a dedicated katalogai.jysk.lt subdomain hosting a
// single current iPaper flipbook (same platform as Rimi's weekly leaflet —
// see rimi.js) — plain server-rendered HTML embedding a window.staticSettings
// JS object with everything needed to fetch page images directly. Its
// pageTitle/name fields are generic ("Galiojantis pasiūlymas JYSK" / an
// internal print-file name) with no dates, but the validity range is
// printed as plain text elsewhere on the page: "PASIŪLYMAS GALIOJA 2026 08
// 25–09 07" (end date shares the start year, month omitted when same).
const CATALOG_URL = 'https://katalogai.jysk.lt/';

function parseDateRange(html) {
    const match = html.match(/PASIŪLYMAS GALIOJA (\d{4}) (\d{2}) (\d{2})\s*[–-]\s*(\d{2})(?:\s+(\d{2}))?/);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, startMonth, startDay, a, b] = match;
    const validFrom = `${year}-${startMonth}-${startDay}`;
    const validTo = b ? `${year}-${a}-${b}` : `${year}-${startMonth}-${a}`;

    return { validFrom, validTo };
}

(async () => {
    const html = await (await fetch(CATALOG_URL)).text();

    const pagesMatch = html.match(/"pages":\[([\d,]+)\]/);
    const awsUrlMatch = html.match(/"aws":\{"url":"((?:[^"\\]|\\.)*)"/);
    const policyMatch = html.match(/"policy":"((?:[^"\\]|\\.)*)"/);

    if (!pagesMatch || !awsUrlMatch || !policyMatch) {
        console.log('Could not find window.staticSettings fields on', CATALOG_URL);
        return;
    }

    const unescape = s => s.replace(/\\u0026/g, '&').replace(/\\\//g, '/').replace(/\\"/g, '"');
    const pageCount = pagesMatch[1].split(',').length;
    const awsUrl = unescape(awsUrlMatch[1]);
    const policy = unescape(policyMatch[1]);

    const { validFrom, validTo } = parseDateRange(html);
    if (!validFrom) {
        console.log('No date range found — submitting without dates');
    }

    try {
        const buffers = [];
        for (let n = 1; n <= pageCount; n++) {
            buffers.push(await fetchBuffer(`${awsUrl}Pages/${n}/Zoom.jpg?${policy}`));
        }

        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Jysk',
            title: 'Jysk katalogas',
            catalogName: 'Jysk',
            validFrom,
            validTo,
            pdfBuffer,
            filename: 'jysk-katalogas.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
