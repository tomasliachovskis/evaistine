import { fetchBuffer, submitFlyer } from './_shared.js';

// ramunelesvaistine.lt (not ramuneles.lt, which doesn't resolve) is plain
// server-rendered HTML — its /akcijos/10 page has a single direct PDF link
// to the current monthly leaflet ("laikrastukas"), no flipbook platform at
// all. No validity-date text appears on the page itself, but the PDF's own
// upload path encodes it ("/uploads/2026/09/09_26_..._web.pdf" — 2026/09
// from the folder, confirmed against the leaflet's own printed cover text
// "Galioja: 2026 09 01 - 2026 09 30", which always covers the full month).
const LISTING_URL = 'https://www.ramunelesvaistine.lt/akcijos/10';

const LAST_DAY_OF_MONTH = (year, month) => new Date(Date.UTC(year, month, 0)).getUTCDate();

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const pdfMatch = html.match(/href="(\/data\/public\/uploads\/(\d{4})\/(\d{2})\/[^"]+\.pdf)"/);
    if (!pdfMatch) {
        console.log('No PDF link found on', LISTING_URL);
        return;
    }

    const [, pdfPath, year, month] = pdfMatch;
    const pdfUrl = `https://www.ramunelesvaistine.lt${pdfPath}`;
    const validFrom = `${year}-${month}-01`;
    const validTo = `${year}-${month}-${String(LAST_DAY_OF_MONTH(Number(year), Number(month))).padStart(2, '0')}`;

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        await submitFlyer({
            store: 'Ramunėlės vaistinė',
            title: 'Ramunėlės vaistinė mėnesio pasiūlymai',
            catalogName: 'Ramunėlės vaistinė',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: `ramuneles-vaistine-${pdfPath.split('/').pop()}`,
            sourceId: pdfPath,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
