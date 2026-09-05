import { fetchBuffer, imagesToPdf, submitFlyer } from './_shared.js';

// benu.lt/benu-menesio-leidinys is plain server-rendered HTML embedding an
// Issuu flipbook (`e.issuu.com/embed.html?d={docname}&u={username}`) — a
// different platform from every other flyer store in this repo so far.
// Issuu's own reader app (issuu.com/{user}/docs/{docname}) is a client-side
// React app with no fetchable page data, but its public
// `publication.issuu.com/{user}/{docname}/reader4.json` endpoint (found by
// watching the embed's network requests in a real browser) returns the
// page count plus a `publicationId`/`revisionId` pair. Those combine into
// a full-resolution raster JPEG URL per page
// (`image.isu.pub/{revisionId}-{publicationId}/jpg/page_{n}.jpg`) — no need
// to touch the embed's own vector-SVG page renderer at all. No validity
// dates appear anywhere in Issuu's metadata, so the leaflet's own docname
// slug ("benu_lt_2026-09_small_compressed") supplies a coarse year-month
// range, same technique as eurokos.js.
const LISTING_URL = 'https://www.benu.lt/benu-menesio-leidinys';

const LAST_DAY_OF_MONTH = (year, month) => new Date(Date.UTC(year, month, 0)).getUTCDate();

function monthRangeFromDocname(docname) {
    const match = docname.match(/(\d{4})-(\d{2})/);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, month] = match;
    return {
        validFrom: `${year}-${month}-01`,
        validTo: `${year}-${month}-${String(LAST_DAY_OF_MONTH(Number(year), Number(month))).padStart(2, '0')}`,
    };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const embedMatch = html.match(/issuu\.com\/embed\.html\?d=([a-zA-Z0-9_-]+)&(?:amp;)?u=([a-zA-Z0-9_-]+)/);
    if (!embedMatch) {
        console.log('No Issuu embed found on', LISTING_URL);
        return;
    }

    const [, docname, username] = embedMatch;
    console.log(`Found Issuu document ${username}/${docname}`);

    const reader = await (await fetch(`https://publication.issuu.com/${username}/${docname}/reader4.json`)).json();
    const { publicationId, revisionId, pages } = reader.document;

    if (!pages?.length) {
        console.log(`No pages found for ${username}/${docname}`);
        return;
    }

    const { validFrom, validTo } = monthRangeFromDocname(docname);
    if (!validFrom) {
        console.log('No year-month in docname — submitting without dates');
    }

    try {
        const buffers = [];
        for (let n = 1; n <= pages.length; n++) {
            buffers.push(await fetchBuffer(`https://image.isu.pub/${revisionId}-${publicationId}/jpg/page_${n}.jpg`));
        }

        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Benu vaistinė',
            title: 'Benu mėnesio leidinys',
            catalogName: 'Benu vaistinė',
            validFrom,
            validTo,
            pdfBuffer,
            filename: `benu-${docname}.pdf`,
        });
    } catch (error) {
        console.error(`Failed to process leaflet ${docname}:`, error.message);
    }
})();
