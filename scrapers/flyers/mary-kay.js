import { fetchBuffer, submitFlyer } from './_shared.js';

// marykay.lt's "Interaktyvus katalogas" page embeds a Google-App-Engine-
// hosted viewer (mkecatalog.appspot.com) that itself just reads a static
// JSON manifest off Cloud Storage — no Puppeteer needed, a plain fetch of
// that JSON is enough. `mk-lithuania.lt.catalogs.json` lists every current
// catalogue (today just one, "The LOOK") with a direct `fileUrl` straight
// to the source PDF (spaces/parens and all — needs URL-encoding) plus
// Unix-timestamp `startDate`/`endDate` fields for validity.
const CATALOGS_JSON_URL = 'https://storage.googleapis.com/production-ecatalog/deploy/production/mk-lithuania/mk-lithuania.lt.catalogs.json';
const STORAGE_BASE = 'https://storage.googleapis.com/production-ecatalog';

const toDateString = unixSeconds => new Date(unixSeconds * 1000).toISOString().slice(0, 10);

(async () => {
    const catalogs = await (await fetch(CATALOGS_JSON_URL)).json();

    if (!Array.isArray(catalogs) || catalogs.length === 0) {
        console.log('No catalogs found in', CATALOGS_JSON_URL);
        return;
    }

    for (const catalog of catalogs) {
        if (!catalog.fileUrl) {
            console.log(`Catalog "${catalog.name}" has no fileUrl — skipping`);
            continue;
        }

        const pdfUrl = STORAGE_BASE + catalog.fileUrl.split('/').map(encodeURIComponent).join('/');
        const validFrom = catalog.startDate ? toDateString(catalog.startDate) : null;
        const validTo = catalog.endDate ? toDateString(catalog.endDate) : null;

        console.log(`Found "${catalog.name}" (${pdfUrl}), valid ${validFrom} - ${validTo}`);

        try {
            const pdfBuffer = await fetchBuffer(pdfUrl);

            await submitFlyer({
                store: 'Mary Kay',
                title: `Mary Kay ${catalog.name}`,
                catalogName: 'Mary Kay',
                validFrom,
                validTo,
                pdfBuffer,
                sourcePdfUrl: pdfUrl,
                filename: `mary-kay-${catalog.id}.pdf`,
            });
        } catch (error) {
            console.error(`Failed to process catalog "${catalog.name}":`, error.message);
        }
    }
})();
