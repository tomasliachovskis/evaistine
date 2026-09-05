import { fetchBuffer, submitFlyer } from './_shared.js';

// elimart.lt/leidiniai is plain server-rendered HTML — a long archive of
// every past leaflet's direct PDF link, each with a preceding heading
// ("Elimart Tau leidinys 2026.09.07-2026.10.04") carrying its own validity
// dates. Rather than guessing which one is "current" from its position in
// the DOM (not reliably newest-first — mixed manually over time), every
// leaflet is submitted; the backend already skips anything with an expired
// valid_to and dedupes anything already stored, so re-running this safely
// converges on just the current + still-valid ones.
const LISTING_URL = 'https://www.elimart.lt/leidiniai';

function findLeaflets(html) {
    const pdfRe = /href="(https:\/\/irp\.cdn-website\.com\/75553b5e\/files\/uploaded\/[^"]+\.pdf)"/g;
    const dateRe = /(\d{4})[.\-_ ]?(\d{2})[.\-_ ]?(\d{2})\s*[-–]\s*(?:(\d{4})[.\-_]?)?(\d{2})[.\-_]?(\d{2})/;
    const leaflets = [];

    for (const match of html.matchAll(pdfRe)) {
        const context = html.slice(Math.max(0, match.index - 2000), match.index);
        const headingBlocks = [...context.matchAll(/<(?:h2|strong)[^>]*>([\s\S]*?)<\/(?:h2|strong)>/g)];
        const heading = headingBlocks.length
            ? headingBlocks[headingBlocks.length - 1][1].replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
            : '';

        const dateMatch = heading.match(dateRe);
        if (!dateMatch) continue;

        const [, fy, fm, fd, ty, tm, td] = dateMatch;
        leaflets.push({
            url: match[1],
            validFrom: `${fy}-${fm}-${fd}`,
            validTo: `${ty || fy}-${tm}-${td}`,
        });
    }

    return leaflets;
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();
    const today = new Date().toISOString().slice(0, 10);
    // The backend would skip expired ones anyway, but filtering here avoids
    // downloading dozens of long-past PDFs from this archive on every run.
    const leaflets = findLeaflets(html).filter(l => l.validTo >= today);
    console.log(`Found ${leaflets.length} non-expired leaflet(s) on ${LISTING_URL}`);

    for (const leaflet of leaflets) {
        const filename = decodeURIComponent(leaflet.url.split('/').pop());

        try {
            const pdfBuffer = await fetchBuffer(leaflet.url);

            await submitFlyer({
                store: 'Elimart',
                title: 'Elimart Tau leidinys',
                catalogName: 'Elimart',
                validFrom: leaflet.validFrom,
                validTo: leaflet.validTo,
                pdfBuffer,
                sourcePdfUrl: leaflet.url,
                filename: `elimart-${filename}`,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${filename}":`, error.message);
        }
    }
})();
