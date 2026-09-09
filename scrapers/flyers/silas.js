import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// silas.lt/akcijos/ is plain server-rendered HTML — no flipbook platform at
// all, just a self-hosted WordPress image gallery of the leaflet's own
// pages (SLS{issue}_{page}.jpg), with the title and validity dates as
// plain text right above it: "Leidinys Nr. 17. Kainos galioja 2026 m.
// rugpjūčio 26 d. – rugsėjo 8 d."
const LISTING_URL = 'https://www.silas.lt/akcijos/';

const MONTHS = {
    sausio: '01', vasario: '02', kovo: '03', balandžio: '04', gegužės: '05', birželio: '06',
    liepos: '07', rugpjūčio: '08', rugsėjo: '09', spalio: '10', lapkričio: '11', gruodžio: '12',
};

function parseDateRange(text) {
    const match = text.match(/(\d{4})\s*m\.\s*(\S+)\s*(\d{1,2})\s*d\.\s*(?:[–-]|&#8211;)\s*(?:(\S+)\s*)?(\d{1,2})\s*d\./);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, fromMonthName, fromDay, toMonthName, toDay] = match;
    const fromMonth = MONTHS[fromMonthName.toLowerCase()];
    const toMonth = toMonthName ? MONTHS[toMonthName.toLowerCase()] : fromMonth;

    if (!fromMonth || !toMonth) return { validFrom: null, validTo: null };

    return {
        validFrom: `${year}-${fromMonth}-${fromDay.padStart(2, '0')}`,
        validTo: `${year}-${toMonth}-${toDay.padStart(2, '0')}`,
    };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const headingMatch = html.match(/<h4>([^<]*Leidinys[^<]*)<\/h4>/i);
    if (!headingMatch) {
        console.log('No leaflet heading found on', LISTING_URL);
        return;
    }

    const heading = headingMatch[1];
    const issueMatch = heading.match(/Nr\.\s*(\d+)/i);
    const issueNumber = issueMatch ? issueMatch[1] : null;
    let { validFrom, validTo } = parseDateRange(heading);

    if (!validFrom) {
        console.log('No date range parsed from heading:', heading);
    }

    const imageUrls = Array.from(new Set(
        [...html.matchAll(/href="(https:\/\/www\.silas\.lt\/wp-content\/uploads\/[^"]+\.jpg)"/g)].map(m => m[1])
    ));

    if (imageUrls.length === 0) {
        console.log('No page images found on', LISTING_URL);
        return;
    }

    console.log(`Found leaflet "${heading}" with ${imageUrls.length} page(s)`);

    const buffers = [];
    for (const url of imageUrls) {
        buffers.push(await fetchBuffer(url));
    }

    // Dates matter more than anything else here (a leaflet with no validity
    // window can't be shown as "current" or archived correctly) — when the
    // heading text doesn't parse (seen live: Nr.18's heading didn't match
    // parseDateRange's regex), fall back to the same Gemini cover-page OCR
    // aibe.js already uses instead of just submitting with null dates.
    if (!validFrom) {
        const coverInfo = await extractCoverInfo({ store: 'Šilas', imageBuffer: buffers[0], filename: 'silas-cover.jpg' });
        if (coverInfo?.validFrom) {
            validFrom = coverInfo.validFrom;
            validTo = coverInfo.validTo;
        } else {
            console.log('No reliable dates from cover OCR either — submitting without dates');
        }
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Šilas',
            title: issueNumber ? `Šilas leidinys Nr. ${issueNumber}` : 'Šilas leidinys',
            catalogName: 'Šilas',
            issueNumber,
            validFrom,
            validTo,
            pdfBuffer,
            filename: `silas-${issueNumber || 'leidinys'}.pdf`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
