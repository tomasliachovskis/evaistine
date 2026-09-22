import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// lt.oriflame.com's own frontend calls a plain JSON API
// (api-static.oriflame.com) for everything — no Puppeteer needed anywhere.
// `applications/catalogueOverview` lists every currently-active catalogue
// (the numbered monthly "Katalogas N" plus a few themed guides); the one
// with `CatalogueType: "DigitalCurrent"` is the numbered monthly catalog
// shown at /products/digital-catalogue-current, and it already carries
// exact `Valid.From`/`Valid.To` dates and the catalogue code (e.g.
// "2026012" = year 2026, campaign 12). That code's pages come from the
// underlying iPaper flipbook platform (same family as Moki Veži/Eurokos'
// Publitas, different vendor) via
// `lt-catalogue.oriflame.com/oriflame/lt/{code}/Image.ashx?PageNumber=N&ImageType=Zoom`
// — plain, un-tokenized, permanent URLs (unlike the short-lived tokenized
// cdn.ipaper.io URLs the interactive viewer uses) that 404 past the real
// last page, so no separate page-count lookup is needed beyond that.
const CATALOGUE_OVERVIEW_URL = 'https://api-static.oriflame.com/tenants/lt/applications/catalogueOverview?lang=lt-LT';

(async () => {
    const overview = await (await fetch(CATALOGUE_OVERVIEW_URL, { headers: { 'User-Agent': 'Mozilla/5.0' } })).json();

    const current = overview?.Catalogues?.find(c => c.CatalogueType === 'DigitalCurrent');
    if (!current) {
        console.log('No DigitalCurrent catalogue found on', CATALOGUE_OVERVIEW_URL);
        return;
    }

    const code = current.MetadataCollection?.find(m => m.ProviderType === 'Online')?.Id;
    if (!code) {
        console.log('No catalogue code found for', current.Title);
        return;
    }

    let validFrom = current.Valid?.From?.slice(0, 10) ?? null;
    let validTo = current.Valid?.To?.slice(0, 10) ?? null;
    const title = `Oriflame ${current.Title}`;

    console.log(`Found "${title}" (code ${code}), valid ${validFrom} - ${validTo}`);

    const buffers = [];
    for (let n = 1; ; n++) {
        const pageUrl = `https://lt-catalogue.oriflame.com/oriflame/lt/${code}/Image.ashx?PageNumber=${n}&ImageType=Zoom`;
        try {
            buffers.push(await fetchBuffer(pageUrl, { headers: { 'User-Agent': 'Mozilla/5.0' } }));
        } catch (error) {
            console.log(`Stopped at page ${n} (${error.message}) — ${n - 1} page(s) found`);
            break;
        }
    }

    if (buffers.length === 0) {
        console.log('No pages downloaded — aborting');
        return;
    }

    if (!validFrom) {
        const coverInfo = await extractCoverInfo({ store: 'Oriflame', imageBuffer: buffers[0], filename: 'oriflame-cover.jpg' });
        if (coverInfo?.validFrom) {
            validFrom = coverInfo.validFrom;
            validTo = coverInfo.validTo;
        } else {
            console.log('No Valid.From/To in catalogue overview or cover OCR — submitting without dates');
        }
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Oriflame',
            title,
            catalogName: 'Oriflame',
            issueNumber: code,
            validFrom,
            validTo,
            pdfBuffer,
            filename: `oriflame-${code}.pdf`,
            sourceId: code,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
