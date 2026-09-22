import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// ermitazas.lt/leidiniai is a Next.js SSR page (plain fetch works, no
// Puppeteer needed) listing leaflet cards, each linking to a detail page
// (ermitazas.lt/{slug}) embedding a dcatalog.com flipbook (same platform as
// Senukai — see senukai.js). Unlike Senukai, each detail page's own
// `__NEXT_DATA__` JSON carries the dcatalog guid *and* real dateFrom/dateTo
// fields directly (`"id":"{guid}","dateTo":"...","dateFrom":"..."`), so no
// cover-OCR round trip is needed. Page images live at
// `dc-docs.dcatalog.com/Ermitazas/Ermitazas/{guid}/ZPage_{n}.jpg` (a
// different path shape than Senukai's `.../Senukai/katalogai/{guid}/...`),
// and the guid's own `document.json` gives the exact page count.
const LISTING_URL = 'https://www.ermitazas.lt/leidiniai';

async function findLeaflets(html) {
    return [...html.matchAll(/href="(https:\/\/www\.ermitazas\.lt\/[a-z0-9-]+)"[^>]*><div[^>]*><img alt="([^"]*)"/g)]
        .map(m => ({ url: m[1], title: m[2] }));
}

function parseGuidAndDates(html) {
    const guidMatch = html.match(/"id":"([0-9a-f-]{36})","dateTo":"(\d{4}-\d{2}-\d{2})[^"]*","dateFrom":"(\d{4}-\d{2}-\d{2})/);
    if (guidMatch) {
        return { guid: guidMatch[1], validTo: guidMatch[2], validFrom: guidMatch[3] };
    }

    // Dates missing from this leaflet's own JSON (themed catalog?) — still
    // grab the guid alone so cover OCR can be tried before giving up.
    const guidOnlyMatch = html.match(/"id":"([0-9a-f-]{36})"/);
    if (!guidOnlyMatch) return null;

    return { guid: guidOnlyMatch[1], validTo: null, validFrom: null };
}

(async () => {
    const listingHtml = await (await fetch(LISTING_URL)).text();
    const leaflets = await findLeaflets(listingHtml);
    console.log(`Found ${leaflets.length} leaflet(s) on ${LISTING_URL}`);

    for (const leaflet of leaflets) {
        const detailHtml = await (await fetch(leaflet.url)).text();
        const parsed = parseGuidAndDates(detailHtml);

        if (!parsed) {
            console.log(`Could not find dcatalog guid/dates for "${leaflet.title}" (${leaflet.url})`);
            continue;
        }

        try {
            const docJson = await (await fetch(`https://dc-docs.dcatalog.com/Ermitazas/Ermitazas/${parsed.guid}/document.json`)).json();
            const pageCount = docJson.issue.page.length;

            const buffers = [];
            for (let n = 1; n <= pageCount; n++) {
                buffers.push(await fetchBuffer(`https://dc-docs.dcatalog.com/Ermitazas/Ermitazas/${parsed.guid}/ZPage_${n}.jpg`));
            }

            let { validFrom, validTo } = parsed;
            if (!validFrom) {
                const coverInfo = await extractCoverInfo({ store: 'Ermitažas', imageBuffer: buffers[0], filename: 'ermitazas-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    console.log(`No dates in page JSON or cover OCR for "${leaflet.title}" — submitting without dates`);
                }
            }

            const pdfBuffer = await imagesToPdf(buffers);

            await submitFlyer({
                store: 'Ermitažas',
                title: leaflet.title,
                catalogName: 'Ermitažas',
                validFrom,
                validTo,
                pdfBuffer,
                filename: `ermitazas-${parsed.guid}.pdf`,
                sourceId: parsed.guid,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
        }
    }
})();
