import { fetchBuffer, submitFlyer, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// elimart.lt/leidiniai is plain server-rendered HTML — a long archive of
// every past leaflet's direct PDF link, each with a preceding heading
// ("Elimart Tau leidinys 2026.09.07-2026.10.04") carrying its own validity
// dates. Rather than guessing which one is "current" from its position in
// the DOM (not reliably newest-first — mixed manually over time), every
// leaflet is submitted; the backend already skips anything with an expired
// valid_to and dedupes anything already stored, so re-running this safely
// converges on just the current + still-valid ones.
const LISTING_URL = 'https://www.elimart.lt/leidiniai';

export function findLeaflets(html) {
    const pdfRe = /href="(https:\/\/irp\.cdn-website\.com\/75553b5e\/files\/uploaded\/[^"]+\.pdf)"/g;
    const dateRe = /(\d{4})[.\-_ ]?(\d{2})[.\-_ ]?(\d{2})\s*[-–]\s*(?:(\d{4})[.\-_]?)?(\d{2})[.\-_]?(\d{2})/;
    const leaflets = [];

    for (const match of html.matchAll(pdfRe)) {
        const context = html.slice(Math.max(0, match.index - 2000), match.index);
        const headingBlocks = [...context.matchAll(/<(?:h2|strong)[^>]*>([\s\S]*?)<\/(?:h2|strong)>/g)];
        const heading = headingBlocks.length
            ? headingBlocks[headingBlocks.length - 1][1].replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
            : '';

        // The archive also links other PDFs (a job-candidate privacy
        // policy was stored as a "leaflet" once). Leaflets say so in the
        // heading or the filename ("Elimart+Tau+Nr.10.pdf").
        const filename = decodeURIComponent(match[1].split('/').pop()).replace(/\+/g, ' ');
        if (!/leidin|tau/i.test(heading) && !/leidin|tau|\d{4}[._ -]\d{2}[._ -]\d{2}/i.test(filename)) {
            continue;
        }

        // Older entries carry the dates only in the filename
        // ("2024_04_24_-_2024_05_07.pdf", "2025.04.07_-_05.04.pdf").
        const filenameDates = filename.replace(/[_ ]*[-–][_ ]*/g, '-').replace(/[_ ]+/g, '.');
        const dateMatch = heading.match(dateRe) || filenameDates.match(dateRe);

        if (!dateMatch) {
            // No date anywhere: cover OCR gets a chance below.
            leaflets.push({ url: match[1], validFrom: null, validTo: null });
            continue;
        }

        const [, fy, fm, fd, ty, tm, td] = dateMatch;
        leaflets.push({
            url: match[1],
            validFrom: `${fy}-${fm}-${fd}`,
            validTo: `${ty || fy}-${tm}-${td}`,
        });
    }

    return leaflets;
}

// Not when imported (scrapers/flyers/elimart.js is also loaded by tests).
if (process.argv[1]?.endsWith('elimart.js')) (async () => {
    const html = await (await fetch(LISTING_URL)).text();
    const today = new Date().toISOString().slice(0, 10);
    // The backend would skip expired ones anyway, but filtering here avoids
    // downloading dozens of long-past PDFs from this archive on every run.
    // A leaflet with no date at all (validTo null) is kept — undated ones
    // are rare in this archive and worth a cover-OCR attempt rather than
    // silently dropping them (null >= today is false in JS, so this needs
    // its own clause instead of just the plain comparison).
    const leaflets = findLeaflets(html).filter(l => l.validTo === null || l.validTo >= today);
    console.log(`Found ${leaflets.length} non-expired leaflet(s) on ${LISTING_URL}`);

    for (const leaflet of leaflets) {
        const filename = decodeURIComponent(leaflet.url.split('/').pop());

        try {
            const pdfBuffer = await fetchBuffer(leaflet.url);

            let { validFrom, validTo } = leaflet;
            if (!validFrom) {
                const coverImage = await renderFirstPdfPageToJpeg(leaflet.url);
                const coverInfo = await extractCoverInfo({ store: 'Elimart', imageBuffer: coverImage, filename: 'elimart-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    // Every real Elimart leaflet is dated. An undated one
                    // would never expire (an old 2024 leaflet stayed on the
                    // site this way), so skip it.
                    console.log(`No date in heading, filename or cover OCR for "${filename}" — skipping`);
                    continue;
                }
            }

            await submitFlyer({
                store: 'Elimart',
                title: 'Elimart Tau leidinys',
                catalogName: 'Elimart',
                validFrom,
                validTo,
                pdfBuffer,
                sourcePdfUrl: leaflet.url,
                filename: `elimart-${filename}`,
                sourceId: leaflet.url,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${filename}":`, error.message);
        }
    }
})();
