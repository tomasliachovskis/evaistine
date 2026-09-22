import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// koopsmazmena.lt is the real domain behind the "Koops" store name (the
// site never calls itself "Koops" anywhere in its own markup — confirmed
// via web search, koops.lt doesn't resolve). /leidinys/ is a plain
// WordPress/Divi page with a photo gallery of the leaflet's own pages
// (irregularly-named files, no page-number pattern to construct — read
// directly off the gallery's own DOM order) plus a plain-text date range:
// "2026.08.24.-09.06." (end date shares the start year, month omitted when
// unchanged from the start).
const LISTING_URL = 'http://www.koopsmazmena.lt/leidinys/';

function parseDateRange(html) {
    const match = html.match(/(\d{4})\.(\d{2})\.(\d{2})\.\s*[–-]\s*(?:(\d{2})\.)?(\d{2})\./);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, startMonth, startDay, endMonth, endDay] = match;
    const validFrom = `${year}-${startMonth}-${startDay}`;
    const validTo = `${year}-${endMonth || startMonth}-${endDay}`;

    return { validFrom, validTo };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const imageUrls = Array.from(new Set(
        [...html.matchAll(/href="(http:\/\/www\.koopsmazmena\.lt\/wp-content\/uploads\/[^"]+-scaled\.jpg)"/g)].map(m => m[1])
    ));

    if (imageUrls.length === 0) {
        console.log('No gallery images found on', LISTING_URL);
        return;
    }

    let { validFrom, validTo } = parseDateRange(html);

    console.log(`Found ${imageUrls.length} page(s)`);

    const buffers = [];
    for (const url of imageUrls) {
        buffers.push(await fetchBuffer(url));
    }

    if (!validFrom) {
        const coverInfo = await extractCoverInfo({ store: 'Koops', imageBuffer: buffers[0], filename: 'koops-cover.jpg' });
        if (coverInfo?.validFrom) {
            validFrom = coverInfo.validFrom;
            validTo = coverInfo.validTo;
        } else {
            console.log('No date range found in page text or cover OCR — submitting without dates');
        }
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Koops',
            title: 'Koops naudingų pasiūlymų leidinys',
            catalogName: 'Koops',
            validFrom,
            validTo,
            pdfBuffer,
            filename: 'koops-leidinys.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
